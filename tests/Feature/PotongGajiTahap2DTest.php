<?php

namespace Tests\Feature;

use App\Models\Akun;
use App\Models\Anggota;
use App\Models\DompetKoperasi;
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
use App\Models\Simpanan;
use App\Models\User;
use App\Services\MasterDataKoperasiService;
use App\Services\PosCheckoutService;
use App\Services\PotongGajiBulananService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PotongGajiTahap2DTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_anggota_baru_membuat_simpanan_pokok_pending_dan_jurnal_piutang(): void
    {
        $anggota = $this->anggota();

        $simpanan = Simpanan::query()
            ->where('anggota_id', $anggota->id)
            ->where('kode_jenis_snapshot', JenisSimpanan::KODE_SIMPANAN_POKOK)
            ->firstOrFail();

        $this->assertSame(Simpanan::STATUS_PENDING_PAYROLL, $simpanan->status);
        $this->assertSame(Simpanan::METODE_POTONG_GAJI, $simpanan->metode_pembayaran);
        $this->assertSame('100000.00', $simpanan->jumlah);
        $this->assertSame(1, Simpanan::query()->where('anggota_id', $anggota->id)->where('kode_jenis_snapshot', JenisSimpanan::KODE_SIMPANAN_POKOK)->count());

        $jurnal = JurnalUmum::query()
            ->where('idempotency_key', 'simpanan-pokok:pengakuan:jurnal:' . $simpanan->id)
            ->with('details')
            ->firstOrFail();

        $this->assertNotNull($jurnal->details->firstWhere('akun_kode', '103'));
        $this->assertNotNull($jurnal->details->firstWhere('akun_kode', '301'));
        $this->assertSame('100000.00', $jurnal->details->firstWhere('akun_kode', '103')->debit);
        $this->assertSame('100000.00', $jurnal->details->firstWhere('akun_kode', '301')->kredit);
    }

    public function test_pos_payroll_mengonsumsi_limit_dan_konfirmasi_settle_simpanan_pokok_serta_pos(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-10 09:00:00', 'Asia/Jakarta'));
        $user = $this->user();
        $anggota = $this->anggota();
        $bank = $this->bankDefaultPayroll();
        $produk = $this->produk(50000, 10);
        $service = app(PotongGajiBulananService::class);

        $limit = $service->activateLimit(
            $service->createLimit($anggota, '2026-07', 300000, $user->id, 'Limit POS 2D'),
            $user->id
        );

        $simpananPokok = Simpanan::query()->where('anggota_id', $anggota->id)->where('kode_jenis_snapshot', JenisSimpanan::KODE_SIMPANAN_POKOK)->firstOrFail();
        $this->assertSame(Simpanan::STATUS_ALLOCATED, $simpananPokok->fresh()->status);

        $penjualan = app(PosCheckoutService::class)->checkout([
            'tipe_pelanggan' => Penjualan::TIPE_ANGGOTA,
            'anggota_id' => $anggota->id,
            'metode_pembayaran' => Pembayaran::METODE_POTONG_GAJI,
            'tanggal_transaksi' => '2026-07-10 09:00:00',
            'diskon' => 0,
            'items' => [
                ['produk_id' => $produk->id, 'jumlah' => 2],
            ],
        ], $user->id);

        $this->assertSame(Pembayaran::STATUS_PENDING_PAYROLL, $penjualan->pembayaran->status);
        $this->assertSame(2, PemakaianPotongGaji::query()->where('limit_potong_gaji_anggota_id', $limit->id)->where('status', PemakaianPotongGaji::STATUS_CONSUMED)->count());
        $this->assertSame(0, MutasiKas::query()->where('referensi_tipe', Pembayaran::class)->count());

        $service->confirmLimit($service->closeLimit($limit, $user->id), $user->id);

        $this->assertSame(Pembayaran::STATUS_PAID, $penjualan->pembayaran->fresh()->status);
        $this->assertSame(Simpanan::STATUS_SETTLED, $simpananPokok->fresh()->status);
        $this->assertSame('200000.00', $bank->fresh()->saldo);
        $this->assertSame(2, PemakaianPotongGaji::query()->where('limit_potong_gaji_anggota_id', $limit->id)->where('status', PemakaianPotongGaji::STATUS_SETTLED)->count());
        $this->assertSame(1, MutasiKas::query()->where('referensi_tipe', Pembayaran::class)->count());
        $this->assertSame(1, MutasiKas::query()->where('referensi_tipe', PemakaianPotongGaji::class)->count());
    }

    public function test_pos_menolak_anggota_nonpayroll_dan_nonanggota_payroll(): void
    {
        $anggota = $this->anggota();
        $produk = $this->produk();
        $kas = $this->kasDompet();
        $service = app(PosCheckoutService::class);

        $this->expectValidation(fn () => $service->checkout([
            'tipe_pelanggan' => Penjualan::TIPE_ANGGOTA,
            'anggota_id' => $anggota->id,
            'metode_pembayaran' => Pembayaran::METODE_TUNAI,
            'dompet_id' => $kas->id,
            'items' => [['produk_id' => $produk->id, 'jumlah' => 1]],
            'diskon' => 0,
        ], $this->user()->id));

        $karyawan = Karyawan::factory()->create();
        $this->expectValidation(fn () => $service->checkout([
            'tipe_pelanggan' => Penjualan::TIPE_KARYAWAN,
            'karyawan_id' => $karyawan->id,
            'metode_pembayaran' => Pembayaran::METODE_POTONG_GAJI,
            'items' => [['produk_id' => $produk->id, 'jumlah' => 1]],
            'diskon' => 0,
        ], $this->user()->id));
    }

    public function test_pos_umum_nonpayroll_mencatat_mutasi_jurnal_tanpa_ledger(): void
    {
        $produk = $this->produk(25000, 5);
        $kas = $this->kasDompet();

        $penjualan = app(PosCheckoutService::class)->checkout([
            'tipe_pelanggan' => Penjualan::TIPE_UMUM,
            'metode_pembayaran' => Pembayaran::METODE_TUNAI,
            'dompet_id' => $kas->id,
            'items' => [['produk_id' => $produk->id, 'jumlah' => 2]],
            'diskon' => 0,
        ], $this->user()->id);

        $this->assertNull($penjualan->anggota_id);
        $this->assertNull($penjualan->karyawan_id);
        $this->assertSame(Pembayaran::STATUS_PAID, $penjualan->pembayaran->status);
        $this->assertSame('50000.00', $kas->fresh()->saldo);
        $this->assertSame(0, PemakaianPotongGaji::query()->count());
        $this->assertSame(1, MutasiKas::query()->where('referensi_tipe', Penjualan::class)->where('referensi_id', $penjualan->id)->count());
        $this->assertSame(1, JurnalUmum::query()->where('referensi_tipe', Penjualan::class)->where('referensi_id', $penjualan->id)->count());
    }

    public function test_karyawan_berhenti_melepas_pos_dan_simpanan_pokok_ke_outstanding_cash(): void
    {
        $user = $this->user();
        $anggota = $this->anggota();
        $produk = $this->produk(50000, 5);
        $service = app(PotongGajiBulananService::class);

        $limit = $service->activateLimit(
            $service->createLimit($anggota, '2026-07', 250000, $user->id, 'Limit berhenti'),
            $user->id
        );

        $penjualan = app(PosCheckoutService::class)->checkout([
            'tipe_pelanggan' => Penjualan::TIPE_ANGGOTA,
            'anggota_id' => $anggota->id,
            'metode_pembayaran' => Pembayaran::METODE_POTONG_GAJI,
            'tanggal_transaksi' => '2026-07-12 10:00:00',
            'items' => [['produk_id' => $produk->id, 'jumlah' => 1]],
            'diskon' => 0,
        ], $user->id);

        app(MasterDataKoperasiService::class)->updateKaryawan($anggota->karyawan, [
            'nama' => $anggota->karyawan->nama,
            'email' => $anggota->karyawan->email,
            'telepon' => $anggota->karyawan->telepon,
            'jabatan' => $anggota->karyawan->jabatan,
            'status_kerja' => Karyawan::STATUS_BERHENTI,
            'tanggal_berhenti' => '2026-07-31',
        ]);

        $simpananPokok = Simpanan::query()->where('anggota_id', $anggota->id)->where('kode_jenis_snapshot', JenisSimpanan::KODE_SIMPANAN_POKOK)->firstOrFail();

        $this->assertSame(LimitPotongGajiAnggota::STATUS_CANCELLED, $limit->fresh()->status);
        $this->assertSame(Simpanan::STATUS_OUTSTANDING_CASH, $simpananPokok->fresh()->status);
        $this->assertSame(Pembayaran::STATUS_OUTSTANDING_CASH, $penjualan->pembayaran->fresh()->status);
        $this->assertSame(2, PemakaianPotongGaji::query()->where('limit_potong_gaji_anggota_id', $limit->id)->where('status', PemakaianPotongGaji::STATUS_RELEASED)->count());
    }

    private function user(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    private function anggota(): Anggota
    {
        $karyawan = Karyawan::factory()->create();

        return app(MasterDataKoperasiService::class)->createAnggota([
            'karyawan_id' => $karyawan->id,
            'tanggal_bergabung' => '2026-01-01',
            'alamat' => 'Jl. Tahap 2D',
            'plafon_pinjaman' => 5000000,
        ])->fresh('karyawan');
    }

    private function produk(int $harga = 50000, int $stok = 10): Produk
    {
        $kategori = KategoriProduk::query()->create([
            'nama_kategori' => 'Kategori Test ' . uniqid(),
        ]);

        return Produk::query()->create([
            'nama_produk' => 'Produk Test ' . uniqid(),
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
            'nama_dompet' => 'Kas 2D ' . uniqid(),
            'jenis_dompet' => DompetKoperasi::JENIS_KAS,
            'saldo' => 0,
        ]);
    }

    private function bankDefaultPayroll(): DompetKoperasi
    {
        return DompetKoperasi::query()->create([
            'akun_id' => Akun::query()->where('kode_akun', '102')->value('id'),
            'nama_dompet' => 'Bank Payroll 2D ' . uniqid(),
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
