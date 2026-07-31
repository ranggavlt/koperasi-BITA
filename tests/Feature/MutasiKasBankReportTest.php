<?php

namespace Tests\Feature;

use App\Models\DompetKoperasi;
use App\Models\MutasiKas;
use App\Models\PembayaranSewaMobil;
use App\Models\Penjualan;
use App\Models\Pinjaman;
use App\Models\User;
use App\Services\MutasiKasService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use RuntimeException;
use Tests\TestCase;

class MutasiKasBankReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_get_mutasi_kas_bank_is_read_only_and_does_not_change_saldo_dompet(): void
    {
        $finance = $this->user('admin');
        $kas = $this->dompet('Kas Readonly', DompetKoperasi::JENIS_KAS, 500000);

        MutasiKas::query()->create([
            'idempotency_key' => 'test:mutasi:readonly:1',
            'dompet_id' => $kas->id,
            'tipe' => 'masuk',
            'jumlah' => '125000.00',
            'referensi_tipe' => Penjualan::class,
            'referensi_id' => 1001,
            'tanggal' => now()->toDateString(),
            'keterangan' => 'Penerimaan POS',
        ]);

        $mutasiCount = MutasiKas::query()->count();
        $saldoAwal = $kas->fresh()->saldo;

        $this->actingAs($finance)
            ->get(route('mutasi-kas.index'))
            ->assertOk()
            ->assertSee('Mutasi Kas & Bank')
            ->assertSee('Penerimaan POS');

        $this->assertSame($mutasiCount, MutasiKas::query()->count());
        $this->assertSame($saldoAwal, $kas->fresh()->saldo);
    }

    public function test_filter_tanggal_dompet_tipe_sumber_and_summary_are_correct(): void
    {
        $finance = $this->user('admin');
        $kas = $this->dompet('Kas Filter', DompetKoperasi::JENIS_KAS, 0);
        $bank = $this->dompet('Bank Filter', DompetKoperasi::JENIS_BANK, 0);

        $this->mutasi($kas, 'masuk', '100000.00', '2026-07-05', Penjualan::class, 'POS Juli');
        $this->mutasi($kas, 'keluar', '25000.00', '2026-07-08', Penjualan::class, 'Refund POS Juli');
        $this->mutasi($bank, 'masuk', '70000.00', '2026-07-10', Penjualan::class, 'Bank Juli');
        $this->mutasi($kas, 'masuk', '300000.00', '2026-07-11', Pinjaman::class, 'Pinjaman bukan POS');
        $this->mutasi($kas, 'masuk', '90000.00', '2026-06-30', Penjualan::class, 'POS Juni');

        $this->actingAs($finance)
            ->get(route('mutasi-kas.index', [
                'tanggal_mulai' => '2026-07-01',
                'tanggal_selesai' => '2026-07-31',
                'dompet_id' => $kas->id,
                'tipe' => 'masuk',
                'sumber' => Penjualan::class,
            ]))
            ->assertOk()
            ->assertSee('POS Juli')
            ->assertSee('Rp 100.000')
            ->assertSee('Total Uang Masuk')
            ->assertSee('Rp 100.000')
            ->assertSee('Total Uang Keluar')
            ->assertSee('Rp 0')
            ->assertSee('Selisih / Neto')
            ->assertDontSee('Refund POS Juli')
            ->assertDontSee('Bank Juli')
            ->assertDontSee('Pinjaman bukan POS')
            ->assertDontSee('POS Juni');
    }

    public function test_tanggal_selesai_tidak_boleh_sebelum_tanggal_mulai(): void
    {
        $finance = $this->user('admin');

        $this->actingAs($finance)
            ->from(route('mutasi-kas.index'))
            ->get(route('mutasi-kas.index', [
                'tanggal_mulai' => '2026-07-31',
                'tanggal_selesai' => '2026-07-01',
            ]))
            ->assertRedirect(route('mutasi-kas.index'))
            ->assertSessionHasErrors('tanggal_selesai');
    }

    public function test_pagination_mempertahankan_query_filter(): void
    {
        $finance = $this->user('admin');
        $kas = $this->dompet('Kas Pagination', DompetKoperasi::JENIS_KAS, 0);

        for ($i = 1; $i <= 16; $i++) {
            $this->mutasi($kas, 'masuk', '1000.00', '2026-07-' . str_pad((string) min($i, 28), 2, '0', STR_PAD_LEFT), Penjualan::class, 'POS Page ' . $i);
        }

        $this->actingAs($finance)
            ->get(route('mutasi-kas.index', [
                'tanggal_mulai' => '2026-07-01',
                'tanggal_selesai' => '2026-07-31',
                'dompet_id' => $kas->id,
                'tipe' => 'masuk',
                'sumber' => Penjualan::class,
            ]))
            ->assertOk()
            ->assertSee('page=2', false)
            ->assertSee('tipe=masuk', false)
            ->assertSee('dompet_id=' . $kas->id, false);
    }

    public function test_finance_boleh_mengakses_dan_role_lain_ditolak(): void
    {
        $finance = $this->user('admin');
        $kasir = $this->user('kasir');
        $karyawan = $this->user('karyawan');

        $this->get(route('mutasi-kas.index'))->assertRedirect(route('login'));
        $this->actingAs($finance)->get(route('mutasi-kas.index'))->assertOk();
        $this->actingAs($kasir)->get(route('mutasi-kas.index'))->assertForbidden();
        $this->actingAs($karyawan)->get(route('mutasi-kas.index'))->assertForbidden();
    }

    public function test_dompet_yang_sudah_memiliki_mutasi_tidak_dapat_dihapus(): void
    {
        $finance = $this->user('admin');
        $kas = $this->dompet('Kas Terpakai', DompetKoperasi::JENIS_KAS, 0);
        $this->mutasi($kas, 'masuk', '50000.00', '2026-07-05', Penjualan::class, 'POS Dompet Terpakai');

        $this->actingAs($finance)
            ->from(route('dompet-koperasi.index'))
            ->withSession(['_token' => 'test-token'])
            ->delete(route('dompet-koperasi.destroy', $kas), ['_token' => 'test-token'])
            ->assertRedirect(route('dompet-koperasi.index'))
            ->assertSessionHasErrors('dompet_koperasi');

        $this->assertDatabaseHas('dompet_koperasi', ['id' => $kas->id]);
    }

    public function test_sidebar_dan_config_navigation_mengikuti_grup_baru(): void
    {
        config([
            'features.shu_enabled' => false,
            'features.jasa_print_enabled' => false,
            'features.master_printer_enabled' => false,
        ]);

        $modules = collect(config('navigation.modules', []))->keyBy('route');
        $groups = collect(config('navigation.groups', []))->keyBy('key');
        $groupLabel = fn (string $route): string => $groups[$modules[$route]['group']]['label'];

        $this->assertSame('Master Data', $groupLabel('aset-mobil.index'));
        $this->assertSame('Master Data', $groupLabel('aset-printer.index'));
        $this->assertSame('master_printer_enabled', $modules['aset-printer.index']['feature']);
        $this->assertSame('Kas & Bank', $groupLabel('dompet-koperasi.index'));
        $this->assertSame('Kas & Bank', $groupLabel('mutasi-kas.index'));
        $this->assertSame('Mutasi Kas & Bank', $modules['mutasi-kas.index']['label']);
        $this->assertSame('Usaha Koperasi', $groupLabel('sewa-mobil.finance.index'));
        $this->assertSame('Usaha Koperasi', $groupLabel('sewa-printer.index'));
        $this->assertSame('Sewa Hardware', $modules['sewa-printer.index']['label']);
        $this->assertSame('Operasional', $groupLabel('beban-operasional.index'));
        $this->assertSame('Akuntansi', $groupLabel('akun.index'));
        $this->assertSame('Daftar Akun / COA', $modules['akun.index']['label']);
        $this->assertSame('Tagihan Tunai', $modules['outstanding-cash.index']['label']);
        $this->assertSame('Riwayat Koreksi Transaksi', $modules['reversal-transaksi.index']['label']);

        $this->actingAs($this->user('admin'))
            ->get(route('pages.dashboard'))
            ->assertOk()
            ->assertSeeInOrder([
                'POS / Kasir',
                'Penjualan / Kasir',
                'Pembayaran Konsinyasi',
                'Laporan Konsinyasi',
                'Master Data',
                'Karyawan',
                'Anggota',
                'Pengurus',
                'Produk',
                'Kategori Produk',
                'Reseller',
                'Vendor / Supplier',
                'Kas & Bank',
                'Dompet Koperasi',
                'Mutasi Kas & Bank',
                'Simpan Pinjam',
                'Transaksi Simpanan',
                'Pinjaman',
                'Cicilan Pinjaman',
                'Potong Gaji',
                'Periode Potong Gaji',
                'Laporan Potong Gaji',
                'Rekonsiliasi Potong Gaji',
                'Tagihan Tunai',
                'Usaha Koperasi',
                'Sewa Mobil',
                'Sewa Hardware',
                'Invoice Penagihan B2B',
                'Operasional',
                'Beban Operasional',
                'Akuntansi',
                'Daftar Akun / COA',
                'Jurnal Umum Periodik',
                'Buku Besar',
                'Keanggotaan & Koreksi',
                'Penyelesaian Keanggotaan',
                'Riwayat Koreksi Transaksi',
            ], false)
            ->assertDontSee('Printer Koperasi')
            ->assertDontSee('Transaksi SHU')
            ->assertDontSee('Klaim Dana Sosial')
            ->assertDontSee('Jasa Print');
    }

    public function test_route_write_manual_mutasi_tidak_tersedia(): void
    {
        $this->assertTrue(Route::has('mutasi-kas.index'));
        $this->assertFalse(Route::has('mutasi-kas.create'));
        $this->assertFalse(Route::has('mutasi-kas.store'));
        $this->assertFalse(Route::has('mutasi-kas.destroy'));
    }

    public function test_service_mutasi_tidak_memilih_dompet_pertama_dan_backfill_legacy_nonaktif(): void
    {
        $this->dompet('Kas Tidak Boleh Jadi Fallback', DompetKoperasi::JENIS_KAS, 0);

        $service = app(MutasiKasService::class);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Dompet wajib ditentukan secara eksplisit');

        $service->record([
            'tipe' => 'masuk',
            'jumlah' => 10000,
            'tanggal' => '2026-07-01',
            'keterangan' => 'Tidak boleh fallback',
        ]);
    }

    public function test_backfill_legacy_mutasi_dinonaktifkan(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Backfill historis Mutasi Kas & Bank dinonaktifkan');

        app(MutasiKasService::class)->backfillHistoricalTransactions();
    }

    private function user(string $role): User
    {
        return User::factory()->create([
            'role' => $role,
            'is_active' => true,
            'must_change_password' => false,
        ]);
    }

    private function dompet(string $name, string $jenis, int $saldo): DompetKoperasi
    {
        return DompetKoperasi::query()->create([
            'nama_dompet' => $name,
            'jenis_dompet' => $jenis,
            'saldo' => $saldo . '.00',
        ]);
    }

    private function mutasi(
        DompetKoperasi $dompet,
        string $tipe,
        string $jumlah,
        string $tanggal,
        ?string $referensiTipe,
        string $keterangan
    ): MutasiKas {
        return MutasiKas::query()->create([
            'idempotency_key' => 'test:mutasi:' . md5($dompet->id . $tipe . $jumlah . $tanggal . $referensiTipe . $keterangan),
            'dompet_id' => $dompet->id,
            'tipe' => $tipe,
            'jumlah' => $jumlah,
            'referensi_tipe' => $referensiTipe,
            'referensi_id' => $referensiTipe ? fake()->unique()->numberBetween(1, 999999) : null,
            'tanggal' => $tanggal,
            'keterangan' => $keterangan,
        ]);
    }
}
