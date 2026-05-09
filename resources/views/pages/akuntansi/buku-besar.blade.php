@extends('layout.main')

@section('content')
<div class="w-full px-6 py-6 mx-auto">
  <div class="flex flex-wrap -mx-3">
    <div class="flex-none w-full max-w-full px-3">
      <div class="relative flex flex-col min-w-0 mb-6 break-words bg-white border-0 shadow-soft-xl rounded-2xl bg-clip-border">
        <div class="p-6 pb-0 mb-0 bg-white rounded-t-2xl">
          <h6>Buku Besar</h6>
          <p class="text-sm text-slate-400">Periode: {{ $mulai->format('d M Y') }} - {{ $akhir->format('d M Y') }}</p>
        </div>

        <div class="flex-auto p-6">
          <form method="GET" action="{{ route('akuntansi.buku-besar') }}" class="mb-4">
            <div class="flex flex-wrap items-end -mx-2">
              <div class="w-full max-w-full px-2 md:w-3/12">
                <label class="mb-2 ml-1 block text-xs font-bold uppercase text-slate-700">Periode (YYYY-MM)</label>
                <input type="text" name="periode" value="{{ $periode }}"
                  class="focus:shadow-soft-primary-outline text-sm leading-5.6 ease-soft block w-full rounded-lg border border-solid border-gray-300 bg-white px-3 py-2 text-gray-700 focus:border-fuchsia-300 focus:outline-none"
                  placeholder="2026-05" />
              </div>
              <div class="w-full max-w-full px-2 md:w-5/12 mt-3 md:mt-0">
                <label class="mb-2 ml-1 block text-xs font-bold uppercase text-slate-700">Akun</label>
                <select name="akun"
                  class="focus:shadow-soft-primary-outline text-sm leading-5.6 ease-soft block w-full rounded-lg border border-solid border-gray-300 bg-white px-3 py-2 text-gray-700 focus:border-fuchsia-300 focus:outline-none">
                  @foreach($akunList as $item)
                    <option value="{{ $item->akun_kode }}" {{ $akun === $item->akun_kode ? 'selected' : '' }}>
                      {{ $item->akun_kode }} - {{ $item->akun_nama }}
                    </option>
                  @endforeach
                </select>
              </div>
              <div class="w-full max-w-full px-2 md:w-2/12 mt-3 md:mt-0">
                <button type="submit"
                  class="inline-block rounded-lg bg-gradient-to-tl from-blue-600 to-cyan-400 px-4 py-2 text-xs font-bold uppercase text-white shadow-soft-md transition-all hover:scale-105">
                  Tampilkan
                </button>
              </div>
            </div>
          </form>

          <div class="mb-4 rounded-lg bg-gray-50 px-4 py-3 text-sm text-slate-600">
            <div class="flex flex-wrap -mx-2">
              <div class="w-full max-w-full px-2 md:w-4/12">Total Debit: <strong>{{ number_format($totalDebit, 0, ',', '.') }}</strong></div>
              <div class="w-full max-w-full px-2 md:w-4/12 mt-2 md:mt-0">Total Kredit: <strong>{{ number_format($totalKredit, 0, ',', '.') }}</strong></div>
              <div class="w-full max-w-full px-2 md:w-4/12 mt-2 md:mt-0">Saldo Akhir: <strong>{{ number_format($saldoAkhir, 0, ',', '.') }}</strong></div>
            </div>
          </div>

          <div class="overflow-x-auto">
            <table class="items-center w-full mb-0 align-top border-gray-200 text-slate-500">
              <thead class="align-bottom">
                <tr>
                  <th class="px-6 py-3 text-left text-xxs font-bold uppercase text-slate-400 opacity-70 border-b border-gray-200">Tanggal</th>
                  <th class="px-6 py-3 text-left text-xxs font-bold uppercase text-slate-400 opacity-70 border-b border-gray-200">No. Bukti</th>
                  <th class="px-6 py-3 text-left text-xxs font-bold uppercase text-slate-400 opacity-70 border-b border-gray-200">Keterangan</th>
                  <th class="px-6 py-3 text-right text-xxs font-bold uppercase text-slate-400 opacity-70 border-b border-gray-200">Debit</th>
                  <th class="px-6 py-3 text-right text-xxs font-bold uppercase text-slate-400 opacity-70 border-b border-gray-200">Kredit</th>
                  <th class="px-6 py-3 text-right text-xxs font-bold uppercase text-slate-400 opacity-70 border-b border-gray-200">Saldo</th>
                </tr>
              </thead>
              <tbody>
                @forelse($lines as $row)
                  <tr>
                    <td class="p-2 align-middle bg-transparent border-b whitespace-nowrap shadow-transparent">
                      <p class="mb-0 px-4 text-sm font-semibold text-slate-700">{{ $row->tanggal ? \Carbon\Carbon::parse($row->tanggal)->format('d/m/Y') : '-' }}</p>
                    </td>
                    <td class="p-2 align-middle bg-transparent border-b whitespace-nowrap shadow-transparent">
                      <p class="mb-0 px-4 text-sm text-slate-600">{{ $row->nomor_bukti ?? '-' }}</p>
                    </td>
                    <td class="p-2 align-middle bg-transparent border-b shadow-transparent">
                      <p class="mb-0 px-4 text-sm text-slate-600">{{ $row->keterangan ?? '-' }}</p>
                    </td>
                    <td class="p-2 align-middle bg-transparent border-b whitespace-nowrap shadow-transparent text-right">
                      <p class="mb-0 px-4 text-sm text-slate-600">{{ $row->debit > 0 ? number_format($row->debit, 0, ',', '.') : '-' }}</p>
                    </td>
                    <td class="p-2 align-middle bg-transparent border-b whitespace-nowrap shadow-transparent text-right">
                      <p class="mb-0 px-4 text-sm text-slate-600">{{ $row->kredit > 0 ? number_format($row->kredit, 0, ',', '.') : '-' }}</p>
                    </td>
                    <td class="p-2 align-middle bg-transparent border-b whitespace-nowrap shadow-transparent text-right">
                      <p class="mb-0 px-4 text-sm font-bold text-slate-700">{{ number_format($row->saldo, 0, ',', '.') }}</p>
                    </td>
                  </tr>
                @empty
                  <tr>
                    <td colspan="6" class="p-6 text-center text-sm text-slate-400">Belum ada transaksi untuk akun ini pada periode tersebut.</td>
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

