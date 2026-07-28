@extends('layout.main')
@section('title', 'Dashboard Kasir')

@php
  $rupiah = fn ($nominal) => 'Rp ' . number_format((int) $nominal, 0, ',', '.');
  $fallbackFotoProduk = asset('assets/img/demo-products/fallback-produk.svg');
@endphp

@section('content')
<div class="kbsm-cashier-dashboard">
  <section class="kbsm-cashier-hero" aria-labelledby="kasir-dashboard-title">
    <div class="kbsm-cashier-hero__content">
      <span class="kbsm-cashier-eyebrow">Dashboard Kasir</span>
      <h1 id="kasir-dashboard-title">Selamat Datang, {{ $kasirName }}</h1>
      <p>
        Ringkasan operasional POS hari ini, {{ $tanggalDashboard->format('d M Y') }}.
        Transaksi potong gaji yang masih pending tetap dihitung sebagai nilai penjualan.
      </p>
    </div>

    <div class="kbsm-cashier-hero__action">
      <span>Siap melayani pelanggan?</span>
      <a href="{{ route('waserba.index') }}" class="kbsm-cashier-primary-action">
        <svg viewBox="0 0 24 24" aria-hidden="true">
          <path d="M7 3a2 2 0 0 0-2 2v3h14V5a2 2 0 0 0-2-2H7Zm12 7H5a3 3 0 0 0-3 3v4a3 3 0 0 0 3 3h1v-6h12v6h1a3 3 0 0 0 3-3v-4a3 3 0 0 0-3-3Zm-3 6H8v5h8v-5Z"/>
        </svg>
        Buka Mesin Kasir
      </a>
    </div>
  </section>

  <section class="kbsm-cashier-kpi-grid" aria-label="Ringkasan POS hari ini">
    <article class="kbsm-cashier-kpi-card">
      <span class="kbsm-cashier-kpi-card__icon" aria-hidden="true">
        <svg viewBox="0 0 24 24">
          <path d="M4 4h16a1 1 0 0 1 1 1v4H3V5a1 1 0 0 1 1-1Zm-1 7h18v8a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1v-8Zm4 3v2h4v-2H7Zm8 0v2h2v-2h-2Z"/>
        </svg>
      </span>
      <div>
        <p>Total Transaksi Hari Ini</p>
        <strong>{{ number_format($transaksiHariIni, 0, ',', '.') }}</strong>
        <span>Transaksi POS valid</span>
      </div>
    </article>

    <article class="kbsm-cashier-kpi-card kbsm-cashier-kpi-card--navy">
      <span class="kbsm-cashier-kpi-card__icon" aria-hidden="true">
        <svg viewBox="0 0 24 24">
          <path d="M12 3a9 9 0 1 0 9 9h-2a7 7 0 1 1-2.05-4.95L14 10h7V3l-2.62 2.62A8.96 8.96 0 0 0 12 3Zm-1 4v2.05A3.5 3.5 0 0 0 8 12.5c0 1.76 1.31 2.57 3.58 3.14 1.41.35 1.92.64 1.92 1.16 0 .58-.62.95-1.5.95-.95 0-1.88-.33-2.7-.97l-1.1 1.62c.78.65 1.75 1.05 2.8 1.2V21h2v-1.42c1.78-.33 3-1.43 3-2.9 0-1.67-1.1-2.55-3.45-3.14-1.55-.39-2.05-.65-2.05-1.15 0-.54.58-.89 1.35-.89.82 0 1.54.24 2.21.73l1.02-1.66A5.06 5.06 0 0 0 13 9.08V7h-2Z"/>
        </svg>
      </span>
      <div>
        <p>Nilai Penjualan Hari Ini</p>
        <strong>{{ $rupiah($pendapatanHariIni) }}</strong>
        <span>Termasuk payroll pending yang sah</span>
      </div>
    </article>

    <article class="kbsm-cashier-kpi-card kbsm-cashier-kpi-card--gold">
      <span class="kbsm-cashier-kpi-card__icon" aria-hidden="true">
        <svg viewBox="0 0 24 24">
          <path d="M4 4h7v7H4V4Zm9 0h7v7h-7V4ZM4 13h7v7H4v-7Zm9 0h7v7h-7v-7Z"/>
        </svg>
      </span>
      <div>
        <p>Item Terjual Hari Ini</p>
        <strong>{{ number_format($itemTerjualHariIni, 0, ',', '.') }}</strong>
        <span>Total kuantitas detail transaksi</span>
      </div>
    </article>

    <article class="kbsm-cashier-kpi-card">
      <span class="kbsm-cashier-kpi-card__icon" aria-hidden="true">
        <svg viewBox="0 0 24 24">
          <path d="M4 19h16v2H4v-2Zm2-8h3v6H6v-6Zm5-6h3v12h-3V5Zm5 3h3v9h-3V8Z"/>
        </svg>
      </span>
      <div>
        <p>Rata-rata Transaksi</p>
        <strong>{{ $rupiah($rataRataTransaksi) }}</strong>
        <span>Nilai penjualan / transaksi</span>
      </div>
    </article>
  </section>

  <section class="kbsm-cashier-lower-grid">
    <article class="kbsm-cashier-panel">
      <div class="kbsm-cashier-panel__header">
        <div>
          <span class="kbsm-cashier-eyebrow">Pembayaran</span>
          <h2>Metode Pembayaran Hari Ini</h2>
        </div>
      </div>

      <div class="kbsm-cashier-method-list">
        @foreach($metodePembayaranHariIni as $metode)
          <div class="kbsm-cashier-method kbsm-cashier-method--{{ $metode['accent'] }}">
            <div class="kbsm-cashier-method__main">
              <span class="kbsm-cashier-method__dot" aria-hidden="true"></span>
              <div>
                <strong>{{ $metode['label'] }}</strong>
                <span>{{ $metode['hint'] }}</span>
              </div>
            </div>
            <div class="kbsm-cashier-method__numbers">
              <strong>{{ $rupiah($metode['total_nominal']) }}</strong>
              <span>{{ number_format($metode['total_transaksi'], 0, ',', '.') }} transaksi</span>
            </div>
          </div>
        @endforeach
      </div>
    </article>

    <article class="kbsm-cashier-panel">
      <div class="kbsm-cashier-panel__header">
        <div>
          <span class="kbsm-cashier-eyebrow">Produk</span>
          <h2>Produk Terlaris Hari Ini</h2>
        </div>
      </div>

      @if($produkTerlarisHariIni->isEmpty())
        <div class="kbsm-cashier-empty-state">
          <svg viewBox="0 0 24 24" aria-hidden="true">
            <path d="M7 4h10l2 5v10a1 1 0 0 1-1 1H6a1 1 0 0 1-1-1V9l2-5Zm1.24 2-1 2h9.52l-1-2H8.24ZM9 11v2h6v-2H9Z"/>
          </svg>
          <strong>Belum ada produk terjual hari ini</strong>
          <span>Produk terlaris akan muncul otomatis setelah transaksi POS valid tersimpan.</span>
        </div>
      @else
        <div class="kbsm-cashier-product-list">
          @foreach($produkTerlarisHariIni as $index => $item)
            <div class="kbsm-cashier-product">
              <span class="kbsm-cashier-product__rank">{{ $index + 1 }}</span>
              <img
                src="{{ $item->produk?->foto_url ?? $fallbackFotoProduk }}"
                alt="Foto {{ $item->produk?->nama_produk ?? 'Produk' }}"
                class="kbsm-cashier-product__image"
              >
              <div class="kbsm-cashier-product__body">
                <strong>{{ $item->produk?->nama_produk ?? 'Produk tidak ditemukan' }}</strong>
                <span>{{ number_format((int) $item->total_qty, 0, ',', '.') }} item terjual</span>
              </div>
              <div class="kbsm-cashier-product__revenue">
                {{ $rupiah($item->total_revenue) }}
              </div>
            </div>
          @endforeach
        </div>
      @endif
    </article>
  </section>
</div>
@endsection
