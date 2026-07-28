<?php

namespace Tests\Feature;

use App\Models\DetailPenjualan;
use App\Models\KategoriProduk;
use App\Models\Karyawan;
use App\Models\Pembayaran;
use App\Models\Penjualan;
use App\Models\Produk;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DashboardKasirTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['app.timezone' => 'Asia/Jakarta']);
        Carbon::setTestNow(Carbon::parse('2026-07-22 10:15:00', 'Asia/Jakarta'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_kasir_dapat_membuka_dashboard_dan_admin_tetap_mendapat_dashboard_admin(): void
    {
        $kasir = $this->user('kasir', 'kasir-dashboard@kbsm.test');
        $admin = $this->user('admin', 'admin-dashboard@kbsm.test');

        $this->actingAs($kasir)
            ->get(route('pages.dashboard'))
            ->assertOk()
            ->assertSee('Dashboard Kasir')
            ->assertSee('Buka Mesin Kasir')
            ->assertSee(route('penjualan.index'), false)
            ->assertDontSee('Transaksi Terakhir')
            ->assertDontSee('<table', false);

        $this->actingAs($admin)
            ->get(route('pages.dashboard'))
            ->assertOk()
            ->assertSee('Pendapatan Hari Ini')
            ->assertDontSee('Metode Pembayaran Hari Ini');
    }

    public function test_guest_diarahkan_ke_login_saat_membuka_dashboard(): void
    {
        $this->get(route('pages.dashboard'))
            ->assertRedirect(route('login'));
    }

    public function test_kpi_hanya_menghitung_transaksi_pos_valid_hari_ini(): void
    {
        $kasir = $this->user('kasir', 'kasir-kpi@kbsm.test');
        $produkAlpha = $this->produk('Produk Alpha Dashboard', 25000);
        $produkBeta = $this->produk('Produk Beta Dashboard', 10000);
        $produkBatal = $this->produk('Produk Batal Dashboard', 999999);

        $this->penjualan(
            kode: 'POS-TODAY-001',
            tanggal: '2026-07-22 09:00:00',
            grandTotal: 100000,
            metodePembayaran: Pembayaran::METODE_TUNAI,
            statusPembayaran: Pembayaran::STATUS_PAID,
            items: [
                [$produkAlpha, 3, 75000],
                [$produkBeta, 2, 25000],
            ],
        );

        $this->penjualan(
            kode: 'POS-TODAY-002',
            tanggal: '2026-07-22 10:00:00',
            grandTotal: 50000,
            metodePembayaran: Pembayaran::METODE_POTONG_GAJI,
            statusPembayaran: Pembayaran::STATUS_PENDING_PAYROLL,
            items: [
                [$produkBeta, 5, 50000],
            ],
        );

        $this->penjualan(
            kode: 'POS-CANCELLED-001',
            tanggal: '2026-07-22 11:00:00',
            grandTotal: 999999,
            metodePembayaran: Pembayaran::METODE_TUNAI,
            statusPembayaran: Pembayaran::STATUS_CANCELLED,
            statusPenjualan: Penjualan::STATUS_CANCELLED,
            items: [
                [$produkBatal, 99, 999999],
            ],
        );

        $this->penjualan(
            kode: 'POS-REVERSED-001',
            tanggal: '2026-07-22 12:00:00',
            grandTotal: 888888,
            metodePembayaran: Pembayaran::METODE_QRIS,
            statusPembayaran: Pembayaran::STATUS_REFUNDED,
            statusPenjualan: Penjualan::STATUS_REVERSED,
            reversedAt: '2026-07-22 12:30:00',
            items: [
                [$produkBatal, 88, 888888],
            ],
        );

        $this->penjualan(
            kode: 'POS-YESTERDAY-001',
            tanggal: '2026-07-21 09:00:00',
            grandTotal: 77000,
            metodePembayaran: Pembayaran::METODE_TRANSFER_BANK,
            statusPembayaran: Pembayaran::STATUS_PAID,
            items: [
                [$produkAlpha, 7, 77000],
            ],
        );

        $this->actingAs($kasir)
            ->get(route('pages.dashboard'))
            ->assertOk()
            ->assertSeeInOrder([
                'Total Transaksi Hari Ini',
                '2',
                'Nilai Penjualan Hari Ini',
                'Rp 150.000',
                'Item Terjual Hari Ini',
                '10',
                'Rata-rata Transaksi',
                'Rp 75.000',
            ])
            ->assertSeeInOrder([
                'Tunai',
                'Diterima langsung',
                'Rp 100.000',
                '1 transaksi',
            ])
            ->assertSeeInOrder([
                'Potong Gaji',
                '1 transaksi menunggu payroll',
                'Rp 50.000',
                '1 transaksi',
            ])
            ->assertSeeInOrder([
                'Transfer Bank',
                'Rp 0',
                '0 transaksi',
                'QRIS',
                'Rp 0',
                '0 transaksi',
            ])
            ->assertSeeInOrder([
                'Produk Beta Dashboard',
                '7 item terjual',
                'Produk Alpha Dashboard',
                '3 item terjual',
            ])
            ->assertDontSee('Produk Batal Dashboard')
            ->assertDontSee('Rp 999.999')
            ->assertDontSee('Transaksi Terakhir');
    }

    public function test_rata_rata_dan_produk_terlaris_aman_saat_belum_ada_transaksi(): void
    {
        $kasir = $this->user('kasir', 'kasir-empty-dashboard@kbsm.test');

        $this->actingAs($kasir)
            ->get(route('pages.dashboard'))
            ->assertOk()
            ->assertSeeInOrder([
                'Total Transaksi Hari Ini',
                '0',
                'Nilai Penjualan Hari Ini',
                'Rp 0',
                'Item Terjual Hari Ini',
                '0',
                'Rata-rata Transaksi',
                'Rp 0',
            ])
            ->assertSee('Belum ada produk terjual hari ini')
            ->assertDontSee('Transaksi Terakhir');
    }

    private function user(string $role, string $email): User
    {
        return User::factory()->create([
            'name' => ucfirst($role) . ' Dashboard',
            'email' => $email,
            'password' => Hash::make('Kbsm12345!'),
            'role' => $role,
            'is_active' => true,
            'must_change_password' => false,
        ]);
    }

    private function produk(string $nama, int $hargaJual): Produk
    {
        $kategori = KategoriProduk::query()->firstOrCreate(
            ['nama_kategori' => 'Dashboard Test'],
            ['deskripsi' => 'Kategori untuk test dashboard kasir.']
        );

        return Produk::query()->create([
            'nama_produk' => $nama,
            'foto' => Produk::DEMO_PHOTO_PREFIX . 'fallback-produk.svg',
            'kategori_id' => $kategori->id,
            'harga_beli' => max(0, $hargaJual - 1000),
            'harga_jual' => $hargaJual,
            'stok' => 100,
            'konsinyasi' => false,
            'reseller_id' => null,
            'harga_setor' => 0,
        ]);
    }

    private function penjualan(
        string $kode,
        string $tanggal,
        int $grandTotal,
        string $metodePembayaran,
        string $statusPembayaran,
        array $items,
        string $statusPenjualan = Penjualan::STATUS_COMPLETED,
        ?string $reversedAt = null,
    ): Penjualan {
        $karyawan = Karyawan::factory()->create();

        $penjualan = Penjualan::query()->create([
            'idempotency_key' => 'dashboard-' . strtolower($kode),
            'kode_transaksi' => $kode,
            'tipe_pelanggan' => Penjualan::TIPE_UMUM,
            'karyawan_id' => $karyawan->id,
            'anggota_id' => null,
            'tanggal_transaksi' => $tanggal,
            'total_harga' => $grandTotal,
            'diskon' => 0,
            'grand_total' => $grandTotal,
            'status' => $statusPenjualan,
            'reversed_at' => $reversedAt,
        ]);

        foreach ($items as [$produk, $qty, $subtotal]) {
            DetailPenjualan::query()->create([
                'penjualan_id' => $penjualan->id,
                'produk_id' => $produk->id,
                'qty' => $qty,
                'harga' => (int) round($subtotal / max(1, $qty)),
                'subtotal' => $subtotal,
                'konsinyasi' => false,
                'reseller_id' => null,
                'harga_setor' => 0,
                'subtotal_setor' => 0,
            ]);
        }

        Pembayaran::query()->create([
            'idempotency_key' => 'dashboard-payment-' . strtolower($kode),
            'penjualan_id' => $penjualan->id,
            'metode_pembayaran' => $metodePembayaran,
            'status' => $statusPembayaran,
            'jumlah_bayar' => $grandTotal,
            'paid_at' => $statusPembayaran === Pembayaran::STATUS_PAID ? $tanggal : null,
        ]);

        return $penjualan;
    }
}
