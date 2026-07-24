@extends('layout.main')

@section('content')
<style>
  .ju-page {
    color: #334155;
  }

  .ju-card {
    overflow: hidden;
    border: 1px solid #e2e8f0;
    border-radius: 14px;
    background: #fff;
  }

  .ju-header {
    padding: 24px 24px 18px;
    border-bottom: 1px solid #e2e8f0;
  }

  .ju-title {
    margin: 0;
    color: #0f172a;
    font-size: 20px;
    font-weight: 700;
    line-height: 1.3;
  }

  .ju-period {
    margin: 5px 0 0;
    color: #64748b;
    font-size: 13px;
  }

  .ju-body {
    padding: 20px 24px 0;
  }

  .ju-filter {
    display: flex;
    flex-wrap: wrap;
    align-items: flex-end;
    gap: 10px;
    margin-bottom: 20px;
  }

  .ju-filter__field {
    width: 190px;
    max-width: 100%;
  }

  .ju-filter__label {
    display: block;
    margin: 0 0 6px;
    color: #475569;
    font-size: 11px;
    font-weight: 700;
    letter-spacing: .04em;
    text-transform: uppercase;
  }

  .ju-filter__input {
    display: block;
    width: 100%;
    height: 38px;
    padding: 8px 11px;
    border: 1px solid #cbd5e1;
    border-radius: 8px;
    outline: none;
    background: #fff;
    color: #334155;
    font-size: 13px;
    transition: border-color .2s, box-shadow .2s;
  }

  .ju-filter__input:focus {
    border-color: #334e68;
    box-shadow: 0 0 0 3px rgba(51, 78, 104, .12);
  }

  .ju-filter__button {
    height: 38px;
    padding: 0 18px;
    border: 0;
    border-radius: 8px;
    background: #243b53;
    color: #fff;
    cursor: pointer;
    font-size: 12px;
    font-weight: 700;
    letter-spacing: .025em;
    transition: background-color .2s, transform .2s;
  }

  .ju-filter__button:hover {
    background: #102a43;
    transform: translateY(-1px);
  }

  .ju-table-wrap {
    overflow-x: auto;
    border: 1px solid #dbe3ec;
    border-radius: 10px;
  }

  .ju-table {
    width: 100%;
    min-width: 840px;
    margin: 0;
    border-collapse: collapse;
    color: #475569;
    font-size: 13px;
  }

  .ju-table th {
    padding: 12px 14px;
    border-bottom: 1px solid #1e3a5f;
    background: #334e68;
    color: #f8fafc;
    font-size: 11px;
    font-weight: 700;
    letter-spacing: .055em;
    text-transform: uppercase;
    white-space: nowrap;
  }

  .ju-table td {
    padding: 12px 14px;
    border-bottom: 1px solid #edf2f7;
    background: #fff;
    vertical-align: top;
  }

  .ju-table tbody tr:last-child td {
    border-bottom: 0;
  }

  .ju-table tbody tr.ju-entry-start:not(:first-child) td {
    border-top: 1px solid #cbd5e1;
  }

  .ju-col-date {
    width: 120px;
    text-align: center;
  }

  .ju-col-code {
    width: 120px;
    text-align: center;
  }

  .ju-col-description {
    min-width: 280px;
    text-align: left;
  }

  .ju-col-ref {
    width: 140px;
    text-align: center;
  }

  .ju-col-money {
    width: 150px;
    text-align: right;
  }

  .ju-date,
  .ju-code,
  .ju-ref,
  .ju-money {
    white-space: nowrap;
  }

  .ju-date,
  .ju-code,
  .ju-ref {
    color: #475569;
    text-align: center;
  }

  .ju-date,
  .ju-ref {
    font-weight: 600;
  }

  .ju-description {
    text-align: left;
  }

  .ju-entry-note {
    display: block;
    margin-bottom: 4px;
    color: #94a3b8;
    font-size: 11px;
    line-height: 1.35;
  }

  .ju-account-name {
    display: block;
    color: #334155;
    font-size: 13px;
    font-weight: 600;
    line-height: 1.4;
  }

  .ju-account-name--credit {
    padding-left: 24px;
  }

  .ju-money {
    color: #334155;
    font-variant-numeric: tabular-nums;
    text-align: right;
  }

  .ju-empty {
    padding: 36px 18px !important;
    color: #94a3b8;
    text-align: center;
  }

  .ju-table tfoot td {
    padding: 13px 14px;
    border-top: 1px solid #cbd5e1;
    border-bottom: 0;
    background: #f1f5f9;
    color: #0f172a;
    font-weight: 700;
  }

  .ju-total-label {
    text-align: right;
    text-transform: uppercase;
    letter-spacing: .04em;
  }

  .ju-pagination {
    padding: 18px 0 22px;
  }

  @media (max-width: 767px) {
    .ju-header {
      padding: 20px 18px 16px;
    }

    .ju-body {
      padding: 18px 18px 0;
    }

    .ju-filter__field,
    .ju-filter__button {
      width: 100%;
    }
  }
</style>

@php
  $pageTotalDebit = $jurnal->sum(function ($entry) {
      return $entry->details->sum('debit');
  });
  $pageTotalKredit = $jurnal->sum(function ($entry) {
      return $entry->details->sum('kredit');
  });
@endphp

<div class="ju-page w-full px-6 py-6 mx-auto">
  <div class="ju-card">
    <header class="ju-header">
      <h1 class="ju-title">Jurnal Umum Periodik</h1>
      <p class="ju-period">Periode: {{ $mulai->format('d M Y') }} - {{ $akhir->format('d M Y') }}</p>
    </header>

    <div class="ju-body">
      <form method="GET" action="{{ route('akuntansi.jurnal-umum') }}" class="ju-filter">
        <div class="ju-filter__field">
          <label for="tanggal_mulai" class="ju-filter__label">Tanggal Mulai</label>
          <input
            id="tanggal_mulai"
            type="date"
            name="tanggal_mulai"
            value="{{ $mulai->toDateString() }}"
            class="ju-filter__input"
            required
          />
        </div>
        <div class="ju-filter__field">
          <label for="tanggal_akhir" class="ju-filter__label">Tanggal Akhir</label>
          <input
            id="tanggal_akhir"
            type="date"
            name="tanggal_akhir"
            value="{{ $akhir->toDateString() }}"
            class="ju-filter__input"
            required
          />
        </div>
        <button type="submit" class="ju-filter__button">Tampilkan</button>
      </form>

      <div style="overflow-x: auto;" class="ju-table-wrap">
        <table class="ju-table">
          <thead>
            <tr>
              <th class="ju-col-date">Tanggal</th>
              <th class="ju-col-code">Kode Akun</th>
              <th class="ju-col-description">Keterangan</th>
              <th class="ju-col-ref">Ref</th>
              <th class="ju-col-money">Debit</th>
              <th class="ju-col-money">Kredit</th>
            </tr>
          </thead>
          <tbody>
            @forelse($jurnal as $entry)
              @foreach($entry->details as $detail)
                <tr class="{{ $loop->first ? 'ju-entry-start' : '' }}">
                  <td class="ju-date">
                    {{ $loop->first ? optional($entry->tanggal)->format('d/m/Y') : '' }}
                  </td>
                  <td class="ju-code">{{ $detail->akun_kode ?? '-' }}</td>
                  <td class="ju-description">
                    @if($loop->first && filled($entry->keterangan))
                      <span class="ju-entry-note">{{ $entry->keterangan }}</span>
                    @endif
                    <span class="ju-account-name {{ (float) $detail->kredit > 0 ? 'ju-account-name--credit' : '' }}">
                      {{ $detail->akun_nama ?? '-' }}
                    </span>
                  </td>
                  <td class="ju-ref">
                    {{ $loop->first ? ($entry->nomor_bukti ?? '-') : '' }}
                  </td>
                  <td class="ju-money">
                    {{ (float) $detail->debit > 0 ? 'Rp '.number_format($detail->debit, 0, ',', '.') : '' }}
                  </td>
                  <td class="ju-money">
                    {{ (float) $detail->kredit > 0 ? 'Rp '.number_format($detail->kredit, 0, ',', '.') : '' }}
                  </td>
                </tr>
              @endforeach
            @empty
              <tr>
                <td colspan="6" class="ju-empty">Belum ada jurnal untuk periode ini.</td>
              </tr>
            @endforelse
          </tbody>
          <tfoot>
            <tr>
              <td colspan="4" class="ju-total-label">Total</td>
              <td class="ju-money">Rp {{ number_format($pageTotalDebit, 0, ',', '.') }}</td>
              <td class="ju-money">Rp {{ number_format($pageTotalKredit, 0, ',', '.') }}</td>
            </tr>
          </tfoot>
        </table>
      </div>

      <div class="ju-pagination">
        {{ $jurnal->links() }}
      </div>
    </div>
  </div>
</div>
@endsection
