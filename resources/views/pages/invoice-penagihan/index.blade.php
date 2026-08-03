@extends('layout.main')

@section('content')
<div class="kbsm-business-page">
  @if(session('success'))<div class="kbsm-business-alert kbsm-business-alert--success">{{ session('success') }}</div>@endif
  @if($errors->any())<div class="kbsm-business-alert kbsm-business-alert--danger"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
  <div class="kbsm-business-header"><div><p class="kbsm-business-eyebrow">Keuangan B2B</p><h1 class="kbsm-business-title">Invoice Perusahaan</h1><p class="kbsm-business-subtitle">Satu invoice dapat menggabungkan beberapa Sewa Mobil dan Sewa Hardware dari perusahaan yang sama. Pembayaran perusahaan terpisah dari pembayaran vendor dan boleh parsial.</p></div></div>
  <div class="kbsm-business-grid">
    @foreach($companySummaries as $summary)
      <section class="kbsm-business-panel">
        <div class="kbsm-business-panel__header"><h2 class="kbsm-business-panel__title">{{ $summary['kode'] }}</h2><p class="kbsm-business-panel__copy">{{ $summary['nama'] }} · {{ $summary['jumlah_invoice'] }} invoice</p></div>
        <div class="kbsm-business-readonly"><div>Total tagihan: <strong>Rp {{ number_format($summary['total_tagihan'],0,',','.') }}</strong></div><div>Total terbayar: <strong>Rp {{ number_format($summary['total_dibayar'],0,',','.') }}</strong></div><div>Sisa utang: <strong>Rp {{ number_format($summary['sisa_tagihan'],0,',','.') }}</strong></div></div>
      </section>
    @endforeach
  </div>
  <section class="kbsm-business-panel">
    <div class="kbsm-business-panel__header"><h2 class="kbsm-business-panel__title">Buat Invoice Final</h2><p class="kbsm-business-panel__copy">Hanya kontrak dengan vendor yang sudah dibayar dan belum pernah masuk invoice yang dapat dipilih.</p></div>
    <form method="POST" action="{{ route('invoice-penagihan.store') }}" class="kbsm-business-form">@csrf
      <div class="kbsm-business-grid">
        <div class="kbsm-business-field"><label class="kbsm-business-label">Perusahaan</label><select name="perusahaan_id" required class="kbsm-business-control"><option value="">Pilih BEE/BBS/BKM</option>@foreach($perusahaan as $p)<option value="{{ $p->id }}" @selected((string)old('perusahaan_id')===(string)$p->id)>{{ $p->kode }} — {{ $p->nama }}</option>@endforeach</select></div>
        <div class="kbsm-business-field"><label class="kbsm-business-label">Tanggal Invoice</label><input type="date" name="tanggal_invoice" required value="{{ old('tanggal_invoice',now()->toDateString()) }}" class="kbsm-business-control"></div>
        <div class="kbsm-business-field"><label class="kbsm-business-label">Jatuh Tempo</label><input type="date" name="jatuh_tempo" required min="{{ old('tanggal_invoice', now()->toDateString()) }}" value="{{ old('jatuh_tempo') }}" class="kbsm-business-control"><p class="kbsm-business-help">Pilih sesuai termin perusahaan yang telah disepakati; sistem tidak menetapkan asumsi jumlah hari.</p></div>
      </div>
      <div class="kbsm-business-grid">
        <section class="kbsm-business-section"><h3 class="kbsm-business-section__title">Sewa Mobil Eligible</h3>@forelse($eligibleMobil as $item)<label class="kbsm-business-readonly"><input type="checkbox" name="sewa_mobil_ids[]" value="{{ $item->id }}" @checked(in_array($item->id,old('sewa_mobil_ids',[])))> {{ $item->kode_sewa }} · {{ $item->kode_perusahaan_snapshot }} · {{ $item->vendor_nama_snapshot }} · Rp {{ number_format($item->total_tagihan_perusahaan,0,',','.') }}</label>@empty<p class="kbsm-business-muted">Tidak ada kontrak eligible.</p>@endforelse</section>
        <section class="kbsm-business-section"><h3 class="kbsm-business-section__title">Sewa Hardware Eligible</h3>@forelse($eligibleHardware as $item)<label class="kbsm-business-readonly"><input type="checkbox" name="sewa_hardware_ids[]" value="{{ $item->id }}" @checked(in_array($item->id,old('sewa_hardware_ids',[])))> {{ $item->kode_sewa }} · {{ $item->kode_perusahaan_snapshot }} · {{ $item->vendor_nama }} · Rp {{ number_format($item->total_tagihan_perusahaan,0,',','.') }}</label>@empty<p class="kbsm-business-muted">Tidak ada kontrak eligible.</p>@endforelse</section>
      </div>
      <div class="kbsm-business-actions"><button class="kbsm-btn kbsm-btn--navy">Finalisasi Invoice</button></div>
    </form>
  </section>
  <section class="kbsm-business-panel">
    <div class="kbsm-business-panel__header"><h2 class="kbsm-business-panel__title">Daftar dan Piutang Perusahaan</h2></div>
    <div class="kbsm-business-table-wrap"><table class="kbsm-business-table"><thead><tr><th>Invoice</th><th>Perusahaan</th><th>Kontrak</th><th>Total/Dibayar/Sisa</th><th>Riwayat</th><th>Pembayaran</th></tr></thead><tbody>
      @forelse($invoices as $invoice)<tr>
        <td><div class="kbsm-business-code">{{ $invoice->nomor_invoice }}</div><div class="kbsm-business-muted">{{ $invoice->tanggal_invoice?->format('d/m/Y') }} · JT {{ $invoice->jatuh_tempo?->format('d/m/Y') }}</div><span class="kbsm-status {{ $invoice->status==='paid'?'kbsm-status--green':'kbsm-status--amber' }}">{{ strtoupper($invoice->status) }}</span></td>
        <td><strong>{{ $invoice->kode_perusahaan_snapshot }}</strong><div class="kbsm-business-muted">{{ $invoice->nama_perusahaan_snapshot }}</div></td>
        <td>@foreach($invoice->detail as $d)<div>{{ class_basename($d->referensi_type)==='SewaMobil'?'Mobil':'Hardware' }} · {{ $d->kode_sewa_snapshot }}</div>@endforeach</td>
        <td><div>Total: Rp {{ number_format($invoice->total_tagihan,0,',','.') }}</div><div>Dibayar: Rp {{ number_format($invoice->jumlah_dibayar,0,',','.') }}</div><strong>Sisa: Rp {{ number_format($invoice->sisa_tagihan,0,',','.') }}</strong></td>
        <td>@forelse($invoice->pembayaran as $pay)<div>{{ $pay->tanggal_bayar?->format('d/m/Y') }} · Rp {{ number_format($pay->jumlah_bayar,0,',','.') }} · {{ $pay->dompet?->nama_dompet }}</div>@empty<span class="kbsm-business-muted">Belum ada</span>@endforelse</td>
        <td>@if($invoice->status!=='paid')<form method="POST" action="{{ route('invoice-penagihan.pay',$invoice) }}" class="kbsm-business-form">@csrf<input type="number" name="jumlah_bayar" min="1" max="{{ (int)$invoice->sisa_tagihan }}" required placeholder="Jumlah" class="kbsm-business-control"><select name="metode_pembayaran" required class="kbsm-business-control"><option value="tunai">Tunai</option><option value="transfer_bank">Transfer Bank</option></select><select name="dompet_id" required class="kbsm-business-control"><option value="">Kas/Bank tujuan</option>@foreach($dompetOptions as $d)<option value="{{ $d->id }}">{{ $d->nama_dompet }}</option>@endforeach</select><input type="date" name="tanggal_bayar" value="{{ now()->toDateString() }}" required class="kbsm-business-control"><input name="nomor_referensi" placeholder="Referensi opsional" class="kbsm-business-control"><button class="kbsm-btn kbsm-btn--green kbsm-btn--sm">Catat Pembayaran</button></form>@else<span class="kbsm-status kbsm-status--green">LUNAS</span>@endif</td>
      </tr>@empty<tr><td colspan="6" class="kbsm-business-empty">Belum ada invoice.</td></tr>@endforelse
    </tbody></table></div><div class="kbsm-business-pagination">{{ $invoices->links() }}</div>
  </section>
</div>
@endsection
