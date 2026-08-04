<?php

namespace Tests\Feature;

use App\Http\Requests\StorePinjamanRequest;
use App\Http\Requests\UpdatePinjamanRequest;
use App\Models\Akun;
use App\Models\Anggota;
use App\Models\CicilanPinjaman;
use App\Models\DompetKoperasi;
use App\Models\JadwalCicilanPinjaman;
use App\Models\JenisSimpanan;
use App\Models\Karyawan;
use App\Models\LimitPotongGajiAnggota;
use App\Models\MutasiKas;
use App\Models\PemakaianPotongGaji;
use App\Models\PeriodePotongGaji;
use App\Models\Pinjaman;
use App\Models\SiklusKeanggotaan;
use App\Models\Simpanan;
use App\Models\User;
use App\Services\MasterDataKoperasiService;
use App\Services\PinjamanKoperasiService;
use App\Services\PotongGajiBulananService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class PinjamanReportSp6BTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_halaman_cicilan_read_only_filter_summary_nominal_sisa_dan_authorization(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-20 10:00:00', 'Asia/Jakarta'));
        $finance = $this->finance();
        $pinjaman = $this->pinjaman($this->anggota(), 500000, 1, '2026-06-10');
        $jadwal = $pinjaman->jadwalCicilan()->firstOrFail();
        $jadwal->update([
            'nominal_offset' => '200000.00',
            'nominal_sisa' => '300000.00',
        ]);
        $pinjaman->update(['sisa_pinjaman' => '300000.00']);

        $before = $this->databaseSnapshot();

        $this->actingAs($finance)
            ->get(route('cicilan-pinjaman.index', [
                'status' => 'tertunggak',
                'anggota_id' => $pinjaman->anggota_id,
                'pinjaman_id' => $pinjaman->id,
                'periode_mulai' => '2026-07',
                'periode_selesai' => '2026-07',
                'page' => 1,
            ]))
            ->assertOk()
            ->assertSee('Filter Cicilan Pinjaman')
            ->assertSee('Tertunggak')
            ->assertSee('Rp 300.000')
            ->assertSee('Detail Pinjaman');

        $this->assertSame($before, $this->databaseSnapshot());

        $this->actingAs(User::factory()->create(['role' => 'kasir']))
            ->get(route('cicilan-pinjaman.index'))
            ->assertForbidden();

        auth()->guard()->logout();
        $this->get(route('cicilan-pinjaman.index'))->assertRedirect(route('login'));
    }

    public function test_detail_pinjaman_menampilkan_offset_pembayaran_sisa_dan_badge_siklus_lama(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-20 10:00:00', 'Asia/Jakarta'));
        $finance = $this->finance();
        $anggota = $this->anggota();
        $oldCycle = $anggota->siklusAktif()->firstOrFail();
        $pinjaman = $this->pinjaman($anggota, 400000, 2, '2026-06-10');
        $jadwal = $pinjaman->jadwalCicilan()->firstOrFail();
        $jadwal->update([
            'nominal_offset' => '50000.00',
            'nominal_sisa' => '150000.00',
        ]);
        $pinjaman->update(['sisa_pinjaman' => '350000.00']);
        $oldCycle->update(['status' => SiklusKeanggotaan::STATUS_CLOSED, 'tanggal_selesai' => '2026-07-01']);
        SiklusKeanggotaan::query()->create([
            'anggota_id' => $anggota->id,
            'siklus_ke' => 2,
            'tanggal_mulai' => '2026-07-02',
            'status' => SiklusKeanggotaan::STATUS_ACTIVE,
            'created_by' => $finance->id,
        ]);

        $this->actingAs($finance)
            ->get(route('pinjaman.show', $pinjaman))
            ->assertOk()
            ->assertSee('Kewajiban Siklus Lama')
            ->assertSee('Rp 50.000')
            ->assertSee('Rp 350.000')
            ->assertSee('Jadwal Cicilan')
            ->assertSee('Mutasi dan Jurnal Terkait');
    }

    public function test_laporan_payroll_menampilkan_warning_due_tanpa_ledger_dan_siklus_lama_tidak_masuk(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-20 10:00:00', 'Asia/Jakarta'));
        $finance = $this->finance();
        $anggota = $this->anggota();
        $pinjaman = $this->pinjaman($anggota, 300000, 1, '2026-06-10');
        $periode = PeriodePotongGaji::query()->create([
            'periode' => '2026-07-01',
            'status' => 'active',
            'created_by' => $finance->id,
        ]);
        LimitPotongGajiAnggota::query()->create([
            'periode_potong_gaji_id' => $periode->id,
            'anggota_id' => $anggota->id,
            'limit_nominal' => '500000.00',
            'status' => LimitPotongGajiAnggota::STATUS_ACTIVE,
            'activated_by' => $finance->id,
            'activated_at' => now(),
        ]);

        $this->actingAs($finance)
            ->get(route('laporan.potong-gaji', ['periode' => '2026-07']))
            ->assertOk()
            ->assertSee('Cicilan jatuh tempo')
            ->assertSee('Rp 300.000');

        $oldCycle = $anggota->siklusAktif()->firstOrFail();
        $oldCycle->update(['status' => SiklusKeanggotaan::STATUS_CLOSED, 'tanggal_selesai' => '2026-07-01']);
        SiklusKeanggotaan::query()->create([
            'anggota_id' => $anggota->id,
            'siklus_ke' => 2,
            'tanggal_mulai' => '2026-07-02',
            'status' => SiklusKeanggotaan::STATUS_ACTIVE,
            'created_by' => $finance->id,
        ]);

        $this->actingAs($finance)
            ->get(route('laporan.potong-gaji', ['periode' => '2026-07']))
            ->assertOk()
            ->assertDontSee('Rp 300.000</td>', false);
    }

    public function test_rekonsiliasi_balanced_dan_mismatch_terdeteksi_tanpa_memperbaiki_data(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-20 10:00:00', 'Asia/Jakarta'));
        $this->deferSimpananWajib();
        $finance = $this->finance();
        $bank = $this->bankDefaultPayroll(0, '902');
        config()->set('account_map.accounts.bank.kode_akun', '902');
        $pinjaman = $this->pinjaman($this->anggota(), 300000, 1, '2026-06-10');
        $payroll = app(PotongGajiBulananService::class);
        $limit = $payroll->activateLimit(
            $payroll->createLimit($pinjaman->anggota, '2026-07', 300000, $finance->id, 'Limit rekonsiliasi SP-6B'),
            $finance->id
        );
        $payroll->confirmLimit($payroll->closeLimit($limit, $finance->id), $finance->id);

        $before = $this->databaseSnapshot();

        $this->actingAs($finance)
            ->get(route('rekonsiliasi-potong-gaji.index', ['periode' => '2026-07']))
            ->assertOk()
            ->assertSee('Sesuai')
            ->assertSee('Rp 300.000');

        $this->assertSame($before, $this->databaseSnapshot());

        $payment = CicilanPinjaman::query()->firstOrFail();
        MutasiKas::query()->create([
            'idempotency_key' => 'test:duplicate-mutasi-sp6b',
            'dompet_id' => $bank->id,
            'tipe' => 'masuk',
            'jumlah' => '1000.00',
            'keterangan' => 'Mismatch test SP-6B',
            'referensi_tipe' => CicilanPinjaman::class,
            'referensi_id' => $payment->id,
            'tanggal' => '2026-07-20',
        ]);

        $this->actingAs($finance)
            ->get(route('rekonsiliasi-potong-gaji.index', ['periode' => '2026-07']))
            ->assertOk()
            ->assertSee('Perlu Diperiksa')
            ->assertSee('Uang yang seharusnya diterima dibandingkan dengan Bank');
    }

    public function test_outstanding_cash_memuat_pinjaman_mantan_karyawan_dan_tidak_memuat_anggota_aktif(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-20 10:00:00', 'Asia/Jakarta'));
        $finance = $this->finance();
        $mantan = $this->anggota();
        $pinjamanMantan = $this->pinjaman($mantan, 300000, 1, '2026-06-10');
        $this->stopKaryawan($mantan->karyawan);
        $aktif = $this->anggota();
        $pinjamanAktif = $this->pinjaman($aktif, 400000, 1, '2026-06-10');

        $this->actingAs($finance)
            ->get(route('outstanding-cash.index'))
            ->assertOk()
            ->assertSee('Cicilan Pinjaman')
            ->assertSee($pinjamanMantan->kode_pinjaman)
            ->assertDontSee($pinjamanAktif->kode_pinjaman)
            ->assertSee('Detail Pinjaman');
    }

    public function test_route_report_get_only_psr4_casing_baru_dan_preflight_bersih_setelah_seed(): void
    {
        $this->assertTrue(class_exists(StorePinjamanRequest::class));
        $this->assertTrue(class_exists(UpdatePinjamanRequest::class));
        $requestFiles = collect(scandir(app_path('Http/Requests')))->values()->all();
        $this->assertContains('StorePinjamanRequest.php', $requestFiles);
        $this->assertContains('UpdatePinjamanRequest.php', $requestFiles);
        $this->assertNotContains('Store' . 'pinjamanRequest.php', $requestFiles);
        $this->assertNotContains('Update' . 'pinjamanRequest.php', $requestFiles);

        foreach (['laporan.potong-gaji', 'rekonsiliasi-potong-gaji.index', 'outstanding-cash.index', 'cicilan-pinjaman.index'] as $name) {
            $route = Route::getRoutes()->getByName($name);
            $this->assertNotNull($route);
            $this->assertSame(['GET', 'HEAD'], $route->methods());
        }

        $this->seed(\Database\Seeders\DatabaseSeeder::class);
        $this->artisan('koperasi:preflight-potong-gaji')->assertExitCode(0);
    }

    private function finance(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    private function anggota(int $plafon = 5000000): Anggota
    {
        $karyawan = Karyawan::factory()->create();

        $anggota = app(MasterDataKoperasiService::class)->createAnggota([
            'karyawan_id' => $karyawan->id,
            'tanggal_bergabung' => '2026-01-01',
            'alamat' => 'Jl. SP-6B',
            'plafon_pinjaman' => $plafon,
        ]);

        Simpanan::query()
            ->where('anggota_id', $anggota->id)
            ->where('kode_jenis_snapshot', JenisSimpanan::KODE_SIMPANAN_WAJIB)
            ->update(['status' => Simpanan::STATUS_SETTLED, 'settled_at' => now()]);

        return $anggota->fresh(['karyawan', 'siklusAktif']);
    }

    private function pinjaman(Anggota $anggota, int $jumlah, int $tenor, string $tanggal): Pinjaman
    {
        return app(PinjamanKoperasiService::class)->create([
            'anggota_id' => $anggota->id,
            'jumlah_pinjaman' => $jumlah,
            'tenor_bulan' => $tenor,
            'tanggal_pengajuan' => $tanggal,
            'tanggal_pinjaman' => $tanggal,
            'dompet_id' => $this->kasDompet(10000000)->id,
            'keterangan' => 'Pinjaman fixture SP-6B',
        ], $this->finance()->id);
    }

    private function kasDompet(int $saldo = 0): DompetKoperasi
    {
        return DompetKoperasi::query()->create([
            'akun_id' => Akun::query()->where('kode_akun', '101')->value('id'),
            'nama_dompet' => 'Kas SP6B ' . uniqid(),
            'jenis_dompet' => DompetKoperasi::JENIS_KAS,
            'saldo' => $saldo,
        ]);
    }

    private function bankDefaultPayroll(int $saldo = 0, string $kodeAkun = '102'): DompetKoperasi
    {
        $akun = Akun::query()->firstOrCreate(
            ['kode_akun' => $kodeAkun],
            [
                'nama_akun' => 'Bank',
                'kategori' => 'aset',
                'posisi_saldo' => 'debit',
                'is_aktif' => true,
            ]
        );

        return DompetKoperasi::query()->create([
            'akun_id' => $akun->id,
            'nama_dompet' => 'Bank Payroll SP6B ' . uniqid(),
            'jenis_dompet' => DompetKoperasi::JENIS_BANK,
            'is_default_penerimaan_payroll' => true,
            'saldo' => $saldo,
        ]);
    }

    private function deferSimpananWajib(): void
    {
        JenisSimpanan::query()
            ->where('kode', JenisSimpanan::KODE_SIMPANAN_WAJIB)
            ->update([
                'aktif' => true,
                'berlaku_mulai' => '2027-01-01',
            ]);
    }

    private function stopKaryawan(Karyawan $karyawan): void
    {
        app(MasterDataKoperasiService::class)->updateKaryawan($karyawan, [
            'nama' => $karyawan->nama,
            'email' => $karyawan->email,
            'telepon' => $karyawan->telepon,
            'jabatan' => $karyawan->jabatan,
            'status_kerja' => Karyawan::STATUS_BERHENTI,
            'tanggal_berhenti' => '2026-07-01',
        ]);
    }

    /**
     * @return array<string,int|string>
     */
    private function databaseSnapshot(): array
    {
        return [
            'mutasi' => MutasiKas::query()->count(),
            'ledger' => PemakaianPotongGaji::query()->count(),
            'cicilan' => CicilanPinjaman::query()->count(),
            'pinjaman_sisa' => (string) Pinjaman::query()->sum('sisa_pinjaman'),
            'dompet_saldo' => (string) DompetKoperasi::query()->sum('saldo'),
        ];
    }
}
