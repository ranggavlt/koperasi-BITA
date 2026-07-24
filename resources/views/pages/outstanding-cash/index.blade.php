@extends('layout.main')

@section('content')
@php
  $money = fn ($value) => 'Rp ' . number_format((float) $value, 0, ',', '.');
@endphp

<div class="kbsm-business-page">
  <div class="kbsm-business-header">
    <div>
      <p class="kbsm-business-eyebrow">Laporan Finance</p>
      <h1 class="kbsm-business-title">Outstanding Cash Mantan Karyawan</h1>
      <p class="kbsm-business-subtitle">Laporan read-only kewajiban tunai dari POS, Simpanan Pokok, dan Cicilan Pinjaman. Pembayaran Pinjaman dilakukan dari detail Pinjaman/service resmi.</p>
    </div>
  </div>

  <section class="kbsm-business-panel">
    <div class="kbsm-business-panel__header">
      <h2 class="kbsm-business-panel__title">Filter Outstanding Cash</h2>
    </div>
    <form method="GET" class="kbsm-business-filter kbsm-business-filter--compact">
      <div class="kbsm-business-field">
        <label class="kbsm-business-label">Status</label>
        <select name="status" class="kbsm-business-control">
          <option value="">Semua status</option>
          @foreach(['outstanding_cash' => 'Belum Diselesaikan', 'settled_cash' => 'Selesai Tunai'] as $status => $label)
            <option value="{{ $status }}" @selected(request('status') === $status)>{{ $label }}</option>
          @endforeach
        </select>
      </div>
      <div class="kbsm-business-field">
        <label class="kbsm-business-label">Anggota</label>
        <select name="anggota_id" class="kbsm-business-control">
          <option value="">Semua Anggota</option>
          @foreach($anggotaOptions as $anggota)
            <option value="{{ $anggota->id }}" @selected(request('anggota_id') == $anggota->id)>{{ $anggota->nomor_anggota }} - {{ $anggota->karyawan?->nama }}</option>
          @endforeach
        </select>
      </div>
      <div class="kbsm-business-filter__actions kbsm-business-filter__actions--split">
        <button class="kbsm-btn kbsm-btn--navy">Filter</button>
        <a href="{{ route('outstanding-cash.index') }}" class="kbsm-btn kbsm-btn--outline-slate">Reset</a>
      </div>
    </form>
  </section>

  <section class="kbsm-business-summary kbsm-business-summary--form">
    <article class="kbsm-business-summary-card kbsm-business-summary-card--red">
      <span class="kbsm-business-summary-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M12 2 1 21h22L12 2Zm1 16h-2v-2h2v2Zm0-4h-2V8h2v6Z"/></svg></span>
      <p class="kbsm-business-summary-label">Total Belum Diselesaikan</p>
      <p class="kbsm-business-summary-value">{{ $money($summary['total_outstanding']) }}</p>
    </article>
    <article class="kbsm-business-summary-card kbsm-business-summary-card--green">
      <span class="kbsm-business-summary-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="m9 16.2-3.5-3.5L4 14.2l5 5L20.5 7.7 19 6.2 9 16.2Z"/></svg></span>
      <p class="kbsm-business-summary-label">Total Dibayar</p>
      <p class="kbsm-business-summary-value">{{ $money($summary['total_dibayar']) }}</p>
    </article>
    <article class="kbsm-business-summary-card kbsm-business-summary-card--navy">
      <span class="kbsm-business-summary-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M4 4h16v16H4V4Zm3 4v2h10V8H7Zm0 4v2h10v-2H7Z"/></svg></span>
      <p class="kbsm-business-summary-label">Jumlah Sumber</p>
      <p class="kbsm-business-summary-value">{{ $summary['jumlah_sumber'] }}</p>
    </article>
  </section>

  <section class="kbsm-business-panel">
    <div class="kbsm-business-panel__header">
      <h2 class="kbsm-business-panel__title">Daftar Outstanding</h2>
      <p class="kbsm-business-panel__copy">Cicilan Anggota aktif yang masih eligible payroll tidak dimasukkan ke outstanding tunai.</p>
    </div>
    <div class="kbsm-business-table-wrap">
      <table class="kbsm-business-table">
        <thead>
          <tr>
            <th>Anggota/Karyawan</th>
            <th>Sumber</th>
            <th>Kode</th>
            <th>Detail</th>
            <th class="kbsm-business-table__right">Nominal Awal</th>
            <th class="kbsm-business-table__right">Dibayar</th>
            <th class="kbsm-business-table__right">Sisa</th>
            <th>Status</th>
            <th>Aksi</th>
          </tr>
        </thead>
        <tbody>
          @forelse($rows as $row)
            <tr>
              <td>
                <span class="kbsm-business-strong">{{ $row->anggota?->nomor_anggota ?? '-' }}</span>
                <div class="kbsm-business-muted">{{ $row->karyawan?->nama ?? '-' }} Â· {{ ucfirst($row->karyawan?->status_kerja ?? '-') }}</div>
              </td>
              <td>{{ $row->kelompok }}</td>
              <td><span class="kbsm-business-code">{{ $row->kode_transaksi }}</span></td>
              <td>
                @if(($row->kelompok ?? '') === 'Cicilan Pinjaman')
                  <span class="kbsm-business-muted">
                    {{ $row->siklus_lama ? 'Siklus lama' : 'Tidak eligible payroll' }} Â·
                    jadwal terbuka {{ $row->jumlah_jadwal_terbuka }} Â·
                    tertua {{ optional($row->jadwal_tertua)->format('Y-m') ?? '-' }}
                  </span>
                @else
                  <span class="kbsm-business-muted">{{ optional($row->tanggal)->format('d/m/Y') ?? '-' }}</span>
                @endif
              </td>
              <td class="kbsm-business-amount">{{ $money($row->nominal_awal) }}</td>
              <td class="kbsm-business-amount">{{ $money($row->nominal_dibayar) }}</td>
              <td class="kbsm-business-amount">{{ $money($row->sisa) }}</td>
              <td><span class="kbsm-status {{ ($row->sisa ?? 0) > 0 ? 'kbsm-status--red' : 'kbsm-status--green' }}">{{ $row->status_label ?? $row->status }}</span></td>
              <td>
                @if(!empty($row->detail_route))
                  <a href="{{ $row->detail_route }}" class="kbsm-btn kbsm-btn--outline-navy kbsm-btn--sm">Detail Pinjaman</a>
                @else
                  <span class="kbsm-business-muted">Lihat sumber transaksi</span>
                @endif
              </td>
            </tr>
          @empty
            <tr><td colspan="9" class="kbsm-business-empty">Tidak ada outstanding cash sesuai filter.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </section>
</div>
@endsection
