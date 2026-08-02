@extends('layout.main')

@section('content')
@php
  $money = fn ($value) => 'Rp ' . number_format((float) $value, 0, ',', '.');
  $cards = [
    ['label' => 'Total Potongan Tercatat', 'value' => $summary['gross_payroll'], 'accent' => 'green'],
    ['label' => 'Kredit/Refund', 'value' => $summary['kredit_refund'], 'accent' => 'gold'],
    ['label' => 'Nilai Bersih Payroll', 'value' => $summary['net_payroll'], 'accent' => 'navy'],
    ['label' => 'Diterima Bank', 'value' => $summary['total_diterima_bank'], 'accent' => 'green'],
    ['label' => 'Belum Diselesaikan', 'value' => $summary['total_outstanding'], 'accent' => 'red'],
    ['label' => 'Dilepas/Dikoreksi', 'value' => $summary['total_released_reversed'], 'accent' => 'gold'],
  ];
@endphp

<div class="kbsm-business-page">
  <div class="kbsm-business-header">
    <div>
      <p class="kbsm-business-eyebrow">Laporan Finance</p>
      <h1 class="kbsm-business-title">Laporan Potong Gaji Bulanan</h1>
      <p class="kbsm-business-subtitle">Read model dari periode, limit, ledger pemakaian, kredit refund, pembayaran, Mutasi Kas, dan Jurnal. Pinjaman baru tidak dihitung; hanya Cicilan yang sudah menjadi kewajiban.</p>
    </div>
    <a href="{{ route('rekonsiliasi-potong-gaji.index', ['periode' => $periode]) }}" class="kbsm-business-back-link">Rekonsiliasi</a>
  </div>

  <section class="kbsm-business-panel">
    <div class="kbsm-business-panel__header">
      <h2 class="kbsm-business-panel__title">Filter Laporan</h2>
      <p class="kbsm-business-panel__copy">Filter mempertahankan query saat berpindah halaman.</p>
    </div>
    <form method="GET" action="{{ route('laporan.potong-gaji') }}" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4 items-end p-5 border-b border-slate-100">
      <div class="kbsm-business-field !mb-0">
        <label class="kbsm-business-label">Periode</label>
        <input type="month" name="periode" value="{{ $periode }}" class="kbsm-business-control">
      </div>
      <div class="kbsm-business-field !mb-0">
        <label class="kbsm-business-label">Anggota</label>
        <select name="anggota_id" class="kbsm-business-control">
          <option value="">Semua</option>
          @foreach($anggotaOptions as $anggota)
            <option value="{{ $anggota->id }}" @selected(request('anggota_id') == $anggota->id)>
              {{ $anggota->nomor_anggota }} - {{ $anggota->karyawan?->nama }}
            </option>
          @endforeach
        </select>
      </div>
      <div class="kbsm-business-field !mb-0">
        <label class="kbsm-business-label">Status</label>
        <select name="status" class="kbsm-business-control">
          <option value="">Semua</option>
          @foreach(['draft','active','closed_pending_confirmation','confirmed','cancelled'] as $status)
            <option value="{{ $status }}" @selected(request('status') === $status)>{{ $status }}</option>
          @endforeach
        </select>
      </div>
      <div class="kbsm-business-field !mb-0">
        <label class="kbsm-business-label">Kategori</label>
        <select name="kategori" class="kbsm-business-control">
          <option value="">Semua</option>
          @foreach($kategoriOptions as $key => $label)
            <option value="{{ $key }}" @selected(request('kategori') === $key)>{{ $label }}</option>
          @endforeach
        </select>
      </div>
      <div class="flex items-center gap-3">
        <button class="kbsm-btn kbsm-btn--navy flex-1">Filter</button>
        <a href="{{ route('laporan.potong-gaji') }}" class="kbsm-btn kbsm-btn--outline-slate flex-1 text-center">Reset</a>
      </div>
    </form>
  </section>

  <section class="kbsm-business-summary kbsm-business-summary--wajib">
    @foreach($cards as $card)
      <article class="kbsm-business-summary-card kbsm-business-summary-card--{{ $card['accent'] }}">
        <span class="kbsm-business-summary-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M4 7h16v10H4V7Zm2 2v6h12V9H6Zm2 1h3v2H8v-2Z"/></svg></span>
        <p class="kbsm-business-summary-label">{{ $card['label'] }}</p>
        <p class="kbsm-business-summary-value">{{ $money($card['value']) }}</p>
      </article>
    @endforeach
  </section>

  @if(($summary['cicilan_due_belum_dialokasikan'] ?? 0) > 0)
    <div class="kbsm-business-alert kbsm-business-alert--danger">
      Ada Cicilan jatuh tempo sebesar {{ $money($summary['cicilan_due_belum_dialokasikan']) }} yang belum mempunyai ledger payroll. Ini warning laporan; sistem tidak memperbaiki otomatis.
    </div>
  @endif

  <section class="kbsm-business-panel">
    <div class="kbsm-business-panel__header">
      <h2 class="kbsm-business-panel__title">Ringkasan per Anggota</h2>
      <p class="kbsm-business-panel__copy">Cicilan siklus lama tidak muncul pada payroll siklus baru; due tanpa ledger tampil sebagai warning.</p>
    </div>
    <div class="kbsm-business-table-wrap">
      <table class="kbsm-business-table">
        <thead>
          <tr>
            <th>Anggota</th>
            <th class="kbsm-business-table__right">Limit</th>
            <th class="kbsm-business-table__right">Cicilan</th>
            <th class="kbsm-business-table__right">Reserved</th>
            <th class="kbsm-business-table__right">Settled</th>
            <th class="kbsm-business-table__right">Belum Dialokasikan</th>
            <th class="kbsm-business-table__right">Legacy Pokok</th>
            <th class="kbsm-business-table__right">Simpanan Wajib</th>
            <th class="kbsm-business-table__right">POS</th>
            <th class="kbsm-business-table__right">Kredit</th>
            <th class="kbsm-business-table__right">Net</th>
            <th>Status</th>
          </tr>
        </thead>
        <tbody>
          @forelse($laporan as $row)
            <tr>
              <td>
                <span class="kbsm-business-strong">{{ $row->nomor_anggota }}</span>
                <div class="kbsm-business-muted">{{ $row->nama }}</div>
              </td>
              <td class="kbsm-business-amount">{{ $money($row->limit_nominal) }}</td>
              <td class="kbsm-business-amount">{{ $money($row->cicilan) }}</td>
              <td class="kbsm-business-amount">{{ $money($row->cicilan_reserved) }}</td>
              <td class="kbsm-business-amount">{{ $money($row->cicilan_settled) }}</td>
              <td class="kbsm-business-amount">{{ $money($row->cicilan_belum_dialokasikan) }}</td>
              <td class="kbsm-business-amount">{{ $money($row->simpanan_pokok) }}</td>
              <td class="kbsm-business-amount">{{ $money($row->simpanan_wajib ?? 0) }}</td>
              <td class="kbsm-business-amount">{{ $money($row->pos) }}</td>
              <td class="kbsm-business-amount">{{ $money($row->kredit_refund) }}</td>
              <td class="kbsm-business-amount">{{ $money($row->net_payroll) }}</td>
              <td><span class="kbsm-status kbsm-status--navy">{{ $row->status_limit }}</span></td>
            </tr>
          @empty
            <tr><td colspan="12" class="kbsm-business-empty">Belum ada limit/ledger untuk periode ini.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </section>

  <section class="kbsm-business-panel">
    <div class="kbsm-business-panel__header">
      <h2 class="kbsm-business-panel__title">Detail Ledger</h2>
      <p class="kbsm-business-panel__copy">Status internal ditampilkan sebagai istilah operasional yang mudah dipahami.</p>
    </div>
    <div class="kbsm-business-table-wrap">
      <table class="kbsm-business-table">
        <thead>
          <tr>
            <th>Anggota</th>
            <th>Kategori</th>
            <th>Sumber</th>
            <th>Tanggal</th>
            <th class="kbsm-business-table__right">Nominal</th>
            <th>Status</th>
            <th>Koreksi Transaksi</th>
          </tr>
        </thead>
        <tbody>
          @forelse($details as $detail)
            <tr>
              <td>{{ $detail->anggota?->nomor_anggota }} - {{ $detail->anggota?->karyawan?->nama }}</td>
              <td>{{ $detail->kategori_label }}</td>
              <td><span class="kbsm-business-code">{{ $detail->kode_sumber }}</span></td>
              <td>{{ optional($detail->tanggal)->format('d/m/Y H:i') }}</td>
              <td class="kbsm-business-amount">{{ $money($detail->nominal) }}</td>
              <td>{{ $detail->status_label }}</td>
              <td>{{ $detail->reversal?->kode_reversal ?? '-' }}</td>
            </tr>
          @empty
            <tr><td colspan="7" class="kbsm-business-empty">Tidak ada ledger sesuai filter.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </section>
</div>
@endsection
