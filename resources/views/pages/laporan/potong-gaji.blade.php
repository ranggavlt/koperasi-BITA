@extends('layout.main')

@section('content')
<div class="w-full px-6 py-6 mx-auto">

  <div class="relative mb-6 flex min-w-0 flex-col break-words rounded-2xl border-0 bg-white bg-clip-border shadow-soft-xl">
    <div class="mb-0 rounded-t-2xl bg-white p-6 pb-0">
      <h6>Laporan Potong Gaji Karyawan</h6>
      <p class="text-sm text-slate-400">
        Rekap penggunaan koperasi per karyawan untuk acuan finance pada periode gaji berikutnya
      </p>
    </div>

    <div class="p-6">
      <form class="flex flex-wrap items-end gap-3" method="GET" action="{{ route('laporan.potong-gaji') }}">
        <div>
          <label class="mb-1 block text-xs font-bold uppercase text-slate-700">Periode</label>
          <input type="month" name="periode" value="{{ $periode }}" class="rounded-lg border border-gray-300 px-3 py-2 text-sm">
        </div>

        <button class="rounded-lg bg-gradient-to-tl from-purple-700 to-pink-500 px-6 py-3 text-xs font-bold uppercase text-white">
          Tampilkan
        </button>

        <p class="text-sm text-slate-400">
          Periode: {{ $mulai->translatedFormat('d M Y') }} s/d {{ $akhir->translatedFormat('d M Y') }}
        </p>
      </form>
    </div>
  </div>

  @php
    $metricCards = [
        [
            'title' => 'Karyawan Terpakai',
            'value' => $summary['total_karyawan'],
            'suffix' => 'Karyawan',
            'icon' => 'ni ni-single-02',
        ],
        [
            'title' => 'Belanja Bulan Ini',
            'value' => 'Rp ' . number_format($summary['total_belanja'], 0, ',', '.'),
            'suffix' => null,
            'icon' => 'ni ni-cart',
        ],
        [
            'title' => 'Pinjaman Cair Bulan Ini',
            'value' => 'Rp ' . number_format($summary['total_pinjaman_baru'], 0, ',', '.'),
            'suffix' => null,
            'icon' => 'ni ni-money-coins',
        ],
        [
            'title' => 'Total Penggunaan Periode',
            'value' => 'Rp ' . number_format($summary['total_penggunaan'], 0, ',', '.'),
            'suffix' => null,
            'icon' => 'ni ni-chart-bar-32',
            'note' => 'Sisa Pinjaman Aktif: Rp ' . number_format($summary['total_sisa_pinjaman'], 0, ',', '.'),
        ],
    ];
  @endphp

  <div class="mb-6 flex flex-wrap -mx-3">
    @foreach($metricCards as $card)
      <div class="w-full max-w-full px-3 mb-6 sm:w-1/2 sm:flex-none xl:mb-0 xl:w-1/4">
        <div class="relative flex flex-col min-w-0 break-words bg-white shadow-soft-xl rounded-2xl bg-clip-border h-full">
          <div class="flex-auto p-4">
            <div class="flex flex-row -mx-3 h-full">
              <div class="flex-none w-2/3 max-w-full px-3">
                <div>
                  <p class="mb-0 font-sans font-semibold leading-normal text-sm text-slate-500">{{ $card['title'] }}</p>
                  <h5 class="mb-0 font-bold text-slate-700">
                    {{ $card['value'] }}
                    @if($card['suffix'])
                      <span class="text-sm font-semibold text-slate-500">{{ $card['suffix'] }}</span>
                    @endif
                  </h5>

                  @if(isset($card['note']))
                    <p class="mt-2 mb-0 text-xs text-slate-400">{{ $card['note'] }}</p>
                  @endif
                </div>
              </div>

              <div class="px-3 text-right basis-1/3">
                <div class="inline-block w-12 h-12 text-center rounded-lg bg-gradient-to-tl from-purple-700 to-pink-500">
                  <i class="{{ $card['icon'] }} text-lg relative top-3.5 text-white"></i>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    @endforeach
  </div>

  <div class="relative flex min-w-0 flex-col break-words rounded-2xl border-0 bg-white bg-clip-border shadow-soft-xl">
    <div class="mb-0 rounded-t-2xl bg-white p-6 pb-0">
      <h6>Rekap Finance</h6>
      <p class="text-sm text-slate-400">
        Total penggunaan periode = belanja kasbon + pinjaman cair pada bulan terpilih
      </p>
    </div>

    <div class="flex-auto px-0 pt-0 pb-2">
      <div class="overflow-x-auto p-0">
        <table class="mb-0 w-full items-center align-top border-gray-200 text-slate-500">
          <thead>
            <tr>
              <th class="px-6 py-3 text-left text-xxs font-bold uppercase text-slate-400 opacity-70">Karyawan</th>
              <th class="px-6 py-3 text-left text-xxs font-bold uppercase text-slate-400 opacity-70">Rincian Belanja</th>
              <th class="px-6 py-3 text-center text-xxs font-bold uppercase text-slate-400 opacity-70">Belanja</th>
              <th class="px-6 py-3 text-center text-xxs font-bold uppercase text-slate-400 opacity-70">Pinjaman Cair</th>
              <th class="px-6 py-3 text-center text-xxs font-bold uppercase text-slate-400 opacity-70">Cicilan Tercatat</th>
              <th class="px-6 py-3 text-center text-xxs font-bold uppercase text-slate-400 opacity-70">Sisa Pinjaman</th>
              <th class="px-6 py-3 text-center text-xxs font-bold uppercase text-slate-400 opacity-70">Sisa Limit</th>
              <th class="px-6 py-3 text-center text-xxs font-bold uppercase text-slate-400 opacity-70">Total Penggunaan</th>
            </tr>
          </thead>
          <tbody>
            @forelse($laporan as $row)
              <tr>
                <td class="border-b p-2 align-middle whitespace-nowrap">
                  <div class="px-4 py-2">
                    <h6 class="mb-0 text-sm leading-normal text-slate-700">{{ $row->karyawan->nama }}</h6>
                    <p class="mb-0 text-xs text-slate-400">{{ $row->karyawan->jabatan ?: 'Tanpa jabatan' }}</p>
                  </div>
                </td>
                <td class="border-b p-2 align-middle">
                  <div class="px-4 py-2">
                    @forelse($row->rincian_belanja as $rincian)
                      <span class="mb-1 mr-1 inline-block rounded-lg bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-600">
                        {{ $rincian }}
                      </span>
                    @empty
                      <span class="text-xs text-slate-400">Tidak ada belanja pada periode ini</span>
                    @endforelse
                    <p class="mt-2 mb-0 text-xs text-slate-400">
                      {{ $row->jumlah_transaksi }} transaksi belanja
                    </p>
                  </div>
                </td>
                <td class="border-b p-2 text-center align-middle">
                  Rp {{ number_format($row->total_belanja, 0, ',', '.') }}
                </td>
                <td class="border-b p-2 text-center align-middle">
                  Rp {{ number_format($row->total_pinjaman_baru, 0, ',', '.') }}
                </td>
                <td class="border-b p-2 text-center align-middle">
                  Rp {{ number_format($row->total_cicilan, 0, ',', '.') }}
                </td>
                <td class="border-b p-2 text-center align-middle">
                  Rp {{ number_format($row->sisa_pinjaman_aktif, 0, ',', '.') }}
                </td>
                <td class="border-b p-2 text-center align-middle">
                  Rp {{ number_format($row->sisa_limit_bulan, 0, ',', '.') }}
                </td>
                <td class="border-b p-2 text-center align-middle">
                  <span class="rounded-lg bg-green-100 px-3 py-2 text-xs font-bold text-green-700">
                    Rp {{ number_format($row->total_penggunaan, 0, ',', '.') }}
                  </span>
                </td>
              </tr>
            @empty
              <tr>
                <td colspan="8" class="p-6 text-center text-sm text-slate-400">
                  Belum ada transaksi karyawan pada periode ini.
                </td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>
  </div>

</div>
@endsection
