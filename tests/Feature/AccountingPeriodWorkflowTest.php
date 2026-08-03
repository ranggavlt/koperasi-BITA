<?php

namespace Tests\Feature;

use App\Models\Akun;
use App\Models\JurnalUmum;
use App\Models\PeriodeAkuntansi;
use App\Models\User;
use App\Services\AccountingPeriodService;
use App\Services\AkuntansiService;
use App\Services\AkunResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class AccountingPeriodWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_render_accounting_period_page_and_non_admin_is_forbidden(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true, 'must_change_password' => false]);
        $cashier = User::factory()->create(['role' => 'kasir', 'is_active' => true, 'must_change_password' => false]);

        $this->actingAs($cashier)->get(route('akuntansi.periode.index'))->assertForbidden();
        $this->actingAs($admin)->get(route('akuntansi.periode.index'))
            ->assertOk()
            ->assertSee('Periode Akuntansi')
            ->assertSee('Buat Periode Open');
    }

    public function test_close_uses_only_posted_balanced_journals_creates_snapshot_and_locks_dates(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $period = app(AccountingPeriodService::class)->create([
            'kode' => 'FY-2025', 'nama' => 'Tahun Buku 2025',
            'tanggal_mulai' => '2025-01-01', 'tanggal_selesai' => '2025-12-31',
        ], $admin->id);
        $resolver = app(AkunResolver::class);
        $journal = app(AkuntansiService::class);
        $cash = Akun::query()->where('kode_akun', '101')->firstOrFail();
        $income = Akun::query()->where('kode_akun', '401')->firstOrFail();
        $expense = Akun::query()->where('kode_akun', '502')->firstOrFail();

        $journal->record(['tanggal' => '2025-06-01', 'nomor_bukti' => 'REV-001', 'created_by' => $admin->id], [
            $resolver->line($cash, 'debit', 1000000), $resolver->line($income, 'kredit', 1000000),
        ]);
        $journal->record(['tanggal' => '2025-07-01', 'nomor_bukti' => 'EXP-001', 'created_by' => $admin->id], [
            $resolver->line($expense, 'debit', 400000), $resolver->line($cash, 'kredit', 400000),
        ]);

        $closed = app(AccountingPeriodService::class)->close($period, 'Tutup buku untuk RAT 2025', $admin->id);

        $this->assertSame(PeriodeAkuntansi::STATUS_CLOSED, $closed->status);
        $this->assertSame('1000000.00', $closed->total_pendapatan);
        $this->assertSame('400000.00', $closed->total_beban);
        $this->assertSame('600000.00', $closed->laba_bersih);
        $this->assertSame(2, $closed->jumlah_jurnal);
        $this->assertNotNull($closed->checksum);
        $this->assertNotNull($closed->closing_journal_id);
        $this->assertSame('600000.00', $closed->closingJournal->details->firstWhere('akun_kode', '304')->kredit);
        $this->assertEquals($closed->closingJournal->details->sum('debit'), $closed->closingJournal->details->sum('kredit'));
        $this->assertSame(2, JurnalUmum::query()->where('periode_akuntansi_id', $period->id)->where('id', '!=', $closed->closing_journal_id)->count());

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('sudah dikunci');
        $journal->record(['tanggal' => '2025-10-01', 'nomor_bukti' => 'LATE-001'], [
            $resolver->line($cash, 'debit', 100), $resolver->line($income, 'kredit', 100),
        ]);
    }

    public function test_official_correction_is_posted_in_open_date_and_links_closed_period(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $period = PeriodeAkuntansi::query()->create(['kode' => 'FY-2024', 'nama' => 'Tahun Buku 2024', 'tanggal_mulai' => '2024-01-01', 'tanggal_selesai' => '2024-12-31', 'status' => 'closed', 'created_by' => $admin->id, 'closed_by' => $admin->id, 'closed_at' => now(), 'idempotency_key' => 'period-2024']);
        $resolver = app(AkunResolver::class);
        $cash = Akun::query()->where('kode_akun', '101')->firstOrFail();
        $income = Akun::query()->where('kode_akun', '401')->firstOrFail();

        $correction = app(AkuntansiService::class)->recordCorrection($period, ['tanggal' => '2026-08-03', 'nomor_bukti' => 'ADJ-001', 'created_by' => $admin->id], [
            $resolver->line($income, 'debit', 25000), $resolver->line($cash, 'kredit', 25000),
        ], 'Koreksi pendapatan salah catat');

        $this->assertTrue($correction->is_adjustment);
        $this->assertSame($period->id, $correction->correction_period_id);
        $this->assertSame('Koreksi pendapatan salah catat', $correction->correction_reason);
        $this->assertSame(JurnalUmum::STATUS_POSTED, $correction->status);
    }
}
