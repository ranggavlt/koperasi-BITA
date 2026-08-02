@extends('layout.main')

@section('content')
@php
  $fmt = fn ($value) => 'Rp ' . number_format((float) $value, 0, ',', '.');
  $statusClass = function (?string $status): string {
      return match ($status) {
          \App\Models\JadwalSimpananWajib::STATUS_SETTLED => 'kbsm-status kbsm-status--green',
          \App\Models\JadwalSimpananWajib::STATUS_RESERVED => 'kbsm-status kbsm-status--blue',
          \App\Models\JadwalSimpananWajib::STATUS_OUTSTANDING => 'kbsm-status kbsm-status--amber',
          default => 'kbsm-status kbsm-status--slate',
      };
  };
@endphp

<div class="kbsm-business-page">
  @if ($errors->any())
    <div class="kbsm-business-alert kbsm-business-alert--danger">
      <ul class="mb-0 list-disc pl-5">
        @foreach ($errors->all() as $error)
          <li>{{ $error }}</li>
        @endforeach
      </ul>
    </div>
  @endif

  <div class="kbsm-business-header">
    <div>
      <p class="kbsm-business-eyebrow">Simpan Pinjam</p>
      <h1 class="kbsm-business-title">Histori Jadwal Wajib Lama</h1>
      <p class="kbsm-business-subtitle">Read-only histori Wajib berkala legacy. SP-7 tidak lagi membuat jadwal Wajib baru; Simpanan Wajib final dibuat satu kali per siklus.</p>
    </div>
  </div>

  <section class="kbsm-business-panel">
    <div class="kbsm-business-panel__header">
      <h2 class="kbsm-business-panel__title">Filter Histori Jadwal Legacy</h2>
      <p class="kbsm-business-panel__copy">Gunakan filter hanya untuk membaca histori lama, tanpa membuat, mengedit, atau menghapus jadwal.</p>
    </div>
    <form method="GET" action="{{ route('jadwal-simpanan-wajib.index') }}" class="kbsm-business-filter kbsm-business-filter--wajib">
      <div class="kbsm-business-field">
        <label class="kbsm-business-label" for="periode_mulai">Periode Mulai</label>
        <input id="periode_mulai" type="month" name="periode_mulai" value="{{ old('periode_mulai', $filters['periode_mulai'] ?? '') }}" class="kbsm-business-control">
      </div>
      <div class="kbsm-business-field">
        <label class="kbsm-business-label" for="periode_selesai">Periode Selesai</label>
        <input id="periode_selesai" type="month" name="periode_selesai" value="{{ old('periode_selesai', $filters['periode_selesai'] ?? '') }}" class="kbsm-business-control">
      </div>
      <div class="kbsm-business-field">
        <label class="kbsm-business-label" for="anggota_id">Anggota</label>
        <select id="anggota_id" name="anggota_id" class="kbsm-business-control">
          <option value="">Semua Anggota</option>
          @foreach($anggotaOptions as $anggota)
            <option value="{{ $anggota->id }}" @selected(($filters['anggota_id'] ?? '') == $anggota->id)>
              {{ $anggota->nomor_anggota }} - {{ $anggota->karyawan?->nama }}
            </option>
          @endforeach
        </select>
      </div>
      <div class="kbsm-business-field">
        <label class="kbsm-business-label" for="status">Status</label>
        <select id="status" name="status" class="kbsm-business-control">
          <option value="">Semua Status</option>
          @foreach($statusOptions as $key => $label)
            <option value="{{ $key }}" @selected(($filters['status'] ?? '') === $key)>{{ $label }}</option>
          @endforeach
        </select>
      </div>
      <div class="kbsm-business-filter__actions kbsm-business-filter__actions--split">
        <button class="kbsm-btn kbsm-btn--green">Filter</button>
        <a href="{{ route('jadwal-simpanan-wajib.index') }}" class="kbsm-btn kbsm-btn--outline-slate">Reset</a>
      </div>
    </form>
  </section>

  <section class="kbsm-business-summary kbsm-business-summary--wajib">
    <div class="kbsm-business-summary-card kbsm-business-summary-card--navy">
      <p class="kbsm-business-summary-label">Total Tagihan Legacy</p>
      <p class="kbsm-business-summary-value">{{ $fmt($summary['total_tagihan']) }}</p>
    </div>
    <div class="kbsm-business-summary-card kbsm-business-summary-card--gold">
      <p class="kbsm-business-summary-label">Sudah Dialokasikan</p>
      <p class="kbsm-business-summary-value">{{ $fmt($summary['sudah_dialokasikan']) }}</p>
    </div>
    <div class="kbsm-business-summary-card kbsm-business-summary-card--green">
      <p class="kbsm-business-summary-label">Sudah Dibayar</p>
      <p class="kbsm-business-summary-value">{{ $fmt($summary['sudah_dibayar']) }}</p>
    </div>
    <div class="kbsm-business-summary-card kbsm-business-summary-card--gold">
      <p class="kbsm-business-summary-label">Tunggakan Legacy</p>
      <p class="kbsm-business-summary-value">{{ $fmt($summary['tunggakan']) }}</p>
    </div>
  </section>

  <section class="kbsm-business-panel">
    <div class="kbsm-business-panel__header">
      <h2 class="kbsm-business-panel__title">Daftar Histori Jadwal Wajib Legacy</h2>
      <p class="kbsm-business-panel__copy">Tidak tersedia tombol create/edit/delete manual. Jadwal ini tidak lagi menjadi sumber transaksi baru setelah SP-7.</p>
    </div>
    <div class="kbsm-business-table-wrap">
      <table class="kbsm-business-table kbsm-business-table--wajib">
        <thead>
          <tr>
            <th>Kode</th>
            <th>Anggota</th>
            <th>Periode</th>
            <th>Nominal</th>
            <th>Snapshot Frekuensi</th>
            <th>Status</th>
            <th>Payroll/Limit</th>
            <th>Tanggal Lunas</th>
            <th>Keterangan</th>
          </tr>
        </thead>
        <tbody>
          @forelse($jadwal as $row)
            @php
              $ledger = $row->activeLedger ?? $row->simpanan?->ledger;
              $limit = $ledger?->limit;
            @endphp
            <tr>
              <td><span class="kbsm-business-code">{{ $row->kode_tagihan }}</span></td>
              <td>
                <div class="kbsm-business-strong">{{ $row->anggota?->nomor_anggota ?? '-' }}</div>
                <div class="kbsm-business-muted">{{ $row->anggota?->karyawan?->nama ?? '-' }}</div>
              </td>
              <td>{{ $row->periode?->format('M Y') }}</td>
              <td class="kbsm-business-table__right"><span class="kbsm-business-amount">{{ $fmt($row->nominal_snapshot) }}</span></td>
              <td>Setiap {{ (int) $row->interval_bulan_snapshot }} bulan</td>
              <td><span class="{{ $statusClass($row->status) }}">{{ $row->status_label }}</span></td>
              <td>
                @if($limit)
                  <span class="kbsm-business-code">{{ $limit->periodePotongGaji?->periode?->format('Y-m') }}</span>
                  <div class="kbsm-business-muted">{{ str_replace('_', ' ', $limit->status) }}</div>
                @else
                  <span class="kbsm-business-muted">Belum dialokasikan</span>
                @endif
              </td>
              <td>{{ $row->settled_at?->format('d/m/Y H:i') ?? '-' }}</td>
              <td>
                <span class="kbsm-business-muted">{{ $row->simpanan?->keterangan ?: '-' }}</span>
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="9" class="kbsm-business-empty">Belum ada histori Jadwal Wajib lama sesuai filter.</td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>
    <div class="kbsm-business-pagination">
      {{ $jadwal->links() }}
    </div>
  </section>
</div>
@endsection
