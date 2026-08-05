@extends('layout.main')

@section('content')
@php
  $rupiahInt = function ($value): int {
    if (is_int($value)) {
      return $value;
    }
    $normalized = trim((string) ($value ?? '0'));
    $negative = str_starts_with($normalized, '-');
    $normalized = ltrim($normalized, '+-');
    [$whole] = array_pad(explode('.', $normalized, 2), 1, '0');
    $whole = preg_replace('/\D/', '', $whole) ?: '0';
    $rupiah = (int) $whole;

    return $negative ? -1 * $rupiah : $rupiah;
  };
  $money = fn($value) => 'Rp ' . number_format($rupiahInt($value), 0, ',', '.');
  $statusClass = fn(string $status) => match ($status) {
    \App\Models\PenyelesaianKeanggotaan::STATUS_PENDING_REVIEW => 'kbsm-status--gold',
    \App\Models\PenyelesaianKeanggotaan::STATUS_WAITING_SETTLEMENT => 'kbsm-status--red',
    \App\Models\PenyelesaianKeanggotaan::STATUS_READY_TO_COMPLETE => 'kbsm-status--navy',
    \App\Models\PenyelesaianKeanggotaan::STATUS_COMPLETED => 'kbsm-status--green',
    default => 'kbsm-status--slate',
  };
@endphp

<div class="kbsm-business-page">
  @if (session('success'))
    <div class="kbsm-business-alert kbsm-business-alert--success">{{ session('success') }}</div>
  @endif
  @if ($errors->any())
    <div class="kbsm-business-alert kbsm-business-alert--danger">
      <ul>
        @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
      </ul>
    </div>
  @endif

  <div class="kbsm-business-header">
    <div>
      <p class="kbsm-business-eyebrow">Keanggotaan</p>
      <h1 class="kbsm-business-title">Penyelesaian Anggota Keluar</h1>
      <p class="kbsm-business-subtitle">Selesaikan uang milik Anggota, sisa utang, dan pengembalian dana ketika keanggotaan berakhir.</p>
    </div>
  </div>

  <section class="kbsm-business-panel">
    <div class="kbsm-business-panel__header">
      <h2 class="kbsm-business-panel__title">Filter Penyelesaian</h2>
      <p class="kbsm-business-panel__copy">Cari proses keluar berdasarkan Anggota, status, dan tanggal.</p>
    </div>
    <form method="GET" action="{{ route('penyelesaian-keanggotaan.index') }}" class="kbsm-business-filter kbsm-business-filter--simpanan">
      <div class="kbsm-business-field">
        <label class="kbsm-business-label">Anggota</label>
        <input name="anggota" value="{{ $filters['anggota'] ?? '' }}" class="kbsm-business-control" placeholder="Nomor anggota / nama karyawan">
      </div>
      <div class="kbsm-business-field">
        <label class="kbsm-business-label">Status</label>
        <select name="status" class="kbsm-business-control">
          <option value="">Semua Status</option>
          @foreach($statuses as $status)
            <option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>
              {{ (new \App\Models\PenyelesaianKeanggotaan(['status' => $status]))->status_label }}
            </option>
          @endforeach
        </select>
      </div>
      <div class="kbsm-business-field">
        <label class="kbsm-business-label">Tanggal Keluar Mulai</label>
        <input type="date" name="tanggal_mulai" value="{{ $filters['tanggal_mulai'] ?? '' }}" class="kbsm-business-control">
      </div>
      <div class="kbsm-business-field">
        <label class="kbsm-business-label">Tanggal Keluar Selesai</label>
        <input type="date" name="tanggal_selesai" value="{{ $filters['tanggal_selesai'] ?? '' }}" class="kbsm-business-control">
      </div>
      <div class="kbsm-business-filter__actions kbsm-business-filter__actions--split">
        <button class="kbsm-btn kbsm-btn--navy">Filter</button>
        <a href="{{ route('penyelesaian-keanggotaan.index') }}" class="kbsm-btn kbsm-btn--outline-slate">Reset</a>
      </div>
    </form>
  </section>

  <section class="kbsm-business-summary kbsm-business-summary--simpanan">
    <article class="kbsm-business-summary-card kbsm-business-summary-card--green">
      <p class="kbsm-business-summary-label">Uang Milik Anggota</p>
      <p class="kbsm-business-summary-value">{{ $money($summary['total_hak'] ?? 0) }}</p>
    </article>
    <article class="kbsm-business-summary-card kbsm-business-summary-card--red">
      <p class="kbsm-business-summary-label">Sisa Utang Anggota</p>
      <p class="kbsm-business-summary-value">{{ $money($summary['total_kewajiban'] ?? 0) }}</p>
    </article>
    <article class="kbsm-business-summary-card kbsm-business-summary-card--navy">
      <p class="kbsm-business-summary-label">Dipakai Membayar Utang</p>
      <p class="kbsm-business-summary-value">{{ $money($summary['total_offset'] ?? 0) }}</p>
    </article>
    <article class="kbsm-business-summary-card kbsm-business-summary-card--gold">
      <p class="kbsm-business-summary-label">Dikembalikan ke Anggota</p>
      <p class="kbsm-business-summary-value">{{ $money($summary['total_refund'] ?? 0) }}</p>
    </article>
  </section>

  <section class="kbsm-business-panel">
    <div class="kbsm-business-panel__header">
      <h2 class="kbsm-business-panel__title">Daftar Anggota Keluar</h2>
      <p class="kbsm-business-panel__copy">Kode proses dan rincian audit tersedia pada halaman detail.</p>
    </div>

    <div class="kbsm-business-table-wrap">
      <table class="kbsm-business-table">
        <thead>
          <tr>
            <th>Anggota</th>
            <th>Tanggal Keluar</th>
            <th class="kbsm-business-table__right">Uang Milik</th>
            <th class="kbsm-business-table__right">Sisa Utang</th>
            <th class="kbsm-business-table__right">Dipakai Bayar Utang</th>
            <th class="kbsm-business-table__right">Dikembalikan</th>
            <th>Status</th>
            <th>Aksi</th>
          </tr>
        </thead>
        <tbody>
          @forelse($penyelesaianList as $penyelesaian)
            <tr>
              <td>
                <div class="kbsm-business-strong">{{ $penyelesaian->anggota?->nomor_anggota }}</div>
                <div class="kbsm-business-muted">{{ $penyelesaian->anggota?->karyawan?->nama }}</div>
              </td>
              <td>{{ $penyelesaian->tanggal_keluar?->format('d/m/Y') }}</td>
              <td class="kbsm-business-amount">{{ $money($penyelesaian->total_hak_anggota) }}</td>
              <td class="kbsm-business-amount">{{ $money($penyelesaian->sisa_kewajiban) }}</td>
              <td class="kbsm-business-amount">{{ $money($penyelesaian->total_offset) }}</td>
              <td class="kbsm-business-amount">{{ $money($penyelesaian->total_refund) }}</td>
              <td><span class="kbsm-status {{ $statusClass($penyelesaian->status) }}">{{ $penyelesaian->status_label }}</span></td>
              <td>
                <a href="{{ route('penyelesaian-keanggotaan.show', $penyelesaian) }}" class="kbsm-btn kbsm-btn--outline-slate">Detail</a>
              </td>
            </tr>
          @empty
            <tr><td colspan="8" class="kbsm-business-empty">Belum ada Anggota yang perlu menyelesaikan proses keluar.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
    <div class="kbsm-business-pagination">{{ $penyelesaianList->links() }}</div>
  </section>
</div>
@endsection
