<?php

namespace Tests\Feature;

use App\Models\Akun;
use App\Models\BatasKlaimDanaSosial;
use App\Models\DanaSosialSumber;
use App\Models\DompetKoperasi;
use App\Models\Karyawan;
use App\Models\KlaimDanaSosial;
use App\Models\User;
use App\Services\DanaSosialService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class DanaSosialFinalTest extends TestCase
{
    use RefreshDatabase;

    public function test_donation_claim_limits_maker_checker_payout_and_reversal_are_complete(): void
    {
        $maker = User::factory()->create(['role' => 'admin']);
        $checker = User::factory()->create(['role' => 'admin']);
        $employee = Karyawan::factory()->create(['nama' => 'Penerima Dana Sosial']);
        $wallet = DompetKoperasi::query()->create(['nama_dompet' => 'Kas Dana Sosial', 'jenis_dompet' => 'kas', 'akun_id' => Akun::query()->where('kode_akun', '101')->value('id'), 'saldo' => 1000000]);
        $service = app(DanaSosialService::class);
        $service->createLimit(['kategori' => 'melahirkan', 'nominal_maksimal' => 300000, 'berlaku_mulai' => '2026-01-01', 'alasan' => 'Batas kelahiran hasil keputusan'], $maker->id);

        $source = $service->createSource(['nama_sumber' => 'Donasi Mitra Resmi', 'jenis_sumber' => DanaSosialSumber::JENIS_DONASI, 'dompet_id' => $wallet->id, 'metode_penerimaan' => 'tunai', 'tanggal_diterima' => '2026-08-02', 'nomor_referensi' => 'DON-001', 'bukti_penerimaan' => 'bukti/DON-001.pdf', 'nominal_awal' => 800000, 'keterangan' => 'Donasi diterima resmi'], $maker->id);
        $this->assertSame(0, (int) $source->saldo_tersedia);
        $this->assertSame(1000000, (int) $wallet->fresh()->saldo);
        $this->assertDatabaseCount('jurnal_umum', 0);

        try {
            $service->approveSource($source, $maker->id, 'Menyetujui sendiri');
            $this->fail('Self approval donasi harus ditolak.');
        } catch (ValidationException) {
            $this->assertSame(1000000, (int) $wallet->fresh()->saldo);
        }

        $source = $service->approveSource($source, $checker->id, 'Donasi dan bukti sudah diverifikasi');
        $this->assertSame(1800000, (int) $wallet->fresh()->saldo);
        $this->assertSame($maker->id, $source->created_by);
        $this->assertSame($checker->id, $source->approved_by);
        $donationJournal = DB::table('jurnal_umum')->where('idempotency_key', 'dana-donation:journal:'.$source->id)->first();
        $this->assertNotNull($donationJournal);
        $this->assertDatabaseHas('jurnal_umum_detail', ['jurnal_umum_id' => $donationJournal->id, 'akun_kode' => '101', 'debit' => 800000]);
        $this->assertDatabaseHas('jurnal_umum_detail', ['jurnal_umum_id' => $donationJournal->id, 'akun_kode' => '210', 'kredit' => 800000]);
        $this->assertDatabaseMissing('jurnal_umum_detail', ['jurnal_umum_id' => $donationJournal->id, 'akun_kode' => '209']);

        try {
            $service->createClaim(['karyawan_id' => $employee->id, 'kategori' => 'melahirkan', 'nominal' => 300001, 'tanggal_pengajuan' => '2026-08-02', 'keterangan' => 'Melebihi batas'], $maker->id);
            $this->fail('Klaim di atas batas harus ditolak.');
        } catch (ValidationException) {
            $this->assertDatabaseCount('klaim_dana_sosial', 0);
        }

        $claim = $service->createClaim(['karyawan_id' => $employee->id, 'kategori' => 'melahirkan', 'nominal' => 250000, 'tanggal_pengajuan' => '2026-08-02', 'keterangan' => 'Bantuan kelahiran'], $maker->id);
        $this->assertSame('300000.00', $claim->batas_nominal_snapshot);
        $service->submit($claim);
        try {
            $service->approve($claim->fresh(), $source->id, $maker->id, 'Approval sendiri');
            $this->fail('Self approval klaim harus ditolak.');
        } catch (ValidationException) {
            $this->assertSame(KlaimDanaSosial::STATUS_DIAJUKAN, $claim->fresh()->status);
        }
        $service->approve($claim->fresh(), $source->id, $checker->id, 'Klaim dan dokumen sudah diverifikasi');
        $service->pay($claim->fresh(), ['dompet_id' => $wallet->id, 'metode_pembayaran' => 'tunai', 'tanggal_bayar' => '2026-08-03'], $checker->id);

        $this->assertSame(550000, (int) $source->fresh()->saldo_tersedia);
        $this->assertSame(1550000, (int) $wallet->fresh()->saldo);
        $this->assertDatabaseHas('mutasi_kas', ['idempotency_key' => 'dana-claim:cash:'.$claim->id, 'tipe' => 'keluar']);
        $service->reversePayment($claim->fresh(), 'Koreksi bukti pembayaran ganda.', $checker->id);
        $service->reversePayment($claim->fresh(), 'Retry idempotent.', $checker->id);
        $this->assertSame(800000, (int) $source->fresh()->saldo_tersedia);
        $this->assertSame(1800000, (int) $wallet->fresh()->saldo);
        $this->assertSame(1, DB::table('jurnal_umum')->where('idempotency_key', 'dana-claim:reversal:journal:'.$claim->id)->count());

        $service->reverseSource($source->fresh(), 'Donasi dibatalkan oleh pemberi.', $checker->id);
        $this->assertSame(DanaSosialSumber::STATUS_REVERSED, $source->fresh()->status);
        $this->assertSame(1000000, (int) $wallet->fresh()->saldo);
        $this->artisan('koperasi:preflight-dana-sosial')->assertExitCode(0);
    }

    public function test_limit_versions_are_immutable_and_effective_by_claim_date(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $service = app(DanaSosialService::class);
        $old = $service->createLimit(['kategori' => 'khitan', 'nominal_maksimal' => 1000000, 'berlaku_mulai' => '2026-01-01', 'alasan' => 'Batas awal khitan'], $admin->id);
        $service->createLimit(['kategori' => 'khitan', 'nominal_maksimal' => 1500000, 'berlaku_mulai' => '2026-07-01', 'alasan' => 'Penyesuaian batas khitan'], $admin->id);
        $employee = Karyawan::factory()->create();
        $claim = $service->createClaim(['karyawan_id' => $employee->id, 'kategori' => 'khitan', 'nominal' => 1200000, 'tanggal_pengajuan' => '2026-08-01', 'keterangan' => 'Klaim setelah batas baru'], $admin->id);
        $this->assertSame('1500000.00', $claim->batas_nominal_snapshot);
        $this->expectException(\RuntimeException::class);
        $old->update(['nominal_maksimal' => 1]);
    }

    public function test_route_dana_sosial_default_404_and_admin_only_when_enabled(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true, 'must_change_password' => false]);
        $kasir = User::factory()->create(['role' => 'kasir', 'is_active' => true, 'must_change_password' => false]);
        config()->set('features.dana_sosial_enabled', false);
        $this->get(route('klaim-dana-sosial.index'))->assertRedirect(route('login'));
        $this->actingAs($kasir)->get(route('klaim-dana-sosial.index'))->assertForbidden();
        $this->actingAs($admin)->get(route('klaim-dana-sosial.index'))->assertNotFound();
        config()->set('features.dana_sosial_enabled', true); config()->set('features.shu_enabled', false);
        $this->actingAs($admin)->get(route('klaim-dana-sosial.index'))->assertNotFound();
        config()->set('features.shu_enabled', true);
        $this->actingAs($admin)->get(route('klaim-dana-sosial.index'))->assertOk()->assertSee('Dana Sosial');
        $this->actingAs($admin)->get('/klaim-dana-khusus')->assertNotFound();
    }
}
