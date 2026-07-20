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
  $hak = $penyelesaian->details->where('tipe_detail', \App\Models\PenyelesaianKeanggotaanDetail::TIPE_HAK);
  $batalWajib = $penyelesaian->details->where('tipe_detail', \App\Models\PenyelesaianKeanggotaanDetail::TIPE_PEMBATALAN_WAJIB);
  $kewajiban = $penyelesaian->details->where('tipe_detail', \App\Models\PenyelesaianKeanggotaanDetail::TIPE_KEWAJIBAN);
  $remainingHak = $hak->sum(fn($d) => max(0, $rupiahInt($d->nominal_hak_awal) - $rupiahInt($d->nominal_dipakai_offset) - $rupiahInt($d->nominal_direfund)));
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

  <div class="kbsm-business-header kbsm-business-form-header">
    <div>
      <p class="kbsm-business-eyebrow">Penyelesaian Keanggotaan</p>
      <h1 class="kbsm-business-title">{{ $penyelesaian->kode_penyelesaian }}</h1>
      <p class="kbsm-business-subtitle">{{ $penyelesaian->anggota?->nomor_anggota }} - {{ $penyelesaian->anggota?->karyawan?->nama }} · Siklus #{{ $penyelesaian->siklus?->siklus_ke }}</p>
    </div>
    <a href="{{ route('penyelesaian-keanggotaan.index') }}" class="kbsm-business-back-link">Kembali</a>
  </div>

  <section class="kbsm-business-summary kbsm-business-summary--simpanan">
    <article class="kbsm-business-summary-card kbsm-business-summary-card--green">
      <p class="kbsm-business-summary-label">Total Hak</p>
      <p class="kbsm-business-summary-value">{{ $money($penyelesaian->total_hak_anggota) }}</p>
    </article>
    <article class="kbsm-business-summary-card kbsm-business-summary-card--red">
      <p class="kbsm-business-summary-label">Sisa Kewajiban</p>
      <p class="kbsm-business-summary-value">{{ $money($penyelesaian->sisa_kewajiban) }}</p>
    </article>
    <article class="kbsm-business-summary-card kbsm-business-summary-card--navy">
      <p class="kbsm-business-summary-label">Offset</p>
      <p class="kbsm-business-summary-value">{{ $money($penyelesaian->total_offset) }}</p>
    </article>
    <article class="kbsm-business-summary-card kbsm-business-summary-card--gold">
      <p class="kbsm-business-summary-label">Refund/Sisa Hak</p>
      <p class="kbsm-business-summary-value">{{ $money(max($rupiahInt($penyelesaian->total_refund), $remainingHak)) }}</p>
    </article>
  </section>

  <section class="kbsm-business-panel">
    <div class="kbsm-business-panel__header">
      <h2 class="kbsm-business-panel__title">Aksi Finance</h2>
      <p class="kbsm-business-panel__copy">Semua aksi POST memakai service settlement; tidak ada edit/hard delete transaksi final.</p>
    </div>
    <div class="kbsm-business-actions">
      @if($penyelesaian->status !== \App\Models\PenyelesaianKeanggotaan::STATUS_COMPLETED)
        <form method="POST" action="{{ route('penyelesaian-keanggotaan.refresh', $penyelesaian) }}">
          @csrf
          <button class="kbsm-btn kbsm-btn--outline-slate">Refresh Snapshot</button>
        </form>
      @endif
      @if(in_array($penyelesaian->status, [\App\Models\PenyelesaianKeanggotaan::STATUS_PENDING_REVIEW, \App\Models\PenyelesaianKeanggotaan::STATUS_WAITING_SETTLEMENT], true) && $rupiahInt($penyelesaian->total_offset) <= 0)
        <form method="POST" action="{{ route('penyelesaian-keanggotaan.process-offset', $penyelesaian) }}">
          @csrf
          <button class="kbsm-btn kbsm-btn--navy">Proses Offset</button>
        </form>
      @endif
      @if($penyelesaian->status === \App\Models\PenyelesaianKeanggotaan::STATUS_READY_TO_COMPLETE && $remainingHak > 0)
        <form method="POST" action="{{ route('penyelesaian-keanggotaan.refund', $penyelesaian) }}" class="kbsm-business-grid">
          @csrf
          <div class="kbsm-business-field">
            <label class="kbsm-business-label">Metode Refund</label>
            <select name="metode_refund" required class="kbsm-business-control">
              <option value="">Pilih Metode</option>
              <option value="{{ \App\Models\PenyelesaianKeanggotaan::METODE_TUNAI }}">Tunai</option>
              <option value="{{ \App\Models\PenyelesaianKeanggotaan::METODE_TRANSFER_BANK }}">Transfer Bank</option>
            </select>
          </div>
          <div class="kbsm-business-field">
            <label class="kbsm-business-label">Dompet Refund</label>
            <select name="dompet_id" required class="kbsm-business-control">
              <option value="">Pilih Dompet</option>
              @foreach($dompetOptions as $dompet)
                <option value="{{ $dompet->id }}">{{ $dompet->nama_dompet }} · {{ strtoupper($dompet->jenis_dompet) }} · {{ $dompet->akun?->kode_akun ?? 'tanpa COA' }}</option>
              @endforeach
            </select>
          </div>
          <div class="kbsm-business-field kbsm-business-field--full">
            <label class="kbsm-business-label">Alasan Refund</label>
            <textarea name="alasan" minlength="5" required class="kbsm-business-control" rows="2">Refund sisa hak Anggota setelah penyelesaian keanggotaan.</textarea>
          </div>
          <div class="kbsm-business-field">
            <button class="kbsm-btn kbsm-btn--navy">Proses Refund {{ $money($remainingHak) }}</button>
          </div>
        </form>
      @endif
      @if($penyelesaian->status === \App\Models\PenyelesaianKeanggotaan::STATUS_READY_TO_COMPLETE && $remainingHak <= 0)
        <form method="POST" action="{{ route('penyelesaian-keanggotaan.complete', $penyelesaian) }}">
          @csrf
          <button class="kbsm-btn kbsm-btn--green">Tandai Selesai</button>
        </form>
      @endif
    </div>
  </section>

  <section class="kbsm-business-panel">
    <div class="kbsm-business-panel__header">
      <h2 class="kbsm-business-panel__title">Hak Anggota</h2>
      <p class="kbsm-business-panel__copy">Pokok, Wajib paid, Sukarela, dan kredit/refund valid ditampilkan sebagai sumber terpisah.</p>
    </div>
    <div class="kbsm-business-table-wrap">
      <table class="kbsm-business-table">
        <thead><tr><th>Sumber</th><th>Akun</th><th class="kbsm-business-table__right">Hak</th><th class="kbsm-business-table__right">Offset</th><th class="kbsm-business-table__right">Refund</th><th>Status</th></tr></thead>
        <tbody>
          @forelse($hak as $detail)
            <tr>
              <td>{{ str_replace('_', ' ', $detail->kategori_sumber) }}<div class="kbsm-business-muted">{{ class_basename($detail->source_type) }} #{{ $detail->source_id }}</div></td>
              <td>{{ $detail->akun_kode_snapshot }} {{ $detail->akun_nama_snapshot }}</td>
              <td class="kbsm-business-amount">{{ $money($detail->nominal_hak_awal) }}</td>
              <td class="kbsm-business-amount">{{ $money($detail->nominal_dipakai_offset) }}</td>
              <td class="kbsm-business-amount">{{ $money($detail->nominal_direfund) }}</td>
              <td><span class="kbsm-status kbsm-status--navy">{{ $detail->status_label }}</span></td>
            </tr>
          @empty
            <tr><td colspan="6" class="kbsm-business-empty">Tidak ada hak Anggota tersnapshot.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </section>

  <section class="kbsm-business-panel">
    <div class="kbsm-business-panel__header">
      <h2 class="kbsm-business-panel__title">Tagihan Wajib Dibatalkan</h2>
      <p class="kbsm-business-panel__copy">Tagihan Wajib outstanding/reserved dibatalkan secara akuntansi tanpa Mutasi Kas.</p>
    </div>
    <div class="kbsm-business-table-wrap">
      <table class="kbsm-business-table">
        <thead><tr><th>Sumber</th><th>Akun</th><th class="kbsm-business-table__right">Nominal Dibatalkan</th><th>Status</th></tr></thead>
        <tbody>
          @forelse($batalWajib as $detail)
            <tr>
              <td>{{ class_basename($detail->source_type) }} #{{ $detail->source_id }}</td>
              <td>{{ $detail->akun_kode_snapshot }} {{ $detail->akun_nama_snapshot }}</td>
              <td class="kbsm-business-amount">{{ $money($detail->nominal_dibatalkan) }}</td>
              <td><span class="kbsm-status kbsm-status--gold">{{ $detail->status_label }}</span></td>
            </tr>
          @empty
            <tr><td colspan="4" class="kbsm-business-empty">Tidak ada tagihan Wajib yang dibatalkan.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </section>

  <section class="kbsm-business-panel">
    <div class="kbsm-business-panel__header">
      <h2 class="kbsm-business-panel__title">Kewajiban dan Offset</h2>
      <p class="kbsm-business-panel__copy">Offset tidak membuat pembayaran cicilan palsu; sisa kewajiban dilanjutkan melalui flow mantan Karyawan.</p>
    </div>
    <div class="kbsm-business-table-wrap">
      <table class="kbsm-business-table">
        <thead><tr><th>Sumber</th><th class="kbsm-business-table__right">Awal</th><th class="kbsm-business-table__right">Offset</th><th class="kbsm-business-table__right">Tunai</th><th class="kbsm-business-table__right">Sisa</th><th>Status</th></tr></thead>
        <tbody>
          @forelse($kewajiban as $detail)
            <tr>
              <td>{{ str_replace('_', ' ', $detail->kategori_sumber) }}<div class="kbsm-business-muted">{{ class_basename($detail->source_type) }} #{{ $detail->source_id }}</div></td>
              <td class="kbsm-business-amount">{{ $money($detail->nominal_kewajiban_awal) }}</td>
              <td class="kbsm-business-amount">{{ $money($detail->nominal_offset) }}</td>
              <td class="kbsm-business-amount">{{ $money($detail->nominal_dibayar_tunai) }}</td>
              <td class="kbsm-business-amount">{{ $money($detail->nominal_sisa) }}</td>
              <td><span class="kbsm-status kbsm-status--navy">{{ $detail->status_label }}</span></td>
            </tr>
          @empty
            <tr><td colspan="6" class="kbsm-business-empty">Tidak ada kewajiban aktif.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </section>

  <section class="kbsm-business-panel">
    <div class="kbsm-business-panel__header">
      <h2 class="kbsm-business-panel__title">Audit</h2>
    </div>
    <div class="kbsm-business-grid">
      <div class="kbsm-business-readonly">Status: {{ $penyelesaian->status_label }}</div>
      <div class="kbsm-business-readonly">Tanggal keluar: {{ $penyelesaian->tanggal_keluar?->format('d/m/Y') }}</div>
      <div class="kbsm-business-readonly">Diproses: {{ $penyelesaian->processed_at?->format('d/m/Y H:i') ?? '-' }}</div>
      <div class="kbsm-business-readonly">Selesai: {{ $penyelesaian->completed_at?->format('d/m/Y H:i') ?? '-' }}</div>
      <div class="kbsm-business-readonly kbsm-business-field--full">Alasan: {{ $penyelesaian->alasan }}</div>
    </div>
  </section>
</div>
@endsection
