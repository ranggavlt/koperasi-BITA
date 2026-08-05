@extends('layout.main')
@section('content')
@php $category = old('kategori',$akun->kategori); @endphp
<div class="coa-page">
  <header class="coa-page-header"><div><a href="{{ route('akun.index') }}" class="kbsm-business-back-link">← Daftar Akun</a><p class="coa-eyebrow">Akuntansi</p><h1 class="coa-page-title">{{ $isEdit?'Edit Akun':'Tambah Akun' }}</h1><p class="coa-page-subtitle">Saldo normal ditentukan otomatis berdasarkan kategori akun.</p></div></header>
  @if($errors->any())<div class="coa-alert coa-alert--danger"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
  <section class="coa-form-panel"><form method="POST" action="{{ $isEdit?route('akun.update',$akun):route('akun.store') }}">@csrf @if($isEdit)@method('PUT')@endif
    <div class="coa-form-grid coa-form-grid--account">
      <div class="coa-field"><label class="coa-label">Kode Akun</label><input class="coa-input" name="kode_akun" value="{{ old('kode_akun',$akun->kode_akun) }}" inputmode="numeric" {{ $isCoreLocked?'readonly':'' }} required>@if($isCoreLocked)<small>Identitas akun dilindungi karena akun sistem atau sudah digunakan jurnal.</small>@endif</div>
      <div class="coa-field"><label class="coa-label">Nama Akun</label><input class="coa-input" name="nama_akun" value="{{ old('nama_akun',$akun->nama_akun) }}" required></div>
      <div class="coa-field"><label class="coa-label">Kategori</label><select class="coa-input" name="kategori" data-account-category {{ $isCoreLocked?'disabled':'' }} required>@foreach($categories as $key=>$label)<option value="{{ $key }}" @selected($category===$key)>{{ $label }}</option>@endforeach</select>@if($isCoreLocked)<input type="hidden" name="kategori" value="{{ $akun->kategori }}">@endif</div>
      <div class="coa-field"><label class="coa-label">Saldo Normal</label><input class="coa-input" data-normal-balance value="{{ $category ? ucfirst(\App\Models\Akun::posisiSaldoUntuk($category)) : 'Debit' }}" readonly><small>Nilai ini mengikuti kategori dan tidak diisi manual.</small></div>
      <div class="coa-field"><label class="coa-label">Bisa digunakan untuk Beban Operasional</label><input type="hidden" name="is_beban_operasional" value="0"><label class="kbsm-switch"><input type="checkbox" name="is_beban_operasional" value="1" @checked(old('is_beban_operasional',$akun->is_beban_operasional))><span></span><em>On/Off</em></label><small>Hanya dapat diaktifkan untuk akun kategori Beban.</small></div>
      @if($isEdit)<div class="coa-field"><label class="coa-label">Status Akun</label><input type="hidden" name="is_aktif" value="0"><label class="kbsm-switch"><input type="checkbox" name="is_aktif" value="1" @checked(old('is_aktif',$akun->is_aktif)) {{ $akun->is_sistem?'disabled':'' }}><span></span><em>Aktif</em></label>@if($akun->is_sistem)<input type="hidden" name="is_aktif" value="1">@endif</div>@endif
      <div class="coa-field coa-field--description"><label class="coa-label">Keterangan</label><textarea class="coa-input" name="keterangan" rows="3">{{ old('keterangan',$akun->keterangan) }}</textarea></div>
    </div>
    <div class="kbsm-business-actions"><a href="{{ route('akun.index') }}" class="coa-btn coa-btn--secondary">Batal</a><button class="coa-btn coa-btn--primary">Simpan</button></div>
  </form></section>
</div>
<script>document.addEventListener('DOMContentLoaded',()=>{const category=document.querySelector('[data-account-category]'),balance=document.querySelector('[data-normal-balance]');if(!category||!balance)return;category.addEventListener('change',()=>balance.value=['kewajiban','ekuitas','pendapatan'].includes(category.value)?'Kredit':'Debit');});</script>
@endsection
