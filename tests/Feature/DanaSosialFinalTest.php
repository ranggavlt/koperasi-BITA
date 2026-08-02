<?php

namespace Tests\Feature;

use App\Models\Akun;
use App\Models\DanaSosialSumber;
use App\Models\DompetKoperasi;
use App\Models\Karyawan;
use App\Models\KlaimDanaSosial;
use App\Models\User;
use App\Services\DanaSosialService;
use Database\Seeders\AkunSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DanaSosialFinalTest extends TestCase
{
    use RefreshDatabase;

    public function test_sumber_approval_klaim_payout_mutasi_dan_jurnal_berjalan_utuh(): void
    {
        $this->seed(AkunSeeder::class);
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true, 'must_change_password' => false]);
        $employee = Karyawan::factory()->create(['nama' => 'Penerima Dana Sosial']);
        $wallet = DompetKoperasi::query()->create([
            'nama_dompet' => 'Kas Dana Sosial', 'jenis_dompet' => 'kas',
            'akun_id' => Akun::query()->where('kode_akun', '101')->value('id'), 'saldo' => 1000000,
        ]);
        $service = app(DanaSosialService::class);
        $source = $service->createSource(['nama_sumber' => 'Tambahan RAT', 'jenis_sumber' => DanaSosialSumber::JENIS_TAMBAHAN, 'nominal_awal' => 800000, 'keterangan' => 'Keputusan pengurus'], $admin->id);
        $this->assertSame(0, (int) $source->saldo_tersedia);
        $source = $service->approveSource($source, $admin->id);
        $claim = $service->createClaim(['karyawan_id' => $employee->id, 'kategori' => 'melahirkan', 'nominal' => 250000, 'tanggal_pengajuan' => '2026-08-02', 'keterangan' => 'Bantuan kelahiran'], $admin->id);
        $service->submit($claim);
        $service->approve($claim->fresh(), $source->id, $admin->id);
        $service->pay($claim->fresh(), ['dompet_id' => $wallet->id, 'metode_pembayaran' => 'tunai', 'tanggal_bayar' => '2026-08-03'], $admin->id);

        $this->assertDatabaseHas('klaim_dana_sosial', ['id' => $claim->id, 'status' => KlaimDanaSosial::STATUS_PAID, 'dompet_id' => $wallet->id]);
        $this->assertSame(550000, (int) $source->fresh()->saldo_tersedia);
        $this->assertSame(750000, (int) $wallet->fresh()->saldo);
        $this->assertDatabaseHas('mutasi_dana_sosial', ['klaim_dana_sosial_id' => $claim->id, 'nominal' => 250000]);
        $this->assertDatabaseHas('mutasi_kas', ['idempotency_key' => 'dana-claim:kas:'.$claim->id, 'tipe' => 'keluar']);
        $journal = DB::table('jurnal_umum')->where('idempotency_key', 'dana-claim:jurnal:'.$claim->id)->first();
        $this->assertNotNull($journal);
        $this->assertSame((float) DB::table('jurnal_umum_detail')->where('jurnal_umum_id', $journal->id)->sum('debit'), (float) DB::table('jurnal_umum_detail')->where('jurnal_umum_id', $journal->id)->sum('kredit'));
        $this->artisan('koperasi:preflight-dana-sosial')->assertExitCode(0);
    }

    public function test_route_dana_sosial_admin_only_dan_legacy_tidak_tersedia(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true, 'must_change_password' => false]);
        $kasir = User::factory()->create(['role' => 'kasir', 'is_active' => true, 'must_change_password' => false]);
        $this->get(route('klaim-dana-sosial.index'))->assertRedirect(route('login'));
        $this->actingAs($kasir)->get(route('klaim-dana-sosial.index'))->assertForbidden();
        $this->actingAs($admin)->get(route('klaim-dana-sosial.index'))->assertOk()->assertSee('Klaim Dana Sosial');
        $this->actingAs($admin)->get('/klaim-dana-khusus')->assertNotFound();
    }
}
