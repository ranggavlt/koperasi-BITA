@extends('layout.main')

@section('content')
@php
  $money = fn($value) => (float) $value > 0 ? 'Rp ' . number_format((float) $value, 0, ',', '.') : '–';
@endphp
<div class="kbsm-business-page invoice-page">
  @if(session('success'))<div class="kbsm-business-alert kbsm-business-alert--success">{{ session('success') }}</div>@endif
  @if($errors->any())<div class="kbsm-business-alert kbsm-business-alert--danger"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

  <header class="kbsm-business-header">
    <div>
      <p class="kbsm-business-eyebrow">Usaha Koperasi</p>
      <h1 class="kbsm-business-title">Invoice Perusahaan B2B</h1>
      <p class="kbsm-business-subtitle">Pantau tagihan dan cicilan pembayaran perusahaan BEE, BBS, dan BKM.</p>
    </div>
    <a href="{{ route('invoice-penagihan.create') }}" class="kbsm-business-add-button">+ Buat Invoice Perusahaan</a>
  </header>

  <section class="invoice-summary-grid" aria-label="Ringkasan perusahaan">
    @foreach($summary as $item)
      @php $progress = $item['total'] > 0 ? min(100, round($item['paid'] / $item['total'] * 100)) : 0; @endphp
      <article class="invoice-company-card">
        <div class="invoice-company-card__heading"><span>{{ $item['company']->kode }}</span><small>{{ $item['count'] }} invoice</small></div>
        <p>{{ $item['company']->nama }}</p>
        <dl class="invoice-company-stats">
          <div><dt>Total tagihan</dt><dd>{{ $money($item['total']) }}</dd></div>
          <div><dt>Sudah dibayar</dt><dd>{{ $money($item['paid']) }}</dd></div>
          <div class="is-remaining"><dt>Sisa utang</dt><dd>{{ $money($item['remaining']) }}</dd></div>
        </dl>
        <div class="invoice-progress"><span style="width: {{ $progress }}%"></span></div>
        <small>{{ $progress }}% pembayaran diterima</small>
      </article>
    @endforeach
  </section>

  <section class="kbsm-business-panel">
    <div class="kbsm-business-panel__header"><h2 class="kbsm-business-panel__title">Filter Invoice</h2></div>
    <form method="GET" class="kbsm-business-filter invoice-filter">
      <div class="kbsm-business-field"><label class="kbsm-business-label">Cari Invoice</label><input class="kbsm-business-control" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Nomor invoice"></div>
      <div class="kbsm-business-field"><label class="kbsm-business-label">Perusahaan</label><select class="kbsm-business-control" name="perusahaan_id"><option value="">Semua perusahaan</option>@foreach($companies as $company)<option value="{{ $company->id }}" @selected(($filters['perusahaan_id'] ?? null) == $company->id)>{{ $company->kode }} — {{ $company->nama }}</option>@endforeach</select></div>
      <div class="kbsm-business-field"><label class="kbsm-business-label">Status</label><select class="kbsm-business-control" name="status"><option value="">Semua status</option><option value="unpaid" @selected(($filters['status'] ?? '')==='unpaid')>Belum Dibayar</option><option value="partial" @selected(($filters['status'] ?? '')==='partial')>Dibayar Sebagian</option><option value="paid" @selected(($filters['status'] ?? '')==='paid')>Lunas</option><option value="overdue" @selected(($filters['status'] ?? '')==='overdue')>Jatuh Tempo</option></select></div>
      <div class="kbsm-business-filter__actions kbsm-business-filter__actions--split"><button class="kbsm-btn kbsm-btn--navy">Tampilkan</button><a href="{{ route('invoice-penagihan.index') }}" class="kbsm-btn kbsm-btn--outline-slate">Reset</a></div>
    </form>
  </section>

  <section class="kbsm-business-panel">
    <div class="kbsm-business-panel__header"><h2 class="kbsm-business-panel__title">Daftar Invoice</h2><p class="kbsm-business-panel__copy">Pembayaran dicatat dari halaman detail agar histori cicilan tetap jelas.</p></div>
    <div class="kbsm-business-table-wrap"><table class="kbsm-business-table invoice-table">
      <thead><tr><th>Invoice</th><th>Perusahaan</th><th>Tanggal & Jatuh Tempo</th><th class="kbsm-business-table__right">Total</th><th class="kbsm-business-table__right">Dibayar</th><th class="kbsm-business-table__right">Sisa</th><th>Status</th><th>Aksi</th></tr></thead>
      <tbody>@forelse($invoices as $invoice)<tr>
        <td><span class="kbsm-business-code">{{ $invoice->nomor_invoice }}</span></td>
        <td>{{ $invoice->perusahaan->kode }}<div class="kbsm-business-muted">{{ $invoice->perusahaan->nama }}</div></td>
        <td>{{ $invoice->tanggal_invoice->format('d/m/Y') }}<div class="kbsm-business-muted">Jatuh tempo {{ $invoice->jatuh_tempo->format('d/m/Y') }}</div></td>
        <td class="kbsm-business-amount">{{ $money($invoice->total_tagihan) }}</td><td class="kbsm-business-amount">{{ $money($invoice->total_dibayar) }}</td><td class="kbsm-business-amount">{{ $money($invoice->sisa_tagihan) }}</td>
        <td><span class="kbsm-status {{ $invoice->status_label === 'Lunas' ? 'kbsm-status--green' : ($invoice->status_label === 'Jatuh Tempo' ? 'kbsm-status--red' : 'kbsm-status--gold') }}">{{ $invoice->status_label }}</span></td>
        <td><a class="kbsm-btn kbsm-btn--outline-slate kbsm-btn--sm" href="{{ route('invoice-penagihan.show', $invoice) }}">{{ $invoice->status === 'paid' ? 'Lihat Detail' : 'Catat Pembayaran' }}</a></td>
      </tr>@empty<tr><td colspan="8" class="kbsm-business-empty">Belum ada invoice. Buat invoice dari kontrak yang siap ditagihkan.</td></tr>@endforelse</tbody>
    </table></div>
    <div class="kbsm-business-pagination">{{ $invoices->links() }}</div>
  </section>
</div>
@endsection
