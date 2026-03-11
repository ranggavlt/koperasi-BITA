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

  <div class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
    <div class="h-full rounded-2xl border border-slate-100 bg-white shadow-soft-xl">
      <div class="flex h-full min-h-[210px] flex-col p-6">
        <div class="flex items-center justify-end">
          <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-slate-100 text-slate-600">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
              <path d="M16 11c1.66 0 3-1.34 3-3s-1.34-3-3-3-3 1.34-3 3 1.34 3 3 3Zm-8 0c1.66 0 3-1.34 3-3S9.66 5 8 5 5 6.34 5 8s1.34 3 3 3Zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4Zm8 0c-.29 0-.62.02-.97.05 1.16.84 1.97 1.97 1.97 3.45v2H24v-2c0-2.66-5.33-4-8-4Z"/>
            </svg>
          </div>
        </div>
        <div class="mt-auto pt-6">
          <p class="mb-4 text-xs font-bold uppercase tracking-[0.2em] text-slate-400">Karyawan Terpakai</p>
          <h5 class="mb-0 text-3xl font-bold text-slate-700">{{ $summary['total_karyawan'] }}</h5>
        </div>
      </div>
    </div>

    <div class="h-full rounded-2xl border border-slate-100 bg-white shadow-soft-xl">
      <div class="flex h-full min-h-[210px] flex-col p-6">
        <div class="flex items-center justify-end">
          <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-blue-50 text-blue-600">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
              <path d="M3 6h18v2H3V6Zm2 4h14v10H5V10Zm3 2v2h8v-2H8Z"/>
            </svg>
          </div>
        </div>
        <div class="mt-auto pt-6">
          <p class="mb-4 text-xs font-bold uppercase tracking-[0.2em] text-slate-400">Belanja Bulan Ini</p>
          <h5 class="mb-0 text-3xl font-bold text-slate-700">Rp {{ number_format($summary['total_belanja'], 0, ',', '.') }}</h5>
        </div>
      </div>
    </div>

    <div class="h-full rounded-2xl border border-slate-100 bg-white shadow-soft-xl">
      <div class="flex h-full min-h-[210px] flex-col p-6">
        <div class="flex items-center justify-end">
          <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-amber-50 text-amber-600">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
              <path d="M2 6h20v2H2V6Zm2 4h16v10H4V10Zm4 2v2h8v-2H8Zm0 4v2h5v-2H8Z"/>
            </svg>
          </div>
        </div>
        <div class="mt-auto pt-6">
          <p class="mb-4 text-xs font-bold uppercase tracking-[0.2em] text-slate-400">Pinjaman Cair Bulan Ini</p>
          <h5 class="mb-0 text-3xl font-bold text-slate-700">Rp {{ number_format($summary['total_pinjaman_baru'], 0, ',', '.') }}</h5>
        </div>
      </div>
    </div>

    <div class="h-full rounded-2xl border border-green-100 bg-white shadow-soft-xl">
      <div class="flex h-full min-h-[210px] flex-col p-6">
        <div class="flex items-center justify-end">
          <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-green-50 text-green-600">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
              <path d="M12 2 1 7l11 5 9-4.09V17h2V7L12 2Zm-7 9.91V17l7 3 7-3v-5.09l-7 3-7-3Z"/>
            </svg>
          </div>
        </div>
        <div class="mt-auto pt-6">
          <p class="mb-4 text-xs font-bold uppercase tracking-[0.2em] text-slate-400">Total Penggunaan Periode</p>
          <h5 class="mb-0 text-3xl font-bold text-green-600">Rp {{ number_format($summary['total_penggunaan'], 0, ',', '.') }}</h5>
        </div>
        <div class="mt-5 rounded-xl bg-slate-50 px-4 py-4">
          <p class="mb-2 text-[11px] font-bold uppercase tracking-[0.18em] text-slate-400">Sisa Pinjaman Aktif Seluruh Karyawan</p>
          <p class="mb-0 text-sm font-semibold text-slate-600">
            Rp {{ number_format($summary['total_sisa_pinjaman'], 0, ',', '.') }}
          </p>
        </div>
      </div>
    </div>
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
