<?php

namespace Tests\Feature;

use App\Models\Akun;
use App\Models\Anggota;
use App\Models\AsetKoperasi;
use App\Models\DompetKoperasi;
use App\Models\Karyawan;
use App\Models\MutasiKas;
use App\Models\PembayaranSewaMobil;
use App\Models\PemakaianPotongGaji;
use App\Models\PengurusKoperasi;
use App\Models\SewaMobil;
use App\Models\User;
use App\Services\AsetKoperasiService;
use App\Services\KaryawanAccountService;
use App\Services\SewaMobilService;
use Database\Seeders\AkunSeeder;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class SewaMobilTest extends TestCase
{
    use RefreshDatabase;

    public function test_finance_membuat_akun_karyawan_unique_nonaktif_login_dan_must_change_password(): void
    {
        $service = app(KaryawanAccountService::class);
        $finance = $this->user('admin');
        $karyawan = Karyawan::factory()->create(['email' => 'karyawan.sewa@bita.test']);

        $account = $service->createAccount($karyawan, 'sementara123', $finance->id);

        $this->assertSame('karyawan', $account->role);
        $this->assertTrue($account->must_change_password);
        $this->expectValidation(fn () => $service->createAccount($karyawan, 'sementara123', $finance->id));

        $this->post(route('login.submit'), [
            'email' => 'karyawan.sewa@bita.test',
            'password' => 'sementara123',
        ])->assertRedirect(route('password.change'));

        $this->actingAs($account)
            ->post(route('password.update'), [
                'current_password' => 'sementara123',
                'password' => 'password-baru',
                'password_confirmation' => 'password-baru',
            ])
            ->assertRedirect(route('pages.dashboard'));

        $this->assertFalse($account->fresh()->must_change_password);

        $service->deactivateAccount($karyawan, $finance->id);
        $this->post(route('logout'));

        $this->post(route('login.submit'), [
            'email' => 'karyawan.sewa@bita.test',
            'password' => 'password-baru',
        ])->assertSessionHasErrors('email');

        $this->post(route('register.submit'), [
            'name' => 'Public User',
            'email' => 'public-role@bita.test',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => 'karyawan',
        ])->assertRedirect(route('login'));

        $this->assertDatabaseHas('users', [
            'email' => 'public-role@bita.test',
            'role' => 'kasir',
        ]);
    }

    public function test_karyawan_aktif_nonanggota_dapat_mengajukan_dan_hanya_mengelola_milik_sendiri(): void
    {
        $service = app(SewaMobilService::class);
        $asset = $this->mobil();
        [$karyawanA, $userA] = $this->employeeUser('a@bita.test');
        [, $userB] = $this->employeeUser('b@bita.test');

        $sewa = $service->createDraft($this->payload($asset), $userA);

        $this->assertSame($karyawanA->id, $sewa->karyawan_id);
        $this->assertSame(SewaMobil::STATUS_DRAFT, $sewa->status);
        $this->assertNull($sewa->kode_sewa);

        $service->updateDraft($sewa, $this->payload($asset, ['nama_kegiatan' => 'Rapat Updated']), $userA);
        $submitted = $service->submit($sewa->fresh(), $userA);

        $this->assertSame(SewaMobil::STATUS_DIAJUKAN, $submitted->status);
        $this->assertMatchesRegularExpression('/^SWM-\d{6}-\d{6}$/', $submitted->kode_sewa);

        $this->expectValidation(fn () => $service->updateDraft($submitted, $this->payload($asset), $userA));
        $this->expectValidation(fn () => $service->cancelByEmployee($submitted, $userB, 'Manipulasi ownership'));

        $service->cancelByEmployee($submitted, $userA, 'Kegiatan batal');
        $this->assertSame(SewaMobil::STATUS_DIBATALKAN, $submitted->fresh()->status);

        $this->actingAs($userB)->get(route('sewa-mobil.karyawan.index'))->assertOk()->assertDontSee($submitted->kode_sewa);
    }

    public function test_approval_membutuhkan_finance_pengurus_aktif_tarif_dan_menolak_overlap(): void
    {
        $service = app(SewaMobilService::class);
        $finance = $this->user('admin');
        $asset = $this->mobil();
        $pengurus = $this->pengurus();
        [, $employee] = $this->employeeUser('sewa1@bita.test');
        [, $employee2] = $this->employeeUser('sewa2@bita.test');

        $first = $service->submit($service->createDraft($this->payload($asset), $employee), $employee);
        $approved = $service->approve($first, ['tarif_total' => 300000, 'pengurus_penyetuju_id' => $pengurus->id], $finance->id);

        $this->assertSame(SewaMobil::STATUS_DISETUJUI, $approved->status);
        $this->assertSame($pengurus->jabatan, $approved->jabatan_pengurus_snapshot);
        $this->assertNotNull($approved->approval_recorded_by);

        $overlap = $service->submit($service->createDraft($this->payload($asset, ['nama_kegiatan' => 'Bentrok']), $employee2), $employee2);
        $this->expectValidation(fn () => $service->approve($overlap, ['tarif_total' => 250000, 'pengurus_penyetuju_id' => $pengurus->id], $finance->id));

        $pengurus->update(['status' => PengurusKoperasi::STATUS_NONAKTIF]);
        $nonOverlap = $service->submit($service->createDraft($this->payload($asset, [
            'mulai_at' => '2026-08-02 08:00',
            'selesai_at' => '2026-08-02 17:00',
        ]), $employee2), $employee2);
        $this->expectValidation(fn () => $service->approve($nonOverlap, ['tarif_total' => 250000, 'pengurus_penyetuju_id' => $pengurus->id], $finance->id));
    }

    public function test_pembayaran_dimuka_full_dompet_mutasi_jurnal_dan_tidak_membuat_ledger_payroll(): void
    {
        $service = app(SewaMobilService::class);
        $finance = $this->user('admin');
        $sewa = $this->approvedSewa($service, $finance);
        $kas = $this->dompet(DompetKoperasi::JENIS_KAS, 1000000);
        $bank = $this->dompet(DompetKoperasi::JENIS_BANK, 1000000);

        $this->expectValidation(fn () => $service->pay($sewa, [
            'metode_pembayaran' => PembayaranSewaMobil::METODE_TUNAI,
            'dompet_id' => $bank->id,
            'jumlah_bayar' => 300000,
        ], $finance->id));

        $this->expectValidation(fn () => $service->pay($sewa, [
            'metode_pembayaran' => PembayaranSewaMobil::METODE_TUNAI,
            'dompet_id' => $kas->id,
            'jumlah_bayar' => 299999,
        ], $finance->id));

        $paid = $service->pay($sewa, [
            'metode_pembayaran' => PembayaranSewaMobil::METODE_TUNAI,
            'dompet_id' => $kas->id,
            'jumlah_bayar' => 300000,
        ], $finance->id);

        $this->assertSame('1300000.00', $kas->fresh()->saldo);
        $this->assertSame(SewaMobil::PEMBAYARAN_PAID, $paid->status_pembayaran);
        $this->assertSame(1, MutasiKas::query()->where('referensi_tipe', PembayaranSewaMobil::class)->where('tipe', 'masuk')->count());
        $this->assertDatabaseHas('jurnal_umum_detail', [
            'akun_kode' => '206',
            'kredit' => '300000.00',
        ]);
        $this->assertDatabaseMissing('jurnal_umum_detail', [
            'akun_kode' => '404',
            'kredit' => '300000.00',
        ]);
        $this->assertSame(0, PemakaianPotongGaji::query()->count());
    }

    public function test_lifecycle_berjalan_selesai_mengubah_status_aset_dan_pengakuan_pendapatan_idempotent(): void
    {
        $service = app(SewaMobilService::class);
        $finance = $this->user('admin');
        $sewa = $this->approvedSewa($service, $finance);
        $kas = $this->dompet(DompetKoperasi::JENIS_KAS);

        $this->expectValidation(fn () => $service->start($sewa, $finance->id));

        $paid = $service->pay($sewa, [
            'metode_pembayaran' => PembayaranSewaMobil::METODE_TUNAI,
            'dompet_id' => $kas->id,
            'jumlah_bayar' => 300000,
        ], $finance->id);

        $running = $service->start($paid, $finance->id);
        $this->assertSame(SewaMobil::STATUS_BERJALAN, $running->status);
        $this->assertSame(AsetKoperasi::STATUS_DIGUNAKAN_DISEWA, $running->aset->fresh()->status);

        $completed = $service->complete($running, $finance->id);
        $completedAgain = $service->complete($completed, $finance->id);

        $this->assertSame(SewaMobil::STATUS_SELESAI, $completedAgain->status);
        $this->assertSame(AsetKoperasi::STATUS_TERSEDIA, $completedAgain->aset->fresh()->status);
        $this->assertSame(1, DB::table('jurnal_umum')->where('idempotency_key', 'like', 'sewa-mobil:pengakuan-pendapatan:jurnal:%')->count());
        $this->assertDatabaseHas('jurnal_umum_detail', [
            'akun_kode' => '206',
            'debit' => '300000.00',
        ]);
        $this->assertDatabaseHas('jurnal_umum_detail', [
            'akun_kode' => '404',
            'kredit' => '300000.00',
        ]);
    }

    public function test_refund_paid_sebelum_berjalan_penuh_dan_berjalan_selesai_tidak_bisa_dibatalkan(): void
    {
        $service = app(SewaMobilService::class);
        $finance = $this->user('admin');
        $kas = $this->dompet(DompetKoperasi::JENIS_KAS, 1000000);
        $sewa = $this->approvedSewa($service, $finance);

        $paid = $service->pay($sewa, [
            'metode_pembayaran' => PembayaranSewaMobil::METODE_TUNAI,
            'dompet_id' => $kas->id,
            'jumlah_bayar' => 300000,
        ], $finance->id);
        $cancelled = $service->cancelByFinance($paid, 'Batal sebelum kegiatan', $finance->id);

        $this->assertSame(SewaMobil::STATUS_DIBATALKAN, $cancelled->status);
        $this->assertSame(SewaMobil::PEMBAYARAN_REFUNDED, $cancelled->status_pembayaran);
        $this->assertSame('1000000.00', $kas->fresh()->saldo);
        $this->assertSame(1, MutasiKas::query()->where('referensi_tipe', PembayaranSewaMobil::class)->where('tipe', 'keluar')->count());

        $running = $this->paidSewa($service, $finance);
        $running = $service->start($running, $finance->id);
        $this->expectValidation(fn () => $service->cancelByFinance($running, 'Tidak boleh', $finance->id));
    }

    public function test_authorization_routes_dan_preflight_sewa_mobil(): void
    {
        $finance = $this->user('admin');
        $kasir = $this->user('kasir');
        [, $employee] = $this->employeeUser('route@bita.test');

        $this->get(route('sewa-mobil.karyawan.index'))->assertRedirect(route('login'));
        $this->actingAs($kasir)->get(route('sewa-mobil.finance.index'))->assertForbidden();
        $this->actingAs($kasir)->get(route('sewa-mobil.karyawan.index'))->assertForbidden();
        $this->actingAs($employee)->get(route('sewa-mobil.karyawan.index'))->assertOk();
        $this->actingAs($employee)->get(route('sewa-mobil.finance.index'))->assertForbidden();
        $this->actingAs($finance)->get(route('sewa-mobil.finance.index'))->assertOk();

        $this->artisan('koperasi:preflight-sewa-mobil')->assertExitCode(0);

        DB::table('users')->insert([
            'name' => 'Broken Employee',
            'email' => 'broken-employee@bita.test',
            'password' => bcrypt('password'),
            'role' => 'karyawan',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->artisan('koperasi:preflight-sewa-mobil')->assertExitCode(1);
    }

    public function test_seeder_menghasilkan_contoh_sewa_mobil_valid(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertDatabaseHas('users', ['role' => 'karyawan', 'is_active' => true]);
        $this->assertDatabaseHas('sewa_mobil', ['status' => SewaMobil::STATUS_DRAFT]);
        $this->assertDatabaseHas('sewa_mobil', ['status' => SewaMobil::STATUS_DIAJUKAN]);
        $this->assertDatabaseHas('sewa_mobil', ['status' => SewaMobil::STATUS_DISETUJUI]);
        $this->assertDatabaseHas('sewa_mobil', ['status' => SewaMobil::STATUS_BERJALAN]);
        $this->assertDatabaseHas('sewa_mobil', ['status' => SewaMobil::STATUS_SELESAI]);
        $this->assertDatabaseHas('sewa_mobil', ['status' => SewaMobil::STATUS_DITOLAK]);
        $this->assertDatabaseHas('sewa_mobil', ['status' => SewaMobil::STATUS_DIBATALKAN, 'status_pembayaran' => SewaMobil::PEMBAYARAN_REFUNDED]);
        $this->assertSame(0, PemakaianPotongGaji::query()->whereIn('source_type', [SewaMobil::class, PembayaranSewaMobil::class])->count());
        $this->artisan('koperasi:preflight-sewa-mobil')->assertExitCode(0);
    }

    private function approvedSewa(SewaMobilService $service, User $finance): SewaMobil
    {
        $asset = $this->mobil();
        [, $employee] = $this->employeeUser(fake()->unique()->safeEmail());
        $pengurus = $this->pengurus();
        $sewa = $service->submit($service->createDraft($this->payload($asset), $employee), $employee);

        return $service->approve($sewa, [
            'tarif_total' => 300000,
            'pengurus_penyetuju_id' => $pengurus->id,
        ], $finance->id);
    }

    private function paidSewa(SewaMobilService $service, User $finance): SewaMobil
    {
        $sewa = $this->approvedSewa($service, $finance);
        $kas = $this->dompet(DompetKoperasi::JENIS_KAS, 1000000);

        return $service->pay($sewa, [
            'metode_pembayaran' => PembayaranSewaMobil::METODE_TUNAI,
            'dompet_id' => $kas->id,
            'jumlah_bayar' => 300000,
        ], $finance->id);
    }

    private function payload(AsetKoperasi $asset, array $overrides = []): array
    {
        return array_merge([
            'aset_koperasi_id' => $asset->id,
            'nama_kegiatan' => 'Kunjungan Proyek',
            'lokasi_kegiatan' => 'Karawang',
            'mulai_at' => '2026-08-01 08:00',
            'selesai_at' => '2026-08-01 17:00',
            'keterangan' => 'Unit test sewa mobil',
        ], $overrides);
    }

    private function mobil(): AsetKoperasi
    {
        return app(AsetKoperasiService::class)->createMobil([
            'plat_nomor' => 'B ' . fake()->unique()->numberBetween(1000, 9999) . ' KBS',
            'merek' => 'Toyota',
            'model' => 'Avanza',
            'tahun' => 2022,
            'warna' => 'Hitam',
            'keterangan' => 'Unit test mobil',
        ], $this->user('admin')->id);
    }

    private function employeeUser(string $email): array
    {
        $karyawan = Karyawan::factory()->create(['email' => $email]);
        $user = User::factory()->create([
            'name' => $karyawan->nama,
            'email' => 'user-' . $email,
            'role' => 'karyawan',
            'karyawan_id' => $karyawan->id,
            'is_active' => true,
            'must_change_password' => false,
        ]);

        return [$karyawan, $user];
    }

    private function pengurus(): PengurusKoperasi
    {
        $existing = PengurusKoperasi::query()->aktif()->with('anggota.karyawan')->first();
        if ($existing) {
            return $existing;
        }

        $karyawan = Karyawan::factory()->create();
        $anggota = Anggota::factory()->create(['karyawan_id' => $karyawan->id, 'status' => Anggota::STATUS_AKTIF]);

        return PengurusKoperasi::query()->create([
            'anggota_id' => $anggota->id,
            'jabatan' => 'Ketua Pengurus',
            'status' => PengurusKoperasi::STATUS_AKTIF,
        ]);
    }

    private function dompet(string $jenis, int $saldo = 0): DompetKoperasi
    {
        $this->seed(AkunSeeder::class);
        $accountKey = $jenis === DompetKoperasi::JENIS_BANK ? 'bank' : 'kas';
        $akun = Akun::query()->where('kode_akun', config("account_map.accounts.{$accountKey}.kode_akun"))->firstOrFail();

        return DompetKoperasi::query()->create([
            'akun_id' => $akun->id,
            'nama_dompet' => $jenis === DompetKoperasi::JENIS_BANK ? 'Bank Test' . fake()->unique()->numberBetween(1, 9999) : 'Kas Test' . fake()->unique()->numberBetween(1, 9999),
            'jenis_dompet' => $jenis,
            'saldo' => $saldo,
            'is_default_penerimaan_payroll' => false,
        ]);
    }

    private function user(string $role): User
    {
        return User::factory()->create([
            'role' => $role,
            'is_active' => true,
            'must_change_password' => false,
        ]);
    }

    private function expectValidation(callable $callback): void
    {
        try {
            $callback();
            $this->fail('ValidationException tidak dilempar.');
        } catch (ValidationException $exception) {
            $this->assertNotEmpty($exception->errors());
        }
    }
}
