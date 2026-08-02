<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\NavigationMenu;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class NavigationFinalizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'features.shu_enabled' => false,
            'features.jasa_print_enabled' => false,
            'features.master_printer_enabled' => false,
        ]);
    }

    public function test_config_memuat_keputusan_final_dan_visible_route_tidak_fiktif(): void
    {
        $modules = collect(config('navigation.modules', []))->keyBy('key');
        $groups = collect(config('navigation.groups', []))->pluck('label', 'key');

        $this->assertSame([
            'POS / Kasir',
            'Master Data',
            'Kas & Bank',
            'Simpan Pinjam',
            'Potong Gaji',
            'Usaha Koperasi',
            'Operasional',
            'Akuntansi',
            'Keanggotaan & Koreksi',
            'SHU & Dana Sosial',
        ], $groups->values()->all());

        $this->assertSame('Sewa Hardware', $modules['sewa_hardware']['label']);
        $this->assertSame('sewa-hardware.index', $modules['sewa_hardware']['route']);
        $this->assertSame('Tagihan Tunai', $modules['tagihan_tunai']['label']);
        $this->assertSame('Riwayat Koreksi Transaksi', $modules['riwayat_koreksi_transaksi']['label']);
        $this->assertSame('Daftar Akun / COA', $modules['coa']['label']);
        $this->assertFalse($modules['shu_koperasi']['sidebar']);
        $this->assertFalse($modules['shu_koperasi']['search']);
        $this->assertSame('shu_enabled', $modules['shu_koperasi']['feature']);
        $this->assertFalse($modules['klaim_dana_sosial']['enabled']);
        $this->assertSame('master_printer_enabled', $modules['printer_koperasi']['feature']);

        $this->actingAs($this->user('admin'));

        NavigationMenu::visibleRouteNames('sidebar')
            ->each(function (string $route): bool {
                $this->assertTrue(Route::has($route), "Route {$route} harus tersedia.");

                return true;
            });
        NavigationMenu::visibleRouteNames('search')
            ->each(function (string $route): bool {
                $this->assertTrue(Route::has($route), "Route {$route} harus tersedia.");

                return true;
            });
    }

    public function test_admin_sidebar_dan_navbar_search_memakai_config_final(): void
    {
        $admin = $this->user('admin');

        $response = $this->actingAs($admin)
            ->get(route('pages.dashboard'))
            ->assertOk()
            ->assertSee('data-sidebar-accordion', false)
            ->assertSee('kbsm_sidebar_groups:'.$admin->id, false)
            ->assertSeeInOrder([
                'Master Data',
                'Manajemen User',
                'Karyawan',
                'Anggota',
                'Pengurus',
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
            ->assertDontSee('POS / Kasir')
            ->assertDontSee('Penjualan / Kasir')
            ->assertDontSee('Pembayaran Konsinyasi')
            ->assertDontSee('Laporan Konsinyasi')
            ->assertDontSee('Printer Koperasi')
            ->assertDontSee('Outstanding Cash')
            ->assertDontSee('Audit Reversal')
            ->assertDontSee('Transaksi SHU')
            ->assertDontSee('Klaim Dana Sosial')
            ->assertDontSee('Jasa Print');

        $response->assertSee('Cari modul');
    }

    public function test_layout_global_memakai_offset_sidebar_semantik_dan_backdrop(): void
    {
        $admin = $this->user('admin');

        $this->actingAs($admin)
            ->get(route('invoice-penagihan.index'))
            ->assertOk()
            ->assertSee('kbsm-layout-main', false)
            ->assertSee('kbsm-navbar', false)
            ->assertSee('data-kbsm-sidebar', false)
            ->assertSee('data-kbsm-sidebar-backdrop', false)
            ->assertSee('data-sidebar-accordion', false)
            ->assertDontSee('xl:ml-68.5', false)
            ->assertDontSee('main.xl\\:ml-68\\.5', false)
            ->assertDontSee('max-w-62.5', false);
    }

    public function test_css_layout_sidebar_memiliki_satu_sumber_ukuran_desktop_mobile(): void
    {
        $css = file_get_contents(public_path('assets/css/kbsm-theme.css'));
        $sidebar = file_get_contents(resource_path('views/layout/sidebar.blade.php'));

        $this->assertStringContainsString('--kbsm-sidebar-width: 224px;', $css);
        $this->assertStringContainsString('--kbsm-sidebar-gap: 16px;', $css);
        $this->assertStringContainsString('--kbsm-content-offset:', $css);
        $this->assertStringContainsString('.kbsm-layout-main', $css);
        $this->assertStringContainsString('.kbsm-sidebar-backdrop--visible', $css);
        $this->assertStringContainsString('.kbsm-sidebar-drawer-open', $css);
        $this->assertMatchesRegularExpression('/@media\s*\(min-width:\s*1200px\)[\s\S]*?\.kbsm-layout-main\s*\{[\s\S]*?margin-left:\s*var\(--kbsm-content-offset\)/', $css);
        $this->assertMatchesRegularExpression('/@media\s*\(max-width:\s*1199px\)[\s\S]*?\.kbsm-sidebar\s*\{[\s\S]*?width:\s*min\(88vw,\s*232px\)/', $css);
        $this->assertStringNotContainsString('main.xl\\:ml-68\\.5', $css);
        $this->assertStringNotContainsString('max-w-62.5', $sidebar);
        $this->assertStringNotContainsString('xl:ml-68.5', file_get_contents(resource_path('views/layout/main.blade.php')));
    }

    public function test_sidebar_drawer_script_menutup_backdrop_escape_dan_body_scroll(): void
    {
        $js = file_get_contents(public_path('assets/js/sidenav-burger.js'));

        $this->assertStringContainsString('[data-kbsm-sidebar-backdrop]', $js);
        $this->assertStringContainsString('kbsm-sidebar-backdrop--visible', $js);
        $this->assertStringContainsString('kbsm-sidebar-drawer-open', $js);
        $this->assertStringContainsString('event.key === "Escape"', $js);
        $this->assertStringContainsString('window.matchMedia("(min-width: 1200px)")', $js);
        $this->assertStringNotContainsString('var burger = sidenav_trigger.firstElementChild;', $js);
        $this->assertStringNotContainsString('sidenav_trigger.click();', $js);
    }

    public function test_kasir_hanya_melihat_menu_kasir_dan_konsinyasi(): void
    {
        $kasir = $this->user('kasir');

        $this->actingAs($kasir)
            ->get(route('pages.dashboard'))
            ->assertOk()
            ->assertSee('Dashboard')
            ->assertSee('POS / Kasir')
            ->assertSee('Penjualan / Kasir')
            ->assertSee('Pembayaran Konsinyasi')
            ->assertSee('Laporan Konsinyasi')
            ->assertDontSee('data-sidebar-group="master_data"', false)
            ->assertDontSee('data-sidebar-module="karyawan"', false)
            ->assertDontSee('data-sidebar-group="kas_bank"', false)
            ->assertDontSee('data-sidebar-group="simpan_pinjam"', false)
            ->assertDontSee('data-sidebar-group="potong_gaji"', false)
            ->assertDontSee('data-sidebar-module="sewa_hardware"', false)
            ->assertDontSee('Daftar Akun / COA')
            ->assertDontSee('data-sidebar-module="riwayat_koreksi_transaksi"', false)
            ->assertDontSee('Transaksi SHU')
            ->assertDontSee('Klaim Dana Sosial');
    }

    public function test_karyawan_tidak_melihat_menu_admin_atau_kasir(): void
    {
        $labels = NavigationMenu::visibleModules('sidebar', 'karyawan')->pluck('label')->all();

        $this->assertContains('Dashboard', $labels);
        $this->assertNotContains('Penjualan / Kasir', $labels);
        $this->assertNotContains('Pembayaran Konsinyasi', $labels);
        $this->assertNotContains('Master Data', $labels);
        $this->assertNotContains('Karyawan', $labels);
        $this->assertNotContains('Sewa Hardware', $labels);
        $this->assertNotContains('Periode Potong Gaji', $labels);
        $this->assertNotContains('Daftar Akun / COA', $labels);
    }

    public function test_public_registration_tetap_tidak_tersedia(): void
    {
        $this->assertFalse(Route::has('register'));
        $this->get('/register')->assertNotFound();
    }

    private function user(string $role): User
    {
        return User::factory()->create([
            'role' => $role,
            'is_active' => true,
            'must_change_password' => false,
        ]);
    }
}
