@extends('layout.main')

@section('content')
@php
  $money = fn ($value) => 'Rp ' . number_format((float) $value, 0, ',', '.');
  $bankDifference = round((float) ($rekonsiliasi['penerimaan_bersih_seharusnya'] ?? 0) - (float) ($rekonsiliasi['mutasi_kas_masuk'] ?? 0), 2);
  $mismatches = collect($rekonsiliasi['differences'])->filter(fn ($diff) => abs((float) $diff) >= 0.01);
  $largestDifference = $mismatches->map(fn ($diff) => abs((float) $diff))->max() ?? 0;
@endphp

<div class="kbsm-business-page kbsm-ux-page">
  <div class="kbsm-business-header">
    <div>
      <p class="kbsm-business-eyebrow">Laporan Potong Gaji</p>
      <h1 class="kbsm-business-title">Pengecekan Potong Gaji</h1>
      <p class="kbsm-business-subtitle">Memastikan jumlah potongan gaji, uang yang masuk ke Bank, dan pencatatan Jurnal sudah sama.</p>
    </div>
    <a href="{{ route('laporan.potong-gaji', ['periode' => $periode, 'perusahaan_id' => $perusahaanId]) }}" class="kbsm-business-back-link">Laporan Potong Gaji</a>
  </div>

  <section class="kbsm-business-panel">
    <div class="kbsm-business-panel__header">
      <h2 class="kbsm-business-panel__title">Filter Pengecekan</h2>
    </div>
    <form method="GET" action="{{ route('rekonsiliasi-potong-gaji.index') }}" class="kbsm-ux-check-filter">
      <div class="kbsm-business-field">
        <label class="kbsm-business-label" for="periode">Periode</label>
        <input id="periode" type="month" name="periode" value="{{ $periode }}" class="kbsm-business-control">
      </div>
      <div class="kbsm-business-field">
        <label class="kbsm-business-label" for="perusahaan_id">Perusahaan</label>
        <select id="perusahaan_id" name="perusahaan_id" class="kbsm-business-control">
          <option value="">Semua Perusahaan</option>
          @foreach($perusahaanList as $perusahaan)
            <option value="{{ $perusahaan->id }}" @selected((string) $perusahaanId === (string) $perusahaan->id)>{{ $perusahaan->kode }} — {{ $perusahaan->nama }}</option>
          @endforeach
        </select>
      </div>
      <div class="kbsm-ux-filter-actions">
        <button class="kbsm-btn kbsm-btn--navy">Periksa</button>
      </div>
    </form>
  </section>

  <section class="kbsm-business-summary kbsm-business-summary--three">
    @foreach([
      ['label' => 'Uang yang Seharusnya Diterima', 'value' => $rekonsiliasi['penerimaan_bersih_seharusnya'], 'accent' => 'navy'],
      ['label' => 'Uang yang Sudah Masuk ke Bank', 'value' => $rekonsiliasi['mutasi_kas_masuk'], 'accent' => 'green'],
      ['label' => 'Selisih', 'value' => $bankDifference, 'accent' => abs($bankDifference) < 0.01 ? 'green' : 'red'],
    ] as $card)
      <article class="kbsm-business-summary-card kbsm-business-summary-card--{{ $card['accent'] }}">
        <p class="kbsm-business-summary-label">{{ $card['label'] }}</p>
        <p class="kbsm-business-summary-value">{{ $money($card['value']) }}</p>
      </article>
    @endforeach
  </section>

  <section class="kbsm-business-panel">
    <div class="kbsm-ux-status {{ $rekonsiliasi['status'] === 'balanced' ? 'kbsm-ux-status--success' : 'kbsm-ux-status--danger' }}">
      <span class="kbsm-status {{ $rekonsiliasi['status'] === 'balanced' ? 'kbsm-status--green' : 'kbsm-status--red' }}">
        {{ $rekonsiliasi['status_label'] }}
      </span>
      <p>
        @if($rekonsiliasi['status'] === 'balanced')
          Sesuai — seluruh potongan sudah diterima dan dicatat.
        @else
          Perlu Diperiksa — terdapat selisih {{ $money($largestDifference) }}.
        @endif
      </p>
    </div>

    @if($rekonsiliasi['status'] === 'balanced')
      <div class="kbsm-ux-success-state">
        Seluruh data potong gaji periode ini sudah cocok. Tidak ada selisih yang perlu diperiksa.
      </div>
    @else
      <div class="kbsm-business-panel__header">
        <h2 class="kbsm-business-panel__title">Hasil Pengecekan</h2>
        <p class="kbsm-business-panel__copy">Hanya perbandingan yang mempunyai selisih yang ditampilkan.</p>
      </div>
      <div class="kbsm-business-table-wrap">
        <table class="kbsm-business-detail-table">
          <thead><tr><th>Perbandingan</th><th class="kbsm-business-table__right">Selisih</th><th>Status</th></tr></thead>
          <tbody>
            @foreach($mismatches as $label => $diff)
              <tr>
                <td>{{ $label }}</td>
                <td class="kbsm-business-amount">{{ $money($diff) }}</td>
                <td><span class="kbsm-status kbsm-status--red">Perlu Diperiksa</span></td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    @endif

    <details class="kbsm-ux-details">
      <summary>Lihat Detail Pencatatan Akuntansi</summary>
      <dl class="kbsm-ux-breakdown kbsm-ux-breakdown--technical">
        <div><dt>Total Potongan Gaji</dt><dd>{{ $money($rekonsiliasi['total_potongan_tercatat']) }}</dd></div>
        <div><dt>Pengurang atau Pengembalian</dt><dd>{{ $money($rekonsiliasi['kredit_refund_diterapkan']) }}</dd></div>
        <div><dt>Uang yang Sudah Masuk ke Bank</dt><dd>{{ $money($rekonsiliasi['mutasi_kas_masuk']) }}</dd></div>
        <div><dt>Uang Masuk yang Dicatat di Jurnal</dt><dd>{{ $money($rekonsiliasi['debit_bank_jurnal']) }}</dd></div>
        <div><dt>Tagihan Anggota yang Sudah Dilunasi</dt><dd>{{ $money($rekonsiliasi['kredit_piutang_jurnal']) }}</dd></div>
      </dl>
      <p class="kbsm-ux-formula">Rumus utama: Total Potongan Gaji dikurangi Pengurang atau Pengembalian sama dengan Uang yang Seharusnya Diterima.</p>
    </details>
  </section>
</div>
@endsection
