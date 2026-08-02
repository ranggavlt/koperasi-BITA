<?php

namespace Tests\Feature;

use App\Models\Akun;
use App\Models\Anggota;
use App\Models\DompetKoperasi;
use App\Models\JadwalSimpananWajib;
use App\Models\JenisSimpanan;
use App\Models\JurnalUmum;
use App\Models\Karyawan;
use App\Models\KategoriProduk;
use App\Models\MutasiKas;
use App\Models\Pembayaran;
use App\Models\PemakaianPotongGaji;
use App\Models\Penjualan;
use App\Models\Produk;
use App\Models\Simpanan;
use App\Models\User;
use App\Services\MasterDataKoperasiService;
use App\Services\PosCheckoutService;
use App\Services\PotongGajiBulananService;
use App\Services\SimpananWajibService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class SimpananWajibSp2Test extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_generator_legacy_noop_dan_tidak_membuat_jadwal_baru(): void
    {
        $user = $this->admin();
        $service = app(SimpananWajibService::class);
        $this->anggotaAktif();
        $this->anggotaAktif();

        $this->assertSame(0, $service->generateUntil('2026-07-20', null, $user->id));
        $this->assertSame(0, JadwalSimpananWajib::query()->count());
    }

    public function test_limit_reserve_wajib_final_dari_transaksi_simpanan_dan_retry_idempotent(): void
    {
        $user = $this->admin();
        $anggota = $this->anggotaAktif();
        $wajib = $this->wajibFor($anggota);
        $payroll = app(PotongGajiBulananService::class);

        $limit = $payroll->activateLimit(
            $payroll->createLimit($anggota, '2026-07', 10000, $user->id, 'Limit Wajib final'),
            $user->id
        );
        app(SimpananWajibService::class)->reserveOutstandingForLimit($limit, $user->id);

        $this->assertSame(0, JadwalSimpananWajib::query()->count());
        $this->assertSame(Simpanan::STATUS_ALLOCATED, $wajib->fresh()->status);
        $this->assertSame(1, PemakaianPotongGaji::query()
            ->where('kategori', PemakaianPotongGaji::KATEGORI_SIMPANAN_WAJIB)
            ->where('source_type', Simpanan::class)
            ->where('source_id', $wajib->id)
            ->where('status', PemakaianPotongGaji::STATUS_RESERVED)
            ->count());
    }

    public function test_confirm_limit_settle_wajib_final_membuat_mutasi_jurnal_dan_tidak_duplicate(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-31 09:00:00', 'Asia/Jakarta'));
        $user = $this->admin();
        $anggota = $this->anggotaAktif();
        $bank = $this->bankDefaultPayroll();
        $payroll = app(PotongGajiBulananService::class);
        $wajib = $this->wajibFor($anggota);

        $limit = $payroll->activateLimit(
            $payroll->createLimit($anggota, '2026-07', 100000, $user->id, 'Limit settlement Wajib final'),
            $user->id
        );
        $payroll->confirmLimit($payroll->closeLimit($limit, $user->id), $user->id);

        $this->assertSame(Simpanan::STATUS_SETTLED, $wajib->fresh()->status);
        $this->assertSame(PemakaianPotongGaji::STATUS_SETTLED, $wajib->fresh('ledger')->ledger->status);
        $this->assertSame('10000.00', $bank->fresh()->saldo);
        $this->assertSame(1, MutasiKas::query()->where('idempotency_key', 'like', 'simpanan-wajib:payroll:mutasi:%')->count());
        $this->assertSame(1, JurnalUmum::query()->where('idempotency_key', 'like', 'simpanan-wajib:payroll:jurnal:%')->count());

        $this->expectException(ValidationException::class);
        $payroll->confirmLimit($limit->fresh(), $user->id);
    }

    public function test_pos_payroll_diblokir_jika_wajib_final_pending_tetapi_tunai_tetap_boleh(): void
    {
        $user = $this->admin();
        $anggota = $this->anggotaAktif();
        $produk = $this->produk(25000, 10);
        $kas = $this->kasDompet();
        $payroll = app(PotongGajiBulananService::class);

        $limit = $payroll->activateLimit(
            $payroll->createLimit($anggota, '2026-07', 100000, $user->id, 'Limit POS SP-7'),
            $user->id
        );
        app(SimpananWajibService::class)->releaseReservationsForLimit($limit, $user->id, 'Test Wajib pending sebelum POS');

        $this->expectValidation(fn () => app(PosCheckoutService::class)->checkout([
            'tipe_pelanggan' => Penjualan::TIPE_ANGGOTA,
            'anggota_id' => $anggota->id,
            'metode_pembayaran' => Pembayaran::METODE_POTONG_GAJI,
            'tanggal_transaksi' => '2026-07-10 09:00:00',
            'diskon' => 0,
            'items' => [['produk_id' => $produk->id, 'jumlah' => 1]],
        ], $user->id));

        $penjualanTunai = app(PosCheckoutService::class)->checkout([
            'tipe_pelanggan' => Penjualan::TIPE_ANGGOTA,
            'anggota_id' => $anggota->id,
            'metode_pembayaran' => Pembayaran::METODE_TUNAI,
            'dompet_id' => $kas->id,
            'tanggal_transaksi' => '2026-07-10 10:00:00',
            'diskon' => 0,
            'items' => [['produk_id' => $produk->id, 'jumlah' => 1]],
        ], $user->id);

        $this->assertSame(Pembayaran::METODE_TUNAI, $penjualanTunai->pembayaran->metode_pembayaran);
    }

    public function test_release_limit_mengembalikan_wajib_final_ke_pending_dan_route_jadwal_legacy_read_only(): void
    {
        $user = $this->admin();
        $kasir = User::factory()->create(['role' => 'kasir']);
        $anggota = $this->anggotaAktif();
        $payroll = app(PotongGajiBulananService::class);

        $limit = $payroll->activateLimit(
            $payroll->createLimit($anggota, '2026-07', 100000, $user->id, 'Limit release Wajib final'),
            $user->id
        );
        app(SimpananWajibService::class)->releaseReservationsForLimit($limit, $user->id, 'Test release');

        $wajib = $this->wajibFor($anggota);
        $this->assertSame(Simpanan::STATUS_PENDING_PAYROLL, $wajib->fresh()->status);
        $this->assertSame(PemakaianPotongGaji::STATUS_RELEASED, PemakaianPotongGaji::query()
            ->where('kategori', PemakaianPotongGaji::KATEGORI_SIMPANAN_WAJIB)
            ->firstOrFail()
            ->status);

        $before = [
            'jadwal' => JadwalSimpananWajib::query()->count(),
            'simpanan' => Simpanan::query()->count(),
            'ledger' => PemakaianPotongGaji::query()->count(),
        ];

        $this->actingAs($user)->get(route('jadwal-simpanan-wajib.index'))
            ->assertOk()
            ->assertSee('Histori Jadwal Wajib Lama');

        $this->actingAs($kasir)->get(route('jadwal-simpanan-wajib.index'))
            ->assertForbidden();

        $this->assertSame($before['jadwal'], JadwalSimpananWajib::query()->count());
        $this->assertSame($before['simpanan'], Simpanan::query()->count());
        $this->assertSame($before['ledger'], PemakaianPotongGaji::query()->count());
    }

    public function test_preflight_simpanan_wajib_mendeteksi_konflik_dan_read_only(): void
    {
        $jenis = JenisSimpanan::query()
            ->where('kode', JenisSimpanan::KODE_SIMPANAN_WAJIB)
            ->firstOrFail();
        $jenis->update(['interval_bulan' => 1]);

        $before = [
            'jadwal' => JadwalSimpananWajib::query()->count(),
            'simpanan' => Simpanan::query()->count(),
            'ledger' => PemakaianPotongGaji::query()->count(),
        ];

        $this->artisan('koperasi:preflight-simpanan-wajib')
            ->assertExitCode(1);

        $this->assertSame($before['jadwal'], JadwalSimpananWajib::query()->count());
        $this->assertSame($before['simpanan'], Simpanan::query()->count());
        $this->assertSame($before['ledger'], PemakaianPotongGaji::query()->count());
    }

    private function admin(): User
    {
        return User::factory()->create([
            'role' => 'admin',
            'is_active' => true,
            'must_change_password' => false,
        ]);
    }

    private function anggotaAktif(): Anggota
    {
        $this->actingAs($this->admin());
        $karyawan = Karyawan::factory()->create(['status_kerja' => Karyawan::STATUS_AKTIF]);

        return app(MasterDataKoperasiService::class)->createAnggota([
            'karyawan_id' => $karyawan->id,
            'tanggal_bergabung' => '2026-07-01',
            'alamat' => 'Jl. Test SP-2 Final',
            'plafon_pinjaman' => 5000000,
        ])->fresh(['karyawan', 'siklusAktif']);
    }

    private function wajibFor(Anggota $anggota): Simpanan
    {
        return Simpanan::query()
            ->where('anggota_id', $anggota->id)
            ->where('kode_jenis_snapshot', JenisSimpanan::KODE_SIMPANAN_WAJIB)
            ->firstOrFail();
    }

    private function produk(int $harga = 25000, int $stok = 10): Produk
    {
        $kategori = KategoriProduk::query()->create([
            'nama_kategori' => 'Kategori SP2 ' . uniqid(),
        ]);

        return Produk::query()->create([
            'nama_produk' => 'Produk SP2 ' . uniqid(),
            'kategori_id' => $kategori->id,
            'harga_beli' => $harga,
            'harga_jual' => $harga,
            'stok' => $stok,
            'konsinyasi' => false,
            'harga_setor' => 0,
        ]);
    }

    private function kasDompet(): DompetKoperasi
    {
        return DompetKoperasi::query()->create([
            'akun_id' => Akun::query()->where('kode_akun', '101')->value('id'),
            'nama_dompet' => 'Kas SP2 ' . uniqid(),
            'jenis_dompet' => DompetKoperasi::JENIS_KAS,
            'saldo' => 0,
        ]);
    }

    private function bankDefaultPayroll(): DompetKoperasi
    {
        return DompetKoperasi::query()->create([
            'akun_id' => Akun::query()->where('kode_akun', '102')->value('id'),
            'nama_dompet' => 'Bank Payroll SP2 ' . uniqid(),
            'jenis_dompet' => DompetKoperasi::JENIS_BANK,
            'is_default_penerimaan_payroll' => true,
            'saldo' => 0,
        ]);
    }

    private function expectValidation(callable $callback): void
    {
        try {
            $callback();
            $this->fail('ValidationException expected.');
        } catch (ValidationException) {
            $this->assertTrue(true);
        }
    }
}
