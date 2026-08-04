@extends('layout.main')

@section('content')
@php
  $money = fn ($value) => 'Rp ' . number_format((float) $value, 0, ',', '.');
  $moneyOrDash = fn ($value) => abs((float) $value) < 0.01 ? '–' : $money($value);
  $statusLabel = fn ($status) => match ($status) {
    'draft' => 'Draft',
    'active' => 'Aktif',
    'closed_pending_confirmation' => 'Menunggu Konfirmasi',
    'confirmed' => 'Selesai',
    'cancelled' => 'Dibatalkan',
    default => ucfirst(str_replace('_', ' ', (string) $status)),
  };
@endphp

<div class="kbsm-business-page kbsm-ux-page">
  <div class="kbsm-business-header">
    <div>
      <p class="kbsm-business-eyebrow">Laporan Potong Gaji</p>
      <h1 class="kbsm-business-title">Laporan Potong Gaji Bulanan</h1>
      <p class="kbsm-business-subtitle">Menampilkan siapa yang dipotong gajinya, keperluan potongannya, dan total potongan pada periode yang dipilih.</p>
    </div>
    <a href="{{ route('rekonsiliasi-potong-gaji.index', ['periode' => $periode, 'perusahaan_id' => $filters['perusahaan_id']]) }}" class="kbsm-business-back-link">Pengecekan Potong Gaji</a>
  </div>

  <section class="kbsm-business-panel">
    <div class="kbsm-business-panel__header">
      <h2 class="kbsm-business-panel__title">Filter Laporan</h2>
      <p class="kbsm-business-panel__copy">Pilihan Anggota mengikuti Perusahaan. Filter tetap tersimpan saat berpindah halaman.</p>
    </div>
    <form method="GET" action="{{ route('laporan.potong-gaji') }}" class="kbsm-ux-filter-grid kbsm-ux-filter-grid--report" data-member-filter>
      <div class="kbsm-business-field">
        <label class="kbsm-business-label" for="laporan-periode">Periode</label>
        <input id="laporan-periode" type="month" name="periode" value="{{ $periode }}" class="kbsm-business-control">
      </div>
      <div class="kbsm-business-field">
        <label class="kbsm-business-label" for="laporan-perusahaan">Perusahaan</label>
        <select id="laporan-perusahaan" name="perusahaan_id" class="kbsm-business-control" data-company-select>
          <option value="">Semua Perusahaan</option>
          @foreach($perusahaanList as $perusahaan)
            <option value="{{ $perusahaan->id }}" @selected((string) $filters['perusahaan_id'] === (string) $perusahaan->id)>{{ $perusahaan->kode }} — {{ $perusahaan->nama }}</option>
          @endforeach
        </select>
      </div>
      <div class="kbsm-business-field">
        <label class="kbsm-business-label" for="laporan-anggota">Anggota</label>
        <input type="search" class="kbsm-business-control kbsm-member-search" placeholder="Cari nama atau nomor anggota" autocomplete="off" data-member-search>
        <select id="laporan-anggota" name="anggota_id" class="kbsm-business-control" data-member-select>
          <option value="">Semua Anggota yang Memiliki Potongan</option>
          @foreach($anggotaOptions as $anggota)
            <option value="{{ $anggota->id }}" data-company-id="{{ $anggota->karyawan?->perusahaan_id }}" @selected((string) $filters['anggota_id'] === (string) $anggota->id)>
              {{ $anggota->nomor_anggota }} — {{ $anggota->karyawan?->nama }}
            </option>
          @endforeach
        </select>
      </div>
      <div class="kbsm-ux-filter-actions">
        <button class="kbsm-btn kbsm-btn--navy">Filter</button>
        <a href="{{ route('laporan.potong-gaji') }}" class="kbsm-btn kbsm-btn--outline-slate">Reset</a>
      </div>
      <label class="kbsm-ux-toggle">
        <input type="checkbox" name="tampilkan_tanpa_potongan" value="1" @checked($showWithoutDeductions)>
        <span>Tampilkan anggota tanpa potongan</span>
      </label>
    </form>
  </section>

  @if(($summary['cicilan_due_belum_dialokasikan'] ?? 0) > 0)
    <div class="kbsm-business-alert kbsm-business-alert--danger">
      Ada Cicilan jatuh tempo {{ $money($summary['cicilan_due_belum_dialokasikan']) }} yang belum dialokasikan ke potong gaji. Laporan ini tidak melakukan perubahan otomatis.
    </div>
  @endif

  @if($selectedMember)
    <section class="kbsm-business-summary kbsm-business-summary--three">
      @foreach([
        ['label' => 'Limit Bulan Ini', 'value' => $selectedMember->limit_nominal, 'accent' => 'navy'],
        ['label' => 'Total Potongan', 'value' => $selectedMember->gross_payroll, 'accent' => 'green'],
        ['label' => 'Sisa Limit', 'value' => $selectedMember->sisa_kapasitas, 'accent' => 'gold'],
      ] as $card)
        <article class="kbsm-business-summary-card kbsm-business-summary-card--{{ $card['accent'] }}">
          <p class="kbsm-business-summary-label">{{ $card['label'] }}</p>
          <p class="kbsm-business-summary-value">{{ $money($card['value']) }}</p>
        </article>
      @endforeach
    </section>

    <section class="kbsm-business-panel">
      <div class="kbsm-business-panel__header">
        <h2 class="kbsm-business-panel__title">Rincian Potongan {{ $selectedMember->nama }}</h2>
        <p class="kbsm-business-panel__copy">{{ $selectedMember->nomor_anggota }} — {{ $selectedMember->karyawan?->perusahaan?->kode ?? '-' }}</p>
      </div>
      <dl class="kbsm-ux-breakdown">
        <div><dt>Cicilan Pinjaman</dt><dd>{{ $moneyOrDash($selectedMember->cicilan) }}</dd></div>
        <div><dt>Simpanan Wajib</dt><dd>{{ $moneyOrDash($selectedMember->simpanan_wajib) }}</dd></div>
        <div><dt>Manasuka Rutin</dt><dd>{{ $moneyOrDash($selectedMember->simpanan_manasuka ?? 0) }}</dd></div>
        <div><dt>Kredit Waserba</dt><dd>{{ $moneyOrDash($selectedMember->pos) }}</dd></div>
        <div class="kbsm-ux-breakdown__total"><dt>Total yang Dipotong</dt><dd>{{ $money($selectedMember->gross_payroll) }}</dd></div>
        <div><dt>Status Pemrosesan</dt><dd><span class="kbsm-status kbsm-status--navy">{{ $statusLabel($selectedMember->status_limit) }}</span></dd></div>
      </dl>

      <details class="kbsm-ux-details">
        <summary>Lihat Detail Proses Potong Gaji</summary>
        <dl class="kbsm-ux-breakdown kbsm-ux-breakdown--technical">
          <div><dt>Sudah Dialokasikan</dt><dd>{{ $moneyOrDash($selectedMember->reserved) }}</dd></div>
          <div><dt>Sudah Dipotong</dt><dd>{{ $moneyOrDash($selectedMember->settled) }}</dd></div>
          <div><dt>Belum Dialokasikan</dt><dd>{{ $moneyOrDash($selectedMember->cicilan_belum_dialokasikan) }}</dd></div>
          <div><dt>Dilepas atau Dikoreksi</dt><dd>{{ $moneyOrDash($selectedMember->released_reversed) }}</dd></div>
          <div><dt>Data Simpanan Siklus Lama</dt><dd>{{ $moneyOrDash($selectedMember->simpanan_pokok) }}</dd></div>
          <div><dt>Pengurang atau Pengembalian</dt><dd>{{ $moneyOrDash($selectedMember->kredit_refund) }}</dd></div>
        </dl>
      </details>
    </section>
  @else
    <section class="kbsm-business-summary kbsm-business-summary--three">
      @foreach([
        ['label' => 'Total Potongan Gaji', 'value' => $summary['gross_payroll'], 'accent' => 'green'],
        ['label' => 'Pengurang atau Pengembalian', 'value' => $summary['kredit_refund'], 'accent' => 'gold'],
        ['label' => 'Total Setelah Pengurang', 'value' => $summary['net_payroll'], 'accent' => 'navy'],
      ] as $card)
        <article class="kbsm-business-summary-card kbsm-business-summary-card--{{ $card['accent'] }}">
          <p class="kbsm-business-summary-label">{{ $card['label'] }}</p>
          <p class="kbsm-business-summary-value">{{ $money($card['value']) }}</p>
        </article>
      @endforeach
    </section>

    <section class="kbsm-business-panel">
      <div class="kbsm-business-panel__header">
        <h2 class="kbsm-business-panel__title">Ringkasan per Anggota</h2>
        <p class="kbsm-business-panel__copy">Secara default hanya anggota dengan total potongan lebih dari nol yang ditampilkan.</p>
      </div>
      <div class="kbsm-business-table-wrap">
        <table class="kbsm-business-table kbsm-business-table--payroll-compact">
          <thead>
            <tr>
              <th>Anggota</th>
              <th>Perusahaan</th>
              <th class="kbsm-business-table__right">Cicilan Pinjaman</th>
              <th class="kbsm-business-table__right">Simpanan Wajib</th>
              <th class="kbsm-business-table__right">Manasuka Rutin</th>
              <th class="kbsm-business-table__right">Waserba</th>
              <th class="kbsm-business-table__right">Total Potongan</th>
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
                <td>{{ $row->karyawan?->perusahaan?->kode ?? '-' }}</td>
                <td class="kbsm-business-amount">{{ $moneyOrDash($row->cicilan) }}</td>
                <td class="kbsm-business-amount">{{ $moneyOrDash($row->simpanan_wajib) }}</td>
                <td class="kbsm-business-amount">{{ $moneyOrDash($row->simpanan_manasuka ?? 0) }}</td>
                <td class="kbsm-business-amount">{{ $moneyOrDash($row->pos) }}</td>
                <td class="kbsm-business-amount kbsm-business-amount--emphasis">{{ $money($row->gross_payroll) }}</td>
                <td><span class="kbsm-status kbsm-status--navy">{{ $statusLabel($row->status_limit) }}</span></td>
              </tr>
            @empty
              <tr><td colspan="8" class="kbsm-business-empty">Belum ada anggota dengan potongan pada periode ini.</td></tr>
            @endforelse
          </tbody>
        </table>
      </div>
      @if($laporan->hasPages())
        <div class="kbsm-business-pagination">{{ $laporan->links() }}</div>
      @endif
    </section>
  @endif
</div>
@endsection
