@extends('layout.main')

@section('content')
<div class="kbsm-business-page">
  <div class="kbsm-business-header kbsm-business-form-header">
    <div>
      <p class="kbsm-business-eyebrow">Payroll & Cicilan</p>
      <h1 class="kbsm-business-title">Buat Periode Potong Gaji</h1>
      <p class="kbsm-business-subtitle">Pilih bulan dan tahun untuk membuka sesi pemotongan gaji (payroll) yang baru.</p>
    </div>
    <a href="{{ route('periode-potong-gaji.index') }}" class="kbsm-business-back-link">Kembali ke Daftar Periode</a>
  </div>

  <section class="kbsm-business-panel">
    <div class="kbsm-business-panel__header">
      <h2 class="kbsm-business-panel__title">Setup Periode Baru</h2>
      <p class="kbsm-business-panel__copy">Sistem akan secara otomatis memverifikasi ketersediaan jadwal cicilan dan mencegah duplikasi tagihan.</p>
    </div>

    <form method="POST" action="{{ route('periode-potong-gaji.store') }}" class="kbsm-business-form">
      @csrf

      <section class="kbsm-business-section">
        <h3 class="kbsm-business-section__title">Detail Waktu</h3>
        <p class="kbsm-business-section__copy">Pilih bulan jatuh tempo atau penyerahan payroll kepada finance. Pastikan periode sebelumnya sudah terselesaikan (close) untuk menghindari bentrok cicilan.</p>

        <div class="kbsm-business-grid">
          <div class="kbsm-business-field">
            <label class="kbsm-business-label">Bulan & Tahun Payroll <span class="text-red-500">*</span></label>
            <input type="month" name="periode" required value="{{ old('periode', now(config('app.timezone'))->format('Y-m')) }}"
              class="kbsm-business-control">
            <p class="text-xs text-slate-400 mt-2">Contoh: Januari 2026 berarti untuk pemotongan gaji yang dibayarkan akhir Januari.</p>
          </div>
        </div>
      </section>

      <section class="kbsm-business-section">
        <h3 class="kbsm-business-section__title">Sistem Automasi</h3>
        <div class="kbsm-business-summary kbsm-business-summary--form">
          <article class="kbsm-business-summary-card kbsm-business-summary-card--blue">
            <p class="kbsm-business-summary-label">Limit & Kuota</p>
            <p class="kbsm-business-summary-value">Slot Terbuka</p>
            <p class="kbsm-business-muted">Membuat periode ini akan membuka slot baru untuk pengisian limit potong gaji seluruh anggota aktif.</p>
          </article>
          <article class="kbsm-business-summary-card kbsm-business-summary-card--emerald">
            <p class="kbsm-business-summary-label">Deteksi Cicilan</p>
            <p class="kbsm-business-summary-value">Otomatis</p>
            <p class="kbsm-business-muted">Sistem akan otomatis mendeteksi cicilan pinjaman berjalan yang jatuh tempo pada bulan ini.</p>
          </article>
        </div>
        
        <div class="kbsm-business-actions mt-8">
          <button type="submit" class="kbsm-btn kbsm-btn--navy">Buat & Siapkan Periode</button>
          <a href="{{ route('periode-potong-gaji.index') }}" class="kbsm-btn kbsm-btn--outline-slate">Batal</a>
        </div>
      </section>
    </form>
  </section>
</div>
@endsection
