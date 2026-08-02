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
use Illuminate\Support\Facades\Route;
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

        $account = $service->createAccount($karyawan, 'sementara123', 'karyawan', $finance->id);

        $this->assertSame('karyawan', $account->role);
        $this->assertTrue($account->must_change_password);
        $this->expectValidation(fn () => $service->createAccount($karyawan, 'sementara123', 'karyawan', $finance->id));

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

        $this->post('/register', [
            'name' => 'Public User',
            'email' => 'public-role@bita.test',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => 'karyawan',
        ])->assertNotFound();

        $this->assertDatabaseMissing('users', [
            'email' => 'public-role@bita.test',
        ]);
    }

    public function test_finance_mencatat_sewa_mobil_karyawan_aktif_dengan_kalkulasi_hari_server_side(): void
    {
        $service = app(SewaMobilService::class);
        $finance = $this->user('admin');
        $karyawan = Karyawan::factory()->create();
        $asset = $this->mobil(350000);

        $sameDay = $service->createDraft($this->payload($asset, $karyawan, [
            'tanggal_mulai' => '2026-08-10',
            'tanggal_selesai' => '2026-08-10',
            'jumlah_hari' => 999,
            'total_sewa' => 1,
        ]), $finance->id);

        $this->assertSame(1, $sameDay->jumlah_hari);
        $this->assertSame(350000, $sameDay->tarif_harian_snapshot);
        $this->assertSame(350000, $sameDay->total_sewa);

        $threeDays = $service->createDraft($this->payload($asset, $karyawan, [
            'tanggal_mulai' => '2026-08-12',
            'tanggal_selesai' => '2026-08-14',
        ]), $finance->id);

        $this->assertSame(3, $threeDays->jumlah_hari);
        $this->assertSame(1050000, $threeDays->total_sewa);

        $inactive = Karyawan::factory()->create([
            'status_kerja' => Karyawan::STATUS_BERHENTI,
            'tanggal_berhenti' => '2026-07-01',
        ]);
        $this->expectValidation(fn () => $service->createDraft($this->payload($asset, $inactive), $finance->id));
    }

    public function test_form_finance_hanya_menampilkan_karyawan_aktif_dan_self_service_karyawan_diblokir(): void
    {
        $finance = $this->user('admin');
        $kasir = $this->user('kasir');
        $employee = $this->employeeUser('employee-sewa@bita.test');
        $company = \App\Models\Perusahaan::query()->create(['kode' => 'BEE', 'nama' => 'Bita Enarcon Engineering']);
        $active = Karyawan::factory()->create(['nama' => 'Aktif Untuk Sewa', 'perusahaan_id' => $company->id]);
        $inactive = Karyawan::factory()->create([
            'nama' => 'Berhenti Tidak Muncul',
            'status_kerja' => Karyawan::STATUS_BERHENTI,
            'tanggal_berhenti' => '2026-07-01',
        ]);
        $this->mobil();

        $this->assertFalse(Route::has('sewa-mobil.karyawan.index'));

        $this->get(route('sewa-mobil.finance.index'))->assertRedirect(route('login'));
        $this->actingAs($kasir)->get(route('sewa-mobil.finance.index'))->assertForbidden();
        $this->actingAs($kasir)->get(route('sewa-mobil.finance.create'))->assertForbidden();
        $this->actingAs($employee)->get(route('sewa-mobil.finance.index'))->assertForbidden();
        $this->actingAs($employee)->get(route('sewa-mobil.finance.create'))->assertForbidden();
        $this->actingAs($employee)->get('/pengajuan-sewa-mobil')->assertNotFound();

        $this->actingAs($finance)
            ->get(route('sewa-mobil.finance.index'))
            ->assertOk()
            ->assertSee('Filter Sewa Mobil')
            ->assertSee('Daftar Sewa Mobil')
            ->assertSee('+ TAMBAH SEWA MOBIL')
            ->assertSee('href="' . route('sewa-mobil.finance.create') . '"', false)
            ->assertDontSee('name="nama_kegiatan"', false);

        $draft = app(SewaMobilService::class)->createDraft($this->payload($this->mobil(), $active), $finance->id);

        $this->actingAs($finance)
            ->get(route('sewa-mobil.finance.create'))
            ->assertOk()
            ->assertSee('Tambah Sewa Mobil')
            ->assertSee('Kembali ke Daftar Sewa Mobil')
            ->assertSee('data-sewa-mobil-form', false)
            ->assertSee('Aktif Untuk Sewa')
            ->assertDontSee('Berhenti Tidak Muncul');

        $this->actingAs($finance)
            ->get(route('sewa-mobil.finance.edit', $draft))
            ->assertOk()
            ->assertSee('Edit Draft Sewa Mobil')
            ->assertSee('data-sewa-mobil-form', false)
            ->assertSee('name="nama_kegiatan"', false);

        $this->assertDatabaseHas('karyawan', ['id' => $active->id]);
        $this->assertDatabaseHas('karyawan', ['id' => $inactive->id]);
    }

    public function test_filter_sewa_mobil_memakai_overlap_tanggal_mobil_karyawan_dan_pagination_query(): void
    {
        $finance = $this->user('admin');
        $service = app(SewaMobilService::class);
        $assetA = $this->mobil();
        $assetB = $this->mobil();
        $karyawanA = Karyawan::factory()->create(['nama' => 'Pemohon Mobil Filter A']);
        $karyawanB = Karyawan::factory()->create(['nama' => 'Pemohon Mobil Filter B']);

        $service->createDraft($this->payload($assetA, $karyawanA, [
            'nama_kegiatan' => 'Mobil Overlap Masuk',
            'tanggal_mulai' => '2026-08-01',
            'tanggal_selesai' => '2026-08-03',
        ]), $finance->id);
        $service->createDraft($this->payload($assetB, $karyawanB, [
            'nama_kegiatan' => 'Mobil Di Luar Range',
            'tanggal_mulai' => '2026-08-20',
            'tanggal_selesai' => '2026-08-22',
        ]), $finance->id);

        $this->actingAs($finance)
            ->get(route('sewa-mobil.finance.index', [
                'tanggal_dari' => '2026-08-02',
                'tanggal_sampai' => '2026-08-05',
            ]))
            ->assertOk()
            ->assertSee('Mobil Overlap Masuk')
            ->assertDontSee('Mobil Di Luar Range');

        $this->actingAs($finance)
            ->get(route('sewa-mobil.finance.index', [
                'aset_koperasi_id' => $assetB->id,
                'karyawan_id' => $karyawanB->id,
            ]))
            ->assertOk()
            ->assertSee('Mobil Di Luar Range')
            ->assertDontSee('Mobil Overlap Masuk');

        $this->actingAs($finance)
            ->get(route('sewa-mobil.finance.index', [
                'tanggal_dari' => '2026-08-10',
                'tanggal_sampai' => '2026-08-01',
            ]))
            ->assertSessionHasErrors('tanggal_sampai');

        for ($i = 1; $i <= 11; $i++) {
            $service->createDraft($this->payload($assetA, $karyawanA, [
                'nama_kegiatan' => 'Mobil Pagination ' . $i,
                'lokasi_kegiatan' => 'Lokasi ' . $i,
                'tanggal_mulai' => '2026-09-01',
                'tanggal_selesai' => '2026-09-02',
            ]), $finance->id);
        }

        $this->actingAs($finance)
            ->get(route('sewa-mobil.finance.index', [
                'karyawan_id' => $karyawanA->id,
                'tanggal_dari' => '2026-09-01',
                'tanggal_sampai' => '2026-09-30',
            ]))
            ->assertOk()
            ->assertSee('karyawan_id=' . $karyawanA->id, false)
            ->assertSee('tanggal_dari=2026-09-01', false)
            ->assertSee('tanggal_sampai=2026-09-30', false);
    }

    public function test_snapshot_tarif_tidak_berubah_dan_overlap_ditolak(): void
    {
        $service = app(SewaMobilService::class);
        $finance = $this->user('admin');
        $pengurus = $this->pengurus();
        $karyawan = Karyawan::factory()->create();
        $asset = $this->mobil(300000);

        $first = $service->submit($service->createDraft($this->payload($asset, $karyawan, [
            'tanggal_mulai' => '2026-08-10',
            'tanggal_selesai' => '2026-08-12',
        ]), $finance->id), $finance->id);
        $approved = $service->approve($first, ['pengurus_penyetuju_id' => $pengurus->id], $finance->id);

        $asset->mobil()->update(['tarif_sewa_harian' => 999000]);

        $this->assertSame(300000, $approved->fresh()->tarif_harian_snapshot);
        $this->assertSame(900000, $approved->fresh()->total_sewa);

        $overlap = $service->createDraft($this->payload($asset->fresh('mobil'), Karyawan::factory()->create(), [
            'tanggal_mulai' => '2026-08-11',
            'tanggal_selesai' => '2026-08-13',
        ]), $finance->id);

        $this->expectValidation(fn () => $service->submit($overlap, $finance->id));
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
            'jumlah_bayar' => $sewa->total_sewa,
        ], $finance->id));

        $this->expectValidation(fn () => $service->pay($sewa, [
            'metode_pembayaran' => PembayaranSewaMobil::METODE_TUNAI,
            'dompet_id' => $kas->id,
            'jumlah_bayar' => $sewa->total_sewa - 1,
        ], $finance->id));

        $paid = $service->pay($sewa, [
            'metode_pembayaran' => PembayaranSewaMobil::METODE_TUNAI,
            'dompet_id' => $kas->id,
            'jumlah_bayar' => $sewa->total_sewa,
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
            'jumlah_bayar' => $sewa->total_sewa,
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
            'jumlah_bayar' => $sewa->total_sewa,
        ], $finance->id);
        $cancelled = $service->cancelByFinance($paid, 'Batal sebelum kegiatan', $finance->id);

        $this->assertSame(SewaMobil::STATUS_DIBATALKAN, $cancelled->status);
        $this->assertSame(SewaMobil::PEMBAYARAN_REFUNDED, $cancelled->status_pembayaran);
        $this->assertSame('1000000.00', $kas->fresh()->saldo);
        $this->assertSame(1, MutasiKas::query()->where('referensi_tipe', PembayaranSewaMobil::class)->where('tipe', 'keluar')->count());

        $running = $this->paidSewa($service, $finance, $this->dompet(DompetKoperasi::JENIS_KAS, 1000000));
        $running = $service->start($running, $finance->id);
        $this->expectValidation(fn () => $service->cancelByFinance($running, 'Tidak boleh', $finance->id));
    }

    public function test_preflight_sewa_mobil_mendeteksi_kalkulasi_tidak_valid(): void
    {
        $this->artisan('koperasi:preflight-sewa-mobil')->assertExitCode(0);

        DB::table('sewa_mobil')->insert([
            'kode_sewa' => 'SWM-BROKEN-1',
            'aset_koperasi_id' => $this->mobil()->id,
            'karyawan_id' => Karyawan::factory()->create()->id,
            'nama_perusahaan_snapshot' => 'Bita Enarcon Engineering',
            'nama_kegiatan' => 'Broken',
            'lokasi_kegiatan' => 'Broken',
            'tanggal_mulai' => '2026-08-01',
            'tanggal_selesai' => '2026-08-03',
            'jumlah_hari' => 1,
            'tarif_harian_snapshot' => 300000,
            'total_sewa' => 300000,
            'status' => SewaMobil::STATUS_DISETUJUI,
            'status_pembayaran' => SewaMobil::PEMBAYARAN_BELUM_BAYAR,
            'idempotency_key' => 'broken-sewa-mobil',
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
        $this->assertDatabaseHas('sewa_mobil', [
            'status' => SewaMobil::STATUS_DISETUJUI,
            'model_sumber' => 'vendor',
            'aset_koperasi_id' => null,
        ]);
        $this->assertDatabaseHas('pembayaran_vendor_sewa', ['sewa_type' => SewaMobil::class, 'status' => 'paid']);
        $this->assertDatabaseHas('invoice_penagihan_detail', ['referensi_type' => SewaMobil::class]);
        $this->assertSame(0, PemakaianPotongGaji::query()->whereIn('source_type', [SewaMobil::class, PembayaranSewaMobil::class])->count());
        $this->artisan('koperasi:preflight-sewa-mobil')->assertExitCode(0);
    }

    private function approvedSewa(SewaMobilService $service, User $finance): SewaMobil
    {
        $asset = $this->mobil(300000);
        $karyawan = Karyawan::factory()->create();
        $pengurus = $this->pengurus();
        $sewa = $service->submit($service->createDraft($this->payload($asset, $karyawan), $finance->id), $finance->id);

        return $service->approve($sewa, [
            'pengurus_penyetuju_id' => $pengurus->id,
        ], $finance->id);
    }

    private function paidSewa(SewaMobilService $service, User $finance, DompetKoperasi $kas): SewaMobil
    {
        $sewa = $this->approvedSewa($service, $finance);

        return $service->pay($sewa, [
            'metode_pembayaran' => PembayaranSewaMobil::METODE_TUNAI,
            'dompet_id' => $kas->id,
            'jumlah_bayar' => $sewa->total_sewa,
        ], $finance->id);
    }

    private function payload(AsetKoperasi $asset, Karyawan $karyawan, array $overrides = []): array
    {
        return array_merge([
            'karyawan_id' => $karyawan->id,
            'aset_koperasi_id' => $asset->id,
            'nama_kegiatan' => 'Kunjungan Proyek',
            'lokasi_kegiatan' => 'Karawang',
            'tanggal_mulai' => '2026-08-01',
            'tanggal_selesai' => '2026-08-01',
            'keterangan' => 'Unit test sewa mobil',
        ], $overrides);
    }

    private function mobil(int $tarif = 300000): AsetKoperasi
    {
        return app(AsetKoperasiService::class)->createMobil([
            'plat_nomor' => 'B ' . fake()->unique()->numberBetween(1000, 9999) . ' KBS',
            'merek' => 'Toyota',
            'model' => 'Avanza',
            'tahun' => 2022,
            'warna' => 'Hitam',
            'tarif_sewa_harian' => $tarif,
            'keterangan' => 'Unit test mobil',
        ], $this->user('admin')->id);
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
