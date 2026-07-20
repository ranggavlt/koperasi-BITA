<?php

namespace Tests\Feature;

use App\Models\Karyawan;
use App\Models\User;
use Database\Seeders\KoperasiDummySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class AccessMatrixTest extends TestCase
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

    public function test_route_matrix_for_finance_kasir_karyawan_guest_and_disabled_features(): void
    {
        $finance = $this->user('admin');
        $kasir = $this->user('kasir');
        $employee = $this->employeeUser('employee-access@kbsm.test');

        $this->get(route('karyawan.index'))->assertRedirect(route('login'));

        $this->actingAs($finance)->get(route('karyawan.index'))->assertOk();
        $this->actingAs($finance)->get(route('periode-potong-gaji.index'))->assertOk();
        $this->actingAs($finance)->get(route('pinjaman.index'))->assertOk();
        $this->actingAs($finance)->get(route('aset-mobil.index'))->assertOk();
        $this->actingAs($finance)->get(route('sewa-printer.index'))->assertOk();
        $this->actingAs($finance)->get(route('beban-operasional.index'))->assertOk();
        $this->actingAs($finance)->get(route('laporan.potong-gaji'))->assertOk();
        $this->actingAs($finance)->get(route('users.index'))->assertOk();
        $this->actingAs($finance)->get(route('shu-koperasi.index'))->assertNotFound();
        $this->assertFalse(Route::has('users.destroy'));

        $this->actingAs($kasir)->get(route('penjualan.index'))->assertOk();
        $this->actingAs($kasir)->get(route('karyawan.index'))->assertForbidden();
        $this->actingAs($kasir)->get(route('periode-potong-gaji.index'))->assertForbidden();
        $this->actingAs($kasir)->get(route('sewa-mobil.finance.index'))->assertForbidden();
        $this->actingAs($kasir)->get(route('users.index'))->assertForbidden();

        $this->assertFalse(Route::has('sewa-mobil.karyawan.index'));
        $this->actingAs($employee)->get('/pengajuan-sewa-mobil')->assertNotFound();
        $this->actingAs($employee)->get(route('sewa-mobil.finance.index'))->assertForbidden();
        $this->actingAs($employee)->get(route('penjualan.index'))->assertForbidden();
        $this->actingAs($employee)->get(route('karyawan.index'))->assertForbidden();
    }

    public function test_sidebar_and_module_search_follow_role_and_disabled_feature_flags(): void
    {
        $finance = $this->user('admin');
        $kasir = $this->user('kasir');
        $employee = $this->employeeUser('employee-sidebar@kbsm.test');

        $this->actingAs($finance)
            ->get(route('pages.dashboard'))
            ->assertOk()
            ->assertSee('Karyawan')
            ->assertSee('Sewa Printer')
            ->assertSee('Laporan Potong Gaji')
            ->assertDontSee('Transaksi SHU')
            ->assertDontSee('Jasa Print')
            ->assertDontSee('atau SHU');

        $this->actingAs($kasir)
            ->get(route('pages.dashboard'))
            ->assertOk()
            ->assertSee('Penjualan / Kasir')
            ->assertDontSee('Periode Potong Gaji')
            ->assertDontSee('Transaksi SHU')
            ->assertDontSee('Jasa Print');

        $this->actingAs($employee)
            ->get(route('pages.dashboard'))
            ->assertOk()
            ->assertDontSee('Pengajuan Sewa Mobil')
            ->assertDontSee('Penjualan / Kasir')
            ->assertDontSee('Periode Potong Gaji')
            ->assertDontSee('Transaksi SHU')
            ->assertDontSee('Jasa Print');
    }

    public function test_employee_self_service_sewa_mobil_routes_tidak_tersedia(): void
    {
        $employee = $this->employeeUser('owner-sewa@kbsm.test');

        $this->assertFalse(Route::has('sewa-mobil.karyawan.index'));
        $this->assertFalse(Route::has('sewa-mobil.karyawan.store'));
        $this->assertFalse(Route::has('sewa-mobil.karyawan.edit'));
        $this->assertFalse(Route::has('sewa-mobil.karyawan.update'));
        $this->assertFalse(Route::has('sewa-mobil.karyawan.submit'));
        $this->assertFalse(Route::has('sewa-mobil.karyawan.cancel'));

        $this->actingAs($employee)->get('/pengajuan-sewa-mobil')->assertNotFound();
    }

    public function test_inactive_or_stopped_employee_cannot_login_or_use_existing_session(): void
    {
        $karyawan = Karyawan::factory()->create([
            'email' => 'stopped@kbsm.test',
            'status_kerja' => Karyawan::STATUS_BERHENTI,
            'tanggal_berhenti' => '2026-07-01',
        ]);

        $user = User::factory()->create([
            'name' => $karyawan->nama,
            'email' => 'stopped-user@kbsm.test',
            'password' => Hash::make('password123'),
            'role' => 'karyawan',
            'karyawan_id' => $karyawan->id,
            'is_active' => true,
            'must_change_password' => false,
        ]);

        $this->post(route('login.submit'), [
            'email' => 'stopped-user@kbsm.test',
            'password' => 'password123',
        ])->assertSessionHasErrors('email');

        $this->actingAs($user)
            ->get('/pengajuan-sewa-mobil')
            ->assertNotFound();
    }

    public function test_public_registration_route_is_not_available(): void
    {
        $this->get('/register')->assertNotFound();

        $this->post('/register', [
            'name' => 'Public Role Mutation',
            'email' => 'public-role-mutation@kbsm.test',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => 'admin',
            'is_active' => false,
        ])->assertNotFound();

        $this->assertDatabaseMissing('users', [
            'email' => 'public-role-mutation@kbsm.test',
        ]);
    }

    public function test_legacy_keuangan_role_dimigrasikan_menjadi_admin(): void
    {
        $legacy = User::factory()->create([
            'role' => 'keuangan',
            'is_active' => true,
            'must_change_password' => false,
        ]);

        $migration = include database_path('migrations/2026_07_20_000001_migrate_legacy_keuangan_role_to_admin.php');
        $migration->up();

        $this->assertSame('admin', $legacy->fresh()->role);
        $this->assertDatabaseMissing('users', [
            'id' => $legacy->id,
            'role' => 'keuangan',
        ]);
    }

    public function test_admin_dapat_mengelola_akun_karyawan_dan_role_lain_ditolak(): void
    {
        $admin = $this->user('admin');
        $kasir = $this->user('kasir');
        $employee = $this->employeeUser('blocked-account-manager@kbsm.test');
        $target = Karyawan::factory()->create([
            'email' => 'target-account@kbsm.test',
        ]);

        $this->actingAs($kasir)
            ->post(route('karyawan.akun.store', $target), [
                'temporary_password' => 'password123',
                'role' => 'karyawan',
            ])
            ->assertForbidden();

        $this->actingAs($employee)
            ->post(route('karyawan.akun.store', $target), [
                'temporary_password' => 'password123',
                'role' => 'karyawan',
            ])
            ->assertForbidden();

        $this->actingAs($admin)
            ->post(route('karyawan.akun.store', $target), [
                'temporary_password' => 'password123',
                'role' => 'karyawan',
            ])
            ->assertRedirect(route('karyawan.index'));

        $this->assertDatabaseHas('users', [
            'email' => 'target-account@kbsm.test',
            'role' => 'karyawan',
            'karyawan_id' => $target->id,
            'is_active' => true,
            'must_change_password' => true,
            'account_created_by' => $admin->id,
        ]);

        $account = User::query()->where('karyawan_id', $target->id)->firstOrFail();

        $this->actingAs($kasir)
            ->patch(route('karyawan.akun.password', $target), [
                'temporary_password' => 'password456',
                'role' => 'karyawan',
            ])
            ->assertForbidden();

        $this->actingAs($employee)
            ->patch(route('karyawan.akun.deactivate', $target))
            ->assertForbidden();

        $this->actingAs($admin)
            ->patch(route('karyawan.akun.password', $target), [
                'temporary_password' => 'password456',
                'role' => 'karyawan',
            ])
            ->assertRedirect(route('karyawan.index'));

        $this->assertTrue($account->fresh()->must_change_password);
    }

    public function test_preflight_access_is_read_only_and_clean_on_valid_data(): void
    {
        $this->user('admin');
        $userCount = User::query()->count();
        $karyawanCount = Karyawan::query()->count();

        $this->artisan('koperasi:preflight-access')->assertExitCode(0);

        $this->assertSame($userCount, User::query()->count());
        $this->assertSame($karyawanCount, Karyawan::query()->count());
    }

    public function test_preflight_access_bersih_setelah_seed(): void
    {
        $this->seed(KoperasiDummySeeder::class);

        $this->assertSame(0, User::query()->where('role', 'keuangan')->count());
        $this->assertGreaterThan(0, User::query()->where('role', 'admin')->count());

        $this->artisan('koperasi:preflight-access')->assertExitCode(0);
    }

    private function user(string $role): User
    {
        return User::factory()->create([
            'role' => $role,
            'is_active' => true,
            'must_change_password' => false,
        ]);
    }

    private function employeeUser(string $email): User
    {
        $karyawan = Karyawan::factory()->create(['email' => $email]);

        return User::factory()->create([
            'name' => $karyawan->nama,
            'email' => 'user-' . $email,
            'role' => 'karyawan',
            'karyawan_id' => $karyawan->id,
            'is_active' => true,
            'must_change_password' => false,
        ]);
    }

}
