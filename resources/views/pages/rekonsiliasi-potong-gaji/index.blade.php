@extends('layout.main')

@section('content')
@php
  $money = fn ($value) => 'Rp ' . number_format((float) $value, 0, ',', '.');
  $cards = [
    ['key' => 'total_potongan_tercatat', 'label' => 'Total Potongan Tercatat', 'accent' => 'green'],
    ['key' => 'kredit_refund_diterapkan', 'label' => 'Kredit/Refund Terpakai', 'accent' => 'gold'],
    ['key' => 'penerimaan_bersih_seharusnya', 'label' => 'Penerimaan Bersih Seharusnya', 'accent' => 'navy'],
    ['key' => 'mutasi_kas_masuk', 'label' => 'Mutasi Bank Aktual', 'accent' => 'green'],
    ['key' => 'debit_bank_jurnal', 'label' => 'Jurnal Debit Bank', 'accent' => 'navy'],
    ['key' => 'kredit_piutang_jurnal', 'label' => 'Kredit Piutang', 'accent' => 'gold'],
  ];
@endphp

<div class="kbsm-business-page">
  <div class="kbsm-business-header">
    <div>
      <p class="kbsm-business-eyebrow">Laporan Finance</p>
      <h1 class="kbsm-business-title">Rekonsiliasi Potong Gaji</h1>
      <p class="kbsm-business-subtitle">Membandingkan ledger settled, bukti pembayaran, Mutasi Bank, Jurnal, dan kredit/refund. Selisih hanya ditampilkan, tidak diperbaiki otomatis.</p>
    </div>
    <a href="{{ route('laporan.potong-gaji', ['periode' => $periode]) }}" class="kbsm-business-back-link">Laporan Payroll</a>
  </div>

  <section class="kbsm-business-panel">
    <div class="kbsm-business-panel__header">
      <h2 class="kbsm-business-panel__title">Filter Rekonsiliasi</h2>
    </div>
    <form method="GET" class="kbsm-business-filter kbsm-business-filter--compact">
      <div class="kbsm-business-field">
        <label class="kbsm-business-label" for="periode">Periode</label>
        <input id="periode" type="month" name="periode" value="{{ $periode }}" class="kbsm-business-control">
      </div>
      <div class="kbsm-business-filter__actions kbsm-business-filter__actions--split">
        <button class="kbsm-btn kbsm-btn--navy">Tampilkan</button>
      </div>
    </form>
  </section>

  <section class="kbsm-business-panel">
    <div class="kbsm-business-panel__header">
      <h2 class="kbsm-business-panel__title">Status Rekonsiliasi</h2>
      <p class="kbsm-business-panel__copy">Status “Perlu Diperiksa” berarti ada selisih yang harus ditelusuri manual.</p>
    </div>
    <div class="p-5">
      <span class="kbsm-status {{ $rekonsiliasi['status'] === 'balanced' ? 'kbsm-status--green' : 'kbsm-status--red' }}">
        {{ $rekonsiliasi['status_label'] }}
      </span>
    </div>
  </section>

  <section class="kbsm-business-summary kbsm-business-summary--wajib">
    @foreach($cards as $card)
      <article class="kbsm-business-summary-card kbsm-business-summary-card--{{ $card['accent'] }}">
        <span class="kbsm-business-summary-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M3 5h18v4H3V5Zm0 6h18v8H3v-8Zm3 2v2h7v-2H6Z"/></svg></span>
        <p class="kbsm-business-summary-label">{{ $card['label'] }}</p>
        <p class="kbsm-business-summary-value">{{ $money($rekonsiliasi[$card['key']] ?? 0) }}</p>
      </article>
    @endforeach
  </section>

  <section class="kbsm-business-panel">
    <div class="kbsm-business-panel__header">
      <h2 class="kbsm-business-panel__title">Selisih yang Perlu Ditelusuri</h2>
      <p class="kbsm-business-panel__copy">Rumus utama: Total Potongan Tercatat - Kredit/Refund Terpakai = Penerimaan Bersih Seharusnya.</p>
    </div>
    <div class="kbsm-business-table-wrap">
      <table class="kbsm-business-detail-table">
        <thead>
          <tr><th>Pemeriksaan</th><th class="kbsm-business-table__right">Selisih</th><th>Status</th></tr>
        </thead>
        <tbody>
          @foreach($rekonsiliasi['differences'] as $label => $diff)
            <tr>
              <td>{{ $label }}</td>
              <td class="kbsm-business-amount">{{ $money($diff) }}</td>
              <td>
                <span class="kbsm-status {{ abs((float) $diff) < 0.01 ? 'kbsm-status--green' : 'kbsm-status--red' }}">
                  {{ abs((float) $diff) < 0.01 ? 'Sesuai' : 'Perlu Diperiksa' }}
                </span>
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  </section>
</div>
@endsection
