<?php

namespace Tests\Feature;

use App\Models\Akun;
use App\Models\JurnalUmum;
use App\Models\PeriodeAkuntansi;
use App\Models\User;
use App\Services\AccountingPeriodService;
use App\Services\AkuntansiService;
use Database\Seeders\AkunSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class AccountingPeriodLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_tutup_buku_menghasilkan_laba_jurnal_penutup_idempoten_dan_mengunci_tanggal(): void
    {
        $this->seed(AkunSeeder::class);
        $user = User::factory()->create(['role' => 'admin']);
        $periodService = app(AccountingPeriodService::class);
        $period = $periodService->create(['kode' => 'FY-2025', 'nama' => 'Tahun Buku 2025', 'tanggal_mulai' => '2025-01-01', 'tanggal_selesai' => '2025-12-31'], $user->id);
        $accounts = Akun::query()->whereIn('kode_akun', ['101', '401', '502'])->get()->keyBy('kode_akun');
        $accounting = app(AkuntansiService::class);
        $accounting->record(['tanggal' => '2025-06-01', 'nomor_bukti' => 'REV-1', 'keterangan' => 'Pendapatan', 'created_by' => $user->id], [
            $this->line($accounts['101'], 1000000, 0), $this->line($accounts['401'], 0, 1000000),
        ]);
        $accounting->record(['tanggal' => '2025-07-01', 'nomor_bukti' => 'EXP-1', 'keterangan' => 'Beban', 'created_by' => $user->id], [
            $this->line($accounts['502'], 300000, 0), $this->line($accounts['101'], 0, 300000),
        ]);

        $closed = $periodService->close($period, $user->id);
        $this->assertSame(PeriodeAkuntansi::STATUS_CLOSED, $closed->status);
        $this->assertSame(1000000.0, (float) $closed->total_pendapatan);
        $this->assertSame(300000.0, (float) $closed->total_beban);
        $this->assertSame(700000.0, (float) $closed->laba_bersih);
        $this->assertNotNull($closed->closing_journal_id);
        $this->assertSame(1, JurnalUmum::query()->where('idempotency_key', 'tutup-buku:jurnal:' . $period->id)->count());
        $periodService->close($period->fresh(), $user->id);
        $this->assertSame(1, JurnalUmum::query()->where('idempotency_key', 'tutup-buku:jurnal:' . $period->id)->count());

        $this->expectException(ValidationException::class);
        $accounting->record(['tanggal' => '2025-12-31', 'nomor_bukti' => 'LATE', 'keterangan' => 'Terlambat', 'created_by' => $user->id], [
            $this->line($accounts['101'], 1, 0), $this->line($accounts['401'], 0, 1),
        ]);
    }

    private function line(Akun $account, int $debit, int $credit): array
    {
        return ['akun_id' => $account->id, 'akun_kode' => $account->kode_akun, 'akun_nama' => $account->nama_akun, 'debit' => $debit, 'kredit' => $credit];
    }
}
