<?php

namespace Tests\Feature;

use App\Models\JenisManfaatDanaSosial;
use App\Models\KlaimDanaSosial;
use App\Models\PembayaranShu;
use App\Models\PeriodeAkuntansi;
use App\Models\ShuConfig;
use App\Models\ShuKoperasi;
use App\Models\StrukturKoperasi;
use Database\Seeders\AnnualShuDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnnualShuDemoSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_is_complete_auditable_and_safe_to_rerun(): void
    {
        $this->seed();
        $this->seed(AnnualShuDemoSeeder::class);

        $period = PeriodeAkuntansi::query()->where('kode', 'TB-2025-2026')->sole();
        $this->assertSame(PeriodeAkuntansi::STATUS_CLOSED, $period->status);
        $this->assertSame('2025-07-01', $period->tanggal_mulai->toDateString());
        $this->assertSame('2026-06-30', $period->tanggal_selesai->toDateString());
        $this->assertSame('100000000.00', $period->total_pendapatan);
        $this->assertSame('60000000.00', $period->total_beban);
        $this->assertSame('40000000.00', $period->laba_bersih);
        $this->assertNotNull($period->checksum);

        $config = ShuConfig::query()->whereDate('berlaku_mulai', '2025-07-01')->sole();
        $this->assertSame('30.00', $config->persen_dana_cadangan);
        $this->assertSame('40.00', $config->persen_shu_anggota);
        $this->assertSame('10.00', $config->persen_pengurus);
        $this->assertSame('5.00', $config->persen_pengawas);
        $this->assertSame('5.00', $config->persen_pembina);
        $this->assertSame('10.00', $config->persen_dana_sosial);
        $this->assertSame('0.00', $config->persen_dana_pendidikan);

        $shu = ShuKoperasi::query()->where('periode_akuntansi_id', $period->id)->sole();
        $this->assertSame(ShuKoperasi::STATUS_APPROVED, $shu->status);
        $this->assertSame('4000000.00', $shu->nominal_dana_sosial);
        $this->assertDatabaseHas('dana_sosial_sumber', [
            'shu_koperasi_id' => $shu->id,
            'jumlah' => 4000000,
            'is_legacy' => false,
        ]);
        $this->assertSame(6, StrukturKoperasi::query()->count());
        $this->assertSame(2, PembayaranShu::query()->where('status', PembayaranShu::STATUS_PAID)->count());
        $this->assertGreaterThan(0, $shu->recipients()->whereDoesntHave('pembayaran')->count());
        $this->assertEqualsCanonicalizing(JenisManfaatDanaSosial::KODE, JenisManfaatDanaSosial::query()->pluck('kode')->all());
        $this->assertEqualsCanonicalizing([
            KlaimDanaSosial::STATUS_SUBMITTED,
            KlaimDanaSosial::STATUS_APPROVED,
            KlaimDanaSosial::STATUS_PAID,
            KlaimDanaSosial::STATUS_REJECTED,
            KlaimDanaSosial::STATUS_WAITING_FUNDS,
        ], KlaimDanaSosial::query()->pluck('status')->all());
        $this->artisan('koperasi:preflight-shu')->assertExitCode(0);
        $this->artisan('koperasi:preflight-dana-sosial')->assertExitCode(0);
    }
}
