@extends('layout.main')

@section('content')
@php
  $rupiah = fn ($value) => 'Rp ' . number_format((float) $value, 0, ',', '.');
@endphp

<div class="kbsm-business-page">
  @if (session('success'))
    <div class="kbsm-business-alert kbsm-business-alert--success">{{ session('success') }}</div>
  @endif

  @if ($errors->any())
    <div class="kbsm-business-alert kbsm-business-alert--danger">
      <ul>
        @foreach ($errors->all() as $error)
          <li>{{ $error }}</li>
        @endforeach
      </ul>
    </div>
  @endif

  <div class="kbsm-business-header kbsm-business-panel__header--action">
    <div>
      <p class="kbsm-business-eyebrow">Master Data</p>
      <h1 class="kbsm-business-title">Master Jenis Simpanan</h1>
      <p class="kbsm-business-subtitle">Konfigurasi final hanya Simpanan Wajib satu kali per siklus dan Simpanan Manasuka. Histori lama tetap tersimpan tanpa hard delete.</p>
    </div>
    <a href="{{ route('jenis-simpanan.create') }}" class="kbsm-business-add-button">+ Tambah Jenis Simpanan</a>
  </div>

  <section class="kbsm-business-summary kbsm-business-summary--simpanan">
    @foreach($kategoriOptions as $kategori => $label)
      <article class="kbsm-business-summary-card {{ $kategori === 'manasuka' ? 'kbsm-business-summary-card--green' : ($kategori === 'wajib' ? 'kbsm-business-summary-card--gold' : 'kbsm-business-summary-card--navy') }}">
        <p class="kbsm-business-summary-label">Master {{ $label }} Aktif</p>
        <p class="kbsm-business-summary-value">{{ number_format((int) ($activeCounts[$kategori] ?? 0), 0, ',', '.') }}</p>
      </article>
    @endforeach
  </section>

  <section class="kbsm-business-panel">
    <div class="kbsm-business-panel__header">
      <h2 class="kbsm-business-panel__title">Daftar Master</h2>
      <p class="kbsm-business-panel__copy">Satu master aktif per kategori dilindungi oleh service dan database.</p>
    </div>

    <div class="kbsm-business-table-wrap">
      <table class="kbsm-business-table">
        <thead>
          <tr>
            <th>Kode</th>
            <th>Nama</th>
            <th>Kategori</th>
            <th>Nominal Default</th>
            <th>Aturan</th>
            <th>COA</th>
            <th>Status</th>
            <th>Riwayat Terakhir</th>
            <th>Aksi</th>
          </tr>
        </thead>
        <tbody>
          @forelse($jenisSimpanan as $jenis)
            <tr>
              <td><span class="kbsm-business-code">{{ $jenis->kode }}</span></td>
              <td>
                <div class="kbsm-business-strong">{{ $jenis->nama_jenis }}</div>
                <div class="kbsm-business-muted">{{ $jenis->keterangan ?: '-' }}</div>
              </td>
              <td>{{ $jenis->kategori_label }}</td>
              <td class="kbsm-business-amount">{{ $rupiah($jenis->nominal_default) }}</td>
              <td>{{ $jenis->frekuensi_label }}</td>
              <td>
                <div class="kbsm-business-strong">{{ $jenis->akun?->kode_akun ?? '-' }}</div>
                <div class="kbsm-business-muted">{{ $jenis->akun?->nama_akun ?? '-' }}</div>
              </td>
              <td>
                <span class="kbsm-status {{ $jenis->aktif ? 'kbsm-status--green' : 'kbsm-status--red' }}">
                  {{ $jenis->aktif ? 'Aktif' : 'Nonaktif' }}
                </span>
              </td>
              <td>
                <div class="kbsm-business-muted">{{ $jenis->latestRiwayat?->changed_at?->format('d/m/Y H:i') ?? '-' }}</div>
                <div class="kbsm-business-muted">{{ $jenis->latestRiwayat?->alasan ?? '-' }}</div>
              </td>
              <td>
                <a href="{{ route('jenis-simpanan.edit', $jenis) }}" class="kbsm-btn kbsm-btn--outline-slate">Edit</a>
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="9" class="kbsm-business-empty">Belum ada Master Jenis Simpanan.</td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>

    <div class="kbsm-business-pagination">
      {{ $jenisSimpanan->links() }}
    </div>
  </section>
</div>
@endsection
