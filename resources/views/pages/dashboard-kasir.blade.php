@extends('layout.main')
@section('title', 'Dashboard Kasir')

@php
  $rupiah = fn ($nominal) => 'Rp ' . number_format((int) $nominal, 0, ',', '.');
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
      <a href="{{ route('penjualan.index') }}" class="kbsm-cashier-primary-action">
        <svg viewBox="0 0 24 24" aria-hidden="true">
          <path d="M6 7h12a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V9a2 2 0 0 1 2-2Zm1.5 3.5a1 1 0 1 0 0 2h3a1 1 0 1 0 0-2h-3Zm0 4a1 1 0 1 0 0 2h9a1 1 0 1 0 0-2h-9ZM8 4a1 1 0 0 1 1-1h6a1 1 0 0 1 1 1v3H8V4Z" />
        </svg>
        Buka Mesin Kasir
      </a>
    </div>
  </section>

  <section class="kbsm-cashier-kpi-grid" aria-label="Ringkasan penjualan hari ini">
    <article class="kbsm-cashier-kpi-card kbsm-cashier-kpi-card--green">
      <span class="kbsm-cashier-kpi-card__icon">
        <svg viewBox="0 0 24 24" aria-hidden="true">
          <path d="M7 4h10a2 2 0 0 1 2 2v13.25a.75.75 0 0 1-1.12.65L16 18.83l-1.88 1.07a.75.75 0 0 1-.74 0L11.5 18.83 9.62 19.9a.75.75 0 0 1-.74 0L7 18.83 5.12 19.9A.75.75 0 0 1 4 19.25V6a3 3 0 0 1 3-3Zm1 5a1 1 0 0 0 0 2h8a1 1 0 1 0 0-2H8Zm0 4a1 1 0 1 0 0 2h5a1 1 0 1 0 0-2H8Z" />
        </svg>
      </span>
      <div>
        <p>Total Transaksi Hari Ini</p>
        <strong>{{ number_format($transaksiHariIni, 0, ',', '.') }}</strong>
        <span>Struk POS valid</span>
      </div>
    </article>

    <article class="kbsm-cashier-kpi-card kbsm-cashier-kpi-card--navy">
      <span class="kbsm-cashier-kpi-card__icon">
        <svg viewBox="0 0 24 24" aria-hidden="true">
          <path d="M3 7.5A2.5 2.5 0 0 1 5.5 5h13A2.5 2.5 0 0 1 21 7.5v9A2.5 2.5 0 0 1 18.5 19h-13A2.5 2.5 0 0 1 3 16.5v-9Zm3 1A1.5 1.5 0 0 1 4.5 10v4A1.5 1.5 0 0 1 6 15.5h12a1.5 1.5 0 0 1 1.5-1.5v-4A1.5 1.5 0 0 1 18 8.5H6ZM12 9a3 3 0 1 0 0 6 3 3 0 0 0 0-6Z" />
        </svg>
      </span>
      <div>
        <p>Nilai Penjualan Hari Ini</p>
        <strong>{{ $rupiah($pendapatanHariIni) }}</strong>
        <span>Bukan laporan uang masuk</span>
      </div>
    </article>

    <article class="kbsm-cashier-kpi-card kbsm-cashier-kpi-card--gold">
      <span class="kbsm-cashier-kpi-card__icon">
        <svg viewBox="0 0 24 24" aria-hidden="true">
          <path d="M4 7a3 3 0 0 1 3-3h1.1a3 3 0 0 1 5.8 0H17a3 3 0 0 1 3 3v9a4 4 0 0 1-4 4H8a4 4 0 0 1-4-4V7Zm6-.5a1 1 0 0 0 1 1h2a1 1 0 1 0 0-2h-2a1 1 0 0 0-1 1ZM8 11a1 1 0 1 0 0 2h8a1 1 0 1 0 0-2H8Zm0 4a1 1 0 1 0 0 2h5a1 1 0 1 0 0-2H8Z" />
        </svg>
      </span>
      <div>
        <p>Item Terjual Hari Ini</p>
        <strong>{{ number_format($itemTerjualHariIni, 0, ',', '.') }}</strong>
        <span>Total kuantitas detail</span>
      </div>
    </article>

    <article class="kbsm-cashier-kpi-card kbsm-cashier-kpi-card--green">
      <span class="kbsm-cashier-kpi-card__icon">
        <svg viewBox="0 0 24 24" aria-hidden="true">
          <path d="M5 19a1 1 0 0 1-1-1V6a1 1 0 0 1 2 0v10.59l3.3-3.3a1 1 0 0 1 1.4 0l2.3 2.3 4.3-4.3a1 1 0 0 1 1.4 1.42l-5 5a1 1 0 0 1-1.4 0L10 15.42l-3.3 3.3A1 1 0 0 1 6 19H5Zm12-12a2 2 0 1 1 4 0 2 2 0 0 1-4 0Z" />
        </svg>
      </span>
      <div>
        <p>Rata-rata Transaksi</p>
        <strong>{{ $rupiah($rataRataTransaksi) }}</strong>
        <span>Aman saat belum ada transaksi</span>
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
              <span class="kbsm-cashier-method__dot"></span>
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
            <path d="M7 4h10a2 2 0 0 1 2 2v11a3 3 0 0 1-3 3H8a3 3 0 0 1-3-3V6a2 2 0 0 1 2-2Zm2 4a1 1 0 1 0 0 2h6a1 1 0 1 0 0-2H9Zm0 4a1 1 0 1 0 0 2h4a1 1 0 1 0 0-2H9Z" />
          </svg>
          <strong>Belum ada produk terjual hari ini</strong>
          <span>Produk terlaris akan tampil otomatis setelah transaksi POS valid tersimpan.</span>
        </div>
      @else
        <div class="kbsm-cashier-product-list">
          @foreach($produkTerlarisHariIni as $index => $item)
            @php
              $produk = $item->produk;
              $fotoUrl = $produk?->foto_url ?? asset(\App\Models\Produk::FALLBACK_PHOTO_PATH);
            @endphp
            <div class="kbsm-cashier-product">
              <span class="kbsm-cashier-product__rank">{{ $index + 1 }}</span>
              <img src="{{ $fotoUrl }}" alt="Foto {{ $produk?->nama_produk ?? 'Produk' }}" class="kbsm-cashier-product__image">
              <div class="kbsm-cashier-product__body">
                <strong>{{ $produk?->nama_produk ?? 'Produk tidak tersedia' }}</strong>
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
