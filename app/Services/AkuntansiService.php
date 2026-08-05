<?php

namespace App\Services;

use App\Models\Akun;
use App\Models\BebanOperasional;
use App\Models\CicilanPinjaman;
use App\Models\JurnalUmum;
use App\Models\PembayaranKonsinyasi;
use App\Models\PembayaranOutstandingCash;
use App\Models\PembayaranSewaMobil;
use App\Models\PembayaranSewaHardware;
use App\Models\PembayaranInvoicePenagihan;
use App\Models\PembayaranVendorSewa;
use App\Models\PengembalianInvoicePenagihan;
use App\Models\InvoicePenagihan;
use App\Models\ShuKoperasi;
use App\Models\PembayaranShu;
use App\Models\DanaSosialSumber;
use App\Models\KlaimDanaSosial;
use App\Models\Pembayaran;
use App\Models\Penjualan;
use App\Models\PenyelesaianKeanggotaan;
use App\Models\PenyelesaianKeanggotaanDetail;
use App\Models\Pinjaman;
use App\Models\ReversalTransaksi;
use App\Models\SewaMobil;
use App\Models\SewaHardware;
use App\Models\Simpanan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class AkuntansiService
{
    public function __construct(private readonly AkunResolver $akunResolver)
    {
    }

    /**
     * @param  array<int, array{akun_id:int, akun_kode:string, akun_nama:string, debit:float|int, kredit:float|int}>  $lines
     */
    public function record(array $header, array $lines): JurnalUmum
    {
        if (Schema::hasTable('periode_akuntansi') && ! empty($header['tanggal'])) {
            AccountingPeriodService::assertDateUnlocked((string) $header['tanggal']);
        }
        if (count($lines) < 2) {
            throw new RuntimeException('Jurnal harus memiliki minimal dua baris akun.');
        }

        $normalizedLines = collect($lines)
            ->map(fn (array $line) => $this->normalizeLine($line))
            ->values()
            ->all();

        $totalDebit = round(collect($normalizedLines)->sum(fn ($line) => $line['debit']), 2);
        $totalKredit = round(collect($normalizedLines)->sum(fn ($line) => $line['kredit']), 2);

        if ($totalDebit <= 0 || $totalKredit <= 0 || abs($totalDebit - $totalKredit) > 0.01) {
            throw new RuntimeException('Jurnal tidak balance (debit != kredit).');
        }

        return DB::transaction(function () use ($header, $normalizedLines): JurnalUmum {
            $jurnal = JurnalUmum::create($header);

            $jurnal->details()->createMany($normalizedLines);

            return $jurnal;
        });
    }

    public function recordInvoiceB2bFinalization(InvoicePenagihan $invoice, ?int $userId = null): JurnalUmum
    {
        $existing = JurnalUmum::query()->where('idempotency_key', 'invoice-b2b:finalisasi:jurnal:' . $invoice->id)->first();
        if ($existing) {
            return $existing;
        }

        $invoice->loadMissing('detail.referensi');
        $components = [
            'mobil_vendor' => 0.0,
            'mobil_margin' => 0.0,
            'hardware_vendor' => 0.0,
            'hardware_margin' => 0.0,
        ];

        foreach ($invoice->detail as $detail) {
            $reference = $detail->referensi;
            if ($reference instanceof SewaMobil) {
                $components['mobil_vendor'] += (float) $reference->total_harga_vendor;
                $components['mobil_margin'] += (float) $reference->total_markup;
            } elseif ($reference instanceof SewaHardware) {
                $components['hardware_vendor'] += (float) $reference->total_harga_vendor;
                $components['hardware_margin'] += (float) $reference->total_margin;
            }
        }

        $lines = [$this->akunResolver->line($this->akunResolver->posting('invoice_b2b.piutang'), 'debit', $invoice->total_tagihan)];
        foreach ([
            ['mobil_vendor', 'sewa_mobil.utang_vendor'],
            ['mobil_margin', 'sewa_mobil.pendapatan_diterima_dimuka_margin'],
            ['hardware_vendor', 'sewa_hardware.utang_vendor'],
            ['hardware_margin', 'sewa_hardware.pendapatan_diterima_dimuka_margin'],
        ] as [$component, $posting]) {
            if ($components[$component] > 0) {
                $lines[] = $this->akunResolver->line($this->akunResolver->posting($posting), 'kredit', $components[$component]);
            }
        }

        return $this->record([
            'idempotency_key' => 'invoice-b2b:finalisasi:jurnal:' . $invoice->id,
            'tanggal' => $invoice->tanggal_invoice->toDateString(),
            'nomor_bukti' => $invoice->nomor_invoice,
            'keterangan' => 'Penerbitan invoice perusahaan ' . $invoice->nomor_invoice,
            'referensi_tipe' => InvoicePenagihan::class,
            'referensi_id' => $invoice->id,
            'created_by' => $userId ?? auth()->id(),
        ], $lines);
    }

    public function recordInvoiceB2bPayment(PembayaranInvoicePenagihan $payment, ?int $userId = null): JurnalUmum
    {
        $existing = JurnalUmum::query()->where('idempotency_key', 'invoice-b2b:pembayaran:jurnal:' . $payment->id)->first();
        if ($existing) {
            return $existing;
        }

        $payment->loadMissing(['invoice', 'dompet.akun']);
        $this->assertCashAccount($payment->dompet->akun, 'penerimaan invoice perusahaan');

        return $this->record([
            'idempotency_key' => 'invoice-b2b:pembayaran:jurnal:' . $payment->id,
            'tanggal' => $payment->tanggal_bayar->toDateString(),
            'nomor_bukti' => $payment->invoice->nomor_invoice,
            'keterangan' => 'Pembayaran invoice perusahaan ' . $payment->invoice->nomor_invoice,
            'referensi_tipe' => PembayaranInvoicePenagihan::class,
            'referensi_id' => $payment->id,
            'created_by' => $userId ?? auth()->id(),
        ], [
            $this->akunResolver->line($payment->dompet->akun, 'debit', $payment->jumlah),
            $this->akunResolver->line($this->akunResolver->posting('invoice_b2b.piutang'), 'kredit', $payment->jumlah),
        ]);
    }

    public function recordVendorRentalPayment(PembayaranVendorSewa $payment, ?int $userId = null): JurnalUmum
    {
        $existing = JurnalUmum::query()->where('idempotency_key', 'sewa:vendor:pembayaran:jurnal:' . $payment->id)->first();
        if ($existing) {
            return $existing;
        }

        $payment->loadMissing(['sewa', 'dompet.akun']);
        $this->assertCashAccount($payment->dompet->akun, 'pembayaran vendor sewa');
        $posting = $payment->sewa instanceof SewaMobil ? 'sewa_mobil.utang_vendor' : 'sewa_hardware.utang_vendor';

        return $this->record([
            'idempotency_key' => 'sewa:vendor:pembayaran:jurnal:' . $payment->id,
            'tanggal' => $payment->tanggal_bayar->toDateString(),
            'nomor_bukti' => $payment->sewa->kode_sewa,
            'keterangan' => 'Pembayaran vendor untuk ' . $payment->sewa->kode_sewa,
            'referensi_tipe' => PembayaranVendorSewa::class,
            'referensi_id' => $payment->id,
            'created_by' => $userId ?? auth()->id(),
        ], [
            $this->akunResolver->line($this->akunResolver->posting($posting), 'debit', $payment->jumlah),
            $this->akunResolver->line($payment->dompet->akun, 'kredit', $payment->jumlah),
        ]);
    }

    public function recordVendorRentalRefund(PembayaranVendorSewa $payment, ReversalTransaksi $reversal, ?int $userId = null): JurnalUmum
    {
        $existing = JurnalUmum::query()->where('idempotency_key', 'sewa:vendor:pengembalian:jurnal:' . $payment->id)->first();
        if ($existing) {
            return $existing;
        }

        $payment->loadMissing(['sewa', 'dompet.akun']);
        $this->assertCashAccount($payment->dompet->akun, 'pengembalian dana vendor');
        $posting = $payment->sewa instanceof SewaMobil ? 'sewa_mobil.utang_vendor' : 'sewa_hardware.utang_vendor';

        return $this->record([
            'idempotency_key' => 'sewa:vendor:pengembalian:jurnal:' . $payment->id,
            'tanggal' => optional($payment->dikembalikan_pada)->toDateString() ?? now()->toDateString(),
            'nomor_bukti' => $reversal->kode_reversal,
            'keterangan' => 'Pengembalian dana vendor untuk ' . $payment->sewa->kode_sewa,
            'referensi_tipe' => ReversalTransaksi::class,
            'referensi_id' => $reversal->id,
            'created_by' => $userId ?? auth()->id(),
        ], [
            $this->akunResolver->line($payment->dompet->akun, 'debit', $payment->jumlah),
            $this->akunResolver->line($this->akunResolver->posting($posting), 'kredit', $payment->jumlah),
        ]);
    }

    public function recordInvoiceB2bRefund(PengembalianInvoicePenagihan $refund, ?int $userId = null): JurnalUmum
    {
        $existing = JurnalUmum::query()->where('idempotency_key', 'invoice-b2b:pengembalian:jurnal:' . $refund->id)->first();
        if ($existing) {
            return $existing;
        }

        $refund->loadMissing(['detail.referensi', 'detail.invoice', 'dompet.akun']);
        $this->assertCashAccount($refund->dompet->akun, 'pengembalian dana perusahaan');
        $reference = $refund->detail->referensi;
        $total = max(1.0, (float) $refund->detail->nominal);
        $ratio = min(1, (float) $refund->jumlah / $total);
        $vendor = $reference instanceof SewaMobil ? (float) $reference->total_harga_vendor : (float) $reference->total_harga_vendor;
        $margin = $reference instanceof SewaMobil ? (float) $reference->total_markup : (float) $reference->total_margin;
        $vendorRefund = round($vendor * $ratio, 2);
        $marginRefund = round((float) $refund->jumlah - $vendorRefund, 2);
        $prefix = $reference instanceof SewaMobil ? 'sewa_mobil' : 'sewa_hardware';

        return $this->record([
            'idempotency_key' => 'invoice-b2b:pengembalian:jurnal:' . $refund->id,
            'tanggal' => $refund->tanggal_pengembalian->toDateString(),
            'nomor_bukti' => $refund->detail->invoice->nomor_invoice,
            'keterangan' => 'Pengembalian dana perusahaan atas ' . $reference->kode_sewa,
            'referensi_tipe' => PengembalianInvoicePenagihan::class,
            'referensi_id' => $refund->id,
            'created_by' => $userId ?? auth()->id(),
        ], [
            $this->akunResolver->line($this->akunResolver->posting($prefix . '.utang_vendor'), 'debit', $vendorRefund),
            $this->akunResolver->line($this->akunResolver->posting($prefix . '.pendapatan_diterima_dimuka_margin'), 'debit', $marginRefund),
            $this->akunResolver->line($refund->dompet->akun, 'kredit', $refund->jumlah),
        ]);
    }

    private function assertCashAccount(?Akun $akun, string $context): void
    {
        if (! $akun || ! $akun->is_aktif || $akun->kategori !== 'aset' || $akun->posisi_saldo !== 'debit') {
            throw new RuntimeException('Akun Dompet untuk ' . $context . ' harus aktif, kategori Aset, dan saldo normal Debit.');
        }
    }

    public function recordShuApproval(ShuKoperasi $shu, ?int $userId = null): ?JurnalUmum
    {
        $existing=JurnalUmum::query()->where('idempotency_key','shu:persetujuan:jurnal:'.$shu->id)->first();if($existing)return $existing;
        if((float)$shu->shu_total<=0)return null;
        $personal=(float)$shu->nominal_shu_anggota+(float)$shu->nominal_pengurus+(float)$shu->nominal_pengawas+(float)$shu->nominal_pembina;
        $lines=[$this->akunResolver->line($this->akunResolver->posting('shu.belum_dibagi'),'debit',$shu->shu_total)];
        foreach([['shu.dana_cadangan',$shu->nominal_dana_cadangan],['shu.utang_penerima',$personal],['shu.dana_sosial',$shu->nominal_dana_sosial],['shu.dana_pendidikan',$shu->nominal_dana_pendidikan]] as [$posting,$amount])if((float)$amount>0)$lines[]=$this->akunResolver->line($this->akunResolver->posting($posting),'kredit',$amount);
        return $this->record(['idempotency_key'=>'shu:persetujuan:jurnal:'.$shu->id,'tanggal'=>$shu->approved_at->toDateString(),'nomor_bukti'=>'SHU-'.$shu->id,'keterangan'=>'Persetujuan pembagian '.$shu->judul,'referensi_tipe'=>ShuKoperasi::class,'referensi_id'=>$shu->id,'created_by'=>$userId??auth()->id()],$lines);
    }

    public function recordShuPayment(PembayaranShu $payment, ?int $userId = null): JurnalUmum
    {
        $existing=JurnalUmum::query()->where('idempotency_key','shu:pembayaran:jurnal:'.$payment->id)->first();if($existing)return $existing;$payment->loadMissing(['penerima','dompet.akun']);$this->assertCashAccount($payment->dompet->akun,'pembayaran SHU');
        return $this->record(['idempotency_key'=>'shu:pembayaran:jurnal:'.$payment->id,'tanggal'=>$payment->tanggal_bayar->toDateString(),'nomor_bukti'=>'BYR-SHU-'.$payment->id,'keterangan'=>'Pembayaran SHU '.$payment->penerima->nama_snapshot,'referensi_tipe'=>PembayaranShu::class,'referensi_id'=>$payment->id,'created_by'=>$userId??auth()->id()],[
            $this->akunResolver->line($this->akunResolver->posting('shu.utang_penerima'),'debit',$payment->jumlah),$this->akunResolver->line($payment->dompet->akun,'kredit',$payment->jumlah),
        ]);
    }

    public function recordSocialDonation(DanaSosialSumber $source, ?int $userId = null): JurnalUmum
    {
        $existing=JurnalUmum::query()->where('idempotency_key','dana-sosial:donasi:jurnal:'.$source->id)->first();if($existing)return $existing;$source->loadMissing('dompet.akun');$this->assertCashAccount($source->dompet->akun,'donasi resmi');
        return $this->record(['idempotency_key'=>'dana-sosial:donasi:jurnal:'.$source->id,'tanggal'=>$source->tanggal->toDateString(),'nomor_bukti'=>$source->kode_sumber,'keterangan'=>'Donasi resmi Dana Sosial','referensi_tipe'=>DanaSosialSumber::class,'referensi_id'=>$source->id,'created_by'=>$userId??auth()->id()],[
            $this->akunResolver->line($source->dompet->akun,'debit',$source->jumlah),$this->akunResolver->line($this->akunResolver->posting('shu.dana_sosial'),'kredit',$source->jumlah),
        ]);
    }

    public function recordSocialClaim(KlaimDanaSosial $claim, ?int $userId = null): JurnalUmum
    {
        $existing=JurnalUmum::query()->where('idempotency_key','dana-sosial:klaim:jurnal:'.$claim->id)->first();if($existing)return $existing;$claim->loadMissing('dompet.akun');$this->assertCashAccount($claim->dompet->akun,'pembayaran klaim Dana Sosial');
        return $this->record(['idempotency_key'=>'dana-sosial:klaim:jurnal:'.$claim->id,'tanggal'=>$claim->tanggal_bayar->toDateString(),'nomor_bukti'=>$claim->kode_klaim,'keterangan'=>'Pembayaran klaim Dana Sosial '.$claim->penerima_manfaat,'referensi_tipe'=>KlaimDanaSosial::class,'referensi_id'=>$claim->id,'created_by'=>$userId??auth()->id()],[
            $this->akunResolver->line($this->akunResolver->posting('shu.dana_sosial'),'debit',$claim->nominal_diajukan),$this->akunResolver->line($claim->dompet->akun,'kredit',$claim->nominal_diajukan),
        ]);
    }

    public function reverseByReference(string $referensiTipe, int $referensiId): void
    {
        JurnalUmum::query()
            ->where('referensi_tipe', $referensiTipe)
            ->where('referensi_id', $referensiId)
            ->get()
            ->each(fn (JurnalUmum $jurnal) => $jurnal->delete());
    }

    public function recordPenjualan(Penjualan $penjualan, string $metodePembayaran): void
    {
        if (JurnalUmum::query()
            ->where('referensi_tipe', Penjualan::class)
            ->where('referensi_id', $penjualan->id)
            ->exists()) {
            return;
        }

        $tanggal = optional($penjualan->created_at)->toDateString() ?? now()->toDateString();

        $totalSetorKonsinyasi = (float) $penjualan->details()
            ->where('konsinyasi', true)
            ->sum('subtotal_setor');

        $grandTotal = (float) ($penjualan->grand_total ?? 0);
        $pendapatan = max(0, $grandTotal - $totalSetorKonsinyasi);

        $akunDebit = $metodePembayaran === 'potong_gaji'
            ? $this->akunResolver->posting('penjualan.piutang_potong_gaji')
            : $this->akunResolver->posting('penjualan.kas');

        $lines = [
            $this->akunResolver->line($akunDebit, 'debit', $grandTotal),
        ];

        if ($totalSetorKonsinyasi > 0) {
            $lines[] = $this->akunResolver->line(
                $this->akunResolver->posting('penjualan.utang_konsinyasi'),
                'kredit',
                $totalSetorKonsinyasi
            );
        }

        if ($pendapatan > 0) {
            $lines[] = $this->akunResolver->line(
                $this->akunResolver->posting('penjualan.pendapatan'),
                'kredit',
                $pendapatan
            );
        }

        $this->record([
            'tanggal' => $tanggal,
            'nomor_bukti' => $penjualan->kode_transaksi,
            'keterangan' => 'Penjualan ' . $penjualan->kode_transaksi . ' (' . $metodePembayaran . ')',
            'referensi_tipe' => Penjualan::class,
            'referensi_id' => $penjualan->id,
            'created_by' => auth()->id(),
        ], $lines);
    }

    public function recordPenjualanPos(Penjualan $penjualan, Pembayaran $pembayaran, ?Akun $akunDompet = null, ?int $userId = null): JurnalUmum
    {
        $existing = JurnalUmum::query()
            ->where('idempotency_key', 'pos:penjualan:jurnal:' . $penjualan->id)
            ->first();

        if ($existing) {
            return $existing;
        }

        $tanggal = optional($penjualan->tanggal_transaksi)->toDateString()
            ?? optional($penjualan->created_at)->toDateString()
            ?? now()->toDateString();

        $totalSetorKonsinyasi = (float) $penjualan->details()
            ->where('konsinyasi', true)
            ->sum('subtotal_setor');

        $grandTotal = (float) ($penjualan->grand_total ?? 0);
        $pendapatan = max(0, $grandTotal - $totalSetorKonsinyasi);

        if ($pembayaran->metode_pembayaran === Pembayaran::METODE_POTONG_GAJI) {
            $akunDebit = $this->akunResolver->posting('penjualan.piutang_potong_gaji');
        } else {
            if (! $akunDompet || ! $akunDompet->is_aktif || $akunDompet->kategori !== 'aset' || $akunDompet->posisi_saldo !== 'debit') {
                throw new RuntimeException('Akun Dompet pembayaran POS harus aktif, kategori Aset, dan saldo normal Debit.');
            }

            $akunDebit = $akunDompet;
        }

        $lines = [
            $this->akunResolver->line($akunDebit, 'debit', $grandTotal),
        ];

        if ($totalSetorKonsinyasi > 0) {
            $lines[] = $this->akunResolver->line(
                $this->akunResolver->posting('penjualan.utang_konsinyasi'),
                'kredit',
                $totalSetorKonsinyasi
            );
        }

        if ($pendapatan > 0) {
            $lines[] = $this->akunResolver->line(
                $this->akunResolver->posting('penjualan.pendapatan'),
                'kredit',
                $pendapatan
            );
        }

        return $this->record([
            'idempotency_key' => 'pos:penjualan:jurnal:' . $penjualan->id,
            'tanggal' => $tanggal,
            'nomor_bukti' => $penjualan->kode_transaksi,
            'keterangan' => 'Penjualan ' . $penjualan->kode_transaksi . ' (' . $pembayaran->metode_pembayaran . ')',
            'referensi_tipe' => Penjualan::class,
            'referensi_id' => $penjualan->id,
            'created_by' => $userId ?? auth()->id(),
        ], $lines);
    }

    public function recordSimpananPokokPayroll(Simpanan $simpanan, ?int $userId = null): JurnalUmum
    {
        $existing = JurnalUmum::query()
            ->where('idempotency_key', 'simpanan-pokok:pengakuan:jurnal:' . $simpanan->id)
            ->first();

        if ($existing) {
            return $existing;
        }

        $simpanan->loadMissing('jenisSimpanan.akun');
        $akunSimpanan = $simpanan->jenisSimpanan?->akun;

        if (! $akunSimpanan || ! $akunSimpanan->is_aktif || ! in_array($akunSimpanan->kategori, ['kewajiban', 'ekuitas'], true) || $akunSimpanan->posisi_saldo !== 'kredit') {
            throw new RuntimeException('Akun Simpanan Pokok harus aktif, kategori kewajiban/ekuitas, dan saldo normal Kredit.');
        }

        $jumlah = (float) ($simpanan->nominal_snapshot ?? $simpanan->jumlah ?? 0);

        return $this->record([
            'idempotency_key' => 'simpanan-pokok:pengakuan:jurnal:' . $simpanan->id,
            'tanggal' => (string) ($simpanan->tanggal ?? now()->toDateString()),
            'nomor_bukti' => 'SMP-' . $simpanan->id,
            'keterangan' => 'Pengakuan piutang Simpanan Pokok Anggota',
            'referensi_tipe' => Simpanan::class,
            'referensi_id' => $simpanan->id,
            'created_by' => $userId ?? auth()->id(),
        ], [
            $this->akunResolver->line(
                $this->akunResolver->posting('penjualan.piutang_potong_gaji'),
                'debit',
                $jumlah
            ),
            $this->akunResolver->line(
                $akunSimpanan,
                'kredit',
                $jumlah
            ),
        ]);
    }

    public function recordSimpananWajibPayroll(Simpanan $simpanan, ?int $userId = null): JurnalUmum
    {
        $existing = JurnalUmum::query()
            ->where('idempotency_key', 'simpanan-wajib:pengakuan:jurnal:' . $simpanan->id)
            ->first();

        if ($existing) {
            return $existing;
        }

        $simpanan->loadMissing('jenisSimpanan.akun');
        $akunSimpanan = $simpanan->jenisSimpanan?->akun;

        if (! $akunSimpanan || ! $akunSimpanan->is_aktif || ! in_array($akunSimpanan->kategori, ['kewajiban', 'ekuitas'], true) || $akunSimpanan->posisi_saldo !== 'kredit') {
            throw new RuntimeException('Akun Simpanan Wajib harus aktif, kategori kewajiban/ekuitas, dan saldo normal Kredit.');
        }

        $jumlah = (float) ($simpanan->nominal_snapshot ?? $simpanan->jumlah ?? 0);

        return $this->record([
            'idempotency_key' => 'simpanan-wajib:pengakuan:jurnal:' . $simpanan->id,
            'tanggal' => (string) ($simpanan->tanggal ?? now()->toDateString()),
            'nomor_bukti' => 'SWJ-' . $simpanan->id,
            'keterangan' => 'Pengakuan piutang Simpanan Wajib Anggota',
            'referensi_tipe' => Simpanan::class,
            'referensi_id' => $simpanan->id,
            'created_by' => $userId ?? auth()->id(),
        ], [
            $this->akunResolver->line(
                $this->akunResolver->posting('penjualan.piutang_potong_gaji'),
                'debit',
                $jumlah
            ),
            $this->akunResolver->line(
                $akunSimpanan,
                'kredit',
                $jumlah
            ),
        ]);
    }

    public function recordPenerimaanPayrollPotongGaji(string $idempotencyKey, string $nomorBukti, string $tanggal, float|int $jumlah, Akun $akunBank, string $referensiTipe, int $referensiId, ?int $userId = null): JurnalUmum
    {
        $existing = JurnalUmum::query()
            ->where('idempotency_key', $idempotencyKey)
            ->first();

        if ($existing) {
            return $existing;
        }

        if (! $akunBank->is_aktif || $akunBank->kategori !== 'aset' || $akunBank->posisi_saldo !== 'debit') {
            throw new RuntimeException('Akun Bank payroll harus aktif, kategori Aset, dan saldo normal Debit.');
        }

        return $this->record([
            'idempotency_key' => $idempotencyKey,
            'tanggal' => $tanggal,
            'nomor_bukti' => $nomorBukti,
            'keterangan' => 'Penerimaan payroll potong gaji ' . $nomorBukti,
            'referensi_tipe' => $referensiTipe,
            'referensi_id' => $referensiId,
            'created_by' => $userId ?? auth()->id(),
        ], [
            $this->akunResolver->line($akunBank, 'debit', $jumlah),
            $this->akunResolver->line(
                $this->akunResolver->posting('penjualan.piutang_potong_gaji'),
                'kredit',
                $jumlah
            ),
        ]);
    }

    public function recordSimpanan(Simpanan $simpanan, ?Akun $akunDompet = null, ?int $userId = null, ?string $idempotencyKey = null): JurnalUmum
    {
        if ($idempotencyKey) {
            $existing = JurnalUmum::query()
                ->where('idempotency_key', $idempotencyKey)
                ->first();

            if ($existing) {
                return $existing;
            }
        } else {
            $existing = JurnalUmum::query()
                ->where('referensi_tipe', Simpanan::class)
                ->where('referensi_id', $simpanan->id)
                ->first();

            if ($existing) {
                return $existing;
            }
        }

        $jumlah = $this->rupiahDecimal($simpanan->jumlah ?? 0);
        $tanggal = (string) ($simpanan->tanggal ?? now()->toDateString());
        $simpanan->loadMissing(['jenisSimpanan.akun', 'mutasiKas.dompet.akun']);
        $akunSimpanan = $simpanan->jenisSimpanan?->akun;
        $akunDompet ??= $simpanan->mutasiKas?->dompet?->akun;

        if (! $akunSimpanan) {
            throw new RuntimeException(
                'Jenis simpanan belum memiliki pemetaan ke master COA.'
            );
        }

        if (! $akunSimpanan->is_aktif || ! in_array($akunSimpanan->kategori, ['kewajiban', 'ekuitas'], true)) {
            throw new RuntimeException('Akun jenis simpanan harus aktif dan berkategori kewajiban atau ekuitas.');
        }

        if ($akunSimpanan->posisi_saldo !== 'kredit') {
            throw new RuntimeException('Akun jenis simpanan harus memiliki saldo normal Kredit.');
        }

        if (! $akunDompet) {
            throw new RuntimeException('Dompet Simpanan belum mempunyai pemetaan COA untuk jurnal debit.');
        }

        if (! $akunDompet->is_aktif || $akunDompet->kategori !== 'aset' || $akunDompet->posisi_saldo !== 'debit') {
            throw new RuntimeException('Akun Dompet Simpanan harus aktif, kategori Aset, dan saldo normal Debit.');
        }

        return $this->record([
            'idempotency_key' => $idempotencyKey,
            'tanggal' => $tanggal,
            'nomor_bukti' => 'SIMP-' . $simpanan->id,
            'keterangan' => 'Setoran simpanan',
            'referensi_tipe' => Simpanan::class,
            'referensi_id' => $simpanan->id,
            'created_by' => $userId ?? auth()->id(),
        ], [
            $this->akunResolver->line(
                $akunDompet,
                'debit',
                $jumlah
            ),
            $this->akunResolver->line(
                $akunSimpanan,
                'kredit',
                $jumlah
            ),
        ]);
    }

    public function recordSimpananManasukaPenarikan(Simpanan $simpanan, Akun $akunDompet, ?int $userId = null, ?string $idempotencyKey = null): JurnalUmum
    {
        $idempotencyKey ??= 'simpanan-manasuka:penarikan:jurnal:' . $simpanan->id;

        $existing = JurnalUmum::query()
            ->where('idempotency_key', $idempotencyKey)
            ->first();

        if ($existing) {
            return $existing;
        }

        $simpanan->loadMissing('jenisSimpanan.akun');
        $akunSimpanan = $simpanan->jenisSimpanan?->akun;

        if (! $akunSimpanan || ! $akunSimpanan->is_aktif || ! in_array($akunSimpanan->kategori, ['kewajiban', 'ekuitas'], true) || $akunSimpanan->posisi_saldo !== 'kredit') {
            throw new RuntimeException('Akun Simpanan Manasuka harus aktif, kategori kewajiban/ekuitas, dan saldo normal Kredit.');
        }

        if (! $akunDompet->is_aktif || $akunDompet->kategori !== 'aset' || $akunDompet->posisi_saldo !== 'debit') {
            throw new RuntimeException('Akun Dompet penarikan Simpanan Manasuka harus aktif, kategori Aset, dan saldo normal Debit.');
        }

        $jumlah = $this->rupiahDecimal($simpanan->jumlah ?? 0);
        $tanggal = (string) ($simpanan->tanggal ?? now()->toDateString());

        return $this->record([
            'idempotency_key' => $idempotencyKey,
            'tanggal' => $tanggal,
            'nomor_bukti' => $simpanan->kode_transaksi ?: 'SMN-' . $simpanan->id,
            'keterangan' => 'Penarikan Simpanan Manasuka',
            'referensi_tipe' => Simpanan::class,
            'referensi_id' => $simpanan->id,
            'created_by' => $userId ?? auth()->id(),
        ], [
            $this->akunResolver->line($akunSimpanan, 'debit', $jumlah),
            $this->akunResolver->line($akunDompet, 'kredit', $jumlah),
        ]);
    }

    public function recordSimpananManasukaCorrection(ReversalTransaksi $reversal, Simpanan $simpanan, Akun $akunDompet, ?int $userId = null): JurnalUmum
    {
        $existing = JurnalUmum::query()
            ->where('idempotency_key', 'reversal:jurnal:' . $reversal->id)
            ->first();

        if ($existing) {
            return $existing;
        }

        $simpanan->loadMissing('jenisSimpanan.akun');
        $akunSimpanan = $simpanan->jenisSimpanan?->akun;

        if (! $akunSimpanan || ! $akunSimpanan->is_aktif || ! in_array($akunSimpanan->kategori, ['kewajiban', 'ekuitas'], true) || $akunSimpanan->posisi_saldo !== 'kredit') {
            throw new RuntimeException('Akun Simpanan Manasuka tidak valid untuk koreksi.');
        }

        if (! $akunDompet->is_aktif || $akunDompet->kategori !== 'aset' || $akunDompet->posisi_saldo !== 'debit') {
            throw new RuntimeException('Akun Dompet koreksi Simpanan Manasuka harus aktif, kategori Aset, dan saldo normal Debit.');
        }

        $jumlah = (float) $reversal->nominal;

        $lines = $simpanan->jenis_transaksi === Simpanan::JENIS_SETORAN
            ? [
                $this->akunResolver->line($akunSimpanan, 'debit', $jumlah),
                $this->akunResolver->line($akunDompet, 'kredit', $jumlah),
            ]
            : [
                $this->akunResolver->line($akunDompet, 'debit', $jumlah),
                $this->akunResolver->line($akunSimpanan, 'kredit', $jumlah),
            ];

        return $this->record([
            'idempotency_key' => 'reversal:jurnal:' . $reversal->id,
            'tanggal' => now(config('app.timezone', 'Asia/Jakarta'))->toDateString(),
            'nomor_bukti' => $reversal->kode_reversal,
            'keterangan' => 'Koreksi Transaksi Simpanan Manasuka ' . ($simpanan->kode_transaksi ?: ('#' . $simpanan->id)),
            'referensi_tipe' => ReversalTransaksi::class,
            'referensi_id' => $reversal->id,
            'created_by' => $userId ?? auth()->id(),
        ], $lines);
    }

    public function recordPinjaman(Pinjaman $pinjaman): void
    {
        if (JurnalUmum::query()
            ->where('referensi_tipe', Pinjaman::class)
            ->where('referensi_id', $pinjaman->id)
            ->exists()) {
            return;
        }

        $jumlah = (float) ($pinjaman->jumlah_pinjaman ?? 0);
        $tanggal = (string) ($pinjaman->tanggal_pinjaman ?? now()->toDateString());

        $this->record([
            'tanggal' => $tanggal,
            'nomor_bukti' => 'PJM-' . $pinjaman->id,
            'keterangan' => 'Pencairan pinjaman',
            'referensi_tipe' => Pinjaman::class,
            'referensi_id' => $pinjaman->id,
            'created_by' => auth()->id(),
        ], [
            $this->akunResolver->line(
                $this->akunResolver->posting('pinjaman.piutang'),
                'debit',
                $jumlah
            ),
            $this->akunResolver->line(
                $this->akunResolver->posting('pinjaman.kas'),
                'kredit',
                $jumlah
            ),
        ]);
    }

    public function recordPencairanPinjaman(Pinjaman $pinjaman, Akun $akunDompet, ?int $userId = null): JurnalUmum
    {
        $jumlah = (float) ($pinjaman->jumlah_pinjaman ?? 0);
        $biayaAdmin = (float) ($pinjaman->biaya_admin ?? 0);
        $tanggal = (string) ($pinjaman->tanggal_pinjaman ?? now()->toDateString());

        if (! $akunDompet->is_aktif || $akunDompet->kategori !== 'aset' || $akunDompet->posisi_saldo !== 'debit') {
            throw new RuntimeException('Akun Dompet pencairan harus aktif, kategori Aset, dan saldo normal Debit.');
        }

        $lines = [
            $this->akunResolver->line(
                $this->akunResolver->posting('pinjaman.piutang'),
                'debit',
                $jumlah
            ),
        ];

        if ($pinjaman->cara_bayar_admin === 'potong_pinjaman' && $biayaAdmin > 0) {
            $lines[] = $this->akunResolver->line($akunDompet, 'kredit', $jumlah - $biayaAdmin);
            $lines[] = $this->akunResolver->line($this->akunResolver->posting('pinjaman.pendapatan_admin'), 'kredit', $biayaAdmin);
        } elseif ($pinjaman->cara_bayar_admin === 'tunai' && $biayaAdmin > 0) {
            // Pencairan utuh
            $lines[] = $this->akunResolver->line($akunDompet, 'kredit', $jumlah);
            // Tambahan Jurnal untuk penerimaan kas admin
            $lines[] = $this->akunResolver->line($akunDompet, 'debit', $biayaAdmin);
            $lines[] = $this->akunResolver->line($this->akunResolver->posting('pinjaman.pendapatan_admin'), 'kredit', $biayaAdmin);
        } else {
            $lines[] = $this->akunResolver->line($akunDompet, 'kredit', $jumlah);
        }

        return $this->record([
            'idempotency_key' => 'pinjaman:pencairan:jurnal:' . $pinjaman->id,
            'tanggal' => $tanggal,
            'nomor_bukti' => $pinjaman->kode_pinjaman,
            'keterangan' => 'Pencairan pinjaman ' . $pinjaman->kode_pinjaman,
            'referensi_tipe' => Pinjaman::class,
            'referensi_id' => $pinjaman->id,
            'created_by' => $userId ?? auth()->id(),
        ], $lines);
    }

    public function recordPembayaranDimukaSewaMobil(
        SewaMobil $sewaMobil,
        PembayaranSewaMobil $pembayaran,
        Akun $akunDompet,
        ?int $userId = null
    ): JurnalUmum {
        $existing = JurnalUmum::query()
            ->where('idempotency_key', 'sewa-mobil:pembayaran-dimuka:jurnal:' . $pembayaran->id)
            ->first();

        if ($existing) {
            return $existing;
        }

        if (! $akunDompet->is_aktif || $akunDompet->kategori !== 'aset' || $akunDompet->posisi_saldo !== 'debit') {
            throw new RuntimeException('Akun Dompet pembayaran sewa harus aktif, kategori Aset, dan saldo normal Debit.');
        }

        $totalTagihan = (float) ($sewaMobil->total_tagihan_perusahaan ?? $pembayaran->jumlah_diterima ?? $pembayaran->jumlah_bayar ?? 0);
        $hargaVendor = (float) ($sewaMobil->total_harga_vendor ?? $pembayaran->jumlah_bayar_vendor ?? 0);
        $margin = (float) ($sewaMobil->total_markup ?? max(0, $totalTagihan - $hargaVendor));

        return $this->record([
            'idempotency_key' => 'sewa-mobil:pembayaran-dimuka:jurnal:' . $pembayaran->id,
            'tanggal' => optional($pembayaran->received_at ?? $pembayaran->paid_at)->toDateString() ?? now()->toDateString(),
            'nomor_bukti' => $sewaMobil->kode_sewa,
            'keterangan' => 'Pembayaran dimuka sewa mobil ' . $sewaMobil->kode_sewa,
            'referensi_tipe' => PembayaranSewaMobil::class,
            'referensi_id' => $pembayaran->id,
            'created_by' => $userId ?? auth()->id(),
        ], [
            $this->akunResolver->line($akunDompet, 'debit', $totalTagihan),
            $this->akunResolver->line(
                $this->akunResolver->posting('sewa_mobil.utang_vendor'),
                'kredit',
                $hargaVendor
            ),
            $this->akunResolver->line(
                $this->akunResolver->posting('sewa_mobil.pendapatan_diterima_dimuka_margin'),
                'kredit',
                $margin
            ),
        ]);
    }

    public function recordPembayaranVendorSewaMobil(
        SewaMobil $sewaMobil,
        PembayaranSewaMobil $pembayaran,
        Akun $akunDompetVendor,
        ?int $userId = null
    ): JurnalUmum {
        $existing = JurnalUmum::query()
            ->where('idempotency_key', 'sewa-mobil:pembayaran-vendor:jurnal:' . $pembayaran->id)
            ->first();

        if ($existing) {
            return $existing;
        }

        if (! $akunDompetVendor->is_aktif || $akunDompetVendor->kategori !== 'aset' || $akunDompetVendor->posisi_saldo !== 'debit') {
            throw new RuntimeException('Akun Dompet pembayaran vendor sewa mobil harus aktif, kategori Aset, dan saldo normal Debit.');
        }

        $jumlahVendor = (float) ($sewaMobil->total_harga_vendor ?? $pembayaran->jumlah_bayar_vendor ?? 0);

        return $this->record([
            'idempotency_key' => 'sewa-mobil:pembayaran-vendor:jurnal:' . $pembayaran->id,
            'tanggal' => optional($pembayaran->vendor_paid_at ?? $pembayaran->paid_at)->toDateString() ?? now()->toDateString(),
            'nomor_bukti' => $sewaMobil->kode_sewa,
            'keterangan' => 'Pembayaran vendor sewa mobil ' . $sewaMobil->kode_sewa,
            'referensi_tipe' => PembayaranSewaMobil::class,
            'referensi_id' => $pembayaran->id,
            'created_by' => $userId ?? auth()->id(),
        ], [
            $this->akunResolver->line(
                $this->akunResolver->posting('sewa_mobil.utang_vendor'),
                'debit',
                $jumlahVendor
            ),
            $this->akunResolver->line($akunDompetVendor, 'kredit', $jumlahVendor),
        ]);
    }

    public function recordPengakuanPendapatanSewaMobil(SewaMobil $sewaMobil, ?int $userId = null): JurnalUmum
    {
        $existing = JurnalUmum::query()
            ->where('idempotency_key', 'sewa-mobil:pengakuan-pendapatan:jurnal:' . $sewaMobil->id)
            ->first();

        if ($existing) {
            return $existing;
        }

        $jumlah = (float) ($sewaMobil->total_markup ?? $sewaMobil->total_sewa ?? 0);

        return $this->record([
            'idempotency_key' => 'sewa-mobil:pengakuan-pendapatan:jurnal:' . $sewaMobil->id,
            'tanggal' => optional($sewaMobil->completed_at)->toDateString() ?? now()->toDateString(),
            'nomor_bukti' => $sewaMobil->kode_sewa,
            'keterangan' => 'Pengakuan pendapatan sewa mobil ' . $sewaMobil->kode_sewa,
            'referensi_tipe' => SewaMobil::class,
            'referensi_id' => $sewaMobil->id,
            'created_by' => $userId ?? auth()->id(),
        ], [
            $this->akunResolver->line(
                $this->akunResolver->posting('sewa_mobil.pendapatan_diterima_dimuka_margin'),
                'debit',
                $jumlah
            ),
            $this->akunResolver->line(
                $this->akunResolver->posting('sewa_mobil.pendapatan_margin'),
                'kredit',
                $jumlah
            ),
        ]);
    }

    public function recordRefundSewaMobil(
        SewaMobil $sewaMobil,
        PembayaranSewaMobil $pembayaran,
        Akun $akunDompet,
        ?int $userId = null
    ): JurnalUmum {
        $existing = JurnalUmum::query()
            ->where('idempotency_key', 'sewa-mobil:refund:jurnal:' . $pembayaran->id)
            ->first();

        if ($existing) {
            return $existing;
        }

        if (! $akunDompet->is_aktif || $akunDompet->kategori !== 'aset' || $akunDompet->posisi_saldo !== 'debit') {
            throw new RuntimeException('Akun Dompet refund sewa harus aktif, kategori Aset, dan saldo normal Debit.');
        }

        $jumlah = (float) $pembayaran->jumlah_bayar;

        return $this->record([
            'idempotency_key' => 'sewa-mobil:refund:jurnal:' . $pembayaran->id,
            'tanggal' => optional($pembayaran->refunded_at)->toDateString() ?? now()->toDateString(),
            'nomor_bukti' => $sewaMobil->kode_sewa,
            'keterangan' => 'Refund penuh pembayaran sewa mobil ' . $sewaMobil->kode_sewa,
            'referensi_tipe' => PembayaranSewaMobil::class,
            'referensi_id' => $pembayaran->id,
            'created_by' => $userId ?? auth()->id(),
        ], [
            $this->akunResolver->line(
                $this->akunResolver->posting('sewa_mobil.pendapatan_diterima_dimuka'),
                'debit',
                $jumlah
            ),
            $this->akunResolver->line($akunDompet, 'kredit', $jumlah),
        ]);
    }

    public function recordRefundVendorSewaMobil(
        SewaMobil $sewaMobil,
        PembayaranSewaMobil $pembayaran,
        Akun $akunDompetVendor,
        ReversalTransaksi $reversal,
        ?int $userId = null
    ): JurnalUmum {
        $existing = JurnalUmum::query()
            ->where('idempotency_key', 'sewa-mobil:refund-vendor:jurnal:' . $pembayaran->id)
            ->first();

        if ($existing) {
            return $existing;
        }

        if (! $akunDompetVendor->is_aktif || $akunDompetVendor->kategori !== 'aset' || $akunDompetVendor->posisi_saldo !== 'debit') {
            throw new RuntimeException('Akun Dompet refund vendor sewa mobil harus aktif, kategori Aset, dan saldo normal Debit.');
        }

        $jumlahVendor = (float) ($pembayaran->jumlah_bayar_vendor ?? $sewaMobil->total_harga_vendor ?? 0);

        return $this->record([
            'idempotency_key' => 'sewa-mobil:refund-vendor:jurnal:' . $pembayaran->id,
            'tanggal' => optional($pembayaran->refunded_at)->toDateString() ?? now()->toDateString(),
            'nomor_bukti' => $reversal->kode_reversal,
            'keterangan' => 'Refund vendor atas sewa mobil ' . $sewaMobil->kode_sewa,
            'referensi_tipe' => ReversalTransaksi::class,
            'referensi_id' => $reversal->id,
            'created_by' => $userId ?? auth()->id(),
        ], [
            $this->akunResolver->line($akunDompetVendor, 'debit', $jumlahVendor),
            $this->akunResolver->line(
                $this->akunResolver->posting('sewa_mobil.utang_vendor'),
                'kredit',
                $jumlahVendor
            ),
        ]);
    }

    public function recordRefundPerusahaanSewaMobil(
        SewaMobil $sewaMobil,
        PembayaranSewaMobil $pembayaran,
        Akun $akunDompetPenerimaan,
        ReversalTransaksi $reversal,
        ?int $userId = null
    ): JurnalUmum {
        $existing = JurnalUmum::query()
            ->where('idempotency_key', 'sewa-mobil:refund-perusahaan:jurnal:' . $pembayaran->id)
            ->first();

        if ($existing) {
            return $existing;
        }

        if (! $akunDompetPenerimaan->is_aktif || $akunDompetPenerimaan->kategori !== 'aset' || $akunDompetPenerimaan->posisi_saldo !== 'debit') {
            throw new RuntimeException('Akun Dompet refund perusahaan sewa mobil harus aktif, kategori Aset, dan saldo normal Debit.');
        }

        $jumlahDiterima = (float) ($pembayaran->jumlah_diterima ?? $pembayaran->jumlah_bayar ?? $sewaMobil->total_tagihan_perusahaan ?? 0);
        $jumlahVendor = (float) ($pembayaran->jumlah_bayar_vendor ?? $sewaMobil->total_harga_vendor ?? 0);
        $margin = (float) ($sewaMobil->total_markup ?? max(0, $jumlahDiterima - $jumlahVendor));

        return $this->record([
            'idempotency_key' => 'sewa-mobil:refund-perusahaan:jurnal:' . $pembayaran->id,
            'tanggal' => optional($pembayaran->refunded_at)->toDateString() ?? now()->toDateString(),
            'nomor_bukti' => $reversal->kode_reversal,
            'keterangan' => 'Refund perusahaan atas sewa mobil ' . $sewaMobil->kode_sewa,
            'referensi_tipe' => ReversalTransaksi::class,
            'referensi_id' => $reversal->id,
            'created_by' => $userId ?? auth()->id(),
        ], [
            $this->akunResolver->line(
                $this->akunResolver->posting('sewa_mobil.utang_vendor'),
                'debit',
                $jumlahVendor
            ),
            $this->akunResolver->line(
                $this->akunResolver->posting('sewa_mobil.pendapatan_diterima_dimuka_margin'),
                'debit',
                $margin
            ),
            $this->akunResolver->line($akunDompetPenerimaan, 'kredit', $jumlahDiterima),
        ]);
    }

    public function recordPembayaranDimukaSewaHardware(
        SewaHardware $sewaHardware,
        PembayaranSewaHardware $pembayaran,
        Akun $akunDompet,
        ?int $userId = null
    ): JurnalUmum {
        $existing = JurnalUmum::query()
            ->where('idempotency_key', 'sewa-hardware:pembayaran-dimuka:jurnal:' . $pembayaran->id)
            ->first();

        if ($existing) {
            return $existing;
        }

        if (! $akunDompet->is_aktif || $akunDompet->kategori !== 'aset' || $akunDompet->posisi_saldo !== 'debit') {
            throw new RuntimeException('Akun Dompet pembayaran sewa hardware harus aktif, kategori Aset, dan saldo normal Debit.');
        }

        $totalTagihan = (float) $sewaHardware->total_tagihan_perusahaan;
        $hargaVendor = (float) $sewaHardware->total_harga_vendor;
        $margin = (float) $sewaHardware->total_margin;

        return $this->record([
            'idempotency_key' => 'sewa-hardware:pembayaran-dimuka:jurnal:' . $pembayaran->id,
            'tanggal' => optional($pembayaran->paid_at)->toDateString() ?? now()->toDateString(),
            'nomor_bukti' => $sewaHardware->kode_sewa,
            'keterangan' => 'Pembayaran dimuka sewa hardware ' . $sewaHardware->kode_sewa,
            'referensi_tipe' => PembayaranSewaHardware::class,
            'referensi_id' => $pembayaran->id,
            'created_by' => $userId ?? auth()->id(),
        ], [
            $this->akunResolver->line($akunDompet, 'debit', $totalTagihan),
            $this->akunResolver->line(
                $this->akunResolver->posting('sewa_hardware.utang_vendor'),
                'kredit',
                $hargaVendor
            ),
            $this->akunResolver->line(
                $this->akunResolver->posting('sewa_hardware.pendapatan_diterima_dimuka_margin'),
                'kredit',
                $margin
            ),
        ]);
    }

    public function recordPembayaranVendorSewaHardware(
        SewaHardware $sewaHardware,
        PembayaranSewaHardware $pembayaran,
        Akun $akunDompetVendor,
        ?int $userId = null
    ): JurnalUmum {
        $existing = JurnalUmum::query()
            ->where('idempotency_key', 'sewa-hardware:pembayaran-vendor:jurnal:' . $pembayaran->id)
            ->first();

        if ($existing) {
            return $existing;
        }

        if (! $akunDompetVendor->is_aktif || $akunDompetVendor->kategori !== 'aset' || $akunDompetVendor->posisi_saldo !== 'debit') {
            throw new RuntimeException('Akun Dompet pembayaran vendor hardware harus aktif, kategori Aset, dan saldo normal Debit.');
        }

        $jumlahVendor = (float) $sewaHardware->total_harga_vendor;

        return $this->record([
            'idempotency_key' => 'sewa-hardware:pembayaran-vendor:jurnal:' . $pembayaran->id,
            'tanggal' => optional($pembayaran->paid_at)->toDateString() ?? now()->toDateString(),
            'nomor_bukti' => $sewaHardware->kode_sewa,
            'keterangan' => 'Pembayaran vendor sewa hardware ' . $sewaHardware->kode_sewa,
            'referensi_tipe' => PembayaranSewaHardware::class,
            'referensi_id' => $pembayaran->id,
            'created_by' => $userId ?? auth()->id(),
        ], [
            $this->akunResolver->line(
                $this->akunResolver->posting('sewa_hardware.utang_vendor'),
                'debit',
                $jumlahVendor
            ),
            $this->akunResolver->line($akunDompetVendor, 'kredit', $jumlahVendor),
        ]);
    }

    public function recordPengakuanPendapatanSewaHardware(SewaHardware $sewaHardware, ?int $userId = null): JurnalUmum
    {
        $existing = JurnalUmum::query()
            ->where('idempotency_key', 'sewa-hardware:pengakuan-pendapatan:jurnal:' . $sewaHardware->id)
            ->first();

        if ($existing) {
            return $existing;
        }

        $margin = (float) $sewaHardware->total_margin;

        return $this->record([
            'idempotency_key' => 'sewa-hardware:pengakuan-pendapatan:jurnal:' . $sewaHardware->id,
            'tanggal' => optional($sewaHardware->completed_at)->toDateString() ?? now()->toDateString(),
            'nomor_bukti' => $sewaHardware->kode_sewa,
            'keterangan' => 'Pengakuan pendapatan sewa hardware ' . $sewaHardware->kode_sewa,
            'referensi_tipe' => SewaHardware::class,
            'referensi_id' => $sewaHardware->id,
            'created_by' => $userId ?? auth()->id(),
        ], [
            $this->akunResolver->line(
                $this->akunResolver->posting('sewa_hardware.pendapatan_diterima_dimuka_margin'),
                'debit',
                $margin
            ),
            $this->akunResolver->line(
                $this->akunResolver->posting('sewa_hardware.pendapatan_margin'),
                'kredit',
                $margin
            ),
        ]);
    }

    public function recordRefundVendorSewaHardware(
        SewaHardware $sewaHardware,
        PembayaranSewaHardware $pembayaran,
        Akun $akunDompetVendor,
        ReversalTransaksi $reversal,
        ?int $userId = null
    ): JurnalUmum {
        $existing = JurnalUmum::query()
            ->where('idempotency_key', 'sewa-hardware:refund-vendor:jurnal:' . $pembayaran->id)
            ->first();

        if ($existing) {
            return $existing;
        }

        if (! $akunDompetVendor->is_aktif || $akunDompetVendor->kategori !== 'aset' || $akunDompetVendor->posisi_saldo !== 'debit') {
            throw new RuntimeException('Akun Dompet refund vendor hardware harus aktif, kategori Aset, dan saldo normal Debit.');
        }

        $jumlahVendor = (int) $pembayaran->jumlah_bayar_vendor;

        return $this->record([
            'idempotency_key' => 'sewa-hardware:refund-vendor:jurnal:' . $pembayaran->id,
            'tanggal' => optional($pembayaran->refunded_at)->toDateString() ?? now()->toDateString(),
            'nomor_bukti' => $reversal->kode_reversal,
            'keterangan' => 'Refund vendor atas sewa hardware ' . $sewaHardware->kode_sewa,
            'referensi_tipe' => ReversalTransaksi::class,
            'referensi_id' => $reversal->id,
            'created_by' => $userId ?? auth()->id(),
        ], [
            $this->akunResolver->line($akunDompetVendor, 'debit', $jumlahVendor),
            $this->akunResolver->line(
                $this->akunResolver->posting('sewa_hardware.utang_vendor'),
                'kredit',
                $jumlahVendor
            ),
        ]);
    }

    public function recordRefundPerusahaanSewaHardware(
        SewaHardware $sewaHardware,
        PembayaranSewaHardware $pembayaran,
        Akun $akunDompetPenerimaan,
        ReversalTransaksi $reversal,
        ?int $userId = null
    ): JurnalUmum {
        $existing = JurnalUmum::query()
            ->where('idempotency_key', 'sewa-hardware:refund-perusahaan:jurnal:' . $pembayaran->id)
            ->first();

        if ($existing) {
            return $existing;
        }

        if (! $akunDompetPenerimaan->is_aktif || $akunDompetPenerimaan->kategori !== 'aset' || $akunDompetPenerimaan->posisi_saldo !== 'debit') {
            throw new RuntimeException('Akun Dompet refund perusahaan hardware harus aktif, kategori Aset, dan saldo normal Debit.');
        }

        $jumlahDiterima = (int) $pembayaran->jumlah_diterima;
        $jumlahVendor = (int) $pembayaran->jumlah_bayar_vendor;
        $margin = (int) $sewaHardware->total_margin;

        return $this->record([
            'idempotency_key' => 'sewa-hardware:refund-perusahaan:jurnal:' . $pembayaran->id,
            'tanggal' => optional($pembayaran->refunded_at)->toDateString() ?? now()->toDateString(),
            'nomor_bukti' => $reversal->kode_reversal,
            'keterangan' => 'Refund perusahaan atas sewa hardware ' . $sewaHardware->kode_sewa,
            'referensi_tipe' => ReversalTransaksi::class,
            'referensi_id' => $reversal->id,
            'created_by' => $userId ?? auth()->id(),
        ], [
            $this->akunResolver->line(
                $this->akunResolver->posting('sewa_hardware.utang_vendor'),
                'debit',
                $jumlahVendor
            ),
            $this->akunResolver->line(
                $this->akunResolver->posting('sewa_hardware.pendapatan_diterima_dimuka_margin'),
                'debit',
                $margin
            ),
            $this->akunResolver->line($akunDompetPenerimaan, 'kredit', $jumlahDiterima),
        ]);
    }

    public function recordBebanOperasionalPosting(BebanOperasional $beban, Akun $akunDompet, ?int $userId = null): JurnalUmum
    {
        $existing = JurnalUmum::query()
            ->where('idempotency_key', 'beban-operasional:posting:jurnal:' . $beban->id)
            ->first();

        if ($existing) {
            return $existing;
        }

        if (! $akunDompet->is_aktif || $akunDompet->kategori !== 'aset' || $akunDompet->posisi_saldo !== 'debit') {
            throw new RuntimeException('Akun Dompet Beban Operasional harus aktif, kategori Aset, dan saldo normal Debit.');
        }

        $beban->loadMissing('details.akun');
        $lines = [];

        foreach ($beban->details as $detail) {
            $akun = $detail->akun;

            if (! $akun || ! $akun->is_aktif || $akun->kategori !== 'beban' || $akun->posisi_saldo !== 'debit') {
                throw new RuntimeException('Akun detail Beban Operasional harus aktif, kategori Beban, dan saldo normal Debit.');
            }

            $lines[] = $this->akunResolver->line($akun, 'debit', $detail->nominal);
        }

        $lines[] = $this->akunResolver->line($akunDompet, 'kredit', $beban->total_beban);

        return $this->record([
            'idempotency_key' => 'beban-operasional:posting:jurnal:' . $beban->id,
            'tanggal' => $beban->tanggal_beban->toDateString(),
            'nomor_bukti' => $beban->kode_beban,
            'keterangan' => 'Posting Beban Operasional ' . $beban->kode_beban,
            'referensi_tipe' => BebanOperasional::class,
            'referensi_id' => $beban->id,
            'created_by' => $userId ?? auth()->id(),
        ], $lines);
    }

    public function recordBebanOperasionalReversal(
        BebanOperasional $beban,
        ReversalTransaksi $reversal,
        Akun $akunDompet,
        ?int $userId = null
    ): JurnalUmum {
        $existing = JurnalUmum::query()
            ->where('idempotency_key', 'beban-operasional:reversal:jurnal:' . $reversal->id)
            ->first();

        if ($existing) {
            return $existing;
        }

        if (! $akunDompet->is_aktif || $akunDompet->kategori !== 'aset' || $akunDompet->posisi_saldo !== 'debit') {
            throw new RuntimeException('Akun Dompet reversal Beban Operasional harus aktif, kategori Aset, dan saldo normal Debit.');
        }

        $beban->loadMissing('details.akun');
        $lines = [
            $this->akunResolver->line($akunDompet, 'debit', $beban->total_beban),
        ];

        foreach ($beban->details as $detail) {
            $akun = $detail->akun;

            if (! $akun || $akun->kategori !== 'beban' || $akun->posisi_saldo !== 'debit') {
                throw new RuntimeException('Akun detail Beban Operasional tidak valid untuk reversal.');
            }

            $lines[] = $this->akunResolver->line($akun, 'kredit', $detail->nominal);
        }

        return $this->record([
            'idempotency_key' => 'beban-operasional:reversal:jurnal:' . $reversal->id,
            'tanggal' => optional($reversal->processed_at)->toDateString() ?? now()->toDateString(),
            'nomor_bukti' => $reversal->kode_reversal,
            'keterangan' => 'Reversal penuh Beban Operasional ' . $beban->kode_beban,
            'referensi_tipe' => ReversalTransaksi::class,
            'referensi_id' => $reversal->id,
            'created_by' => $userId ?? auth()->id(),
        ], $lines);
    }

    public function recordCicilan(CicilanPinjaman $cicilan): void
    {
        if (JurnalUmum::query()
            ->where('referensi_tipe', CicilanPinjaman::class)
            ->where('referensi_id', $cicilan->id)
            ->exists()) {
            return;
        }

        $jumlah = (float) ($cicilan->jumlah_cicilan ?? 0);
        $tanggal = (string) ($cicilan->tanggal_bayar ?? optional($cicilan->created_at)->toDateString() ?? now()->toDateString());

        $this->record([
            'tanggal' => $tanggal,
            'nomor_bukti' => 'CIC-' . $cicilan->id,
            'keterangan' => 'Pembayaran cicilan pinjaman',
            'referensi_tipe' => CicilanPinjaman::class,
            'referensi_id' => $cicilan->id,
            'created_by' => auth()->id(),
        ], [
            $this->akunResolver->line(
                $this->akunResolver->posting('pinjaman.kas'),
                'debit',
                $jumlah
            ),
            $this->akunResolver->line(
                $this->akunResolver->posting('pinjaman.piutang'),
                'kredit',
                $jumlah
            ),
        ]);
    }

    public function recordPembayaranCicilan(CicilanPinjaman $cicilan, Akun $akunDompet, ?int $userId = null): JurnalUmum
    {
        $jumlah = (float) ($cicilan->jumlah_cicilan ?? 0);
        $tanggal = (string) ($cicilan->tanggal_bayar ?? now()->toDateString());
        $cicilan->loadMissing('pinjaman', 'jadwal');
        $pinjaman = $cicilan->pinjaman;

        if (! $akunDompet->is_aktif || $akunDompet->kategori !== 'aset' || $akunDompet->posisi_saldo !== 'debit') {
            throw new RuntimeException('Akun Dompet penerimaan cicilan harus aktif, kategori Aset, dan saldo normal Debit.');
        }

        $existing = JurnalUmum::query()
            ->where('idempotency_key', 'cicilan:pembayaran:jurnal:' . $cicilan->id)
            ->first();

        if ($existing) {
            return $existing;
        }

        return $this->record([
            'idempotency_key' => 'cicilan:pembayaran:jurnal:' . $cicilan->id,
            'tanggal' => $tanggal,
            'nomor_bukti' => 'CIC-' . $cicilan->id,
            'keterangan' => 'Pembayaran cicilan ' . ($pinjaman?->kode_pinjaman ?? ('Pinjaman #' . $cicilan->pinjaman_id)) . ' periode ' . $cicilan->periode,
            'referensi_tipe' => CicilanPinjaman::class,
            'referensi_id' => $cicilan->id,
            'created_by' => $userId ?? auth()->id(),
        ], [
            $this->akunResolver->line(
                $akunDompet,
                'debit',
                $jumlah
            ),
            $this->akunResolver->line(
                $this->akunResolver->posting('pinjaman.piutang'),
                'kredit',
                $jumlah
            ),
        ]);
    }

    public function recordPenerimaanPayrollPotongGajiNet(
        string $idempotencyKey,
        string $nomorBukti,
        string $tanggal,
        float|int $gross,
        float|int $creditApplied,
        Akun $akunBank,
        string $referensiTipe,
        int $referensiId,
        ?int $userId = null
    ): JurnalUmum {
        $existing = JurnalUmum::query()->where('idempotency_key', $idempotencyKey)->first();

        if ($existing) {
            return $existing;
        }

        if (! $akunBank->is_aktif || $akunBank->kategori !== 'aset' || $akunBank->posisi_saldo !== 'debit') {
            throw new RuntimeException('Akun Bank payroll harus aktif, kategori Aset, dan saldo normal Debit.');
        }

        $gross = round((float) $gross, 2);
        $creditApplied = round((float) $creditApplied, 2);
        $net = round($gross - $creditApplied, 2);

        if ($gross <= 0 || $creditApplied < 0 || $net < 0) {
            throw new RuntimeException('Nominal payroll net tidak valid.');
        }

        $lines = [];

        if ($net > 0) {
            $lines[] = $this->akunResolver->line($akunBank, 'debit', $net);
        }

        if ($creditApplied > 0) {
            $lines[] = $this->akunResolver->line(
                $this->akunResolver->posting('refund.utang_anggota'),
                'debit',
                $creditApplied
            );
        }

        $lines[] = $this->akunResolver->line(
            $this->akunResolver->posting('refund.piutang_potong_gaji'),
            'kredit',
            $gross
        );

        return $this->record([
            'idempotency_key' => $idempotencyKey,
            'tanggal' => $tanggal,
            'nomor_bukti' => $nomorBukti,
            'keterangan' => 'Penerimaan payroll potong gaji net ' . $nomorBukti,
            'referensi_tipe' => $referensiTipe,
            'referensi_id' => $referensiId,
            'created_by' => $userId ?? auth()->id(),
        ], $lines);
    }

    public function recordPembayaranCicilanPayrollNet(CicilanPinjaman $cicilan, Akun $akunBank, float|int $creditApplied, ?int $userId = null): JurnalUmum
    {
        $existing = JurnalUmum::query()
            ->where('idempotency_key', 'cicilan:pembayaran:jurnal:' . $cicilan->id)
            ->first();

        if ($existing) {
            return $existing;
        }

        if (! $akunBank->is_aktif || $akunBank->kategori !== 'aset' || $akunBank->posisi_saldo !== 'debit') {
            throw new RuntimeException('Akun Bank payroll harus aktif, kategori Aset, dan saldo normal Debit.');
        }

        $gross = round((float) ($cicilan->jumlah_cicilan ?? 0), 2);
        $creditApplied = round((float) $creditApplied, 2);
        $net = round($gross - $creditApplied, 2);

        if ($gross <= 0 || $creditApplied < 0 || $net < 0) {
            throw new RuntimeException('Nominal pembayaran cicilan payroll net tidak valid.');
        }

        $lines = [];

        if ($net > 0) {
            $lines[] = $this->akunResolver->line($akunBank, 'debit', $net);
        }

        if ($creditApplied > 0) {
            $lines[] = $this->akunResolver->line(
                $this->akunResolver->posting('refund.utang_anggota'),
                'debit',
                $creditApplied
            );
        }

        $lines[] = $this->akunResolver->line(
            $this->akunResolver->posting('refund.piutang_pinjaman'),
            'kredit',
            $gross
        );

        $tanggal = (string) ($cicilan->tanggal_bayar ?? now()->toDateString());
        $cicilan->loadMissing('pinjaman');

        return $this->record([
            'idempotency_key' => 'cicilan:pembayaran:jurnal:' . $cicilan->id,
            'tanggal' => $tanggal,
            'nomor_bukti' => 'CIC-' . $cicilan->id,
            'keterangan' => 'Pembayaran cicilan payroll net ' . ($cicilan->pinjaman?->kode_pinjaman ?? ('Pinjaman #' . $cicilan->pinjaman_id)) . ' periode ' . $cicilan->periode,
            'referensi_tipe' => CicilanPinjaman::class,
            'referensi_id' => $cicilan->id,
            'created_by' => $userId ?? auth()->id(),
        ], $lines);
    }

    public function recordPosReversal(ReversalTransaksi $reversal, Penjualan $penjualan, string $creditTarget, ?Akun $akunDompet = null, ?int $userId = null): JurnalUmum
    {
        $existing = JurnalUmum::query()
            ->where('idempotency_key', 'reversal:jurnal:' . $reversal->id)
            ->first();

        if ($existing) {
            return $existing;
        }

        $grandTotal = round((float) ($penjualan->grand_total ?? 0), 2);
        if ($grandTotal <= 0) {
            throw new RuntimeException('Nominal reversal POS harus lebih besar dari nol.');
        }

        $creditAkun = match ($creditTarget) {
            'piutang_potong_gaji' => $this->akunResolver->posting('refund.piutang_potong_gaji'),
            'utang_refund_anggota' => $this->akunResolver->posting('refund.utang_anggota'),
            'dompet' => $akunDompet,
            default => throw new RuntimeException('Target kredit reversal POS tidak valid.'),
        };

        if (! $creditAkun || ! $creditAkun->is_aktif || $creditAkun->posisi_saldo !== 'debit' && $creditTarget === 'dompet') {
            throw new RuntimeException('Akun Dompet refund POS harus aktif.');
        }

        if ($creditTarget === 'dompet' && ($creditAkun->kategori !== 'aset' || $creditAkun->posisi_saldo !== 'debit')) {
            throw new RuntimeException('Akun Dompet refund POS harus kategori Aset dan saldo normal Debit.');
        }

        $lines = $this->posReversalDebitLines($penjualan);
        $lines[] = $this->akunResolver->line($creditAkun, 'kredit', $grandTotal);

        return $this->record([
            'idempotency_key' => 'reversal:jurnal:' . $reversal->id,
            'tanggal' => now()->toDateString(),
            'nomor_bukti' => $reversal->kode_reversal,
            'keterangan' => 'Reversal POS ' . $penjualan->kode_transaksi . ': ' . $reversal->alasan,
            'referensi_tipe' => ReversalTransaksi::class,
            'referensi_id' => $reversal->id,
            'created_by' => $userId ?? auth()->id(),
        ], $lines);
    }

    public function recordSimpananPokokReversal(Simpanan $simpanan, ReversalTransaksi $reversal, ?int $userId = null): JurnalUmum
    {
        $existing = JurnalUmum::query()
            ->where('idempotency_key', 'reversal:jurnal:' . $reversal->id)
            ->first();

        if ($existing) {
            return $existing;
        }

        $simpanan->loadMissing('jenisSimpanan.akun');
        $akunSimpanan = $simpanan->jenisSimpanan?->akun;

        if (! $akunSimpanan || ! $akunSimpanan->is_aktif || ! in_array($akunSimpanan->kategori, ['kewajiban', 'ekuitas'], true)) {
            throw new RuntimeException('Akun Simpanan Pokok tidak valid untuk reversal.');
        }

        $jumlah = (float) ($simpanan->nominal_snapshot ?? $simpanan->jumlah ?? 0);

        return $this->record([
            'idempotency_key' => 'reversal:jurnal:' . $reversal->id,
            'tanggal' => now()->toDateString(),
            'nomor_bukti' => $reversal->kode_reversal,
            'keterangan' => 'Reversal Simpanan Pokok #' . $simpanan->id,
            'referensi_tipe' => ReversalTransaksi::class,
            'referensi_id' => $reversal->id,
            'created_by' => $userId ?? auth()->id(),
        ], [
            $this->akunResolver->line($akunSimpanan, 'debit', $jumlah),
            $this->akunResolver->line(
                $this->akunResolver->posting('refund.piutang_potong_gaji'),
                'kredit',
                $jumlah
            ),
        ]);
    }

    public function recordSimpananWajibExitCancellation(Simpanan $simpanan, ReversalTransaksi $reversal, ?int $userId = null): JurnalUmum
    {
        $existing = JurnalUmum::query()
            ->where('idempotency_key', 'reversal:jurnal:' . $reversal->id)
            ->first();

        if ($existing) {
            return $existing;
        }

        $simpanan->loadMissing('jenisSimpanan.akun');
        $akunSimpanan = $simpanan->jenisSimpanan?->akun;

        if (! $akunSimpanan || ! $akunSimpanan->is_aktif || ! in_array($akunSimpanan->kategori, ['kewajiban', 'ekuitas'], true) || $akunSimpanan->posisi_saldo !== 'kredit') {
            throw new RuntimeException('Akun Simpanan Wajib tidak valid untuk pembatalan tagihan keluar.');
        }

        $jumlah = (float) ($simpanan->nominal_snapshot ?? $simpanan->jumlah ?? 0);

        return $this->record([
            'idempotency_key' => 'reversal:jurnal:' . $reversal->id,
            'tanggal' => now(config('app.timezone', 'Asia/Jakarta'))->toDateString(),
            'nomor_bukti' => $reversal->kode_reversal,
            'keterangan' => 'Pembatalan tagihan Simpanan Wajib karena Keanggotaan Berakhir #' . $simpanan->id,
            'referensi_tipe' => ReversalTransaksi::class,
            'referensi_id' => $reversal->id,
            'created_by' => $userId ?? auth()->id(),
        ], [
            $this->akunResolver->line($akunSimpanan, 'debit', $jumlah),
            $this->akunResolver->line(
                $this->akunResolver->posting('refund.piutang_potong_gaji'),
                'kredit',
                $jumlah
            ),
        ]);
    }

    public function recordSimpananWajibExitRecovery(Simpanan $simpanan, ?int $userId = null): JurnalUmum
    {
        $idempotencyKey = $simpanan->jadwal_simpanan_wajib_id
            ? 'keanggotaan:wajib-recovery:jurnal:' . $simpanan->jadwal_simpanan_wajib_id
            : 'keanggotaan:wajib-final-recovery:jurnal:' . $simpanan->id;
        $existing = JurnalUmum::query()
            ->where('idempotency_key', $idempotencyKey)
            ->first();

        if ($existing) {
            return $existing;
        }

        $simpanan->loadMissing('jenisSimpanan.akun', 'jadwalSimpananWajib');
        $akunSimpanan = $simpanan->jenisSimpanan?->akun;

        if (! $akunSimpanan || ! $akunSimpanan->is_aktif || ! in_array($akunSimpanan->kategori, ['kewajiban', 'ekuitas'], true) || $akunSimpanan->posisi_saldo !== 'kredit') {
            throw new RuntimeException('Akun Simpanan Wajib tidak valid untuk pemulihan tagihan keluar.');
        }

        $jadwal = $simpanan->jadwalSimpananWajib;
        $jumlah = (float) ($simpanan->nominal_snapshot ?? $simpanan->jumlah ?? 0);

        return $this->record([
            'idempotency_key' => $idempotencyKey,
            'tanggal' => now(config('app.timezone', 'Asia/Jakarta'))->toDateString(),
            'nomor_bukti' => $jadwal ? 'REC-' . $jadwal->kode_tagihan : 'REC-SWJ-' . $simpanan->id,
            'keterangan' => 'Pemulihan tagihan Simpanan Wajib karena penonaktifan dibatalkan #' . $simpanan->id,
            'referensi_tipe' => $jadwal ? \App\Models\JadwalSimpananWajib::class : Simpanan::class,
            'referensi_id' => $jadwal?->id ?? $simpanan->id,
            'created_by' => $userId ?? auth()->id(),
        ], [
            $this->akunResolver->line(
                $this->akunResolver->posting('refund.piutang_potong_gaji'),
                'debit',
                $jumlah
            ),
            $this->akunResolver->line($akunSimpanan, 'kredit', $jumlah),
        ]);
    }

    public function recordCicilanReversalToCredit(CicilanPinjaman $cicilan, ReversalTransaksi $reversal, ?int $userId = null): JurnalUmum
    {
        return $this->recordCicilanReversal($cicilan, $reversal, $this->akunResolver->posting('refund.utang_anggota'), $userId);
    }

    public function recordCicilanReversalCash(CicilanPinjaman $cicilan, ReversalTransaksi $reversal, Akun $akunDompet, ?int $userId = null): JurnalUmum
    {
        if (! $akunDompet->is_aktif || $akunDompet->kategori !== 'aset' || $akunDompet->posisi_saldo !== 'debit') {
            throw new RuntimeException('Akun Dompet reversal cicilan tunai harus kategori Aset aktif.');
        }

        return $this->recordCicilanReversal($cicilan, $reversal, $akunDompet, $userId);
    }

    public function recordOutstandingCashReceipt(PembayaranOutstandingCash $payment, Akun $akunDompet, ?int $userId = null): JurnalUmum
    {
        $existing = JurnalUmum::query()
            ->where('idempotency_key', 'outstanding-cash:jurnal:' . $payment->id)
            ->first();

        if ($existing) {
            return $existing;
        }

        if (! $akunDompet->is_aktif || $akunDompet->kategori !== 'aset' || $akunDompet->posisi_saldo !== 'debit') {
            throw new RuntimeException('Akun Dompet pembayaran outstanding harus kategori Aset aktif.');
        }

        $jumlah = (float) $payment->nominal;

        return $this->record([
            'idempotency_key' => 'outstanding-cash:jurnal:' . $payment->id,
            'tanggal' => $payment->paid_at?->toDateString() ?? now()->toDateString(),
            'nomor_bukti' => $payment->kode_pembayaran,
            'keterangan' => 'Penerimaan outstanding cash ' . $payment->kode_pembayaran,
            'referensi_tipe' => PembayaranOutstandingCash::class,
            'referensi_id' => $payment->id,
            'created_by' => $userId ?? auth()->id(),
        ], [
            $this->akunResolver->line($akunDompet, 'debit', $jumlah),
            $this->akunResolver->line(
                $this->akunResolver->posting('refund.piutang_potong_gaji'),
                'kredit',
                $jumlah
            ),
        ]);
    }

    public function recordPembayaranKonsinyasi(PembayaranKonsinyasi $payment): void
    {
        if (JurnalUmum::query()
            ->where('referensi_tipe', PembayaranKonsinyasi::class)
            ->where('referensi_id', $payment->id)
            ->exists()) {
            return;
        }

        $jumlah = (float) ($payment->total_bayar ?? 0);
        $tanggal = (string) ($payment->tanggal_bayar ?? now()->toDateString());

        $this->record([
            'tanggal' => $tanggal,
            'nomor_bukti' => $payment->kode_pembayaran,
            'keterangan' => 'Pembayaran konsinyasi ' . $payment->kode_pembayaran,
            'referensi_tipe' => PembayaranKonsinyasi::class,
            'referensi_id' => $payment->id,
            'created_by' => auth()->id(),
        ], [
            $this->akunResolver->line(
                $this->akunResolver->posting('konsinyasi.utang_reseller'),
                'debit',
                $jumlah
            ),
            $this->akunResolver->line(
                $this->akunResolver->posting('konsinyasi.kas'),
                'kredit',
                $jumlah
            ),
        ]);
    }

    public function recordPenyelesaianKeanggotaanOffset(
        PenyelesaianKeanggotaan $penyelesaian,
        float|int $simpananPokokUsed,
        float|int $kreditRefundUsed,
        float|int $pinjamanSettled,
        float|int $piutangAnggotaSettled,
        ?int $userId = null
    ): ?JurnalUmum {
        $idempotencyKey = 'keanggotaan:offset:jurnal:' . $penyelesaian->id;
        $existing = JurnalUmum::query()->where('idempotency_key', $idempotencyKey)->first();

        if ($existing) {
            return $existing;
        }

        $simpananPokokUsed = round((float) $simpananPokokUsed, 2);
        $kreditRefundUsed = round((float) $kreditRefundUsed, 2);
        $pinjamanSettled = round((float) $pinjamanSettled, 2);
        $piutangAnggotaSettled = round((float) $piutangAnggotaSettled, 2);

        if (($simpananPokokUsed + $kreditRefundUsed) <= 0 || ($pinjamanSettled + $piutangAnggotaSettled) <= 0) {
            return null;
        }

        $lines = [];

        if ($simpananPokokUsed > 0) {
            $lines[] = $this->akunResolver->line(
                $this->akunResolver->posting('keanggotaan.simpanan_pokok'),
                'debit',
                $simpananPokokUsed
            );
        }

        if ($kreditRefundUsed > 0) {
            $lines[] = $this->akunResolver->line(
                $this->akunResolver->posting('keanggotaan.utang_refund_anggota'),
                'debit',
                $kreditRefundUsed
            );
        }

        if ($pinjamanSettled > 0) {
            $lines[] = $this->akunResolver->line(
                $this->akunResolver->posting('keanggotaan.piutang_pinjaman'),
                'kredit',
                $pinjamanSettled
            );
        }

        if ($piutangAnggotaSettled > 0) {
            $lines[] = $this->akunResolver->line(
                $this->akunResolver->posting('keanggotaan.piutang_anggota'),
                'kredit',
                $piutangAnggotaSettled
            );
        }

        return $this->record([
            'idempotency_key' => $idempotencyKey,
            'tanggal' => now()->toDateString(),
            'nomor_bukti' => $penyelesaian->kode_penyelesaian,
            'keterangan' => 'Offset hak Anggota terhadap kewajiban keluar ' . $penyelesaian->kode_penyelesaian,
            'referensi_tipe' => PenyelesaianKeanggotaan::class,
            'referensi_id' => $penyelesaian->id,
            'created_by' => $userId ?? auth()->id(),
        ], $lines);
    }

    public function recordPenyelesaianKeanggotaanOffsetFromDetails(PenyelesaianKeanggotaan $penyelesaian, ?int $userId = null): ?JurnalUmum
    {
        $idempotencyKey = 'keanggotaan:offset:jurnal:' . $penyelesaian->id;
        $existing = JurnalUmum::query()->where('idempotency_key', $idempotencyKey)->first();

        if ($existing) {
            return $existing;
        }

        $hakDetails = PenyelesaianKeanggotaanDetail::query()
            ->with('akun')
            ->where('penyelesaian_keanggotaan_id', $penyelesaian->id)
            ->where('tipe_detail', PenyelesaianKeanggotaanDetail::TIPE_HAK)
            ->whereRaw('CAST(nominal_dipakai_offset AS DECIMAL(15,2)) > 0')
            ->orderBy('urutan_alokasi')
            ->get();

        $kewajibanDetails = PenyelesaianKeanggotaanDetail::query()
            ->where('penyelesaian_keanggotaan_id', $penyelesaian->id)
            ->where('tipe_detail', PenyelesaianKeanggotaanDetail::TIPE_KEWAJIBAN)
            ->whereRaw('CAST(nominal_offset AS DECIMAL(15,2)) > 0')
            ->orderBy('urutan_alokasi')
            ->get();

        if ($hakDetails->isEmpty() || $kewajibanDetails->isEmpty()) {
            return null;
        }

        $lines = [];

        foreach ($hakDetails as $detail) {
            $akun = $detail->akun;
            if (! $akun || ! $akun->is_aktif || ! in_array($akun->kategori, ['kewajiban', 'ekuitas'], true) || $akun->posisi_saldo !== 'kredit') {
                throw new RuntimeException('Akun hak Anggota pada detail penyelesaian tidak valid.');
            }

            $lines[] = $this->akunResolver->line($akun, 'debit', $detail->nominal_dipakai_offset);
        }

        $pinjamanSettled = $kewajibanDetails
            ->where('kategori_sumber', PenyelesaianKeanggotaanDetail::KATEGORI_PINJAMAN)
            ->sum(fn (PenyelesaianKeanggotaanDetail $detail): float => (float) $detail->nominal_offset);
        $piutangAnggotaSettled = $kewajibanDetails
            ->where('kategori_sumber', '!=', PenyelesaianKeanggotaanDetail::KATEGORI_PINJAMAN)
            ->sum(fn (PenyelesaianKeanggotaanDetail $detail): float => (float) $detail->nominal_offset);

        if ($pinjamanSettled > 0) {
            $lines[] = $this->akunResolver->line(
                $this->akunResolver->posting('keanggotaan.piutang_pinjaman'),
                'kredit',
                $pinjamanSettled
            );
        }

        if ($piutangAnggotaSettled > 0) {
            $lines[] = $this->akunResolver->line(
                $this->akunResolver->posting('keanggotaan.piutang_anggota'),
                'kredit',
                $piutangAnggotaSettled
            );
        }

        return $this->record([
            'idempotency_key' => $idempotencyKey,
            'tanggal' => now(config('app.timezone', 'Asia/Jakarta'))->toDateString(),
            'nomor_bukti' => $penyelesaian->kode_penyelesaian,
            'keterangan' => 'Offset hak Anggota terhadap kewajiban keluar ' . $penyelesaian->kode_penyelesaian,
            'referensi_tipe' => PenyelesaianKeanggotaan::class,
            'referensi_id' => $penyelesaian->id,
            'created_by' => $userId ?? auth()->id(),
        ], $lines);
    }

    public function recordPenyelesaianKeanggotaanRefund(
        PenyelesaianKeanggotaan $penyelesaian,
        Akun $akunDompet,
        float|int $simpananPokokRefund,
        float|int $kreditRefundRefund,
        ?int $userId = null
    ): ?JurnalUmum {
        $idempotencyKey = 'keanggotaan:refund:jurnal:' . $penyelesaian->id;
        $existing = JurnalUmum::query()->where('idempotency_key', $idempotencyKey)->first();

        if ($existing) {
            return $existing;
        }

        if (! $akunDompet->is_aktif || $akunDompet->kategori !== 'aset' || $akunDompet->posisi_saldo !== 'debit') {
            throw new RuntimeException('Akun Dompet refund penyelesaian keanggotaan harus aktif, kategori Aset, dan saldo normal Debit.');
        }

        $simpananPokokRefund = round((float) $simpananPokokRefund, 2);
        $kreditRefundRefund = round((float) $kreditRefundRefund, 2);
        $total = round($simpananPokokRefund + $kreditRefundRefund, 2);

        if ($total <= 0) {
            return null;
        }

        $lines = [];

        if ($simpananPokokRefund > 0) {
            $lines[] = $this->akunResolver->line(
                $this->akunResolver->posting('keanggotaan.simpanan_pokok'),
                'debit',
                $simpananPokokRefund
            );
        }

        if ($kreditRefundRefund > 0) {
            $lines[] = $this->akunResolver->line(
                $this->akunResolver->posting('keanggotaan.utang_refund_anggota'),
                'debit',
                $kreditRefundRefund
            );
        }

        $lines[] = $this->akunResolver->line($akunDompet, 'kredit', $total);

        return $this->record([
            'idempotency_key' => $idempotencyKey,
            'tanggal' => now()->toDateString(),
            'nomor_bukti' => $penyelesaian->kode_penyelesaian,
            'keterangan' => 'Refund hak Anggota keluar ' . $penyelesaian->kode_penyelesaian,
            'referensi_tipe' => PenyelesaianKeanggotaan::class,
            'referensi_id' => $penyelesaian->id,
            'created_by' => $userId ?? auth()->id(),
        ], $lines);
    }

    public function recordPenyelesaianKeanggotaanRefundFromDetails(PenyelesaianKeanggotaan $penyelesaian, Akun $akunDompet, ?int $userId = null): ?JurnalUmum
    {
        $idempotencyKey = 'keanggotaan:refund:jurnal:' . $penyelesaian->id;
        $existing = JurnalUmum::query()->where('idempotency_key', $idempotencyKey)->first();

        if ($existing) {
            return $existing;
        }

        if (! $akunDompet->is_aktif || $akunDompet->kategori !== 'aset' || $akunDompet->posisi_saldo !== 'debit') {
            throw new RuntimeException('Akun Dompet refund penyelesaian keanggotaan harus aktif, kategori Aset, dan saldo normal Debit.');
        }

        $hakDetails = PenyelesaianKeanggotaanDetail::query()
            ->with('akun')
            ->where('penyelesaian_keanggotaan_id', $penyelesaian->id)
            ->where('tipe_detail', PenyelesaianKeanggotaanDetail::TIPE_HAK)
            ->whereRaw('CAST(nominal_direfund AS DECIMAL(15,2)) > 0')
            ->orderBy('urutan_alokasi')
            ->get();

        if ($hakDetails->isEmpty()) {
            return null;
        }

        $lines = [];
        $total = 0.0;

        foreach ($hakDetails as $detail) {
            $akun = $detail->akun;
            if (! $akun || ! $akun->is_aktif || ! in_array($akun->kategori, ['kewajiban', 'ekuitas'], true) || $akun->posisi_saldo !== 'kredit') {
                throw new RuntimeException('Akun hak Anggota pada detail refund tidak valid.');
            }

            $total += (float) $detail->nominal_direfund;
            $lines[] = $this->akunResolver->line($akun, 'debit', $detail->nominal_direfund);
        }

        $lines[] = $this->akunResolver->line($akunDompet, 'kredit', $total);

        return $this->record([
            'idempotency_key' => $idempotencyKey,
            'tanggal' => now(config('app.timezone', 'Asia/Jakarta'))->toDateString(),
            'nomor_bukti' => $penyelesaian->kode_penyelesaian,
            'keterangan' => 'Refund hak Anggota keluar ' . $penyelesaian->kode_penyelesaian,
            'referensi_tipe' => PenyelesaianKeanggotaan::class,
            'referensi_id' => $penyelesaian->id,
            'created_by' => $userId ?? auth()->id(),
        ], $lines);
    }

    private function recordCicilanReversal(CicilanPinjaman $cicilan, ReversalTransaksi $reversal, Akun $creditAkun, ?int $userId = null): JurnalUmum
    {
        $existing = JurnalUmum::query()
            ->where('idempotency_key', 'reversal:jurnal:' . $reversal->id)
            ->first();

        if ($existing) {
            return $existing;
        }

        $jumlah = (float) ($cicilan->jumlah_cicilan ?? 0);

        return $this->record([
            'idempotency_key' => 'reversal:jurnal:' . $reversal->id,
            'tanggal' => now()->toDateString(),
            'nomor_bukti' => $reversal->kode_reversal,
            'keterangan' => 'Reversal pembayaran cicilan #' . $cicilan->id,
            'referensi_tipe' => ReversalTransaksi::class,
            'referensi_id' => $reversal->id,
            'created_by' => $userId ?? auth()->id(),
        ], [
            $this->akunResolver->line(
                $this->akunResolver->posting('refund.piutang_pinjaman'),
                'debit',
                $jumlah
            ),
            $this->akunResolver->line($creditAkun, 'kredit', $jumlah),
        ]);
    }

    /**
     * @return array<int, array{akun_id:int, akun_kode:string, akun_nama:string, debit:float, kredit:float}>
     */
    private function posReversalDebitLines(Penjualan $penjualan): array
    {
        $totalSetorKonsinyasi = (float) $penjualan->details()
            ->where('konsinyasi', true)
            ->sum('subtotal_setor');

        $grandTotal = (float) ($penjualan->grand_total ?? 0);
        $pendapatan = max(0, $grandTotal - $totalSetorKonsinyasi);
        $lines = [];

        if ($totalSetorKonsinyasi > 0) {
            $lines[] = $this->akunResolver->line(
                $this->akunResolver->posting('refund.utang_konsinyasi'),
                'debit',
                $totalSetorKonsinyasi
            );
        }

        if ($pendapatan > 0) {
            $lines[] = $this->akunResolver->line(
                $this->akunResolver->posting('refund.pendapatan_penjualan'),
                'debit',
                $pendapatan
            );
        }

        return $lines;
    }

    private function rupiahInt(mixed $value): int
    {
        if (is_int($value)) {
            return $value;
        }

        $string = trim((string) $value);

        if ($string === '') {
            return 0;
        }

        if (preg_match('/^\d+(\.\d{1,2})?$/', $string) === 1) {
            return (int) explode('.', $string)[0];
        }

        return (int) preg_replace('/[^\d]/', '', $string);
    }

    private function rupiahDecimal(mixed $value): string
    {
        return number_format($this->rupiahInt($value), 2, '.', '');
    }

    /**
     * @param  array<string, mixed>  $line
     * @return array{akun_id:int, akun_kode:string, akun_nama:string, debit:float, kredit:float}
     */
    private function normalizeLine(array $line): array
    {
        $akun = Akun::query()->aktif()->find($line['akun_id'] ?? null);

        if (! $akun) {
            throw new RuntimeException('Setiap baris jurnal wajib menggunakan akun aktif dari master COA.');
        }

        $debit = round((float) ($line['debit'] ?? 0), 2);
        $kredit = round((float) ($line['kredit'] ?? 0), 2);

        if (! is_finite($debit) || ! is_finite($kredit) || $debit < 0 || $kredit < 0) {
            throw new RuntimeException('Nilai debit dan kredit harus berupa nominal positif yang valid.');
        }

        if (($debit > 0) === ($kredit > 0)) {
            throw new RuntimeException('Satu baris jurnal harus memiliki tepat satu sisi: debit atau kredit.');
        }

        return [
            'akun_id' => $akun->id,
            'akun_kode' => $akun->kode_akun,
            'akun_nama' => $akun->nama_akun,
            'debit' => $debit,
            'kredit' => $kredit,
        ];
    }
}
