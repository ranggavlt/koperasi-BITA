@extends('layout.main')

@section('content')
@php
  $formAction = $editData ? route('sewa-mobil.finance.update', $editData) : route('sewa-mobil.finance.store');
  $selectedMobilId = old('aset_koperasi_id', $editData?->aset_koperasi_id);
  $selectedKaryawanId = old('karyawan_id', $editData?->karyawan_id);
  $selectedMobil = $mobilOptions->firstWhere('id', (int) $selectedMobilId) ?? $editData?->aset;
  $previewTarif = (int) ($selectedMobil?->harga_dasar_vendor ?? $editData?->tarif_harian_snapshot ?? 0);
  $previewHari = (int) old('jumlah_hari', $editData?->jumlah_hari ?? 0);
  $previewTotal = (int) old('total_sewa', $editData?->total_sewa ?? ($previewTarif * max(0, $previewHari)));
@endphp

<div class="kbsm-business-page">
  @if (session('success'))
    <div class="kbsm-business-alert kbsm-business-alert--success">{{ session('success') }}</div>
  @endif
  @if ($errors->any())
    <div class="kbsm-business-alert kbsm-business-alert--danger">
      <ul class="mb-0 list-disc pl-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
    </div>
  @endif

  <div class="kbsm-business-header kbsm-business-form-header">
    <div>
      <p class="kbsm-business-eyebrow">Usaha Koperasi</p>
      <h1 class="kbsm-business-title">{{ $editData ? 'Edit Draft Sewa Mobil' : 'Tambah Sewa Mobil' }}</h1>
      <p class="kbsm-business-subtitle">Finance mencatat transaksi untuk Karyawan aktif BITA. Tarif dihitung otomatis dari Master Mobil dan disnapshot agar perubahan tarif master tidak mengubah transaksi lama.</p>
    </div>
    <a href="{{ route('sewa-mobil.finance.index') }}" class="kbsm-business-back-link">Kembali ke Daftar Sewa Mobil</a>
  </div>

  <section class="kbsm-business-panel">
    <div class="kbsm-business-panel__header">
      <h2 class="kbsm-business-panel__title">{{ $editData ? 'Edit Draft Sewa Mobil' : 'Buat Draft Sewa Mobil' }}</h2>
      <p class="kbsm-business-panel__copy">Kode sewa otomatis saat draft diajukan. Tanggal menggunakan kalender harian, jumlah hari dihitung inklusif.</p>
    </div>

    <form method="POST" action="{{ $formAction }}" class="kbsm-business-form" data-sewa-mobil-form>
      @csrf
      @if($editData) @method('PUT') @endif

      <section class="kbsm-business-section">
        <h3 class="kbsm-business-section__title">Pemohon</h3>
        <p class="kbsm-business-section__copy">Hanya Karyawan aktif perusahaan BITA yang dapat dibuatkan transaksi sewa mobil.</p>
        <div class="kbsm-business-grid">
          <div class="kbsm-business-field">
            <label class="kbsm-business-label">Karyawan</label>
            <select name="karyawan_id" required class="kbsm-business-control">
              <option value="">Pilih Karyawan aktif</option>
              @foreach($karyawanOptions as $karyawan)
                <option value="{{ $karyawan->id }}" {{ (string) $selectedKaryawanId === (string) $karyawan->id ? 'selected' : '' }}>{{ $karyawan->nama }} - {{ $karyawan->jabatan }}</option>
              @endforeach
            </select>
          </div>
          <div class="kbsm-business-field">
            <label class="kbsm-business-label">Penyewa</label>
            <div class="kbsm-business-readonly">{{ config('koperasi.nama_perusahaan_penyewa', 'Bita Enarcon Engineering') }}</div>
          </div>
        </div>
      </section>

      <section class="kbsm-business-section">
        <h3 class="kbsm-business-section__title">Mobil dan Periode</h3>
        <p class="kbsm-business-section__copy">Dropdown hanya menampilkan mobil tersedia yang memiliki Tarif Sewa Harian valid.</p>
        <div class="kbsm-business-grid">
          <div class="kbsm-business-field">
            <label class="kbsm-business-label">Mobil</label>
            <select name="aset_koperasi_id" required class="kbsm-business-control" data-sewa-mobil-asset>
              <option value="" data-tarif="0">Pilih Mobil</option>
              @foreach($mobilOptions as $mobil)
                <option value="{{ $mobil->id }}" data-tarif="{{ (int) ($mobil->harga_dasar_vendor ?? 0) }}" {{ (string) $selectedMobilId === (string) $mobil->id ? 'selected' : '' }}>
                  {{ $mobil->kode_aset }} - {{ $mobil->merek }} {{ $mobil->model }} (Tarif/Hari: Rp {{ number_format((int) ($mobil->harga_dasar_vendor ?? 0), 0, ',', '.') }})
                </option>
              @endforeach
            </select>
          </div>
          <div class="kbsm-business-field">
            <label class="kbsm-business-label">Status mobil</label>
            <div class="kbsm-business-readonly">{{ $selectedMobil?->status_label ?? 'Pilih mobil untuk melihat status' }}</div>
          </div>
          <div class="kbsm-business-field">
            <label class="kbsm-business-label">Tanggal Mulai</label>
            <input type="date" name="tanggal_mulai" required value="{{ old('tanggal_mulai', $editData?->tanggal_mulai?->toDateString()) }}" class="kbsm-business-control" data-sewa-mobil-start>
          </div>
          <div class="kbsm-business-field">
            <label class="kbsm-business-label">Tanggal Selesai</label>
            <input type="date" name="tanggal_selesai" required value="{{ old('tanggal_selesai', $editData?->tanggal_selesai?->toDateString()) }}" class="kbsm-business-control" data-sewa-mobil-end>
          </div>
        </div>
      </section>

      <section class="kbsm-business-section">
        <h3 class="kbsm-business-section__title">Kalkulasi Tarif</h3>
        <p class="kbsm-business-section__copy">Preview ini dihitung di browser; server tetap menghitung ulang dan menyimpan snapshot final.</p>
        <div class="kbsm-business-summary">
          <div class="kbsm-business-summary-card kbsm-business-summary-card--green">
            <p class="kbsm-business-summary-label">Jumlah Hari</p>
            <p class="kbsm-business-summary-value" data-sewa-mobil-days>{{ $previewHari ?: '-' }}</p>
          </div>
          <div class="kbsm-business-summary-card kbsm-business-summary-card--gold">
            <p class="kbsm-business-summary-label">Tarif Harian Snapshot</p>
            <p class="kbsm-business-summary-value" data-sewa-mobil-tarif>Rp {{ number_format($previewTarif, 0, ',', '.') }}</p>
          </div>
          <div class="kbsm-business-summary-card kbsm-business-summary-card--navy">
            <p class="kbsm-business-summary-label">Total Sewa</p>
            <p class="kbsm-business-summary-value" data-sewa-mobil-total>Rp {{ number_format($previewTotal, 0, ',', '.') }}</p>
          </div>
        </div>
      </section>

      <section class="kbsm-business-section">
        <h3 class="kbsm-business-section__title">Kegiatan dan Keterangan</h3>
        <div class="kbsm-business-grid">
          <div class="kbsm-business-field">
            <label class="kbsm-business-label">Nama Kegiatan</label>
            <input name="nama_kegiatan" required maxlength="150" value="{{ old('nama_kegiatan', $editData?->nama_kegiatan) }}" class="kbsm-business-control" placeholder="Kunjungan proyek">
          </div>
          <div class="kbsm-business-field">
            <label class="kbsm-business-label">Lokasi Kegiatan</label>
            <input name="lokasi_kegiatan" required maxlength="150" value="{{ old('lokasi_kegiatan', $editData?->lokasi_kegiatan) }}" class="kbsm-business-control" placeholder="Area Jakarta">
          </div>
          <div class="kbsm-business-field kbsm-business-field--full">
            <label class="kbsm-business-label">Keterangan</label>
            <textarea name="keterangan" rows="3" maxlength="1000" class="kbsm-business-control" placeholder="Catatan tambahan">{{ old('keterangan', $editData?->keterangan) }}</textarea>
          </div>
        </div>
      </section>

      <section class="kbsm-business-section">
        <h3 class="kbsm-business-section__title">Approval dan Pembayaran</h3>
        <p class="kbsm-business-section__copy">Approval Pengurus dicatat oleh Finance setelah draft diajukan. Pembayaran dilakukan penuh di muka melalui Kas/Bank.</p>
        <div class="kbsm-business-actions">
          <button class="kbsm-btn kbsm-btn--navy">{{ $editData ? 'Simpan Draft' : 'Buat Draft' }}</button>
          @if($editData)
            <a href="{{ route('sewa-mobil.finance.index') }}" class="kbsm-btn kbsm-btn--outline-slate">Batal Edit</a>
          @endif
        </div>
      </section>
    </form>
  </section>
</div>

<script>
  document.addEventListener('DOMContentLoaded', () => {
    const form = document.querySelector('[data-sewa-mobil-form]');
    if (!form) return;

    const rupiah = (value) => new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(value || 0);
    const asset = form.querySelector('[data-sewa-mobil-asset]');
    const start = form.querySelector('[data-sewa-mobil-start]');
    const end = form.querySelector('[data-sewa-mobil-end]');
    const days = form.querySelector('[data-sewa-mobil-days]');
    const tarif = form.querySelector('[data-sewa-mobil-tarif]');
    const total = form.querySelector('[data-sewa-mobil-total]');

    const diffDaysInclusive = () => {
      if (!start.value || !end.value) return 0;
      const startDate = new Date(`${start.value}T00:00:00+07:00`);
      const endDate = new Date(`${end.value}T00:00:00+07:00`);
      if (Number.isNaN(startDate.getTime()) || Number.isNaN(endDate.getTime()) || endDate < startDate) return 0;
      return Math.floor((endDate - startDate) / 86400000) + 1;
    };

    const refresh = () => {
      const selected = asset.options[asset.selectedIndex];
      const tarifHarian = parseInt(selected?.dataset?.tarif || '0', 10) || 0;
      const jumlahHari = diffDaysInclusive();
      days.textContent = jumlahHari || '-';
      tarif.textContent = rupiah(tarifHarian);
      total.textContent = rupiah(tarifHarian * jumlahHari);
    };

    [asset, start, end].forEach((input) => input?.addEventListener('input', refresh));
    refresh();
  });
</script>
@endsection
