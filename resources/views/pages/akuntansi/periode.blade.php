@extends('layout.main')

@section('content')
<div class="kbsm-business-page">
  @if(session('success'))<div class="kbsm-business-alert kbsm-business-alert--success">{{ session('success') }}</div>@endif
  @if($errors->any())<div class="kbsm-business-alert kbsm-business-alert--danger"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
  <div class="kbsm-business-header"><div><p class="kbsm-business-eyebrow">Akuntansi</p><h1 class="kbsm-business-title">Periode Akuntansi</h1><p class="kbsm-business-subtitle">Tutup periode menghitung laba dari jurnal posted, membuat jurnal penutup, lalu mengunci tanggal transaksi.</p></div></div>
  <section class="kbsm-business-panel">
    <div class="kbsm-business-panel__header"><h2 class="kbsm-business-panel__title">Buat Periode</h2><p class="kbsm-business-panel__copy">Rentang tidak boleh bertumpang tindih. Periode closed tidak dapat dibuka atau diubah kembali.</p></div>
    <form method="POST" action="{{ route('akuntansi.periode.store') }}" class="kbsm-business-form">@csrf
      <div class="kbsm-business-grid">
        <div class="kbsm-business-field"><label class="kbsm-business-label">Kode</label><input name="kode" required maxlength="30" value="{{ old('kode') }}" placeholder="2026" class="kbsm-business-control"></div>
        <div class="kbsm-business-field"><label class="kbsm-business-label">Nama</label><input name="nama" required maxlength="150" value="{{ old('nama') }}" placeholder="Tahun Buku 2026" class="kbsm-business-control"></div>
        <div class="kbsm-business-field"><label class="kbsm-business-label">Tanggal Mulai</label><input type="date" name="tanggal_mulai" required value="{{ old('tanggal_mulai', now()->startOfYear()->toDateString()) }}" class="kbsm-business-control"></div>
        <div class="kbsm-business-field"><label class="kbsm-business-label">Tanggal Selesai</label><input type="date" name="tanggal_selesai" required value="{{ old('tanggal_selesai', now()->endOfYear()->toDateString()) }}" class="kbsm-business-control"></div>
      </div>
      <button class="kbsm-btn kbsm-btn--navy">Buat Periode Open</button>
    </form>
  </section>
  <section class="kbsm-business-panel">
    <div class="kbsm-business-panel__header"><h2 class="kbsm-business-panel__title">Daftar Periode dan Snapshot</h2></div>
    <div class="kbsm-business-table-wrap"><table class="kbsm-business-table"><thead><tr><th>Periode</th><th>Status</th><th>Pendapatan</th><th>Beban</th><th>Laba Bersih</th><th>Audit/Aksi</th></tr></thead><tbody>
      @forelse($periods as $period)<tr>
        <td><strong>{{ $period->kode }} · {{ $period->nama }}</strong><div class="kbsm-business-muted">{{ $period->tanggal_mulai->format('d/m/Y') }}–{{ $period->tanggal_selesai->format('d/m/Y') }}</div></td>
        <td><span class="kbsm-status {{ $period->status === 'closed' ? 'kbsm-status--green' : 'kbsm-status--amber' }}">{{ strtoupper($period->status) }}</span></td>
        <td>Rp {{ number_format($period->total_pendapatan, 0, ',', '.') }}</td><td>Rp {{ number_format($period->total_beban, 0, ',', '.') }}</td><td><strong>Rp {{ number_format($period->laba_bersih, 0, ',', '.') }}</strong></td>
        <td>@if($period->status === 'open')<form method="POST" action="{{ route('akuntansi.periode.close', $period) }}" class="kbsm-business-form">@csrf<input name="closing_reason" required minlength="5" maxlength="1000" placeholder="Alasan tutup periode" class="kbsm-business-control"><button class="kbsm-btn kbsm-btn--green kbsm-btn--sm">Tutup & Kunci</button></form>@else<div>{{ $period->jumlah_jurnal }} jurnal · {{ $period->closer?->name ?? '-' }}</div><div class="kbsm-business-muted">{{ $period->closed_at?->format('d/m/Y H:i') }} · {{ $period->closingJournal?->nomor_bukti ?? 'Tanpa jurnal penutup' }}</div>@endif</td>
      </tr>@empty<tr><td colspan="6" class="kbsm-business-empty">Belum ada periode akuntansi.</td></tr>@endforelse
    </tbody></table></div><div class="kbsm-business-pagination">{{ $periods->links() }}</div>
  </section>
</div>
@endsection
