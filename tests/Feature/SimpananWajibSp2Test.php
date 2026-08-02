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
use App\Models\LimitPotongGajiAnggota;
use App\Models\MutasiKas;
use App\Models\Pembayaran;
use App\Models\PemakaianPotongGaji;
use App\Models\Penjualan;
use App\Models\Produk;
use App\Models\SiklusKeanggotaan;
use App\Models\Simpanan;
use App\Models\User;
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

    public function test_generator_interval_anchor_januari_join_date_snapshot_dan_idempotent(): void
    {
        $user = $this->admin();
        $service = app(SimpananWajibService::class);
        $anggotaJanuari = $this->anggotaAktif('2026-01-05');
        $anggotaMei = $this->anggotaAktif('2026-05-15');
        $anggotaDesember = $this->anggotaAktif('2026-12-20');

        $this->assertSame(4, $service->generateUntil('2026-07-20', null, $user->id));
        $this->assertSame(0, $service->generateUntil('2026-07-01', null, $user->id));

        $this->assertSame(
            ['2026-01-01', '2026-04-01', '2026-07-01'],
            JadwalSimpananWajib::query()
                ->where('anggota_id', $anggotaJanuari->id)
                ->orderBy('periode')
                ->pluck('periode')
                ->map(fn ($date) => $date->toDateString())
                ->all()
        );

        $this->assertSame(
            ['2026-07-01'],
            JadwalSimpananWajib::query()
                ->where('anggota_id', $anggotaMei->id)
                ->orderBy('periode')
                ->pluck('periode')
                ->map(fn ($date) => $date->toDateString())
                ->all()
        );

        $this->assertSame(1, $service->generateUntil('2027-01-01', $anggotaDesember, $user->id));
        $desemberSchedule = JadwalSimpananWajib::query()
            ->where('anggota_id', $anggotaDesember->id)
            ->firstOrFail();
        $this->assertSame('2027-01-01', $desemberSchedule->periode->toDateString());
        $this->assertSame('100000.00', $desemberSchedule->nominal_snapshot);
        $this->assertSame(3, $desemberSchedule->interval_bulan_snapshot);

        JenisSimpanan::query()
            ->where('kode', JenisSimpanan::KODE_SIMPANAN_WAJIB)
            ->update(['nominal_default' => '150000.00', 'interval_bulan' => 1, 'berlaku_mulai' => '2026-08-01']);

        $service->generateUntil('2026-08-01', $anggotaJanuari, $user->id);

        $juliSnapshot = JadwalSimpananWajib::query()
            ->where('anggota_id', $anggotaJanuari->id)
            ->whereDate('periode', '2026-07-01')
            ->firstOrFail();
        $agustusSnapshot = JadwalSimpananWajib::query()
            ->where('anggota_id', $anggotaJanuari->id)
            ->whereDate('periode', '2026-08-01')
            ->firstOrFail();

        $this->assertSame('100000.00', $juliSnapshot->nominal_snapshot);
        $this->assertSame(3, $juliSnapshot->interval_bulan_snapshot);
        $this->assertSame('150000.00', $agustusSnapshot->nominal_snapshot);
        $this->assertSame(1, $agustusSnapshot->interval_bulan_snapshot);
    }

    public function test_limit_reserve_wajib_oldest_first_full_only_dan_retry_setelah_limit_naik(): void
    {
        $user = $this->admin();
        $anggota = $this->anggotaAktif('2026-01-05');
        $payroll = app(PotongGajiBulananService::class);

        $limit = $payroll->activateLimit(
            $payroll->createLimit($anggota, '2026-07', 250000, $user->id, 'Limit awal Wajib'),
            $user->id
        );

        $this->assertSame(2, JadwalSimpananWajib::query()->where('anggota_id', $anggota->id)->where('status', JadwalSimpananWajib::STATUS_RESERVED)->count());
        $juli = JadwalSimpananWajib::query()
            ->where('anggota_id', $anggota->id)
            ->where('status', JadwalSimpananWajib::STATUS_OUTSTANDING)
            ->firstOrFail();
        $this->assertSame('2026-07-01', $juli->periode->toDateString());
        $this->assertSame(2, PemakaianPotongGaji::query()->where('kategori', PemakaianPotongGaji::KATEGORI_SIMPANAN_WAJIB)->count());

        $payroll->updateLimit($limit, 350000, $user->id, 'Naik agar Juli ikut dialokasikan');

        $this->assertSame(3, JadwalSimpananWajib::query()->where('anggota_id', $anggota->id)->where('status', JadwalSimpananWajib::STATUS_RESERVED)->count());
        $this->assertSame(3, PemakaianPotongGaji::query()->where('kategori', PemakaianPotongGaji::KATEGORI_SIMPANAN_WAJIB)->where('status', PemakaianPotongGaji::STATUS_RESERVED)->count());
    }

    public function test_confirm_limit_settle_wajib_membuat_mutasi_jurnal_dan_tidak_duplicate_saat_retry(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-31 09:00:00', 'Asia/Jakarta'));
        $user = $this->admin();
        $anggota = $this->anggotaAktif('2026-07-02');
        $bank = $this->bankDefaultPayroll();
        $payroll = app(PotongGajiBulananService::class);

        $limit = $payroll->activateLimit(
            $payroll->createLimit($anggota, '2026-07', 100000, $user->id, 'Limit settlement Wajib'),
            $user->id
        );

        $jadwal = JadwalSimpananWajib::query()->where('anggota_id', $anggota->id)->firstOrFail();
        $this->assertSame(JadwalSimpananWajib::STATUS_RESERVED, $jadwal->status);

        $payroll->confirmLimit($payroll->closeLimit($limit, $user->id), $user->id);

        $this->assertSame(JadwalSimpananWajib::STATUS_SETTLED, $jadwal->fresh()->status);
        $this->assertSame(Simpanan::STATUS_SETTLED, $jadwal->simpanan->fresh()->status);
        $this->assertSame(PemakaianPotongGaji::STATUS_SETTLED, $jadwal->activeLedger->fresh()->status);
        $this->assertSame('100000.00', $bank->fresh()->saldo);
        $this->assertSame(1, MutasiKas::query()->where('idempotency_key', 'like', 'simpanan-wajib:payroll:mutasi:%')->count());
        $this->assertSame(1, JurnalUmum::query()->where('idempotency_key', 'like', 'simpanan-wajib:payroll:jurnal:%')->count());

        $this->expectException(ValidationException::class);
        $payroll->confirmLimit($limit->fresh(), $user->id);
    }

    public function test_wajib_final_sekali_per_siklus_dapat_dibayar_tunai_secara_idempotent(): void
    {
        $user = $this->admin();
        $anggota = $this->anggotaAktif('2026-07-02');
        $service = app(SimpananWajibService::class);
        $kas = $this->kasDompet();

        JenisSimpanan::query()
            ->where('kode', JenisSimpanan::KODE_SIMPANAN_WAJIB)
            ->update(['nominal_default' => 10000, 'interval_bulan' => null]);

        $jadwal = $service->createForMembershipCycle($anggota, $anggota->siklusAktif, $user->id);
        $this->assertSame('10000.00', $jadwal->nominal_snapshot);
        $this->assertSame(1, JadwalSimpananWajib::query()->where('siklus_keanggotaan_id', $anggota->siklusAktif->id)->count());

        $this->expectValidation(fn () => $service->settleDirect($anggota, [
            'jenis_transaksi' => Simpanan::JENIS_SETORAN,
            'metode_pembayaran' => Simpanan::METODE_TRANSFER_BANK,
            'dompet_id' => $kas->id,
            'jumlah' => 10000,
            'tanggal' => '2026-07-02',
        ], $user->id));

        $simpanan = $service->settleDirect($anggota, [
            'jenis_transaksi' => Simpanan::JENIS_SETORAN,
            'metode_pembayaran' => Simpanan::METODE_TUNAI,
            'dompet_id' => $kas->id,
            'jumlah' => 10000,
            'tanggal' => '2026-07-02',
        ], $user->id);
        $service->settleDirect($anggota, [
            'jenis_transaksi' => Simpanan::JENIS_SETORAN,
            'metode_pembayaran' => Simpanan::METODE_TUNAI,
            'dompet_id' => $kas->id,
            'jumlah' => 10000,
            'tanggal' => '2026-07-02',
        ], $user->id);

        $this->assertSame(Simpanan::STATUS_SETTLED_CASH, $simpanan->status);
        $this->assertSame('10000.00', $kas->fresh()->saldo);
        $this->assertSame(1, MutasiKas::query()->where('idempotency_key', 'simpanan-wajib:direct:mutasi:'.$simpanan->id)->count());
        $this->assertSame(1, JurnalUmum::query()->where('idempotency_key', 'simpanan-wajib:direct:jurnal:'.$simpanan->id)->count());
        $this->artisan('koperasi:preflight-simpanan-wajib')->assertExitCode(0);
    }

    public function test_pos_payroll_diblokir_jika_wajib_belum_dialokasikan_tetapi_tunai_tetap_boleh(): void
    {
        $user = $this->admin();
        $anggota = $this->anggotaAktif('2026-01-05');
        $produk = $this->produk(25000, 10);
        $kas = $this->kasDompet();
        $payroll = app(PotongGajiBulananService::class);

        $payroll->activateLimit(
            $payroll->createLimit($anggota, '2026-07', 250000, $user->id, 'Limit tidak cukup untuk semua Wajib'),
            $user->id
        );

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
        $this->assertSame(0, PemakaianPotongGaji::query()->where('kategori', PemakaianPotongGaji::KATEGORI_POS)->count());
    }

    public function test_release_limit_mengembalikan_wajib_ke_outstanding_dan_get_jadwal_read_only(): void
    {
        $user = $this->admin();
        $kasir = User::factory()->create(['role' => 'kasir']);
        $anggota = $this->anggotaAktif('2026-07-02');
        $payroll = app(PotongGajiBulananService::class);

        $limit = $payroll->activateLimit(
            $payroll->createLimit($anggota, '2026-07', 100000, $user->id, 'Limit release Wajib'),
            $user->id
        );

        app(SimpananWajibService::class)->releaseReservationsForLimit($limit, $user->id, 'Test release');

        $jadwal = JadwalSimpananWajib::query()->where('anggota_id', $anggota->id)->firstOrFail();
        $this->assertSame(JadwalSimpananWajib::STATUS_OUTSTANDING, $jadwal->status);
        $this->assertSame(PemakaianPotongGaji::STATUS_RELEASED, PemakaianPotongGaji::query()->where('kategori', PemakaianPotongGaji::KATEGORI_SIMPANAN_WAJIB)->firstOrFail()->status);

        $before = [
            'jadwal' => JadwalSimpananWajib::query()->count(),
            'simpanan' => Simpanan::query()->count(),
            'ledger' => PemakaianPotongGaji::query()->count(),
        ];

        $this->actingAs($user)->get(route('jadwal-simpanan-wajib.index'))
            ->assertOk()
            ->assertSee('Jadwal Simpanan Wajib')
            ->assertSee($jadwal->kode_tagihan);

        $this->actingAs($kasir)->get(route('jadwal-simpanan-wajib.index'))
            ->assertForbidden();

        $this->assertSame($before['jadwal'], JadwalSimpananWajib::query()->count());
        $this->assertSame($before['simpanan'], Simpanan::query()->count());
        $this->assertSame($before['ledger'], PemakaianPotongGaji::query()->count());
    }

    public function test_preflight_simpanan_wajib_mendeteksi_konflik_dan_read_only(): void
    {
        $anggota = $this->anggotaAktif('2026-01-05');

        JadwalSimpananWajib::query()->create([
            'kode_tagihan' => 'SWJ-TEST-INVALID',
            'anggota_id' => $anggota->id,
            'siklus_keanggotaan_id' => $anggota->siklusAktif()->value('id'),
            'jenis_simpanan_id' => JenisSimpanan::query()->where('kode', JenisSimpanan::KODE_SIMPANAN_WAJIB)->value('id'),
            'periode' => '2026-07-15',
            'nominal_snapshot' => '0.00',
            'interval_bulan_snapshot' => 13,
            'kode_jenis_snapshot' => JenisSimpanan::KODE_SIMPANAN_WAJIB,
            'nama_jenis_snapshot' => 'Simpanan Wajib',
            'status' => JadwalSimpananWajib::STATUS_RESERVED,
        ]);

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
        return User::factory()->create(['role' => 'admin']);
    }

    private function anggotaAktif(string $tanggalBergabung): Anggota
    {
        $karyawan = Karyawan::factory()->create();
        $anggota = Anggota::factory()->create([
            'karyawan_id' => $karyawan->id,
            'tanggal_bergabung' => $tanggalBergabung,
            'status' => Anggota::STATUS_AKTIF,
            'plafon_pinjaman' => 5000000,
        ]);

        SiklusKeanggotaan::query()->create([
            'anggota_id' => $anggota->id,
            'siklus_ke' => 1,
            'tanggal_mulai' => $tanggalBergabung,
            'status' => SiklusKeanggotaan::STATUS_ACTIVE,
        ]);

        return $anggota->fresh(['karyawan', 'siklusAktif']);
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
