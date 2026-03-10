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
                                <h5 class="mb-0 font-bold">
                                    Rp {{ number_format($pendapatanHariIni ?? 0, 0, ',', '.') }}
                                </h5>
                            </div>
                        </div>
                        <div class="px-3 text-right basis-1/3">
                            <div class="inline-block w-12 h-12 text-center rounded-lg bg-gradient-to-tl from-purple-700 to-pink-500">
                                <i class="ni ni-money-coins text-lg relative top-3.5 text-white"></i>
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
                                <h5 class="mb-0 font-bold">
                                    {{ $transaksiHariIni ?? 0 }} Struk
                                </h5>
                            </div>
                        </div>
                        <div class="px-3 text-right basis-1/3">
                            <div class="inline-block w-12 h-12 text-center rounded-lg bg-gradient-to-tl from-purple-700 to-pink-500">
                                <i class="ni ni-world text-lg relative top-3.5 text-white"></i>
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
                                <p class="mb-0 font-sans font-semibold leading-normal text-sm">Konsinyasi Laku</p>
                                <h5 class="mb-0 font-bold">
                                    {{ $konsinyasiBulanIni ?? 0 }} Item
                                </h5>
                            </div>
                        </div>
                        <div class="px-3 text-right basis-1/3">
                            <div class="inline-block w-12 h-12 text-center rounded-lg bg-gradient-to-tl from-purple-700 to-pink-500">
                                <i class="ni ni-paper-diploma text-lg relative top-3.5 text-white"></i>
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
                                <h5 class="mb-0 font-bold">
                                    Rp {{ number_format($pendapatanBulanIni ?? 0, 0, ',', '.') }}
                                </h5>
                            </div>
                        </div>
                        <div class="px-3 text-right basis-1/3">
                            <div class="inline-block w-12 h-12 text-center rounded-lg bg-gradient-to-tl from-purple-700 to-pink-500">
                                <i class="ni ni-cart text-lg relative top-3.5 text-white"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="flex flex-wrap mt-6 -mx-3">
        <div class="w-full px-3 mb-6 lg:mb-0 lg:w-5/12 lg:flex-none">
            <div class="relative flex flex-col min-w-0 break-words bg-white shadow-soft-xl rounded-2xl bg-clip-border p-4 h-full">
                <div class="relative h-full overflow-hidden bg-cover rounded-xl" style="background-image: url('../assets/img/ivancik.jpg')">
                    <span class="absolute top-0 left-0 w-full h-full bg-center bg-cover bg-gradient-to-tl from-gray-900 to-slate-800 opacity-80"></span>
                    <div class="relative z-10 flex flex-col flex-auto h-full p-4">
                        <h5 class="pt-2 mb-6 font-bold text-white">Sistem POS Koperasi</h5>
                        <p class="text-white">Kelola transaksi kasbon karyawan dan produk konsinyasi secara efisien.</p>
                        <a class="mt-auto mb-0 font-semibold leading-normal text-white group text-sm" href="{{ route('penjualan.index') }}">
                            Buka Mesin Kasir
                            <i class="fas fa-arrow-right ease-bounce text-sm group-hover:translate-x-1.25 ml-1 transition-all duration-200"></i>
                        </a>
                    </div>
                </div>
            </div>
        </div>
        <div class="w-full max-w-full px-3 mt-0 lg:w-7/12 lg:flex-none">
            <div class="border-black/12.5 shadow-soft-xl relative z-20 flex min-w-0 flex-col break-words rounded-2xl border-0 border-solid bg-white bg-clip-border">
                <div class="p-6 pb-0 mb-0 bg-white rounded-t-2xl">
                    <h6>Grafik Pendapatan Tahun {{ date('Y') }}</h6>
                </div>
                <div class="flex-auto p-4">
                    <div>
                        <canvas id="chart-line" height="300"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="flex flex-wrap my-6 -mx-3">
        <div class="w-full max-w-full px-3 mt-0 mb-6 md:mb-0 md:w-1/2 lg:w-2/3 lg:flex-none">
            <div class="border-black/12.5 shadow-soft-xl relative flex min-w-0 flex-col break-words rounded-2xl border-0 border-solid bg-white bg-clip-border">
                <div class="p-6 pb-0 mb-0 bg-white rounded-t-2xl">
                    <h6>Top 5 Produk Terlaris</h6>
                </div>
                <div class="flex-auto p-6 px-0 pb-2">
                    <div class="overflow-x-auto">
                        <table class="items-center w-full mb-0 align-top border-gray-200 text-slate-500">
                            <thead class="align-bottom">
                                <tr>
                                    <th class="px-6 py-3 font-bold text-left uppercase bg-transparent border-b text-xxs border-b-gray-200 text-slate-400 opacity-70">Produk</th>
                                    <th class="px-6 py-3 font-bold text-center uppercase bg-transparent border-b text-xxs border-b-gray-200 text-slate-400 opacity-70">Terjual</th>
                                    <th class="px-6 py-3 font-bold text-center uppercase bg-transparent border-b text-xxs border-b-gray-200 text-slate-400 opacity-70">Revenue</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($produkTerlaris as $item)
                                <tr>
                                    <td class="p-2 align-middle bg-transparent border-b whitespace-nowrap">
                                        <div class="flex px-4 py-1">
                                            <div class="flex flex-col justify-center">
                                                <h6 class="mb-0 text-sm font-bold text-slate-700">{{ $item->produk->nama_produk ?? 'N/A' }}</h6>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="p-2 text-center align-middle bg-transparent border-b text-sm whitespace-nowrap">
                                        <span class="font-bold">{{ $item->total_qty }}</span>
                                    </td>
                                    <td class="p-2 text-center align-middle bg-transparent border-b text-sm whitespace-nowrap">
                                        <span class="font-bold text-green-600">Rp {{ number_format($item->total_revenue, 0, ',', '.') }}</span>
                                    </td>
                                </tr>
                                @empty
                                <tr><td colspan="3" class="text-center p-4">Belum ada penjualan.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="w-full max-w-full px-3 md:w-1/2 lg:w-1/3 lg:flex-none">
            <div class="border-black/12.5 shadow-soft-xl relative flex h-full min-w-0 flex-col break-words rounded-2xl border-0 border-solid bg-white bg-clip-border">
                <div class="p-6 pb-0 mb-0 bg-white rounded-t-2xl">
                    <h6>Aktivitas Terakhir</h6>
                </div>
                <div class="flex-auto p-4">
                    <div class="before:border-r-solid relative before:absolute before:top-0 before:left-4 before:h-full before:border-r-2 before:border-r-slate-100 before:content-['']">
                        @foreach($transaksiTerakhir as $trx)
                        <div class="relative mb-4 mt-0">
                            <span class="w-6.5 h-6.5 text-base absolute left-4 z-10 inline-flex -translate-x-1/2 items-center justify-center rounded-full bg-white text-center">
                                <i class="ni ni-cart text-transparent bg-gradient-to-tl from-green-600 to-lime-400 bg-clip-text"></i>
                            </span>
                            <div class="ml-11 pt-1.4 relative -top-1.5">
                                <h6 class="mb-0 font-semibold text-sm text-slate-700">Rp {{ number_format($trx->grand_total, 0, ',', '.') }}</h6>
                                <p class="mt-1 mb-0 text-xs text-slate-400 font-bold">{{ $trx->kode_transaksi }} | {{ $trx->karyawan->nama ?? 'Seseorang' }}</p>
                            </div>
                        </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    var ctx2 = document.getElementById("chart-line").getContext("2d");
    var gradientStroke1 = ctx2.createLinearGradient(0, 230, 0, 50);
    gradientStroke1.addColorStop(1, 'rgba(203,12,159,0.2)');
    gradientStroke1.addColorStop(0, 'rgba(203,12,159,0)');

    new Chart(ctx2, {
        type: "line",
        data: {
            labels: {!! json_encode($grafikBulan) !!},
            datasets: [{
                label: "Pendapatan",
                tension: 0.4,
                borderColor: "#cb0c9f",
                borderWidth: 3,
                backgroundColor: gradientStroke1,
                fill: true,
                data: {!! json_encode($grafikPendapatan) !!},
            }],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                y: { grid: { borderDash: [5, 5] } },
                x: { grid: { display: false } }
            },
        },
    });
</script>
@endpush