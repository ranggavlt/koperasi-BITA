<?php

namespace Tests\Feature;

use App\Models\DanaSosialSumber;
use App\Models\PembayaranShu;
use App\Models\ShuAlokasi;
use App\Models\ShuKoperasi;
use App\Services\ShuKoperasiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class ShuKoperasiTest extends TestCase
{
    use RefreshDatabase;

    public function test_approved_shu_uses_closed_ledger_and_posts_final_allocations_atomically(): void
    {
        $this->seed();

        $shu = ShuKoperasi::query()->with(['periode', 'allocationJournal.details', 'allocations', 'socialFund', 'recipients.pembayaran'])->sole();
        $this->assertSame(ShuKoperasi::STATUS_APPROVED, $shu->status);
        $this->assertSame('40000000.00', $shu->shu_total);
        $this->assertSame('12000000.00', $shu->nominal_dana_cadangan);
        $this->assertSame('16000000.00', $shu->nominal_shu_anggota);
        $this->assertSame('4000000.00', $shu->nominal_pengurus);
        $this->assertSame('2000000.00', $shu->nominal_pengawas);
        $this->assertSame('2000000.00', $shu->nominal_pembina);
        $this->assertSame('4000000.00', $shu->nominal_dana_sosial);
        $this->assertSame('0.00', $shu->nominal_dana_pendidikan);
        $this->assertSame($shu->periode->checksum, $shu->source_snapshot['periode_checksum']);

        $this->assertNotNull($shu->allocation_journal_id);
        $this->assertEquals($shu->allocationJournal->details->sum('debit'), $shu->allocationJournal->details->sum('kredit'));
        $this->assertEqualsCanonicalizing(
            [ShuAlokasi::DANA_CADANGAN, ShuAlokasi::DANA_SOSIAL],
            $shu->allocations->pluck('jenis')->all()
        );
        $this->assertSame(DanaSosialSumber::JENIS_SHU, $shu->socialFund->jenis);
        $this->assertSame('4000000.00', $shu->socialFund->jumlah);
        $this->assertFalse($shu->socialFund->is_legacy);

        $methods = PembayaranShu::query()->where('status', PembayaranShu::STATUS_PAID)->pluck('metode')->all();
        $this->assertContains('tunai', $methods);
        $this->assertContains('transfer_bank', $methods);
        $this->assertGreaterThan(0, $shu->recipients->whereNull('pembayaran')->count());
    }

    public function test_finalized_shu_is_immutable_and_legacy_manual_input_is_disabled(): void
    {
        $this->seed();
        $shu = ShuKoperasi::query()->sole();

        try {
            $shu->update(['nominal_dana_sosial' => 1]);
            $this->fail('SHU final tidak boleh diubah langsung.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('permanen', $exception->getMessage());
        }

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('manual dinonaktifkan');
        app(ShuKoperasiService::class)->addTransaksi($shu, ['jumlah' => 100]);
    }
}
