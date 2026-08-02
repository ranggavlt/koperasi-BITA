@extends('layout.main')

@section('content')
@php
  $selectedKategori = old('kategori', $jenis->kategori);
  $selectedAkun = old('akun_id', $jenis->akun_id);
  $selectedAktif = old('aktif', $jenis->exists ? ($jenis->aktif ? '1' : '0') : '1');
@endphp

<div class="kbsm-business-page">
  @if ($errors->any())
    <div class="kbsm-business-alert kbsm-business-alert--danger">
      <ul>
        @foreach ($errors->all() as $error)
          <li>{{ $error }}</li>
        @endforeach
      </ul>
    </div>
  @endif

  <div class="kbsm-business-header kbsm-business-form-header">
    <div>
      <p class="kbsm-business-eyebrow">Master Data</p>
      <h1 class="kbsm-business-title">{{ $title }}</h1>
      <p class="kbsm-business-subtitle">Gunakan dua kategori final: Wajib satu kali per siklus dan Manasuka sesuai transaksi.</p>
    </div>
    <a href="{{ route('jenis-simpanan.index') }}" class="kbsm-business-back-link">Kembali ke Master Jenis Simpanan</a>
  </div>

  <section class="kbsm-business-panel">
    <form method="POST" action="{{ $action }}" class="kbsm-business-form">
      @csrf
      @if($method !== 'POST')
        @method($method)
      @endif

      <section class="kbsm-business-section">
        <h2 class="kbsm-business-section__title">Konfigurasi Master</h2>
        <div class="kbsm-business-grid">
          <div class="kbsm-business-field">
            <label class="kbsm-business-label">Kategori</label>
            <select name="kategori" required class="kbsm-business-control">
              <option value="">Pilih Kategori</option>
              @foreach($kategoriOptions as $value => $label)
                <option value="{{ $value }}" {{ $selectedKategori === $value ? 'selected' : '' }}>{{ $label }}</option>
              @endforeach
            </select>
          </div>

          <div class="kbsm-business-field">
            <label class="kbsm-business-label">Nama Jenis</label>
            <input type="text" name="nama_jenis" maxlength="100" required value="{{ old('nama_jenis', $jenis->nama_jenis) }}" class="kbsm-business-control" placeholder="Contoh: Simpanan Manasuka">
          </div>

          <div class="kbsm-business-field">
            <label class="kbsm-business-label">Akun COA</label>
            <select name="akun_id" required class="kbsm-business-control">
              <option value="">Pilih Akun</option>
              @foreach($akunOptions as $akun)
                <option value="{{ $akun->id }}" {{ (string) $selectedAkun === (string) $akun->id ? 'selected' : '' }}>
                  {{ $akun->kode_akun }} - {{ $akun->nama_akun }} ({{ $akun->kategori_label }})
                </option>
              @endforeach
            </select>
          </div>

          <div class="kbsm-business-field">
            <label class="kbsm-business-label">Nominal Default</label>
            <div class="kbsm-money-input">
              <span>Rp</span>
              <input type="number" name="nominal_default" min="0" step="1" required value="{{ old('nominal_default', (int) $jenis->nominal_default) }}" class="kbsm-business-control">
            </div>
          </div>

          <div class="kbsm-business-field">
            <label class="kbsm-business-label">Berlaku Mulai</label>
            <input type="date" name="berlaku_mulai" required value="{{ old('berlaku_mulai', $jenis->berlaku_mulai?->toDateString() ?? now(config('app.timezone', 'Asia/Jakarta'))->toDateString()) }}" class="kbsm-business-control">
          </div>

          <div class="kbsm-business-field">
            <label class="kbsm-business-label">Status</label>
            <select name="aktif" required class="kbsm-business-control">
              <option value="1" {{ (string) $selectedAktif === '1' ? 'selected' : '' }}>Aktif</option>
              <option value="0" {{ (string) $selectedAktif === '0' ? 'selected' : '' }}>Nonaktif</option>
            </select>
          </div>

          <div class="kbsm-business-field kbsm-business-field--full">
            <label class="kbsm-business-label">Aturan Final</label>
            <div class="kbsm-business-note">
              Simpanan Wajib dibayar Rp10.000 satu kali setiap siklus keanggotaan. Simpanan Manasuka adalah tabungan pilihan dan tidak memakai payroll pada SP-7.
            </div>
          </div>

          <div class="kbsm-business-field kbsm-business-field--full">
            <label class="kbsm-business-label">Keterangan</label>
            <textarea name="keterangan" rows="3" maxlength="1000" class="kbsm-business-control">{{ old('keterangan', $jenis->keterangan) }}</textarea>
          </div>

          <div class="kbsm-business-field kbsm-business-field--full">
            <label class="kbsm-business-label">Alasan Perubahan</label>
            <textarea name="alasan_perubahan" rows="3" maxlength="1000" class="kbsm-business-control" placeholder="Wajib saat mengubah master aktif atau master yang sudah dipakai transaksi.">{{ old('alasan_perubahan') }}</textarea>
          </div>
        </div>
      </section>

      <div class="kbsm-business-actions">
        <button class="kbsm-btn kbsm-btn--navy">{{ $submitLabel }}</button>
        <a href="{{ route('jenis-simpanan.index') }}" class="kbsm-btn kbsm-btn--outline-slate">Batal</a>
      </div>
    </form>
  </section>
</div>
@endsection
