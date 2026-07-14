<?php

namespace Tests\Feature;

use App\Models\AsetKoperasi;
use App\Models\AsetMobil;
use App\Models\Karyawan;
use App\Models\SewaMobil;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
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
        ]);
    }

    public function test_route_matrix_for_finance_kasir_karyawan_guest_and_disabled_features(): void
    {
        $finance = $this->user('keuangan');
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
        $this->actingAs($finance)->get(route('shu-koperasi.index'))->assertNotFound();

        $this->actingAs($kasir)->get(route('penjualan.index'))->assertOk();
        $this->actingAs($kasir)->get(route('karyawan.index'))->assertForbidden();
        $this->actingAs($kasir)->get(route('periode-potong-gaji.index'))->assertForbidden();
        $this->actingAs($kasir)->get(route('sewa-mobil.finance.index'))->assertForbidden();

        $this->actingAs($employee)->get(route('sewa-mobil.karyawan.index'))->assertOk();
        $this->actingAs($employee)->get(route('penjualan.index'))->assertForbidden();
        $this->actingAs($employee)->get(route('karyawan.index'))->assertForbidden();
    }

    public function test_sidebar_and_module_search_follow_role_and_disabled_feature_flags(): void
    {
        $finance = $this->user('keuangan');
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
            ->assertSee('Pengajuan Sewa Mobil')
            ->assertDontSee('Penjualan / Kasir')
            ->assertDontSee('Periode Potong Gaji')
            ->assertDontSee('Transaksi SHU')
            ->assertDontSee('Jasa Print');
    }

    public function test_employee_cannot_access_other_employee_sewa_mobil_resource(): void
    {
        $owner = $this->employeeUser('owner-sewa@kbsm.test');
        $other = $this->employeeUser('other-sewa@kbsm.test');
        $asset = $this->mobil();

        $sewa = SewaMobil::query()->create([
            'kode_sewa' => null,
            'aset_koperasi_id' => $asset->id,
            'karyawan_id' => $owner->karyawan_id,
            'pemohon_user_id' => $owner->id,
            'nama_perusahaan_snapshot' => 'Bita Enarcon Engineering',
            'nama_kegiatan' => 'Rapat Proyek',
            'lokasi_kegiatan' => 'Karawang',
            'mulai_at' => '2026-08-01 08:00:00',
            'selesai_at' => '2026-08-01 17:00:00',
            'tarif_total' => '0.00',
            'status' => SewaMobil::STATUS_DRAFT,
            'status_pembayaran' => SewaMobil::PEMBAYARAN_BELUM_BAYAR,
            'created_by' => $owner->id,
            'updated_by' => $owner->id,
            'idempotency_key' => 'access-matrix-sewa',
        ]);

        $payload = [
            'aset_koperasi_id' => $asset->id,
            'nama_kegiatan' => 'Manipulasi',
            'lokasi_kegiatan' => 'Jakarta',
            'mulai_at' => '2026-08-02 08:00:00',
            'selesai_at' => '2026-08-02 17:00:00',
        ];

        $this->actingAs($other)->get(route('sewa-mobil.karyawan.edit', $sewa))->assertForbidden();
        $this->actingAs($other)->put(route('sewa-mobil.karyawan.update', $sewa), $payload)->assertForbidden();
        $this->actingAs($other)->post(route('sewa-mobil.karyawan.submit', $sewa))->assertForbidden();
        $this->actingAs($other)->post(route('sewa-mobil.karyawan.cancel', $sewa), ['alasan' => 'Bukan milik saya'])->assertForbidden();
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
            ->get(route('sewa-mobil.karyawan.index'))
            ->assertForbidden();
    }

    public function test_public_registration_cannot_mutate_role_from_request(): void
    {
        $this->post(route('register.submit'), [
            'name' => 'Public Role Mutation',
            'email' => 'public-role-mutation@kbsm.test',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => 'keuangan',
            'is_active' => false,
        ])->assertRedirect(route('login'));

        $this->assertDatabaseHas('users', [
            'email' => 'public-role-mutation@kbsm.test',
            'role' => 'kasir',
            'is_active' => true,
        ]);
    }

    public function test_preflight_access_is_read_only_and_clean_on_valid_data(): void
    {
        $this->user('keuangan');
        $userCount = User::query()->count();
        $karyawanCount = Karyawan::query()->count();

        $this->artisan('koperasi:preflight-access')->assertExitCode(0);

        $this->assertSame($userCount, User::query()->count());
        $this->assertSame($karyawanCount, Karyawan::query()->count());
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

    private function mobil(): AsetKoperasi
    {
        $asset = AsetKoperasi::query()->create([
            'kode_aset' => 'MBL-ACCESS',
            'jenis_aset' => AsetKoperasi::JENIS_MOBIL,
            'merek' => 'Toyota',
            'model' => 'Avanza',
            'status' => AsetKoperasi::STATUS_TERSEDIA,
        ]);

        AsetMobil::query()->create([
            'aset_koperasi_id' => $asset->id,
            'plat_nomor' => 'B 1234 KBS',
            'tahun' => 2022,
            'warna' => 'Hitam',
        ]);

        return $asset;
    }
}
