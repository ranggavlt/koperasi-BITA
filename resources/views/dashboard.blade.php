@extends('layout.main')
@section('content')
  <div class="w-full px-6 py-6 mx-auto">
    <div class="flex flex-wrap -mx-3">
      <div class="w-full max-w-full px-3 mb-6 sm:w-1/2 sm:flex-none xl:mb-0 xl:w-1/4">
        <div class="relative flex flex-col min-w-0 break-words bg-white shadow-soft-xl rounded-2xl bg-clip-border">
          <div class="flex-auto p-4">
            <div class="flex flex-row -mx-3">
              <div class="flex-none w-2/3 max-w-full px-3">
                <div>
                  <p class="mb-0 font-sans font-semibold leading-normal text-sm">Pendapatan Hari Ini</p>
                  <h5 class="mb-0 font-bold text-lg">
                    Rp {{ number_format($pendapatanHariIni, 0, ',', '.') }}
                  </h5>
                </div>
              </div>
              <div class="px-3 text-right basis-1/3">
                <div class="inline-block w-12 h-12 text-center rounded-lg bg-gradient-to-tl from-purple-700 to-pink-500">
                  <i class="ni leading-none ni-money-coins text-lg relative top-3.5 text-white"></i>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="w-full max-w-full px-3 mb-6 sm:w-1/2 sm:flex-none xl:mb-0 xl:w-1/4">
        <div class="relative flex flex-col min-w-0 break-words bg-white shadow-soft-xl rounded-2xl bg-clip-border">
          <div class="flex-auto p-4">
            <div class="flex flex-row -mx-3">
              <div class="flex-none w-2/3 max-w-full px-3">
                <div>
                  <p class="mb-0 font-sans font-semibold leading-normal text-sm">Transaksi Hari Ini</p>
                  <h5 class="mb-0 font-bold text-lg">
                    {{ number_format($transaksiHariIni, 0, ',', '.') }} Struk
                  </h5>
                </div>
              </div>
              <div class="px-3 text-right basis-1/3">
                <div class="inline-block w-12 h-12 text-center rounded-lg bg-gradient-to-tl from-purple-700 to-pink-500">
                  <i class="ni leading-none ni-world text-lg relative top-3.5 text-white"></i>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="w-full max-w-full px-3 mb-6 sm:w-1/2 sm:flex-none xl:mb-0 xl:w-1/4">
        <div class="relative flex flex-col min-w-0 break-words bg-white shadow-soft-xl rounded-2xl bg-clip-border">
          <div class="flex-auto p-4">
            <div class="flex flex-row -mx-3">
              <div class="flex-none w-2/3 max-w-full px-3">
                <div>
                  <p class="mb-0 font-sans font-semibold leading-normal text-sm">Konsinyasi Terjual</p>
                  <h5 class="mb-0 font-bold text-lg">
                    {{ number_format($konsinyasiBulanIni, 0, ',', '.') }} Item
                  </h5>
                </div>
              </div>
              <div class="px-3 text-right basis-1/3">
                <div class="inline-block w-12 h-12 text-center rounded-lg bg-gradient-to-tl from-purple-700 to-pink-500">
                  <i class="ni leading-none ni-paper-diploma text-lg relative top-3.5 text-white"></i>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="w-full max-w-full px-3 sm:w-1/2 sm:flex-none xl:w-1/4">
        <div class="relative flex flex-col min-w-0 break-words bg-white shadow-soft-xl rounded-2xl bg-clip-border">
          <div class="flex-auto p-4">
            <div class="flex flex-row -mx-3">
              <div class="flex-none w-2/3 max-w-full px-3">
                <div>
                  <p class="mb-0 font-sans font-semibold leading-normal text-sm">Omzet Bulan Ini</p>
                  <h5 class="mb-0 font-bold text-lg">
                    Rp {{ number_format($pendapatanBulanIni, 0, ',', '.') }}
                  </h5>
                </div>
              </div>
              <div class="px-3 text-right basis-1/3">
                <div class="inline-block w-12 h-12 text-center rounded-lg bg-gradient-to-tl from-purple-700 to-pink-500">
                  <i class="ni leading-none ni-cart text-lg relative top-3.5 text-white"></i>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="flex flex-wrap mt-6 -mx-3">
      <div class="w-full px-3 mb-6 lg:mb-0 lg:w-5/12 lg:flex-none">
        <div class="border-black/12.5 shadow-soft-xl relative flex h-full min-w-0 flex-col break-words rounded-2xl border-0 border-solid bg-white bg-clip-border p-4">
          <div class="relative h-full overflow-hidden bg-cover rounded-xl" style="background-image: url('{{ asset('assets/img/home-decor-1.jpg') }}')">
            <span class="absolute top-0 left-0 w-full h-full bg-center bg-cover bg-gradient-to-tl from-gray-900 to-slate-800 opacity-80"></span>
            <div class="relative z-10 flex flex-col flex-auto h-full p-4">
              <h5 class="pt-2 mb-6 font-bold text-white">Sistem POS Koperasi</h5>
              <p class="text-white">
                Pantau seluruh transaksi kasbon karyawan dan penjualan konsinyasi secara real-time dari satu tempat.
              </p>
              <a class="mt-auto mb-0 font-semibold leading-normal text-white group text-sm" href="{{ route('waserba.index') }}">
                Buka Mesin Kasir <i class="fas fa-arrow-right ease-bounce text-sm group-hover:translate-x-1.25 ml-1 leading-normal transition-all duration-200"></i>
              </a>
            </div>
          </div>
        </div>
      </div>

      <div class="w-full max-w-full px-3 mt-0 lg:w-7/12 lg:flex-none">
        <div class="border-black/12.5 shadow-soft-xl relative z-20 flex h-full min-w-0 flex-col break-words rounded-2xl border-0 border-solid bg-white bg-clip-border">
          <div class="border-black/12.5 mb-0 rounded-t-2xl border-b-0 border-solid bg-white p-6 pb-0">
            <h6>Grafik Pendapatan Kasbon {{ Carbon\Carbon::now()->year }}</h6>
            <p class="leading-normal text-sm">Pemantauan transaksi bulanan</p>
          </div>
          <div class="flex-auto p-4">
            <div>
              <canvas id="chart-line" height="250"></canvas>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="flex flex-wrap my-6 -mx-3">
      <div class="w-full max-w-full px-3 mt-0 mb-6 md:mb-0 md:w-1/2 lg:w-2/3 lg:flex-none">
        <div class="border-black/12.5 shadow-soft-xl relative flex min-w-0 flex-col break-words rounded-2xl border-0 border-solid bg-white bg-clip-border">
          <div class="border-black/12.5 mb-0 rounded-t-2xl border-b-0 border-solid bg-white p-6 pb-0">
            <h6>Top 5 Produk Terlaris Bulan Ini</h6>
          </div>
          <div class="flex-auto p-6 px-0 pb-2">
            <div class="overflow-x-auto">
              <table class="items-center w-full mb-0 align-top border-gray-200 text-slate-500">
                <thead class="align-bottom">
                  <tr>
                    <th class="px-6 py-3 font-bold tracking-normal text-left uppercase align-middle bg-transparent border-b letter border-b-solid text-xxs whitespace-nowrap border-b-gray-200 text-slate-400 opacity-70">Produk</th>
                    <th class="px-6 py-3 pl-2 font-bold tracking-normal text-center uppercase align-middle bg-transparent border-b letter border-b-solid text-xxs whitespace-nowrap border-b-gray-200 text-slate-400 opacity-70">Terjual (Qty)</th>
                    <th class="px-6 py-3 font-bold tracking-normal text-center uppercase align-middle bg-transparent border-b letter border-b-solid text-xxs whitespace-nowrap border-b-gray-200 text-slate-400 opacity-70">Pendapatan</th>
                  </tr>
                </thead>
                <tbody>
                  @forelse($produkTerlaris as $item)
                    <tr>
                      <td class="p-2 align-middle bg-transparent border-b whitespace-nowrap">
                        <div class="flex px-4 py-1">
                          <div class="flex flex-col justify-center">
                            <h6 class="mb-0 leading-normal text-sm font-bold text-slate-700">{{ $item->produk->nama_produk ?? 'Produk Dihapus' }}</h6>
                            <p class="mb-0 text-xs leading-tight text-slate-400">
                              Kategori: {{ $item->produk->kategori->nama_kategori ?? '-' }}
                              @if($item->produk && $item->produk->konsinyasi) <span class="text-blue-500 font-bold">(Konsinyasi)</span> @endif
                            </p>
                          </div>
                        </div>
                      </td>
                      <td class="p-2 leading-normal text-center align-middle bg-transparent border-b text-sm whitespace-nowrap">
                        <span class="font-bold text-slate-700 text-sm">{{ $item->total_qty }}</span>
                      </td>
                      <td class="p-2 leading-normal text-center align-middle bg-transparent border-b text-sm whitespace-nowrap">
                        <span class="font-semibold leading-tight text-green-600 text-xs">Rp {{ number_format($item->total_revenue, 0, ',', '.') }}</span>
                      </td>
                    </tr>
                  @empty
                    <tr>
                      <td colspan="3" class="p-4 text-center text-sm text-slate-500 border-b">Belum ada data penjualan bulan ini.</td>
                    </tr>
                  @endforelse
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>

      <div class="w-full max-w-full px-3 md:w-1/2 lg:w-1/3 lg:flex-none">
        <div class="border-black/12.5 shadow-soft-xl relative flex h-full min-w-0 flex-col break-words rounded-2xl border-0 border-solid bg-white bg-clip-border">
          <div class="border-black/12.5 mb-0 rounded-t-2xl border-b-0 border-solid bg-white p-6 pb-0">
            <h6>Ringkasan Operasional</h6>
            <p class="leading-normal text-sm">
              <span class="font-semibold">Pantauan finance berbasis agregat</span>
            </p>
          </div>
          <div class="flex-auto p-4">
            <div class="flex items-start" style="gap: 0.75rem;">
              <div class="flex flex-shrink-0 items-center justify-center w-10 h-10 rounded-xl bg-gradient-to-tl from-green-600 to-lime-400 shadow-soft-sm">
                <i class="ni ni-chart-bar-32 text-white text-lg"></i>
              </div>
              <p class="mb-0 text-sm font-semibold leading-relaxed text-slate-500">
                Detail aktivitas kasir dibaca dari modul Penjualan/Kasir, Mutasi Kas & Bank, dan laporan akuntansi supaya dashboard tidak memiliki sumber transaksi terpisah.
              </p>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
@endsection

{{-- PUSH SCRIPT UNTUK CHART.JS --}}
@push('scripts')
  {{-- Pastikan kamu sudah meload library chart.js di layout/main kamu. Jika belum, tambahkan script src chartjs. --}}
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <script>
    var ctx2 = document.getElementById("chart-line").getContext("2d");

    var gradientStroke1 = ctx2.createLinearGradient(0, 230, 0, 50);
    gradientStroke1.addColorStop(1, 'rgba(47,143,58,0.22)');
    gradientStroke1.addColorStop(0.2, 'rgba(47,143,58,0.06)');
    gradientStroke1.addColorStop(0, 'rgba(47,143,58,0)');

    new Chart(ctx2, {
      type: "line",
      data: {
        labels: {!! json_encode($grafikBulan) !!}, // Label bulan dari Controller
        datasets: [{
            label: "Total Pendapatan (Rp)",
            tension: 0.4,
            borderWidth: 0,
            pointRadius: 0,
            borderColor: "#2f8f3a",
            borderWidth: 3,
            backgroundColor: gradientStroke1,
            fill: true,
            data: {!! json_encode($grafikPendapatan) !!}, // Data angka pendapatan dari Controller
            maxBarThickness: 6
          },
        ],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: {
            display: false,
          }
        },
        interaction: {
          intersect: false,
          mode: 'index',
        },
        scales: {
          y: {
            grid: {
              drawBorder: false,
              display: true,
              drawOnChartArea: true,
              drawTicks: false,
              borderDash: [5, 5]
            },
            ticks: {
              display: true,
              padding: 10,
              color: '#b2b9bf',
              font: {
                size: 11,
                family: "Open Sans",
                style: 'normal',
                lineHeight: 2
              },
            }
          },
          x: {
            grid: {
              drawBorder: false,
              display: false,
              drawOnChartArea: false,
              drawTicks: false,
              borderDash: [5, 5]
            },
            ticks: {
              display: true,
              color: '#b2b9bf',
              padding: 20,
              font: {
                size: 11,
                family: "Open Sans",
                style: 'normal',
                lineHeight: 2
              },
            }
          },
        },
      },
    });
  </script>
@endpush
