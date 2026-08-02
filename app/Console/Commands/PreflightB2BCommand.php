<?php

namespace App\Console\Commands;

use App\Models\PembayaranInvoicePerusahaan;
use App\Models\PembayaranVendorSewa;
use App\Models\SewaMobil;
use App\Models\SewaHardware;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PreflightB2BCommand extends Command
{
    protected $signature = 'koperasi:preflight-b2b';
    protected $description = 'Audit read-only alur vendor-first, invoice perusahaan, piutang, Mutasi Kas, dan jurnal B2B.';

    public function handle(): int
    {
        if (! Schema::hasTable('pembayaran_vendor_sewa')) return self::FAILURE;
        $issues = [];
        $issues['snapshot_mobil_invalid'] = DB::table('sewa_mobil')->where('model_sumber', 'vendor')->where(fn ($q) => $q->whereNull('perusahaan_id')->orWhereNull('vendor_nama_snapshot')->orWhereNull('nomor_polisi_snapshot')->orWhereRaw('harga_vendor_total <= 0')->orWhereRaw('total_tagihan_perusahaan <> harga_vendor_total + markup_total'))->count();
        $issues['snapshot_hardware_invalid'] = DB::table('sewa_hardware')->where('model_sumber', 'vendor')->where(fn ($q) => $q->whereNull('perusahaan_id')->orWhereNull('vendor_nama')->orWhereRaw('total_harga_vendor <= 0')->orWhereRaw('total_tagihan_perusahaan <> total_harga_vendor + total_margin'))->count();
        $issues['vendor_wallet_invalid'] = DB::table('pembayaran_vendor_sewa as p')->join('dompet_koperasi as d', 'd.id', '=', 'p.dompet_id')->where(fn ($q) => $q->where('d.jenis_dompet', '!=', 'kas')->orWhere('d.is_kas_operasional', false))->count();
        $issues['vendor_artifact_missing'] = PembayaranVendorSewa::query()->get()->filter(fn ($p) => ! DB::table('mutasi_kas')->where('idempotency_key', 'b2b:vendor:mutasi:'.$p->id)->exists() || ! DB::table('jurnal_umum')->where('idempotency_key', 'b2b:vendor:jurnal:'.$p->id)->exists())->count();
        $issues['invoice_total_invalid'] = DB::table('invoice_penagihan')->get()->filter(function ($i) { $detail=(int)DB::table('invoice_penagihan_detail')->where('invoice_penagihan_id',$i->id)->sum('nominal'); $paid=(int)DB::table('pembayaran_invoice_perusahaan')->where('invoice_penagihan_id',$i->id)->where('status','paid')->sum('jumlah_bayar'); return $detail!==(int)$i->total_tagihan || $paid!==(int)$i->jumlah_dibayar || max(0,(int)$i->total_tagihan-$paid)!==(int)$i->sisa_tagihan; })->count();
        $issues['invoice_payment_artifact_missing'] = PembayaranInvoicePerusahaan::query()->get()->filter(fn ($p) => ! DB::table('mutasi_kas')->where('idempotency_key', 'b2b:invoice-payment:mutasi:'.$p->id)->exists() || ! DB::table('jurnal_umum')->where('idempotency_key', 'b2b:invoice-payment:jurnal:'.$p->id)->exists())->count();
        $issues['jurnal_unbalanced'] = DB::table('jurnal_umum as j')->join('jurnal_umum_detail as d','d.jurnal_umum_id','=','j.id')->select('j.id')->where('j.idempotency_key','like','b2b:%')->groupBy('j.id')->havingRaw('ABS(SUM(d.debit)-SUM(d.kredit)) > 0.01')->get()->count();
        $this->table(['Pemeriksaan','Count'], collect($issues)->map(fn($v,$k)=>[$k,$v])->values()->all());
        if (array_sum($issues)>0) { $this->error('Preflight B2B menemukan konflik kritis. Tidak ada data yang diubah.'); return self::FAILURE; }
        $this->info('Preflight B2B bersih.'); return self::SUCCESS;
    }
}
