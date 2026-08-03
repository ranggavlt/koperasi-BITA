@extends('layout.main')

@section('content')
@php
  $money = fn ($value) => 'Rp ' . number_format((float) $value, 0, ',', '.');
  $period = fn ($value) => $value ? \Carbon\Carbon::parse($value)->format('Y-m') : '-';
  $summaryCards = [
    ['label' => 'Jatuh Tempo', 'value' => $summary['jatuh_tempo'], 'accent' => 'gold', 'icon' => 'calendar'],
    ['label' => 'Tertunggak', 'value' => $summary['tertunggak'], 'accent' => 'red', 'icon' => 'alert'],
    ['label' => 'Dicadangkan Payroll', 'value' => $summary['dicadangkan_payroll'], 'accent' => 'navy', 'icon' => 'lock'],
    ['label' => 'Sudah Dibayar', 'value' => $summary['sudah_dibayar'], 'accent' => 'green', 'icon' => 'check'],
    ['label' => 'Total Sisa', 'value' => $summary['total_sisa'], 'accent' => 'navy', 'icon' => 'wallet'],
  ];
  $icon = function (string $name): string {
    return match ($name) {
      'calendar' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M7 2h2v3H7V2Zm8 0h2v3h-2V2ZM4 5h16a2 2 0 0 1 2 2v13a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2Zm0 6v9h16v-9H4Z"/></svg>',
      'alert' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2 1 21h22L12 2Zm1 16h-2v-2h2v2Zm0-4h-2V8h2v6Z"/></svg>',
      'lock' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M17 9h1a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2v-9a2 2 0 0 1 2-2h1V7a5 5 0 0 1 10 0v2Zm-2 0V7a3 3 0 0 0-6 0v2h6Z"/></svg>',
      'check' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="m9 16.2-3.5-3.5L4 14.2l5 5L20.5 7.7 19 6.2 9 16.2Z"/></svg>',
      default => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 6h18v12H3V6Zm2 2v8h14V8H5Zm2 2h4v2H7v-2Z"/></svg>',
    };
  };
@endphp

<div class="kbsm-business-page">
  <div class="kbsm-business-header">
    <div>
      <p class="kbsm-business-eyebrow">Simpan Pinjam</p>
      <h1 class="kbsm-business-title">Cicilan Pinjaman</h1>
      <p class="kbsm-business-subtitle">Laporan read-only jadwal Cicilan, tunggakan, reservasi payroll, pembayaran payroll/tunai, dan sisa tagihan.</p>
    </div>
  </div>

  <section class="kbsm-business-panel">
    <div class="kbsm-business-panel__header">
      <h2 class="kbsm-business-panel__title">Filter Cicilan Pinjaman</h2>
      <p class="kbsm-business-panel__copy">Filter ini hanya membaca data jadwal, ledger payroll, dan bukti pembayaran.</p>
    </div>
    <form method="GET" action="{{ route('cicilan-pinjaman.index') }}" class="kbsm-business-filter kbsm-business-filter--cicilan">
      <div class="kbsm-business-field">
        <label class="kbsm-business-label" for="anggota_id">Anggota</label>
        <select id="anggota_id" name="anggota_id" class="kbsm-business-control">
          <option value="">Semua Anggota</option>
          @foreach($anggotaOptions as $anggota)
            <option value="{{ $anggota->id }}" @selected(($filters['anggota_id'] ?? null) == $anggota->id)>
              {{ $anggota->nomor_anggota }} - {{ $anggota->karyawan?->nama }}
            </option>
          @endforeach
        </select>
      </div>
      <div class="kbsm-business-field">
        <label class="kbsm-business-label" for="pinjaman_id">Pinjaman</label>
        <select id="pinjaman_id" name="pinjaman_id" class="kbsm-business-control">
          <option value="">Semua Pinjaman</option>
          @foreach($pinjamanOptions as $pinjaman)
            <option value="{{ $pinjaman->id }}" @selected(($filters['pinjaman_id'] ?? null) == $pinjaman->id)>
              {{ $pinjaman->kode_pinjaman }} - {{ $pinjaman->anggota?->karyawan?->nama }}
            </option>
          @endforeach
        </select>
      </div>
      <div class="kbsm-business-field">
        <label class="kbsm-business-label" for="status">Status</label>
        <select id="status" name="status" class="kbsm-business-control">
          <option value="">Semua Status</option>
          @foreach($statusOptions as $value => $label)
            <option value="{{ $value }}" @selected(($filters['status'] ?? null) === $value)>{{ $label }}</option>
          @endforeach
        </select>
      </div>
      <div class="kbsm-business-field">
        <label class="kbsm-business-label" for="periode_mulai">Periode Mulai</label>
        <input id="periode_mulai" name="periode_mulai" type="month" value="{{ isset($filters['periode_mulai']) ? \Carbon\Carbon::parse($filters['periode_mulai'])->format('Y-m') : '' }}" class="kbsm-business-control">
      </div>
      <div class="kbsm-business-field">
        <label class="kbsm-business-label" for="periode_selesai">Periode Selesai</label>
        <input id="periode_selesai" name="periode_selesai" type="month" value="{{ isset($filters['periode_selesai']) ? \Carbon\Carbon::parse($filters['periode_selesai'])->format('Y-m') : '' }}" class="kbsm-business-control">
      </div>
      <div class="kbsm-business-filter__actions kbsm-business-filter__actions--split">
        <button class="kbsm-btn kbsm-btn--navy">Filter</button>
        <a href="{{ route('cicilan-pinjaman.index') }}" class="kbsm-btn kbsm-btn--outline-slate">Reset</a>
      </div>
    </form>
  </section>

  <section class="kbsm-business-summary kbsm-business-summary--wajib">
    @foreach($summaryCards as $card)
      <article class="kbsm-business-summary-card kbsm-business-summary-card--{{ $card['accent'] }}">
        <span class="kbsm-business-summary-icon">{!! $icon($card['icon']) !!}</span>
        <p class="kbsm-business-summary-label">{{ $card['label'] }}</p>
        <p class="kbsm-business-summary-value">{{ $money($card['value']) }}</p>
      </article>
    @endforeach
  </section>

  <section class="kbsm-business-panel">
    <div class="kbsm-business-panel__header">
      <h2 class="kbsm-business-panel__title">Daftar Cicilan Pinjaman</h2>
      <p class="kbsm-business-panel__copy">Satu jadwal dihitung satu kali; payroll dibaca dari ledger aktif/settled terkait jadwal.</p>
    </div>
    <div class="kbsm-business-table-wrap">
      <table class="kbsm-business-table">
        <thead>
          <tr>
            <th>Kode Pinjaman</th>
            <th>Anggota</th>
            <th>Periode</th>
            <th class="kbsm-business-table__right">Pokok Awal</th>
            <th class="kbsm-business-table__right">Offset</th>
            <th class="kbsm-business-table__right">Sisa Tagihan</th>
            <th>Status Cicilan</th>
            <th>Status Payroll</th>
            <th>Metode/Tanggal Bayar</th>
            <th>Aksi</th>
          </tr>
        </thead>
        <tbody>
          @forelse($jadwalCicilan as $row)
            <tr>
              <td>
                <span class="kbsm-business-code">{{ $row->kode_pinjaman ?? '-' }}</span>
                <div class="kbsm-business-muted">Angsuran ke-{{ $row->jadwal->angsuran_ke }}</div>
              </td>
              <td>
                <span class="kbsm-business-strong">{{ $row->anggota?->nomor_anggota ?? '-' }}</span>
                <div class="kbsm-business-muted">{{ $row->karyawan?->nama ?? '-' }}</div>
              </td>
              <td>{{ $period($row->periode) }}</td>
              <td class="kbsm-business-amount">{{ $money($row->nominal_pokok) }}</td>
              <td class="kbsm-business-amount">{{ $money($row->nominal_offset) }}</td>
              <td class="kbsm-business-amount">{{ $money($row->nominal_sisa) }}</td>
              <td><span class="{{ $row->status_class }}">{{ $row->status_label }}</span></td>
              <td>
                <span class="{{ $row->payroll_status_class }}">{{ $row->payroll_status_label }}</span>
                @if($row->payroll_nominal > 0)
                  <div class="kbsm-business-muted">{{ $money($row->payroll_nominal) }}</div>
                @endif
              </td>
              <td>
                <span class="kbsm-business-strong">{{ $row->metode_pembayaran_label }}</span>
                <div class="kbsm-business-muted">{{ optional($row->tanggal_pembayaran)->format('d/m/Y') ?? '-' }}</div>
              </td>
              <td>
                <a href="{{ route('pinjaman.show', $row->pinjaman) }}" class="kbsm-btn kbsm-btn--outline-navy kbsm-btn--sm">Detail Pinjaman</a>
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="10" class="kbsm-business-empty">Tidak ada jadwal cicilan sesuai filter.</td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>

    <div class="kbsm-business-pagination">
      {{ $jadwalCicilan->withQueryString()->links() }}
    </div>
  </section>
</div>
@endsection
