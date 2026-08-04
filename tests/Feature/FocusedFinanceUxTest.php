<?php

namespace Tests\Feature;

use App\Models\Anggota;
use App\Models\Karyawan;
use App\Models\LimitPotongGajiAnggota;
use App\Models\PemakaianPotongGaji;
use App\Models\PeriodePotongGaji;
use App\Models\Perusahaan;
use App\Models\User;
use App\Services\PotongGajiReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class FocusedFinanceUxTest extends TestCase
{
    use RefreshDatabase;

    public function test_mutasi_dan_pinjaman_memakai_layout_dan_aksi_kontekstual(): void
    {
        $mutasiView = File::get(resource_path('views/pages/mutasi-kas/index.blade.php'));
        $pinjamanIndex = File::get(resource_path('views/pages/pinjaman/index.blade.php'));
        $pinjamanDetail = File::get(resource_path('views/pages/pinjaman/show.blade.php'));
        $theme = File::get(public_path('assets/css/kbsm-theme.css'));

        $this->assertStringContainsString('mkb-filter-form--three-columns', $mutasiView);
        $this->assertMatchesRegularExpression(
            '/\.mkb-filter-form--three-columns\s*\{[^}]*repeat\(3,\s*minmax\(0,\s*1fr\)\)/s',
            $theme
        );
        $this->assertStringContainsString('STATUS_DRAFT', $pinjamanIndex);
        $this->assertStringContainsString('>Edit</a>', $pinjamanIndex);
        $this->assertStringContainsString('>Ajukan</button>', $pinjamanIndex);
        $this->assertStringContainsString('>Setujui</button>', $pinjamanIndex);
        $this->assertStringContainsString('>Tolak</a>', $pinjamanIndex);
        $this->assertStringContainsString('>Cairkan</a>', $pinjamanIndex);
        $this->assertStringContainsString('>Batalkan</a>', $pinjamanIndex);
        $this->assertStringNotContainsString('Tolak/Batal', $pinjamanIndex.$pinjamanDetail);
        $this->assertStringContainsString("status === \\App\\Models\\Pinjaman::STATUS_DISETUJUI", $pinjamanDetail);
        $this->assertStringContainsString('Alasan Penolakan', $pinjamanDetail);
        $this->assertStringContainsString('Alasan Pembatalan', $pinjamanDetail);
    }

    public function test_filter_periode_memakai_dropdown_anggota_per_perusahaan_dan_menolak_pasangan_tidak_valid(): void
    {
        $finance = $this->finance();
        $bee = Perusahaan::query()->firstOrCreate(['kode' => 'BEE'], ['nama' => 'Bita Enarcon Engineering']);
        $bbs = Perusahaan::query()->create(['kode' => 'BBS', 'nama' => 'Bita Beton Sejahtera']);
        $memberBee = $this->member($bee, 'Anggota BEE UX');
        $memberBbs = $this->member($bbs, 'Anggota BBS UX');
        $periode = PeriodePotongGaji::factory()->create(['periode' => '2026-08-01']);

        $response = $this->actingAs($finance)->get(route('periode-potong-gaji.index', [
            'periode_id' => $periode->id,
            'perusahaan_id' => $bee->id,
            'anggota_id' => $memberBee->id,
        ]));

        $response->assertOk()
            ->assertSee('data-company-select', false)
            ->assertSee('data-member-select', false)
            ->assertSee('data-member-search', false)
            ->assertSee('data-company-id="'.$bee->id.'"', false)
            ->assertDontSee('name="search"', false)
            ->assertSee('Siapkan Limit Bulanan')
            ->assertSee('Aktifkan Semua Limit');

        $this->actingAs($finance)
            ->from(route('periode-potong-gaji.index', ['periode_id' => $periode->id]))
            ->get(route('periode-potong-gaji.index', [
                'periode_id' => $periode->id,
                'perusahaan_id' => $bee->id,
                'anggota_id' => $memberBbs->id,
            ]))
            ->assertRedirect()
            ->assertSessionHasErrors('anggota_id');

        $this->actingAs($finance)
            ->from(route('laporan.potong-gaji'))
            ->get(route('laporan.potong-gaji', [
                'periode' => '2026-08',
                'perusahaan_id' => $bee->id,
                'anggota_id' => $memberBbs->id,
            ]))
            ->assertRedirect()
            ->assertSessionHasErrors('anggota_id');
    }

    public function test_laporan_default_menyembunyikan_nol_toggle_dan_mode_individual_tersedia(): void
    {
        $finance = $this->finance();
        $company = Perusahaan::query()->firstOrCreate(['kode' => 'BEE'], ['nama' => 'Bita Enarcon Engineering']);
        $withDeduction = $this->member($company, 'Anggota Dengan Potongan UX');
        $withoutDeduction = $this->member($company, 'Anggota Tanpa Potongan UX');
        $periode = PeriodePotongGaji::factory()->create(['periode' => '2026-08-01']);
        $usedLimit = $this->limit($periode, $withDeduction);
        $this->limit($periode, $withoutDeduction);

        PemakaianPotongGaji::factory()->create([
            'limit_potong_gaji_anggota_id' => $usedLimit->id,
            'kategori' => PemakaianPotongGaji::KATEGORI_POS,
            'nominal' => 50000,
            'status' => PemakaianPotongGaji::STATUS_CONSUMED,
        ]);

        $defaultResponse = $this->actingAs($finance)->get(route('laporan.potong-gaji', [
            'periode' => '2026-08',
            'perusahaan_id' => $company->id,
        ]));
        $defaultContent = $defaultResponse->assertOk()
            ->assertSee('Tampilkan anggota tanpa potongan')
            ->assertSee('Semua Anggota yang Memiliki Potongan')
            ->getContent();

        $this->assertSame(2, substr_count($defaultContent, 'Anggota Dengan Potongan UX'));
        $this->assertSame(1, substr_count($defaultContent, 'Anggota Tanpa Potongan UX'));

        $withZeroContent = $this->actingAs($finance)->get(route('laporan.potong-gaji', [
            'periode' => '2026-08',
            'perusahaan_id' => $company->id,
            'tampilkan_tanpa_potongan' => '1',
        ]))->assertOk()->getContent();
        $this->assertSame(2, substr_count($withZeroContent, 'Anggota Tanpa Potongan UX'));

        $this->actingAs($finance)->get(route('laporan.potong-gaji', [
            'periode' => '2026-08',
            'perusahaan_id' => $company->id,
            'anggota_id' => $withDeduction->id,
        ]))->assertOk()
            ->assertSee('Rincian Potongan Anggota Dengan Potongan UX')
            ->assertSee('Lihat Detail Proses Potong Gaji')
            ->assertSee('Rp 50.000');
    }

    public function test_pengecekan_sesuai_ringkas_dan_formula_laporan_tetap(): void
    {
        $finance = $this->finance();
        $company = Perusahaan::query()->firstOrCreate(['kode' => 'BEE'], ['nama' => 'Bita Enarcon Engineering']);
        $member = $this->member($company, 'Anggota Formula UX');
        $periode = PeriodePotongGaji::factory()->create(['periode' => '2026-08-01']);
        $limit = $this->limit($periode, $member);

        PemakaianPotongGaji::factory()->create([
            'limit_potong_gaji_anggota_id' => $limit->id,
            'kategori' => PemakaianPotongGaji::KATEGORI_POS,
            'nominal' => 50000,
            'status' => PemakaianPotongGaji::STATUS_CONSUMED,
        ]);
        PemakaianPotongGaji::factory()->create([
            'limit_potong_gaji_anggota_id' => $limit->id,
            'kategori' => PemakaianPotongGaji::KATEGORI_SIMPANAN_MANASUKA,
            'nominal' => 20000,
            'status' => PemakaianPotongGaji::STATUS_CONSUMED,
        ]);

        $row = app(PotongGajiReportService::class)->payroll('2026-08', ['anggota_id' => $member->id])['rows']->firstOrFail();
        $this->assertSame(50000.0, $row->gross_payroll);
        $this->assertSame(20000.0, $row->simpanan_manasuka);

        $this->actingAs($finance)->get(route('rekonsiliasi-potong-gaji.index', [
            'periode' => '2026-07',
            'perusahaan_id' => $company->id,
        ]))->assertOk()
            ->assertSee('Sesuai — seluruh potongan sudah diterima dan dicatat.')
            ->assertSee('Seluruh data potong gaji periode ini sudah cocok')
            ->assertDontSee('<table class="kbsm-business-detail-table">', false)
            ->assertSee('Lihat Detail Pencatatan Akuntansi');
    }

    private function finance(): User
    {
        return User::factory()->create([
            'role' => 'admin',
            'is_active' => true,
            'must_change_password' => false,
        ]);
    }

    private function member(Perusahaan $company, string $name): Anggota
    {
        $employee = Karyawan::factory()->create([
            'nama' => $name,
            'perusahaan_id' => $company->id,
            'status_kerja' => Karyawan::STATUS_AKTIF,
        ]);

        return Anggota::factory()->create([
            'karyawan_id' => $employee->id,
            'status' => Anggota::STATUS_AKTIF,
        ]);
    }

    private function limit(PeriodePotongGaji $periode, Anggota $member): LimitPotongGajiAnggota
    {
        return LimitPotongGajiAnggota::factory()->create([
            'periode_potong_gaji_id' => $periode->id,
            'anggota_id' => $member->id,
            'limit_nominal' => 1500000,
            'sumber_limit' => LimitPotongGajiAnggota::SUMBER_LIMIT_UMUM,
            'status' => LimitPotongGajiAnggota::STATUS_ACTIVE,
        ]);
    }
}
