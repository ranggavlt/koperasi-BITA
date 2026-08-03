@extends('layout.main')
@section('content')
<div class="kbsm-business-page">
 @if(session('success'))<div class="kbsm-business-alert kbsm-business-alert--success">{{ session('success') }}</div>@endif
 @if($errors->any())<div class="kbsm-business-alert kbsm-business-alert--danger"><ul>@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif
 <div class="kbsm-business-header"><div><p class="kbsm-business-eyebrow">Akuntansi</p><h1 class="kbsm-business-title">SHU Koperasi</h1><p class="kbsm-business-subtitle">Laba hanya dibaca otomatis dari jurnal posted pada periode akuntansi yang sudah ditutup.</p></div><a href="{{ route('shu-config.index') }}" class="kbsm-btn kbsm-btn--outline">Pengaturan Persentase</a></div>
 <section class="kbsm-business-panel"><div class="kbsm-business-panel__header"><h2 class="kbsm-business-panel__title">Mulai Perhitungan SHU</h2><p class="kbsm-business-panel__copy">Tidak ada input nominal laba. Pilih periode closed, periksa snapshot, lalu proses approval dan posting.</p></div>
  <form method="POST" action="{{ route('shu-koperasi.store') }}" class="kbsm-business-form">@csrf<div class="kbsm-business-grid">
   <div class="kbsm-business-field"><label class="kbsm-business-label">Periode Akuntansi Closed</label><select name="periode_akuntansi_id" required class="kbsm-business-control"><option value="">Pilih periode</option>@foreach($closedPeriods as $p)<option value="{{ $p->id }}">{{ $p->kode }} · {{ $p->nama }} · Laba Rp {{ number_format($p->laba_bersih,0,',','.') }}</option>@endforeach</select></div>
   <div class="kbsm-business-field"><label class="kbsm-business-label">Judul</label><input name="judul" maxlength="255" placeholder="Otomatis bila dikosongkan" class="kbsm-business-control"></div>
   <div class="kbsm-business-field"><label class="kbsm-business-label">Catatan</label><input name="keterangan" maxlength="1000" class="kbsm-business-control"></div>
  </div><button @disabled($closedPeriods->isEmpty()) class="kbsm-btn kbsm-btn--navy">Hitung dari Periode Closed</button></form>
 </section>
 <section class="kbsm-business-panel"><div class="kbsm-business-panel__header"><h2 class="kbsm-business-panel__title">Riwayat SHU</h2></div><div class="kbsm-business-table-wrap"><table class="kbsm-business-table"><thead><tr><th>Periode</th><th>Pendapatan</th><th>Beban</th><th>Laba</th><th>Status</th><th>Aksi</th></tr></thead><tbody>@forelse($data as $item)<tr><td><strong>{{ $item->judul }}</strong><div class="kbsm-business-muted">{{ $item->periodeAkuntansi?->kode }}</div></td><td>Rp {{ number_format($item->total_pendapatan,0,',','.') }}</td><td>Rp {{ number_format($item->total_biaya,0,',','.') }}</td><td><strong>Rp {{ number_format($item->shu_total,0,',','.') }}</strong></td><td>{{ strtoupper($item->status) }}</td><td><a class="kbsm-btn kbsm-btn--navy kbsm-btn--sm" href="{{ route('shu-koperasi.show',$item) }}">Review</a></td></tr>@empty<tr><td colspan="6" class="kbsm-business-empty">Belum ada proses SHU.</td></tr>@endforelse</tbody></table></div><div class="kbsm-business-pagination">{{ $data->links() }}</div></section>
</div>
@endsection
