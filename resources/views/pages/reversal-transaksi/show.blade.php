@extends('layout.main')
@section('content')
@php
$money=fn($v)=>(float)$v>0?'Rp '.number_format((float)$v,0,',','.'):'–';
$sourceRoute=match($reversal->source_type){
  \App\Models\SewaMobil::class=>'sewa-mobil.finance.index',
  \App\Models\SewaHardware::class=>'sewa-hardware.index',
  \App\Models\BebanOperasional::class=>'beban-operasional.index',
  \App\Models\Penjualan::class=>'waserba.index',
  \App\Models\Simpanan::class=>'simpanan.index',
  \App\Models\CicilanPinjaman::class=>'cicilan-pinjaman.index',
  default=>null,
};
@endphp
<div class="kbsm-business-page"><header class="kbsm-business-header"><div><a href="{{ route('reversal-transaksi.index') }}" class="kbsm-business-back-link">← Riwayat Koreksi</a><p class="kbsm-business-eyebrow">Koreksi Transaksi</p><h1 class="kbsm-business-title">{{ $reversal->kode_reversal }}</h1><p class="kbsm-business-subtitle">{{ $reversal->jenis_label }}</p></div><span class="kbsm-status kbsm-status--green">{{ $reversal->status_label }}</span></header><section class="kbsm-business-panel"><dl class="correction-detail"><div><dt>Transaksi Asli</dt><dd>{{ $reversal->source_label }} #{{ $reversal->source_id }}</dd></div><div><dt>Nominal</dt><dd>{{ $money($reversal->nominal) }}</dd></div><div><dt>Diproses</dt><dd>{{ $reversal->processed_at?->format('d/m/Y H:i')??'–' }}</dd></div><div><dt>Diproses Oleh</dt><dd>{{ $reversal->processor->name??'Sistem' }}</dd></div><div class="is-wide"><dt>Alasan</dt><dd>{{ $reversal->alasan }}</dd></div></dl><div class="kbsm-business-actions">@if($sourceRoute&&\Illuminate\Support\Facades\Route::has($sourceRoute))<a href="{{ route($sourceRoute) }}" class="kbsm-btn kbsm-btn--outline-slate">Lihat Transaksi Asli</a>@endif</div></section></div>
@endsection
