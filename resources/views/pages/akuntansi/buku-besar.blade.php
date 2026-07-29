@extends('layout.main')

@php
  $normalSide = strtolower((string) ($akunModel?->posisi_saldo ?? 'debit'));
  $oppositeSide = fn (string $side): string => $side === 'kredit' ? 'debit' : 'kredit';
  $sideFor = function ($nominal) use ($normalSide, $oppositeSide): string {
      return (float) $nominal < 0 ? $oppositeSide($normalSide) : $normalSide;
  };
  $sideLabel = fn (string $side): string => $side === 'kredit' ? 'Kredit' : 'Debit';
  $sideShort = fn (string $side): string => $side === 'kredit' ? 'K' : 'D';
  $rupiah = fn ($nominal): string => 'Rp ' . number_format(abs((float) $nominal), 0, ',', '.');
  $rupiahZeroDash = fn ($nominal): string => (float) $nominal > 0 ? $rupiah($nominal) : '–';
  $tanggalId = fn ($date): string => $date
      ? \Carbon\Carbon::parse($date)->locale('id')->translatedFormat('d F Y')
      : '–';
  $periodeLabel = $mulai->copy()->locale('id')->translatedFormat('d F Y') . ' – ' . $akhir->copy()->locale('id')->translatedFormat('d F Y');
  $saldoAwalSide = $sideFor($saldoAwal);
  $saldoAkhirSide = $sideFor($saldoAkhir);
  $saldoAwalAbnormal = $akunModel && abs((float) $saldoAwal) > 0 && $saldoAwalSide !== $normalSide;
  $saldoAkhirAbnormal = $akunModel && abs((float) $saldoAkhir) > 0 && $saldoAkhirSide !== $normalSide;
@endphp

@section('content')
<div class="bb-page">
  <header class="bb-hero">
    <div>
      <p class="bb-breadcrumb">Laporan Akuntansi / Buku Besar</p>
      <h1>Buku Besar</h1>
      <p class="bb-description">
        Pantau mutasi akun, total debit-kredit, dan saldo berjalan berdasarkan jurnal umum yang sudah tercatat.
      </p>
    </div>
    <div class="bb-period-chip" aria-label="Periode aktif">
      <span>Periode Aktif</span>
      <strong>{{ $periodeLabel }}</strong>
    </div>
  </header>

  <section class="bb-filter-card" aria-labelledby="bb-filter-title">
    <div class="bb-section-heading">
      <div>
        <span class="bb-eyebrow">Filter</span>
        <h2 id="bb-filter-title">Filter Buku Besar</h2>
      </div>
    </div>

    <form method="GET" action="{{ route('akuntansi.buku-besar') }}" class="bb-filter-form">
      <div class="bb-field">
        <label for="periode">Periode</label>
        <input id="periode" type="month" name="periode" value="{{ $periode }}" class="bb-control">
      </div>

      <div class="bb-field">
        <label for="akun">Akun</label>
        <select id="akun" name="akun" class="bb-control">
          @foreach($akunList as $item)
            <option value="{{ $item->kode_akun }}" {{ $akun === $item->kode_akun ? 'selected' : '' }}>
              {{ $item->kode_akun }} - {{ $item->nama_akun }}
            </option>
          @endforeach
        </select>
      </div>

      <div class="bb-filter-actions">
        <button type="submit" class="bb-btn bb-btn--primary">Tampilkan</button>
        <a href="{{ route('akuntansi.buku-besar') }}" class="bb-btn bb-btn--outline">Reset</a>
      </div>
    </form>
  </section>

  @if(! $akunModel)
    <section class="bb-empty-state">
      <svg viewBox="0 0 24 24" aria-hidden="true">
        <path d="M5 4h14a2 2 0 0 1 2 2v12.5a1.5 1.5 0 0 1-2.22 1.31L17 18.83l-1.78.98a1.5 1.5 0 0 1-1.44 0L12 18.83l-1.78.98a1.5 1.5 0 0 1-1.44 0L7 18.83l-1.78.98A1.5 1.5 0 0 1 3 18.5V6a2 2 0 0 1 2-2Zm3 5a1 1 0 1 0 0 2h8a1 1 0 1 0 0-2H8Zm0 4a1 1 0 1 0 0 2h5a1 1 0 1 0 0-2H8Z" />
      </svg>
      <strong>Pilih periode dan akun untuk menampilkan Buku Besar.</strong>
      <span>Tambahkan atau aktifkan akun COA terlebih dahulu jika dropdown akun masih kosong.</span>
    </section>
  @else
    <section class="bb-account-card" aria-label="Identitas akun">
      <div class="bb-account-card__main">
        <span class="bb-account-code">{{ $akunModel->kode_akun }}</span>
        <div>
          <h2>{{ $akunModel->nama_akun }}</h2>
          <div class="bb-badge-row">
            <span class="bb-badge bb-badge--navy">{{ $akunModel->kategori_label }}</span>
            <span class="bb-badge bb-badge--green">Saldo Normal {{ $sideLabel($normalSide) }}</span>
            <span class="bb-badge bb-badge--soft">{{ $periodeLabel }}</span>
          </div>
        </div>
      </div>
      <div class="bb-account-card__balance">
        <span>Saldo Akhir</span>
        <strong>{{ $rupiah($saldoAkhir) }}</strong>
        <div class="bb-badge-row bb-badge-row--right">
          <span class="bb-badge {{ $saldoAkhirAbnormal ? 'bb-badge--warning' : 'bb-badge--green' }}">
            Saldo {{ $sideLabel($saldoAkhirSide) }}
          </span>
          @if($saldoAkhirAbnormal)
            <span class="bb-badge bb-badge--warning">Posisi tidak normal</span>
          @endif
        </div>
      </div>
    </section>

    <section class="bb-summary-grid" aria-label="Ringkasan saldo">
      <article class="bb-summary-card bb-summary-card--navy">
        <span class="bb-summary-card__icon">
          <svg viewBox="0 0 24 24" aria-hidden="true">
            <path d="M4 6.5A2.5 2.5 0 0 1 6.5 4H18a2 2 0 0 1 2 2v2H7a3 3 0 0 0 0 6h13v2a2 2 0 0 1-2 2H6.5A2.5 2.5 0 0 1 4 15.5v-9Zm14 5a1.5 1.5 0 1 1 3 0 1.5 1.5 0 0 1-3 0Z" />
          </svg>
        </span>
        <div>
          <p>Saldo Awal</p>
          <strong>{{ $rupiah($saldoAwal) }}</strong>
          <span class="bb-mini-badge {{ $saldoAwalAbnormal ? 'bb-mini-badge--warning' : '' }}">
            {{ $sideLabel($saldoAwalSide) }}
            @if($saldoAwalAbnormal)
              · Posisi tidak normal
            @endif
          </span>
        </div>
      </article>

      <article class="bb-summary-card bb-summary-card--green">
        <span class="bb-summary-card__icon">
          <svg viewBox="0 0 24 24" aria-hidden="true">
            <path d="M12 3a1 1 0 0 1 1 1v10.59l3.3-3.3a1 1 0 1 1 1.4 1.42l-5 5a1 1 0 0 1-1.4 0l-5-5a1 1 0 1 1 1.4-1.42l3.3 3.3V4a1 1 0 0 1 1-1ZM5 19a1 1 0 1 0 0 2h14a1 1 0 1 0 0-2H5Z" />
          </svg>
        </span>
        <div>
          <p>Total Debit</p>
          <strong>{{ $rupiah($totalDebit) }}</strong>
          <span class="bb-mini-badge">Debit</span>
        </div>
      </article>

      <article class="bb-summary-card bb-summary-card--gold">
        <span class="bb-summary-card__icon">
          <svg viewBox="0 0 24 24" aria-hidden="true">
            <path d="M12 21a1 1 0 0 1-1-1V9.41l-3.3 3.3a1 1 0 1 1-1.4-1.42l5-5a1 1 0 0 1 1.4 0l5 5a1 1 0 1 1-1.4 1.42L13 9.41V20a1 1 0 0 1-1 1ZM5 3a1 1 0 0 0 0 2h14a1 1 0 1 0 0-2H5Z" />
          </svg>
        </span>
        <div>
          <p>Total Kredit</p>
          <strong>{{ $rupiah($totalKredit) }}</strong>
          <span class="bb-mini-badge">Kredit</span>
        </div>
      </article>

      <article class="bb-summary-card bb-summary-card--navy">
        <span class="bb-summary-card__icon">
          <svg viewBox="0 0 24 24" aria-hidden="true">
            <path d="M5 4h14a1 1 0 0 1 1 1v14a1 1 0 0 1-1.45.89L16 18.62l-2.55 1.27a1 1 0 0 1-.9 0L10 18.62l-2.55 1.27A1 1 0 0 1 6 19V5a1 1 0 0 1 1-1Zm4 5a1 1 0 1 0 0 2h6a1 1 0 1 0 0-2H9Zm0 4a1 1 0 1 0 0 2h4a1 1 0 1 0 0-2H9Z" />
          </svg>
        </span>
        <div>
          <p>Saldo Akhir</p>
          <strong>{{ $rupiah($saldoAkhir) }}</strong>
          <span class="bb-mini-badge {{ $saldoAkhirAbnormal ? 'bb-mini-badge--warning' : '' }}">
            {{ $sideLabel($saldoAkhirSide) }}
            @if($saldoAkhirAbnormal)
              · Posisi tidak normal
            @endif
          </span>
        </div>
      </article>
    </section>

    <section class="bb-table-card" aria-labelledby="bb-table-title">
      <div class="bb-section-heading">
        <div>
          <span class="bb-eyebrow">Detail</span>
          <h2 id="bb-table-title">Mutasi Akun</h2>
        </div>
      </div>

      <div class="bb-table-wrap">
        <table class="bb-table">
          <thead>
            <tr>
              <th>Tanggal</th>
              <th>No. Bukti</th>
              <th>Keterangan</th>
              <th class="bb-table__amount">Debit</th>
              <th class="bb-table__amount">Kredit</th>
              <th class="bb-table__amount">Saldo Berjalan</th>
            </tr>
          </thead>
          <tbody>
            @forelse($lines as $row)
              @php
                $rowSide = $sideFor($row->saldo);
                $rowAbnormal = abs((float) $row->saldo) > 0 && $rowSide !== $normalSide;
              @endphp
              <tr>
                <td>{{ $tanggalId($row->tanggal) }}</td>
                <td><span class="bb-proof-number">{{ $row->nomor_bukti ?? '–' }}</span></td>
                <td class="bb-table__description">{{ $row->keterangan ?? '–' }}</td>
                <td class="bb-table__amount">{{ $rupiahZeroDash($row->debit) }}</td>
                <td class="bb-table__amount">{{ $rupiahZeroDash($row->kredit) }}</td>
                <td class="bb-table__amount">
                  <span class="bb-running-balance {{ $rowAbnormal ? 'bb-running-balance--warning' : '' }}">
                    {{ $rupiah($row->saldo) }}
                    <span>{{ $sideShort($rowSide) }}</span>
                  </span>
                </td>
              </tr>
            @empty
              <tr>
                <td colspan="6">
                  <div class="bb-table-empty">Tidak ada transaksi pada akun dan periode yang dipilih.</div>
                </td>
              </tr>
            @endforelse
          </tbody>
          <tfoot>
            <tr>
              <td colspan="3">Total</td>
              <td class="bb-table__amount">{{ $rupiah($totalDebit) }}</td>
              <td class="bb-table__amount">{{ $rupiah($totalKredit) }}</td>
              <td class="bb-table__amount">
                {{ $rupiah($saldoAkhir) }}
                <span class="bb-footer-side">{{ $sideShort($saldoAkhirSide) }}</span>
              </td>
            </tr>
          </tfoot>
        </table>
      </div>
    </section>
  @endif
</div>
@endsection
