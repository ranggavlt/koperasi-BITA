<?php

namespace Tests\Feature;

use App\Http\Controllers\WaserbaController;
use App\Models\JenisSimpanan;
use App\Models\User;
use App\Services\PosCheckoutService;
use Database\Seeders\AkunSeeder;
use Database\Seeders\KoperasiDummySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use ReflectionMethod;
use Tests\TestCase;

class SemanticMergeHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_domain_simpanan_final_adalah_manasuka_tanpa_alias_sukarela(): void
    {
        $this->assertSame('SIMPANAN_MANASUKA', JenisSimpanan::KODE_SIMPANAN_MANASUKA);
        $this->assertSame('manasuka', JenisSimpanan::KATEGORI_MANASUKA);
        $this->assertSame('Manasuka', JenisSimpanan::KATEGORI[JenisSimpanan::KATEGORI_MANASUKA]);
        $this->assertFalse(defined(JenisSimpanan::class . '::KODE_SIMPANAN_SUKARELA'));
        $this->assertFalse(defined(JenisSimpanan::class . '::KATEGORI_SUKARELA'));
    }

    public function test_seeder_membuat_dua_master_final_wajib_dan_manasuka_tanpa_legacy_sukarela(): void
    {
        $this->seed(AkunSeeder::class);
        $this->seed(KoperasiDummySeeder::class);

        $this->assertSame(0, JenisSimpanan::query()->where('aktif', true)->where('kategori', JenisSimpanan::KATEGORI_POKOK)->count());
        $this->assertSame(1, JenisSimpanan::query()->where('aktif', true)->where('kategori', JenisSimpanan::KATEGORI_WAJIB)->count());
        $this->assertSame(1, JenisSimpanan::query()->where('aktif', true)->where('kategori', JenisSimpanan::KATEGORI_MANASUKA)->count());
        $this->assertSame('10000.00', JenisSimpanan::query()
            ->where('aktif', true)
            ->where('kategori', JenisSimpanan::KATEGORI_WAJIB)
            ->value('nominal_default'));
        $this->assertNull(JenisSimpanan::query()
            ->where('aktif', true)
            ->where('kategori', JenisSimpanan::KATEGORI_WAJIB)
            ->value('interval_bulan'));
        $this->assertSame(0, JenisSimpanan::query()
            ->where('kode', 'SIMPANAN_SUKARELA')
            ->orWhere('kategori', 'sukarela')
            ->orWhere('nama_jenis', 'like', '%Sukarela%')
            ->count());
    }

    public function test_pos_final_memakai_route_waserba_dan_login_kasir_redirect_ke_waserba(): void
    {
        $kasir = User::factory()->create([
            'name' => 'Kasir Semantic',
            'email' => 'kasir-semantic@kbsm.test',
            'password' => Hash::make('Kbsm12345!'),
            'role' => 'kasir',
            'is_active' => true,
            'must_change_password' => false,
        ]);

        $this->assertTrue(Route::has('waserba.index'));
        $this->assertTrue(Route::has('waserba.store'));
        $this->assertFalse(Route::has('penjualan.index'));

        $this->post(route('login.submit'), [
            'email' => $kasir->email,
            'password' => 'Kbsm12345!',
        ])->assertRedirect(route('waserba.index'));
    }

    public function test_dashboard_kasir_tombol_mesin_kasir_mengarah_ke_waserba_dan_tanpa_tabel_transaksi_terakhir(): void
    {
        $kasir = User::factory()->create([
            'name' => 'Kasir Dashboard Semantic',
            'email' => 'kasir-dashboard-semantic@kbsm.test',
            'password' => Hash::make('Kbsm12345!'),
            'role' => 'kasir',
            'is_active' => true,
            'must_change_password' => false,
        ]);

        $this->actingAs($kasir)
            ->get(route('pages.dashboard'))
            ->assertOk()
            ->assertSee('Dashboard Kasir')
            ->assertSee(route('waserba.index'), false)
            ->assertDontSee('Transaksi Terakhir')
            ->assertDontSee('<table', false);
    }

    public function test_waserba_store_mendelegasikan_ke_pos_checkout_service(): void
    {
        $method = new ReflectionMethod(WaserbaController::class, 'store');
        $parameters = collect($method->getParameters());

        $this->assertTrue($parameters->contains(fn ($parameter): bool => $parameter->getType()?->getName() === PosCheckoutService::class));
    }

    public function test_livewire_stub_orphan_tidak_ada_dan_register_tetap_tidak_tersedia(): void
    {
        $this->assertFileDoesNotExist(resource_path('views/components/⚡pos-kasir.blade.php'));
        $this->assertFalse(Route::has('register'));
        $this->get('/register')->assertNotFound();
    }

    public function test_modul_incoming_tetap_memiliki_route_admin(): void
    {
        $this->assertTrue(Route::has('vendor.index'));
        $this->assertTrue(Route::has('invoice-penagihan.index'));
        $this->assertTrue(Route::has('dana-sosial.index'));
    }
}
