<?php

namespace Tests\Feature;

use App\Models\DanaSosialSumber;
use App\Models\DompetKoperasi;
use App\Models\JenisManfaatDanaSosial;
use App\Models\KebijakanManfaatDanaSosial;
use App\Models\KlaimDanaSosial;
use App\Models\User;
use App\Services\SocialFundService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class DanaSosialFinalTest extends TestCase
{
    use RefreshDatabase;

    public function test_claim_is_idempotent_maker_checker_paid_fifo_and_reversible(): void
    {
        $this->seed();
        $maker = User::query()->where('email', 'keuangan@kbsm.test')->firstOrFail();
        $checker = User::query()->where('email', 'persetujuan.shu@kbsm.test')->firstOrFail();
        $benefit = JenisManfaatDanaSosial::query()->where('kode', 'MENINGGAL')->firstOrFail();
        $member = \App\Models\Anggota::query()->with('karyawan')->firstOrFail();
        $service = app(SocialFundService::class);

        $payload = [
            'anggota_id' => $member->id,
            'penerima_manfaat' => $member->karyawan->nama,
            'hubungan_penerima' => 'Diri sendiri',
            'jenis_manfaat_id' => $benefit->id,
            'tanggal_kejadian' => '2026-08-06',
            'nominal_diajukan' => 100000,
            'catatan' => 'Klaim integrasi final',
            'idempotency_key' => 'test:dana-sosial:klaim:final',
        ];
        $claim = $service->createClaim($payload, $maker->id);
        $this->assertSame($claim->id, $service->createClaim($payload, $maker->id)->id);
        $this->assertSame('500000.00', $claim->batas_nominal_snapshot);

        try {
            $service->approveClaim($claim, 100000, 'Dokumen lengkap.', $maker->id);
            $this->fail('Maker tidak boleh menyetujui klaim sendiri.');
        } catch (ValidationException) {
            $this->assertSame(KlaimDanaSosial::STATUS_SUBMITTED, $claim->fresh()->status);
        }

        $service->approveClaim($claim->fresh(), 100000, 'Dokumen lengkap dan terverifikasi.', $checker->id);
        $wallet = DompetKoperasi::query()->kas()->orderByDesc('saldo')->firstOrFail();
        $walletBefore = (int) $wallet->saldo;
        $sourceBefore = (int) DanaSosialSumber::query()->where('is_legacy', false)->sum('saldo_tersedia');
        $paid = $service->payClaim($claim->fresh(), [
            'dompet_id' => $wallet->id,
            'metode_pembayaran' => 'tunai',
            'tanggal_bayar' => '2026-08-06',
            'nomor_referensi' => 'TEST-KLAIM-FINAL',
        ], $checker->id);
        $this->assertSame(KlaimDanaSosial::STATUS_PAID, $paid->status);
        $this->assertSame(100000, (int) $paid->allocations()->sum('jumlah'));
        $this->assertSame($sourceBefore - 100000, (int) DanaSosialSumber::query()->where('is_legacy', false)->sum('saldo_tersedia'));
        $this->assertSame($walletBefore - 100000, (int) $wallet->fresh()->saldo);

        $corrected = $service->reversePayment($paid, '2026-08-07', 'Koreksi pembayaran untuk pengujian final.', $checker->id);
        $this->assertSame(KlaimDanaSosial::STATUS_CORRECTED, $corrected->status);
        $this->assertSame($sourceBefore, (int) DanaSosialSumber::query()->where('is_legacy', false)->sum('saldo_tersedia'));
        $this->assertSame($walletBefore, (int) $wallet->fresh()->saldo);
        $this->assertNotNull($corrected->reversal_journal_id);
        $this->artisan('koperasi:preflight-dana-sosial')->assertExitCode(0);
    }

    public function test_five_versioned_benefits_and_all_operational_statuses_are_available(): void
    {
        $this->seed();

        $this->assertEqualsCanonicalizing(JenisManfaatDanaSosial::KODE, JenisManfaatDanaSosial::query()->pluck('kode')->all());
        $this->assertSame(5, KebijakanManfaatDanaSosial::query()->count());
        $this->assertEqualsCanonicalizing([
            KlaimDanaSosial::STATUS_SUBMITTED,
            KlaimDanaSosial::STATUS_APPROVED,
            KlaimDanaSosial::STATUS_PAID,
            KlaimDanaSosial::STATUS_REJECTED,
            KlaimDanaSosial::STATUS_WAITING_FUNDS,
        ], KlaimDanaSosial::query()->pluck('status')->all());

        $policy = KebijakanManfaatDanaSosial::query()->firstOrFail();
        $this->expectException(\RuntimeException::class);
        $policy->update(['batas_maksimal' => 1]);
    }

    public function test_social_fund_route_requires_both_feature_flags_and_admin_role(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true, 'must_change_password' => false]);
        $cashier = User::factory()->create(['role' => 'kasir', 'is_active' => true, 'must_change_password' => false]);

        config(['features.shu_enabled' => true, 'features.dana_sosial_enabled' => false]);
        $this->get(route('dana-sosial.index'))->assertRedirect(route('login'));
        $this->actingAs($cashier)->get(route('dana-sosial.index'))->assertForbidden();
        $this->actingAs($admin)->get(route('dana-sosial.index'))->assertNotFound();

        config(['features.dana_sosial_enabled' => true, 'features.shu_enabled' => false]);
        $this->actingAs($admin)->get(route('dana-sosial.index'))->assertNotFound();

        config(['features.shu_enabled' => true]);
        $this->actingAs($admin)->get(route('dana-sosial.index'))->assertOk()->assertSee('Dana Sosial');
        $this->actingAs($admin)->get('/klaim-dana-khusus')->assertNotFound();
    }
}
