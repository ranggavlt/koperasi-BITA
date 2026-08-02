<?php

namespace Tests\Feature;

use App\Models\Anggota;
use App\Models\Karyawan;
use App\Models\Perusahaan;
use App\Models\User;
use App\Services\PayrollPolicyService;
use App\Services\PotongGajiBulananService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PayrollPolicyFinalTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_limit_umum_override_persisten_reset_dan_waserba_berlaku_periode_berikutnya(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-02 09:00:00', 'Asia/Jakarta'));
        $finance = User::factory()->create(['role' => 'admin']);
        $company = Perusahaan::query()->create(['kode' => 'BEE', 'nama' => 'Bita Enarcon Engineering']);
        $employee = Karyawan::factory()->create(['perusahaan_id' => $company->id]);
        $anggota = Anggota::factory()->create(['karyawan_id' => $employee->id, 'status' => Anggota::STATUS_AKTIF]);
        $service = app(PayrollPolicyService::class);
        $service->ensureGeneralPolicy($finance->id);

        $service->scheduleMemberSetting($anggota, 900000, false, 'Override khusus sampai di-reset.', $finance->id);

        $this->assertSame(1500000, $service->resolveFor($anggota, '2026-08')['nominal']);
        $this->assertTrue($service->resolveFor($anggota, '2026-08')['kredit_waserba_aktif']);
        $this->assertSame(900000, $service->resolveFor($anggota, '2026-09')['nominal']);
        $this->assertFalse($service->resolveFor($anggota, '2026-10')['kredit_waserba_aktif']);

        $limit = app(PotongGajiBulananService::class)->createLimitFromPolicy($anggota, '2026-09', $finance->id);
        $this->assertSame('900000.00', $limit->limit_nominal);
        $this->assertSame('override_anggota', $limit->sumber_limit_snapshot);
        $this->assertSame('BEE', $limit->kode_perusahaan_snapshot);
        $this->assertFalse($limit->kredit_waserba_aktif_snapshot);

        Carbon::setTestNow(Carbon::parse('2026-09-02 09:00:00', 'Asia/Jakarta'));
        $service->scheduleResetToGeneral($anggota, true, 'Kembali ke limit umum aktif.', $finance->id);

        $this->assertSame(900000, $service->resolveFor($anggota, '2026-09')['nominal']);
        $this->assertSame(1500000, $service->resolveFor($anggota, '2026-10')['nominal']);
        $this->assertSame('limit_umum', $service->resolveFor($anggota, '2026-11')['sumber']);
        $this->assertTrue($service->resolveFor($anggota, '2026-11')['kredit_waserba_aktif']);
    }
}
