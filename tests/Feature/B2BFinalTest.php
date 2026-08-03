<?php

namespace Tests\Feature;

use App\Models\InvoicePenagihan;
use App\Models\PembayaranInvoicePerusahaan;
use App\Models\PembayaranVendorSewa;
use App\Models\User;
use App\Services\B2BRentalService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class B2BFinalTest extends TestCase
{
    use RefreshDatabase;

    public function test_vendor_dibayar_dulu_lalu_invoice_gabungan_dapat_dicicil_idempotent(): void
    {
        $this->seed(DatabaseSeeder::class);
        $finance = User::query()->where('role', 'admin')->firstOrFail();
        $invoice = InvoicePenagihan::query()->with(['detail', 'pembayaran.dompet'])->firstOrFail();

        $this->assertSame(2, $invoice->detail->count());
        $this->assertSame(2, PembayaranVendorSewa::query()->where('status', 'paid')->count());
        $this->assertSame((int) $invoice->total_tagihan, (int) $invoice->detail->sum('nominal'));
        $this->assertSame((int) $invoice->total_tagihan - (int) $invoice->jumlah_dibayar, (int) $invoice->sisa_tagihan);

        $bank = $invoice->pembayaran->firstOrFail()->dompet;
        $payload = [
            'dompet_id' => $bank->id,
            'metode_pembayaran' => 'transfer_bank',
            'jumlah_bayar' => (int) $invoice->sisa_tagihan,
            'tanggal_bayar' => '2026-08-25',
            'nomor_referensi' => 'BKM-PELUNASAN-UAT',
            'idempotency_key' => 'test-b2b-pelunasan-final',
        ];

        $payment = app(B2BRentalService::class)->payInvoice($invoice, $payload, $finance->id);
        $retry = app(B2BRentalService::class)->payInvoice($invoice->fresh(), $payload, $finance->id);

        $this->assertSame($payment->id, $retry->id);
        $this->assertSame('paid', $invoice->fresh()->status);
        $this->assertSame(0, (int) $invoice->fresh()->sisa_tagihan);
        $this->assertSame(2, PembayaranInvoicePerusahaan::query()->where('invoice_penagihan_id', $invoice->id)->count());
        $this->assertSame(1, $payment->mutasiKas()->count());
        $this->assertSame(1, $payment->jurnal()->count());
        $this->artisan('koperasi:preflight-b2b')->assertExitCode(0);
        $this->actingAs($finance)->get(route('invoice-penagihan.index'))
            ->assertOk()
            ->assertSee('Total tagihan')
            ->assertSee('Total terbayar')
            ->assertSee('Sisa utang')
            ->assertSee('BEE')
            ->assertSee('BBS')
            ->assertSee('BKM');

        $this->expectException(ValidationException::class);
        app(B2BRentalService::class)->payInvoice($invoice->fresh(), array_merge($payload, ['idempotency_key' => 'test-b2b-after-paid']), $finance->id);
    }

    public function test_overpayment_dan_kegagalan_jurnal_tidak_meninggalkan_state_parsial(): void
    {
        $this->seed(DatabaseSeeder::class);
        $finance = User::query()->where('role', 'admin')->firstOrFail();
        $invoice = InvoicePenagihan::query()->with(['pembayaran.dompet'])->firstOrFail();
        $bank = $invoice->pembayaran->firstOrFail()->dompet;
        $service = app(B2BRentalService::class);

        $before = [
            'payments' => PembayaranInvoicePerusahaan::query()->count(),
            'mutations' => DB::table('mutasi_kas')->count(),
            'journals' => DB::table('jurnal_umum')->count(),
            'wallet' => (int) $bank->saldo,
            'remaining' => (int) $invoice->sisa_tagihan,
        ];

        try {
            $service->payInvoice($invoice, [
                'dompet_id' => $bank->id,
                'metode_pembayaran' => 'transfer_bank',
                'jumlah_bayar' => $before['remaining'] + 1,
                'tanggal_bayar' => '2026-08-24',
                'idempotency_key' => 'test-b2b-overpayment',
            ], $finance->id);
            $this->fail('Overpayment seharusnya ditolak.');
        } catch (ValidationException) {
            $this->assertSame($before['payments'], PembayaranInvoicePerusahaan::query()->count());
        }

        $originalPosting = config('account_map.postings.b2b.piutang_perusahaan');
        config()->set('account_map.postings.b2b.piutang_perusahaan', 'akun_tidak_tersedia');

        try {
            $service->payInvoice($invoice->fresh(), [
                'dompet_id' => $bank->id,
                'metode_pembayaran' => 'transfer_bank',
                'jumlah_bayar' => 1,
                'tanggal_bayar' => '2026-08-24',
                'idempotency_key' => 'test-b2b-journal-rollback',
            ], $finance->id);
            $this->fail('Posting dengan mapping akun invalid seharusnya gagal.');
        } catch (\LogicException) {
            $this->assertSame($before['payments'], PembayaranInvoicePerusahaan::query()->count());
        } finally {
            config()->set('account_map.postings.b2b.piutang_perusahaan', $originalPosting);
        }

        $this->assertSame($before['mutations'], DB::table('mutasi_kas')->count());
        $this->assertSame($before['journals'], DB::table('jurnal_umum')->count());
        $this->assertSame($before['wallet'], (int) $bank->fresh()->saldo);
        $this->assertSame($before['remaining'], (int) $invoice->fresh()->sisa_tagihan);
    }
}
