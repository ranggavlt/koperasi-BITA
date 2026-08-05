@extends('layout.main')
@section('content')
@php
 $fields=['persen_dana_cadangan'=>'Dana Cadangan','persen_shu_anggota'=>'SHU Anggota','persen_pengurus'=>'Pengurus','persen_pengawas'=>'Pengawas','persen_pembina'=>'Pembina','persen_dana_sosial'=>'Dana Sosial','persen_dana_pendidikan'=>'Dana Pendidikan'];
@endphp
<div class="kbsm-business-page">
 @if(session('success'))<div class="kbsm-business-alert kbsm-business-alert--success">{{ session('success') }}</div>@endif
 @if($errors->any())<div class="kbsm-business-alert kbsm-business-alert--danger"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
 <header class="kbsm-business-header"><div><p class="kbsm-business-eyebrow">SHU & Dana Sosial</p><h1 class="kbsm-business-title">Pengaturan SHU</h1><p class="kbsm-business-subtitle">Setiap penyimpanan membuat versi baru. Versi yang sudah dipakai tidak dapat diubah atau dihapus.</p></div></header>
 <section class="kbsm-business-panel"><div class="kbsm-business-panel__header"><h2 class="kbsm-business-panel__title">Buat Versi Baru</h2><p class="kbsm-business-panel__copy">Total kategori utama dan total porsi Anggota masing-masing harus tepat 100%.</p></div>
  <form method="POST" action="{{ route('shu-config.store') }}" id="shu-config-form" class="kbsm-form-grid">@csrf
   <div class="kbsm-field"><label>Berlaku Mulai</label><input type="date" name="berlaku_mulai" value="{{ old('berlaku_mulai',now()->startOfYear()->format('Y-m-d')) }}" required></div>
   <div class="kbsm-field kbsm-field--wide"><label>Dasar Keputusan / RAT</label><input name="dasar_keputusan" value="{{ old('dasar_keputusan') }}" maxlength="255" placeholder="Contoh: Keputusan RAT Nomor 01/2026" required></div>
   @foreach($fields as $name=>$label)<div class="kbsm-field"><label>{{ $label }} (%)</label><input class="shu-main-percent" type="number" name="{{ $name }}" value="{{ old($name,0) }}" min="0" max="100" step="0.01" required></div>@endforeach
   <div class="kbsm-field"><label>Jasa Modal dari SHU Anggota (%)</label><input class="shu-member-percent" type="number" name="persen_jasa_modal" value="{{ old('persen_jasa_modal',50) }}" min="0" max="100" step="0.01" required></div>
   <div class="kbsm-field"><label>Jasa Usaha dari SHU Anggota (%)</label><input class="shu-member-percent" type="number" name="persen_jasa_usaha" value="{{ old('persen_jasa_usaha',50) }}" min="0" max="100" step="0.01" required></div>
   <div class="kbsm-field kbsm-field--wide"><div class="kbsm-business-note">Total kategori utama: <strong id="shu-main-total">0,00%</strong> · Total porsi Anggota: <strong id="shu-member-total">100,00%</strong></div></div>
   <div class="kbsm-field kbsm-field--wide"><button id="shu-config-submit" class="kbsm-btn kbsm-btn--navy" disabled>Simpan Versi Pengaturan</button></div>
  </form>
 </section>
 <section class="kbsm-business-panel"><div class="kbsm-business-panel__header"><h2 class="kbsm-business-panel__title">Riwayat Versi</h2><p class="kbsm-business-panel__copy">Versi bertanda data contoh disediakan untuk presentasi dan bukan keputusan RAT final.</p></div><div class="kbsm-business-table-wrap"><table class="kbsm-business-table"><thead><tr><th>Versi</th><th>Berlaku Mulai</th><th>Dasar Keputusan</th><th>Pembagian Utama</th><th>Porsi Anggota</th><th>Dibuat Oleh</th></tr></thead><tbody>@forelse($configs as $config)<tr><td><span class="kbsm-business-code">v{{ $config->versi }}</span></td><td>{{ $config->berlaku_mulai->format('d/m/Y') }}</td><td>{{ $config->dasar_keputusan }}@if(str_contains($config->dasar_keputusan, 'Data demonstrasi'))<div><span class="kbsm-status kbsm-status--gold">Data contoh</span></div>@endif</td><td class="kbsm-business-muted">Cadangan {{ $config->persen_dana_cadangan }}% · Anggota {{ $config->persen_shu_anggota }}% · Pengurus {{ $config->persen_pengurus }}% · Pengawas {{ $config->persen_pengawas }}% · Pembina {{ $config->persen_pembina }}% · Sosial {{ $config->persen_dana_sosial }}% · Pendidikan {{ $config->persen_dana_pendidikan }}%</td><td>Modal {{ $config->persen_jasa_modal }}% · Usaha {{ $config->persen_jasa_usaha }}%</td><td>{{ $config->creator?->name ?? '–' }}</td></tr>@empty<tr><td colspan="6" class="kbsm-business-empty">Belum ada versi pengaturan SHU.</td></tr>@endforelse</tbody></table></div><div class="kbsm-business-pagination">{{ $configs->links() }}</div></section>
</div>
<script>
document.addEventListener('DOMContentLoaded',()=>{const main=[...document.querySelectorAll('.shu-main-percent')],member=[...document.querySelectorAll('.shu-member-percent')],button=document.getElementById('shu-config-submit');const refresh=()=>{const sum=items=>items.reduce((total,input)=>total+(Number(input.value)||0),0);const a=sum(main),b=sum(member);document.getElementById('shu-main-total').textContent=a.toLocaleString('id-ID',{minimumFractionDigits:2,maximumFractionDigits:2})+'%';document.getElementById('shu-member-total').textContent=b.toLocaleString('id-ID',{minimumFractionDigits:2,maximumFractionDigits:2})+'%';button.disabled=Math.abs(a-100)>.001||Math.abs(b-100)>.001;};[...main,...member].forEach(input=>input.addEventListener('input',refresh));refresh();});
</script>
@endsection
