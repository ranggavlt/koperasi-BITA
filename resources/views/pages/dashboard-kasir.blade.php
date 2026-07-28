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
    <div class="mt-4 sm:mt-0 flex flex-col items-end">
       <p class="text-sm text-slate-500 mb-2 font-semibold">Siap melayani pelanggan?</p>
       <a href="{{ route('waserba.index') }}" class="inline-flex items-center justify-center px-8 py-3 font-bold text-center text-white uppercase transition-all border-0 rounded-xl cursor-pointer hover:scale-105 active:opacity-85" style="background: linear-gradient(135deg, #059669 0%, #10b981 100%); box-shadow: 0 4px 6px -1px rgba(16, 185, 129, 0.4), 0 2px 4px -1px rgba(16, 185, 129, 0.2); font-size: 14px; min-width: 200px;">
         <i class="fas fa-cash-register mr-2" style="font-size: 16px;"></i> Buka Mesin Kasir
       </a>
    </div>
  </div>

  <!-- Cards -->
  <div class="flex flex-wrap -mx-3 mb-6">
    <!-- Total Transaksi -->
    <div class="w-full max-w-full px-3 mb-6 sm:w-1/2 sm:flex-none xl:mb-0 xl:w-1/4">
      <div class="relative flex flex-col h-full min-w-0 break-words bg-white shadow-soft-xl rounded-2xl bg-clip-border">
        <div class="flex-auto p-4">
          <div class="flex flex-row -mx-3">
            <div class="flex-none w-2/3 max-w-full px-3">
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

    <!-- Omzet Hari Ini -->
    <div class="w-full max-w-full px-3 mb-6 sm:w-1/2 sm:flex-none xl:mb-0 xl:w-1/4">
      <div class="relative flex flex-col h-full min-w-0 break-words bg-white shadow-soft-xl rounded-2xl bg-clip-border">
        <div class="flex-auto p-4">
          <div class="flex flex-row -mx-3">
            <div class="flex-none w-2/3 max-w-full px-3">
              <div>
                <p class="mb-0 font-sans text-sm font-semibold leading-normal">Omzet Hari Ini</p>
                <h5 class="mb-0 font-bold">
                  Rp {{ number_format($pendapatanHariIni, 0, ',', '.') }}
                </h5>
              </div>
            </div>
          @endforeach
        </div>
      </div>
    </div>

    <!-- Item Terjual -->
    <div class="w-full max-w-full px-3 mb-6 sm:w-1/2 sm:flex-none xl:mb-0 xl:w-1/4">
      <div class="relative flex flex-col h-full min-w-0 break-words bg-white shadow-soft-xl rounded-2xl bg-clip-border">
        <div class="flex-auto p-4">
          <div class="flex flex-row -mx-3">
            <div class="flex-none w-2/3 max-w-full px-3">
              <div>
                <p class="mb-0 font-sans text-sm font-semibold leading-normal">Item Terjual Hari Ini</p>
                <h5 class="mb-0 font-bold">
                  {{ number_format($itemTerjualHariIni, 0, ',', '.') }}
                </h5>
              </div>
            </div>
            <div class="px-3 text-right basis-1/3">
              <div class="inline-block w-12 h-12 text-center rounded-lg bg-gradient-to-tl from-green-600 to-lime-400">
                <i class="ni leading-none ni-box-2 text-lg relative top-3.5 text-white"></i>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Rata-rata Transaksi -->
    <div class="w-full max-w-full px-3 mb-6 sm:w-1/2 sm:flex-none xl:mb-0 xl:w-1/4">
      <div class="relative flex flex-col h-full min-w-0 break-words bg-white shadow-soft-xl rounded-2xl bg-clip-border">
        <div class="flex-auto p-4">
          <div class="flex flex-row -mx-3">
            <div class="flex-none w-2/3 max-w-full px-3">
              <div>
                <p class="mb-0 font-sans text-sm font-semibold leading-normal">Rata-rata Transaksi</p>
                <h5 class="mb-0 font-bold">
                  Rp {{ number_format($rataRataTransaksi, 0, ',', '.') }}
                </h5>
              </div>
            </div>
            <div class="px-3 text-right basis-1/3">
              <div class="inline-block w-12 h-12 text-center rounded-lg bg-gradient-to-tl from-green-600 to-lime-400">
                <i class="ni leading-none ni-chart-bar-32 text-lg relative top-3.5 text-white"></i>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Transaksi Terakhir -->
  <div class="flex flex-wrap -mx-3">
    <div class="w-full max-w-full px-3 mt-0 mb-6 lg:mb-0 lg:w-full lg:flex-none">
      <div class="border-black/12.5 shadow-soft-xl relative flex min-w-0 flex-col break-words rounded-2xl border-0 border-solid bg-white bg-clip-border">
        <div class="border-black/12.5 mb-0 rounded-t-2xl border-b-0 border-solid bg-white p-6 pb-0">
          <div class="flex flex-wrap mt-0 -mx-3">
            <div class="flex-none w-7/12 max-w-full px-3 mt-0 lg:w-1/2 lg:flex-none">
              <h6 class="mb-0">Transaksi Terakhir</h6>
            </div>
          </div>
        </div>
        <div class="flex-auto p-6 px-0 pb-2">
          <div style="overflow-x: auto;" class="">
            <table class="items-center w-full mb-0 align-top border-gray-200 text-slate-500">
              <thead class="align-bottom">
                <tr>
                  <th class="px-6 py-3 font-bold tracking-normal text-left uppercase align-middle bg-transparent border-b letter border-b-solid text-xxs whitespace-nowrap border-b-gray-200 text-slate-400 opacity-70">
                    No. Ref
                  </th>
                  <th class="px-6 py-3 pl-2 font-bold tracking-normal text-left uppercase align-middle bg-transparent border-b letter border-b-solid text-xxs whitespace-nowrap border-b-gray-200 text-slate-400 opacity-70">
                    Pelanggan
                  </th>
                  <th class="px-6 py-3 font-bold tracking-normal text-center uppercase align-middle bg-transparent border-b letter border-b-solid text-xxs whitespace-nowrap border-b-gray-200 text-slate-400 opacity-70">
                    Total
                  </th>
                  <th class="px-6 py-3 font-bold tracking-normal text-center uppercase align-middle bg-transparent border-b letter border-b-solid text-xxs whitespace-nowrap border-b-gray-200 text-slate-400 opacity-70">
                    Waktu
                  </th>
                </tr>
              </thead>
              <tbody>
                @forelse($transaksiTerakhir as $trx)
                <tr class="hover:bg-slate-50 transition-colors">
                  <td class="p-3 align-middle bg-transparent border-b whitespace-nowrap shadow-transparent">
                    <div class="flex px-2 py-1">
                      <div class="flex flex-col justify-center">
                        <h6 class="mb-0 text-sm font-bold text-slate-700">{{ $trx->kode_transaksi }}</h6>
                      </div>
                    </div>
                  </td>
                  <td class="p-2 align-middle bg-transparent border-b whitespace-nowrap shadow-transparent">
                    <p class="mb-0 text-xs font-semibold leading-tight">
                        {{ $trx->tipe_pelanggan === 'anggota' ? ($trx->anggota->karyawan->nama ?? 'Anggota') : ($trx->tipe_pelanggan === 'karyawan' ? ($trx->karyawan->nama ?? 'Karyawan') : 'Umum') }}
                    </p>
                  </td>
                  <td class="p-2 text-sm leading-normal text-center align-middle bg-transparent border-b whitespace-nowrap shadow-transparent">
                    <span class="text-xs font-semibold leading-tight">Rp {{ number_format($trx->grand_total, 0, ',', '.') }}</span>
                  </td>
                  <td class="p-2 text-center align-middle bg-transparent border-b whitespace-nowrap shadow-transparent">
                    <span class="text-xs font-semibold leading-tight">{{ $trx->created_at->format('H:i') }}</span>
                  </td>
                </tr>
                @empty
                <tr>
                  <td colspan="4" class="p-4 text-center text-sm text-slate-500 bg-transparent border-b">
                    Belum ada transaksi
                  </td>
                </tr>
                @endforelse
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
@endsection
