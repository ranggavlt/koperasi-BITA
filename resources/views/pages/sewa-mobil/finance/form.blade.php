@extends('layout.main')

@section('content')
@php $formAction = $editData ? route('sewa-mobil.finance.update', $editData) : route('sewa-mobil.finance.store'); @endphp
<div class="kbsm-business-page">
  @if($errors->any())<div class="kbsm-business-alert kbsm-business-alert--danger"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
  <div class="kbsm-business-header kbsm-business-form-header">
    <div><p class="kbsm-business-eyebrow">Usaha Koperasi B2B</p><h1 class="kbsm-business-title">{{ $editData ? 'Edit Draft' : 'Tambah' }} Sewa Mobil</h1><p class="kbsm-business-subtitle">Finance memilih karyawan dan mencatat snapshot vendor/kendaraan. Tidak ada Master Mobil dan tidak ada tarif harian.</p></div>
    <a href="{{ route('sewa-mobil.finance.index') }}" class="kbsm-business-back-link">Kembali ke Daftar Sewa Mobil</a>
  </div>
  <section class="kbsm-business-panel">
    <form method="POST" action="{{ $formAction }}" class="kbsm-business-form" data-car-form data-sewa-mobil-form>
      @csrf @if($editData) @method('PUT') @endif
      <section class="kbsm-business-section"><h2 class="kbsm-business-section__title">Karyawan dan kegiatan</h2><div class="kbsm-business-grid">
        <div class="kbsm-business-field"><label class="kbsm-business-label">Karyawan</label><select name="karyawan_id" required class="kbsm-business-control"><option value="">Pilih karyawan BEE/BBS/BKM</option>@foreach($karyawanOptions as $k)<option value="{{ $k->id }}" @selected((string)old('karyawan_id',$editData?->karyawan_id)===(string)$k->id)>{{ $k->nama }} — {{ $k->perusahaan?->kode }}</option>@endforeach</select></div>
        <div class="kbsm-business-field"><label class="kbsm-business-label">Nama Kegiatan</label><input name="nama_kegiatan" required maxlength="150" class="kbsm-business-control" value="{{ old('nama_kegiatan',$editData?->nama_kegiatan) }}"></div>
        <div class="kbsm-business-field"><label class="kbsm-business-label">Lokasi</label><input name="lokasi_kegiatan" required maxlength="150" class="kbsm-business-control" value="{{ old('lokasi_kegiatan',$editData?->lokasi_kegiatan) }}"></div>
        <div class="kbsm-business-field"><label class="kbsm-business-label">Periode</label><div class="kbsm-business-grid"><input type="date" name="tanggal_mulai" required class="kbsm-business-control" value="{{ old('tanggal_mulai',$editData?->tanggal_mulai?->toDateString()) }}"><input type="date" name="tanggal_selesai" required class="kbsm-business-control" value="{{ old('tanggal_selesai',$editData?->tanggal_selesai?->toDateString()) }}"></div></div>
      </div></section>
      <section class="kbsm-business-section"><h2 class="kbsm-business-section__title">Snapshot vendor dan kendaraan</h2><p class="kbsm-business-section__copy">Data ini melekat pada transaksi dan tidak berubah bila vendor mengganti data.</p><div class="kbsm-business-grid">
        <div class="kbsm-business-field"><label class="kbsm-business-label">Nama Vendor</label><input name="vendor_nama" required maxlength="150" class="kbsm-business-control" value="{{ old('vendor_nama',$editData?->vendor_nama_snapshot) }}"></div>
        <div class="kbsm-business-field"><label class="kbsm-business-label">Kontak Vendor</label><input name="vendor_kontak" maxlength="100" class="kbsm-business-control" value="{{ old('vendor_kontak',$editData?->vendor_kontak_snapshot) }}"></div>
        <div class="kbsm-business-field kbsm-business-field--full"><label class="kbsm-business-label">Alamat Vendor</label><textarea name="vendor_alamat" class="kbsm-business-control">{{ old('vendor_alamat',$editData?->vendor_alamat_snapshot) }}</textarea></div>
        <div class="kbsm-business-field"><label class="kbsm-business-label">Jenis Kendaraan</label><input name="kendaraan_jenis" required maxlength="80" class="kbsm-business-control" value="{{ old('kendaraan_jenis',$editData?->kendaraan_jenis_snapshot) }}"></div>
        <div class="kbsm-business-field"><label class="kbsm-business-label">Merek/Tipe</label><input name="kendaraan_merk_tipe" required maxlength="150" class="kbsm-business-control" value="{{ old('kendaraan_merk_tipe',$editData?->kendaraan_merk_tipe_snapshot) }}"></div>
        <div class="kbsm-business-field"><label class="kbsm-business-label">Nomor Polisi</label><input name="nomor_polisi" required maxlength="30" class="kbsm-business-control" value="{{ old('nomor_polisi',$editData?->nomor_polisi_snapshot) }}"></div>
      </div></section>
      <section class="kbsm-business-section"><h2 class="kbsm-business-section__title">Nilai kontrak per periode</h2><div class="kbsm-business-grid">
        <div class="kbsm-business-field"><label class="kbsm-business-label">Total Vendor</label><input type="number" min="1" name="harga_vendor_total" required class="kbsm-business-control" data-vendor value="{{ old('harga_vendor_total',$editData?->harga_vendor_total) }}"></div>
        <div class="kbsm-business-field"><label class="kbsm-business-label">Markup Koperasi</label><input type="number" min="0" name="markup_total" required class="kbsm-business-control" data-markup value="{{ old('markup_total',$editData?->markup_total) }}"></div>
      </div><div class="kbsm-business-summary"><div class="kbsm-business-summary-card kbsm-business-summary-card--navy"><p class="kbsm-business-summary-label">Tagihan Perusahaan</p><p class="kbsm-business-summary-value" data-total>Rp 0</p></div></div></section>
      <section class="kbsm-business-section"><label class="kbsm-business-label">Keterangan</label><textarea name="keterangan" class="kbsm-business-control">{{ old('keterangan',$editData?->keterangan) }}</textarea><div class="kbsm-business-actions"><button class="kbsm-btn kbsm-btn--navy">Simpan Draft</button></div></section>
    </form>
  </section>
</div>
<script>document.addEventListener('DOMContentLoaded',()=>{const f=document.querySelector('[data-car-form]');if(!f)return;const refresh=()=>{const n=(parseInt(f.querySelector('[data-vendor]').value||0)+parseInt(f.querySelector('[data-markup]').value||0));f.querySelector('[data-total]').textContent=new Intl.NumberFormat('id-ID',{style:'currency',currency:'IDR',maximumFractionDigits:0}).format(n)};f.addEventListener('input',refresh);refresh()})</script>
@endsection
