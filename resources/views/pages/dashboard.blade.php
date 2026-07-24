@extends('layout.main')

@section('content')
<div class="w-full px-6 py-6 mx-auto">
    {{-- BARIS KARTU ATAS --}}
    <div class="flex flex-wrap -mx-3">
        
        {{-- KARTU 1: Pendapatan Hari Ini --}}
        <div class="w-full max-w-full px-3 mb-6 sm:w-1/2 sm:flex-none xl:mb-0 xl:w-1/4">
            <div class="relative flex flex-col h-full min-w-0 break-words bg-white shadow-soft-xl rounded-2xl bg-clip-border">
                <div class="flex-auto p-4">
                    <div class="flex items-center justify-between h-full">
                        <div class="flex flex-col flex-1 min-w-0 pr-2">
                            <p class="mb-0 font-sans font-semibold leading-normal text-sm text-slate-500 truncate">Pendapatan Hari Ini</p>
                            <h5 class="mb-0 font-bold text-lg xl:text-xl truncate">
                                Rp {{ number_format($pendapatanHariIni ?? 0, 0, ',', '.') }}
                            </h5>
                        </div>
                        <div class="flex-shrink-0">
                            <div class="kbsm-stat-icon flex items-center justify-center w-12 h-12 rounded-lg">
                                <i class="ni ni-money-coins text-lg text-white"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- KARTU 2: Transaksi Hari Ini --}}
        <div class="w-full max-w-full px-3 mb-6 sm:w-1/2 sm:flex-none xl:mb-0 xl:w-1/4">
            <div class="relative flex flex-col h-full min-w-0 break-words bg-white shadow-soft-xl rounded-2xl bg-clip-border">
                <div class="flex-auto p-4">
                    <div class="flex items-center justify-between h-full">
                        <div class="flex flex-col flex-1 min-w-0 pr-2">
                            <p class="mb-0 font-sans font-semibold leading-normal text-sm text-slate-500 truncate">Transaksi Hari Ini</p>
                            <h5 class="mb-0 font-bold text-lg xl:text-xl truncate">
                                {{ $transaksiHariIni ?? 0 }} Struk
                            </h5>
                        </div>
                        <div class="flex-shrink-0">
                            <div class="kbsm-stat-icon flex items-center justify-center w-12 h-12 rounded-lg">
                                <i class="ni ni-world text-lg text-white"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- KARTU 3: Konsinyasi Laku --}}
        <div class="w-full max-w-full px-3 mb-6 sm:w-1/2 sm:flex-none xl:mb-0 xl:w-1/4">
            <div class="relative flex flex-col h-full min-w-0 break-words bg-white shadow-soft-xl rounded-2xl bg-clip-border">
                <div class="flex-auto p-4">
                    <div class="flex items-center justify-between h-full">
                        <div class="flex flex-col flex-1 min-w-0 pr-2">
                            <p class="mb-0 font-sans font-semibold leading-normal text-sm text-slate-500 truncate">Konsinyasi Laku</p>
                            <h5 class="mb-0 font-bold text-lg xl:text-xl truncate">
                                {{ $konsinyasiBulanIni ?? 0 }} Item
                            </h5>
                        </div>
                        <div class="flex-shrink-0">
                            <div class="kbsm-stat-icon flex items-center justify-center w-12 h-12 rounded-lg">
                                <i class="ni ni-paper-diploma text-lg text-white"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- KARTU 4: Omzet Bulan Ini --}}
        <div class="w-full max-w-full px-3 sm:w-1/2 sm:flex-none xl:w-1/4">
            <div class="relative flex flex-col h-full min-w-0 break-words bg-white shadow-soft-xl rounded-2xl bg-clip-border">
                <div class="flex-auto p-4">
                    <div class="flex items-center justify-between h-full">
                        <div class="flex flex-col flex-1 min-w-0 pr-2">
                            <p class="mb-0 font-sans font-semibold leading-normal text-sm text-slate-500 truncate">Omzet Bulan Ini</p>
                            <h5 class="mb-0 font-bold text-lg xl:text-xl truncate">
                                Rp {{ number_format($pendapatanBulanIni ?? 0, 0, ',', '.') }}
                            </h5>
                        </div>
                        <div class="flex-shrink-0">
                            <div class="kbsm-stat-icon flex items-center justify-center w-12 h-12 rounded-lg">
                                <i class="ni ni-cart text-lg text-white"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- BARIS TENGAH: Banner & Grafik --}}
    <div class="flex flex-wrap mt-6 -mx-3">
        <div class="w-full px-3 mb-6 lg:mb-0 lg:w-5/12 lg:flex-none">
            <div class="relative flex flex-col min-w-0 break-words bg-white shadow-soft-xl rounded-2xl bg-clip-border p-4 h-full">
                <div class="relative h-full overflow-hidden bg-cover rounded-xl" style="background-image: url('../assets/img/ivancik.jpg')">
                    <span class="kbsm-gradient-brand absolute top-0 left-0 w-full h-full bg-center bg-cover opacity-90"></span>
                    <div class="relative z-10 flex flex-col flex-auto h-full p-4">
                        <h5 class="pt-2 mb-6 font-bold text-white">Sistem POS Koperasi</h5>
                        <p class="text-white">Kelola transaksi kasbon karyawan dan produk konsinyasi secara efisien.</p>
                        <a class="mt-auto mb-0 font-semibold leading-normal text-white group text-sm" href="{{ route('waserba.index') }}">
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
                    <h6>Grafik Pendapatan {{ date('Y') }}</h6>
                </div>
                <div class="flex-auto p-4">
                    <div>
                        <canvas id="revenueChart" height="300"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- BARIS BAWAH: Top Produk & Aktivitas --}}
    <div class="flex flex-wrap my-6 -mx-3">
        <div class="w-full max-w-full px-3 mt-0 mb-6 md:mb-0 md:w-1/2 lg:w-2/3 lg:flex-none">
            <div class="border-black/12.5 shadow-soft-xl relative flex min-w-0 flex-col break-words rounded-2xl border-0 border-solid bg-white bg-clip-border">
                <div class="p-6 pb-0 mb-0 bg-white rounded-t-2xl">
                    <h6>Top 5 Produk Terlaris</h6>
                </div>
                <div class="flex-auto p-6 px-0 pb-2">
                    <div style="overflow-x: auto;" class="">
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

        {{-- FIX: Layout Aktivitas Terakhir Dirombak Jadi Flexbox List --}}
        <div class="w-full max-w-full px-3 md:w-1/2 lg:w-1/3 lg:flex-none">
            <div class="border-black/12.5 shadow-soft-xl relative flex h-full min-w-0 flex-col break-words rounded-2xl border-0 border-solid bg-white bg-clip-border">
                <div class="p-6 pb-0 mb-4 bg-white rounded-t-2xl">
                    <h6>Aktivitas Terakhir</h6>
                </div>
                <div class="flex-auto p-6 pt-0">
                    <div class="flex flex-col gap-5">
                        @forelse($transaksiTerakhir as $trx)
                        <div class="flex items-center justify-between">
                            <div class="flex items-center" style="gap: 0.75rem;">
                                {{-- Icon Keranjang --}}
                                <div class="flex flex-shrink-0 items-center justify-center w-10 h-10 rounded-xl bg-gradient-to-tl from-green-600 to-lime-400 shadow-soft-sm">
                                    <i class="ni ni-cart text-white text-lg"></i>
                                </div>
                                {{-- Info Kiri --}}
                                <div class="flex flex-col">
                                    <h6 class="mb-0 text-sm font-semibold text-slate-700">{{ $trx->kode_transaksi }}</h6>
                                    <span class="text-xs font-semibold text-slate-400">{{ $trx->karyawan->nama ?? 'Admin/Kasir' }}</span>
                                </div>
                            </div>
                            {{-- Info Kanan (Harga & Waktu) --}}
                            <div class="flex flex-col text-right">
                                <h6 class="mb-0 text-sm font-bold text-slate-700">Rp {{ number_format($trx->grand_total, 0, ',', '.') }}</h6>
                                <span class="text-xs font-semibold text-slate-400">
                                    {{ \Carbon\Carbon::parse($trx->created_at)->format('d M H:i') }}
                                </span>
                            </div>
                        </div>
                        @empty
                        <div class="text-center text-sm text-slate-400 py-4">Belum ada aktivitas.</div>
                        @endforelse
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
    const labels = {!! json_encode($grafikBulan) !!};
    const dataPendapatan = {!! json_encode($grafikPendapatan) !!};

    const ctx = document.getElementById('revenueChart').getContext('2d');

    const gradient = ctx.createLinearGradient(0,0,0,300);
    gradient.addColorStop(0,"rgba(47,143,58,0.34)");
    gradient.addColorStop(1,"rgba(47,143,58,0)");

    new Chart(ctx,{
        type:'line',
        data:{
            labels:labels,
            datasets:[{
                label:'Pendapatan',
                data:dataPendapatan,
                borderColor:'#2f8f3a',
                backgroundColor:gradient,
                borderWidth:3,
                pointRadius:4,
                pointBackgroundColor:'#2f8f3a',
                pointBorderColor:'#ffffff',
                pointBorderWidth:2,
                fill:true,
                tension:0.4
            }]
        },
        options:{
            responsive:true,
            maintainAspectRatio:false,
            interaction:{
                intersect:false,
                mode:'index'
            },
            plugins:{
                legend:{
                    display:false
                },
                tooltip:{
                    callbacks:{
                        label:function(context){
                            return "Rp " + context.raw.toLocaleString('id-ID');
                        }
                    }
                }
            },
            scales:{
                y:{
                    grid:{
                        color:'#eef2f7'
                    },
                    ticks:{
                        callback:function(value){
                            return 'Rp ' + value.toLocaleString('id-ID');
                        }
                    }
                },
                x:{
                    grid:{
                        display:false
                    }
                }
            }
        }
    });
</script>
@endpush
