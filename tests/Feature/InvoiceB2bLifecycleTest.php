<?php

namespace Tests\Feature;

use App\Models\Akun;
use App\Models\DompetKoperasi;
use App\Models\InvoicePenagihan;
use App\Models\Karyawan;
use App\Models\Perusahaan;
use App\Models\SewaHardware;
use App\Models\User;
use App\Services\InvoicePenagihanService;
use App\Services\VendorRentalPaymentService;
use Database\Seeders\AkunSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoiceB2bLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_invoice_partial_payment_vendor_refund_dan_company_refund_terpisah(): void
    {
        $this->seed(AkunSeeder::class);
        $user = User::factory()->create(['role' => 'admin']);
        $company = Perusahaan::query()->create(['kode' => 'BEE', 'nama' => 'BEE']);
        $employee = Karyawan::query()->create(['nama' => 'PIC BEE', 'email' => 'pic.bee@example.test', 'telepon' => '081', 'jabatan' => 'PIC', 'perusahaan_id' => $company->id, 'status_kerja' => 'aktif']);
        $rental = SewaHardware::query()->create([
            'kode_sewa' => 'SHW-TEST-001', 'nama_perusahaan_snapshot' => 'BEE', 'karyawan_id' => $employee->id,
            'mulai_tanggal' => '2026-09-01', 'selesai_tanggal' => '2026-09-05', 'kebutuhan' => 'Laptop',
            'vendor_nama' => 'Vendor Test', 'vendor_kontak' => '0812345', 'vendor_alamat' => 'Alamat Vendor', 'total_harga_vendor' => 100000, 'total_margin' => 15000,
            'total_tagihan_perusahaan' => 115000, 'status' => SewaHardware::STATUS_DIKONFIRMASI,
            'status_pembayaran' => SewaHardware::PEMBAYARAN_BELUM_BAYAR, 'created_by' => $user->id, 'idempotency_key' => 'test:sewa-hardware:1',
        ]);
        $wallet = DompetKoperasi::query()->create(['akun_id' => Akun::query()->where('kode_akun', '101')->value('id'), 'nama_dompet' => 'Kas B2B', 'jenis_dompet' => 'kas', 'saldo' => 0]);
        $invoiceService = app(InvoicePenagihanService::class);
        $invoice = $invoiceService->create(['perusahaan_id' => $company->id, 'tanggal_invoice' => '2026-08-10', 'jatuh_tempo' => '2026-08-31', 'sewa_hardware_ids' => [$rental->id]], $user->id);
        $this->assertSame(115000.0, (float) $invoice->total_tagihan);
        $this->assertDatabaseHas('jurnal_umum', ['idempotency_key' => 'invoice-b2b:finalisasi:jurnal:' . $invoice->id]);

        $invoiceService->recordPayment($invoice, ['jumlah' => 50000, 'metode' => 'tunai', 'dompet_id' => $wallet->id, 'tanggal_bayar' => '2026-08-15'], $user->id);
        $this->assertSame(InvoicePenagihan::STATUS_PARTIAL, $invoice->fresh()->status);
        $this->assertSame(65000.0, (float) $invoice->fresh()->sisa_tagihan);
        $invoiceService->recordPayment($invoice->fresh(), ['jumlah' => 65000, 'metode' => 'tunai', 'dompet_id' => $wallet->id, 'tanggal_bayar' => '2026-08-20'], $user->id);
        $this->assertSame(InvoicePenagihan::STATUS_PAID, $invoice->fresh()->status);
        $this->assertSame(115000.0, (float) $wallet->fresh()->saldo);

        $vendor = app(VendorRentalPaymentService::class);
        $payment = $vendor->pay($rental->fresh(), ['jumlah' => 100000, 'metode' => 'tunai', 'dompet_id' => $wallet->id, 'tanggal_bayar' => '2026-08-21'], $user->id);
        $this->assertSame(15000.0, (float) $wallet->fresh()->saldo);
        $vendor->requestRefund($rental->fresh(), 'Kontrak dibatalkan sebelum mulai', $user->id);
        $vendor->confirmRefund($rental->fresh(), ['tanggal_pengembalian' => '2026-08-22'], $user->id);
        $this->assertSame(115000.0, (float) $wallet->fresh()->saldo);

        $invoiceService->refundRental($rental->fresh(), ['metode' => 'tunai', 'dompet_id' => $wallet->id, 'tanggal_pengembalian' => '2026-08-23', 'alasan' => 'Kontrak dibatalkan sebelum mulai'], $user->id);
        $this->assertSame(0.0, (float) $wallet->fresh()->saldo);
        $this->assertSame(SewaHardware::STATUS_REFUNDED, $rental->fresh()->status);
        $this->assertDatabaseHas('pengembalian_invoice_penagihan', ['invoice_penagihan_detail_id' => $invoice->detail()->value('id'), 'jumlah' => 115000]);
        $this->assertSame(6, \App\Models\JurnalUmum::query()->count());
    }
}
