<?php

namespace Tests\Feature;

use App\Models\Akun;
use App\Models\AlokasiKreditPotongGaji;
use App\Models\Anggota;
use App\Models\CicilanPinjaman;
use App\Models\DompetKoperasi;
use App\Models\JadwalCicilanPinjaman;
use App\Models\JenisSimpanan;
use App\Models\JurnalUmum;
use App\Models\Karyawan;
use App\Models\KategoriProduk;
use App\Models\KreditPotongGajiAnggota;
use App\Models\LimitPotongGajiAnggota;
use App\Models\MutasiKas;
use App\Models\Pembayaran;
use App\Models\PembayaranOutstandingCash;
use App\Models\PemakaianPotongGaji;
use App\Models\Penjualan;
use App\Models\Pinjaman;
use App\Models\Produk;
use App\Models\ReversalTransaksi;
use App\Models\Simpanan;
use App\Models\User;
use App\Services\MasterDataKoperasiService;
use App\Services\PinjamanKoperasiService;
use App\Services\PosCheckoutService;
use App\Services\PotongGajiBulananService;
use App\Services\TransaksiReversalService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PotongGajiTahap2ETest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_pos_payroll_pending_dapat_dibatalkan_penuh_dan_mengembalikan_limit_stok(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-12 09:00:00', 'Asia/Jakarta'));
        $user = $this->user('keuangan');
        $anggota = $this->anggota();
        $produk = $this->produk(50000, 10);
        $service = app(PotongGajiBulananService::class);
        $limit = $service->activateLimit($service->createLimit($anggota, '2026-07', 300000, $user->id, 'Limit 2E'), $user->id);

        $penjualan = app(PosCheckoutService::class)->checkout([
            'tipe_pelanggan' => Penjualan::TIPE_ANGGOTA,
            'anggota_id' => $anggota->id,
            'metode_pembayaran' => Pembayaran::METODE_POTONG_GAJI,
            'items' => [['produk_id' => $produk->id, 'jumlah' => 2]],
            'diskon' => 0,
        ], $user->id);

        $this->assertSame(8, $produk->fresh()->stok);

        $reversal = app(TransaksiReversalService::class)->cancelPendingPayrollPos($penjualan, 'Batal penuh test 2E.', $user->id);

        $this->assertSame(Pembayaran::STATUS_CANCELLED, $penjualan->pembayaran->fresh()->status);
        $this->assertSame(Penjualan::STATUS_CANCELLED, $penjualan->fresh()->status);
        $this->assertSame(PemakaianPotongGaji::STATUS_REVERSED, $penjualan->pembayaran->ledger->fresh()->status);
        $this->assertSame(10, $produk->fresh()->stok);
        $this->assertSame(0, MutasiKas::query()->where('referensi_tipe', ReversalTransaksi::class)->where('referensi_id', $reversal->id)->count());
        $this->assertBalanced($reversal);
        $this->assertSame(20000000, $limit->fresh()->sisaLimitCents());

        $this->expectValidation(fn () => app(TransaksiReversalService::class)->cancelPendingPayrollPos($penjualan->fresh(), 'Duplikat reversal.', $user->id));
    }

    public function test_refund_pos_payroll_confirmed_membuat_kredit_dan_kredit_dipakai_fifo_tanpa_net_negatif(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-12 09:00:00', 'Asia/Jakarta'));
        $user = $this->user('keuangan');
        $anggota = $this->anggota();
        $produk = $this->produk(50000, 10);
        $bank = $this->bankDefaultPayroll();
        $service = app(PotongGajiBulananService::class);
        $limit = $service->activateLimit($service->createLimit($anggota, '2026-07', 300000, $user->id, 'Limit confirmed'), $user->id);

        $penjualan = app(PosCheckoutService::class)->checkout([
            'tipe_pelanggan' => Penjualan::TIPE_ANGGOTA,
            'anggota_id' => $anggota->id,
            'metode_pembayaran' => Pembayaran::METODE_POTONG_GAJI,
            'items' => [['produk_id' => $produk->id, 'jumlah' => 2]],
            'diskon' => 0,
        ], $user->id);

        $service->confirmLimit($service->closeLimit($limit, $user->id), $user->id);
        $bankSaldoSetelahConfirm = $bank->fresh()->saldo;

        $reversal = app(TransaksiReversalService::class)->refundPos($penjualan, 'Refund confirmed jadi kredit.', $user->id);
        $kredit = KreditPotongGajiAnggota::query()->where('reversal_transaksi_id', $reversal->id)->firstOrFail();

        $this->assertSame('100000.00', $kredit->nominal_sisa);
        $this->assertSame(Pembayaran::STATUS_REFUNDED, $penjualan->pembayaran->fresh()->status);

        $limitNext = $service->activateLimit($service->createLimit($anggota, '2026-08', 300000, $user->id, 'Limit next'), $user->id);
        app(PosCheckoutService::class)->checkout([
            'tipe_pelanggan' => Penjualan::TIPE_ANGGOTA,
            'anggota_id' => $anggota->id,
            'metode_pembayaran' => Pembayaran::METODE_POTONG_GAJI,
            'tanggal_transaksi' => '2026-08-05 10:00:00',
            'items' => [['produk_id' => $produk->id, 'jumlah' => 1]],
            'diskon' => 0,
        ], $user->id);

        $service->confirmLimit($service->closeLimit($limitNext, $user->id), $user->id);

        $this->assertSame('50000.00', $kredit->fresh()->nominal_sisa);
        $this->assertSame(KreditPotongGajiAnggota::STATUS_PARTIALLY_APPLIED, $kredit->fresh()->status);
        $this->assertSame(1, AlokasiKreditPotongGaji::query()->where('limit_potong_gaji_anggota_id', $limitNext->id)->count());
        $this->assertSame($bankSaldoSetelahConfirm, $bank->fresh()->saldo);
    }

    public function test_refund_pos_non_payroll_memakai_dompet_asal_dan_idempotent(): void
    {
        $user = $this->user('keuangan');
        $produk = $this->produk(25000, 5);
        $kas = $this->kasDompet(100000);

        $penjualan = app(PosCheckoutService::class)->checkout([
            'tipe_pelanggan' => Penjualan::TIPE_UMUM,
            'metode_pembayaran' => Pembayaran::METODE_TUNAI,
            'dompet_id' => $kas->id,
            'items' => [['produk_id' => $produk->id, 'jumlah' => 2]],
            'diskon' => 0,
        ], $user->id);

        $saldoSetelahJual = $kas->fresh()->saldo;
        $reversal = app(TransaksiReversalService::class)->refundPos($penjualan, 'Refund tunai penuh.', $user->id);

        $this->assertSame(Pembayaran::STATUS_REFUNDED, $penjualan->pembayaran->fresh()->status);
        $this->assertSame(5, $produk->fresh()->stok);
        $this->assertSame((float) $saldoSetelahJual - 50000, (float) $kas->fresh()->saldo);
        $this->assertSame(1, MutasiKas::query()->where('referensi_tipe', ReversalTransaksi::class)->where('referensi_id', $reversal->id)->count());
        $this->assertBalanced($reversal);
        $this->expectValidation(fn () => app(TransaksiReversalService::class)->refundPos($penjualan->fresh(), 'Refund duplikat.', $user->id));
    }

    public function test_reversal_cicilan_payroll_mengembalikan_jadwal_sisa_pinjaman_dan_membuat_kredit(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-12 09:00:00', 'Asia/Jakarta'));
        $user = $this->user('keuangan');
        $anggota = $this->anggota();
        $this->bankDefaultPayroll();
        $kas = $this->kasDompet(500000);
        $pinjaman = app(PinjamanKoperasiService::class)->create([
            'anggota_id' => $anggota->id,
            'dompet_id' => $kas->id,
            'jumlah_pinjaman' => 120000,
            'tenor_bulan' => 2,
            'tanggal_pinjaman' => '2026-06-15',
            'keterangan' => 'Pinjaman test 2E',
        ], $user->id);

        $service = app(PotongGajiBulananService::class);
        $limit = $service->activateLimit($service->createLimit($anggota, '2026-07', 300000, $user->id, 'Limit cicilan'), $user->id);
        $service->confirmLimit($service->closeLimit($limit, $user->id), $user->id);

        $payment = CicilanPinjaman::query()->where('pinjaman_id', $pinjaman->id)->firstOrFail();
        $reversal = app(TransaksiReversalService::class)->reverseCicilan($payment, 'Cicilan payroll salah.', $user->id);

        $this->assertSame(CicilanPinjaman::STATUS_REVERSED, $payment->fresh()->status);
        $this->assertSame(JadwalCicilanPinjaman::STATUS_SCHEDULED, $payment->jadwal->fresh()->status);
        $this->assertSame('120000.00', $pinjaman->fresh()->sisa_pinjaman);
        $this->assertSame(Pinjaman::STATUS_AKTIF, $pinjaman->fresh()->status);
        $this->assertSame(1, KreditPotongGajiAnggota::query()->where('reversal_transaksi_id', $reversal->id)->count());
        $this->assertBalanced($reversal);
    }

    public function test_outstanding_cash_dibayar_penuh_dan_kasir_ditolak_route_keuangan(): void
    {
        $user = $this->user('keuangan');
        $kasir = $this->user('kasir');
        $anggota = $this->anggota();
        $produk = $this->produk(50000, 5);
        $kas = $this->kasDompet(0);
        $service = app(PotongGajiBulananService::class);
        $limit = $service->activateLimit($service->createLimit($anggota, '2026-07', 250000, $user->id, 'Limit stop'), $user->id);

        $penjualan = app(PosCheckoutService::class)->checkout([
            'tipe_pelanggan' => Penjualan::TIPE_ANGGOTA,
            'anggota_id' => $anggota->id,
            'metode_pembayaran' => Pembayaran::METODE_POTONG_GAJI,
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

        $this->assertSame(LimitPotongGajiAnggota::STATUS_CANCELLED, $limit->fresh()->status);
        $this->assertSame(Pembayaran::STATUS_OUTSTANDING_CASH, $penjualan->pembayaran->fresh()->status);

        $outstandingPayment = app(TransaksiReversalService::class)->payOutstandingSource(Pembayaran::class, $penjualan->pembayaran->id, $kas, $user->id);

        $this->assertSame(Pembayaran::STATUS_SETTLED_CASH, $penjualan->pembayaran->fresh()->status);
        $this->assertSame('50000.00', $kas->fresh()->saldo);
        $this->assertSame(1, PembayaranOutstandingCash::query()->whereKey($outstandingPayment->id)->count());
        $this->assertBalancedPayment($outstandingPayment);

        $this->actingAs($kasir)->get(route('outstanding-cash.index'))->assertForbidden();
    }

    private function user(string $role): User
    {
        return User::factory()->create(['role' => $role]);
    }

    private function anggota(): Anggota
    {
        $karyawan = Karyawan::factory()->create();

        return app(MasterDataKoperasiService::class)->createAnggota([
            'karyawan_id' => $karyawan->id,
            'tanggal_bergabung' => '2026-01-01',
            'alamat' => 'Jl. Tahap 2E',
            'plafon_pinjaman' => 5000000,
        ])->fresh('karyawan');
    }

    private function produk(int $harga = 50000, int $stok = 10): Produk
    {
        $kategori = KategoriProduk::query()->create(['nama_kategori' => 'Kategori 2E ' . uniqid()]);

        return Produk::query()->create([
            'nama_produk' => 'Produk 2E ' . uniqid(),
            'kategori_id' => $kategori->id,
            'harga_beli' => $harga,
            'harga_jual' => $harga,
            'stok' => $stok,
            'konsinyasi' => false,
            'harga_setor' => 0,
        ]);
    }

    private function kasDompet(int $saldo = 0): DompetKoperasi
    {
        return DompetKoperasi::query()->create([
            'akun_id' => Akun::query()->where('kode_akun', '101')->value('id'),
            'nama_dompet' => 'Kas 2E ' . uniqid(),
            'jenis_dompet' => DompetKoperasi::JENIS_KAS,
            'saldo' => $saldo,
        ]);
    }

    private function bankDefaultPayroll(): DompetKoperasi
    {
        return DompetKoperasi::query()->create([
            'akun_id' => Akun::query()->where('kode_akun', '102')->value('id'),
            'nama_dompet' => 'Bank Payroll 2E ' . uniqid(),
            'jenis_dompet' => DompetKoperasi::JENIS_BANK,
            'is_default_penerimaan_payroll' => true,
            'saldo' => 0,
        ]);
    }

    private function assertBalanced(ReversalTransaksi $reversal): void
    {
        $jurnal = JurnalUmum::query()
            ->where('referensi_tipe', ReversalTransaksi::class)
            ->where('referensi_id', $reversal->id)
            ->with('details')
            ->firstOrFail();

        $this->assertSame(
            number_format((float) $jurnal->details->sum('debit'), 2, '.', ''),
            number_format((float) $jurnal->details->sum('kredit'), 2, '.', '')
        );
    }

    private function assertBalancedPayment(PembayaranOutstandingCash $payment): void
    {
        $jurnal = JurnalUmum::query()
            ->where('referensi_tipe', PembayaranOutstandingCash::class)
            ->where('referensi_id', $payment->id)
            ->with('details')
            ->firstOrFail();

        $this->assertSame(
            number_format((float) $jurnal->details->sum('debit'), 2, '.', ''),
            number_format((float) $jurnal->details->sum('kredit'), 2, '.', '')
        );
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
