@extends('layout.main')

@section('content')
<div class="w-full px-6 py-6 mx-auto">
  <div class="flex flex-wrap -mx-3">
    <div class="flex-none w-full max-w-full px-3">
      <div class="relative flex flex-col min-w-0 mb-6 break-words bg-white border-0 shadow-soft-xl rounded-2xl bg-clip-border">
        <div class="p-6 pb-0 mb-0 bg-white rounded-t-2xl">
          <h6>Jurnal Umum Periodik</h6>
          <p class="text-sm text-slate-400">Periode: {{ $mulai->format('d M Y') }} - {{ $akhir->format('d M Y') }}</p>
        </div>

        <div class="flex-auto p-6">
          <form method="GET" action="{{ route('akuntansi.jurnal-umum') }}" class="mb-4">
            <div class="flex flex-wrap items-end -mx-2">
              <div class="w-full max-w-full px-2 md:w-3/12">
                <label class="mb-2 ml-1 block text-xs font-bold uppercase text-slate-700">Periode (YYYY-MM)</label>
                <input type="text" name="periode" value="{{ $periode }}"
                  class="focus:shadow-soft-primary-outline text-sm leading-5.6 ease-soft block w-full rounded-lg border border-solid border-gray-300 bg-white px-3 py-2 text-gray-700 focus:border-fuchsia-300 focus:outline-none"
                  placeholder="2026-05" />
              </div>
              <div class="w-full max-w-full px-2 md:w-2/12 mt-3 md:mt-0">
                <button type="submit"
                  class="inline-block rounded-lg bg-gradient-to-tl from-blue-600 to-cyan-400 px-4 py-2 text-xs font-bold uppercase text-white shadow-soft-md transition-all hover:scale-105">
                  Tampilkan
                </button>
              </div>
            </div>
          </form>

          <div class="overflow-x-auto">
            <table class="items-center w-full mb-0 align-top border-gray-200 text-slate-500">
              <thead class="align-bottom">
                <tr>
                  <th class="px-6 py-3 text-left text-xxs font-bold uppercase text-slate-400 opacity-70 border-b border-gray-200">Tanggal</th>
                  <th class="px-6 py-3 text-left text-xxs font-bold uppercase text-slate-400 opacity-70 border-b border-gray-200">No. Bukti</th>
                  <th class="px-6 py-3 text-left text-xxs font-bold uppercase text-slate-400 opacity-70 border-b border-gray-200">Keterangan</th>
                  <th class="px-6 py-3 text-right text-xxs font-bold uppercase text-slate-400 opacity-70 border-b border-gray-200">Debit</th>
                  <th class="px-6 py-3 text-right text-xxs font-bold uppercase text-slate-400 opacity-70 border-b border-gray-200">Kredit</th>
                </tr>
              </thead>
              <tbody>
                @forelse($jurnal as $entry)
                  @php
                    $totalDebit = (float) $entry->details->sum('debit');
                    $totalKredit = (float) $entry->details->sum('kredit');
                  @endphp
                  <tr class="bg-gray-50/40">
                    <td class="p-2 align-middle bg-transparent border-b whitespace-nowrap shadow-transparent">
                      <div class="px-4 py-2">
                        <p class="mb-0 text-sm font-semibold text-slate-700">{{ optional($entry->tanggal)->format('d/m/Y') }}</p>
                      </div>
                    </td>
                    <td class="p-2 align-middle bg-transparent border-b whitespace-nowrap shadow-transparent">
                      <p class="mb-0 px-4 text-sm font-semibold text-slate-600">{{ $entry->nomor_bukti ?? '-' }}</p>
                    </td>
                    <td class="p-2 align-middle bg-transparent border-b shadow-transparent">
                      <p class="mb-0 px-4 text-sm text-slate-600">{{ $entry->keterangan ?? '-' }}</p>
                    </td>
                    <td class="p-2 align-middle bg-transparent border-b whitespace-nowrap shadow-transparent text-right">
                      <p class="mb-0 px-4 text-sm font-bold text-slate-700">{{ number_format($totalDebit, 0, ',', '.') }}</p>
                    </td>
                    <td class="p-2 align-middle bg-transparent border-b whitespace-nowrap shadow-transparent text-right">
                      <p class="mb-0 px-4 text-sm font-bold text-slate-700">{{ number_format($totalKredit, 0, ',', '.') }}</p>
                    </td>
                  </tr>
                  @foreach($entry->details as $detail)
                    <tr>
                      <td class="p-2 align-middle bg-transparent border-b whitespace-nowrap shadow-transparent"></td>
                      <td class="p-2 align-middle bg-transparent border-b whitespace-nowrap shadow-transparent">
                        <p class="mb-0 px-4 text-xs text-slate-500">{{ $detail->akun_kode }} - {{ $detail->akun_nama }}</p>
                      </td>
                      <td class="p-2 align-middle bg-transparent border-b shadow-transparent"></td>
                      <td class="p-2 align-middle bg-transparent border-b whitespace-nowrap shadow-transparent text-right">
                        <p class="mb-0 px-4 text-xs text-slate-600">{{ $detail->debit > 0 ? number_format($detail->debit, 0, ',', '.') : '-' }}</p>
                      </td>
                      <td class="p-2 align-middle bg-transparent border-b whitespace-nowrap shadow-transparent text-right">
                        <p class="mb-0 px-4 text-xs text-slate-600">{{ $detail->kredit > 0 ? number_format($detail->kredit, 0, ',', '.') : '-' }}</p>
                      </td>
                    </tr>
                  @endforeach
                @empty
                  <tr>
                    <td colspan="5" class="p-6 text-center text-sm text-slate-400">Belum ada jurnal untuk periode ini.</td>
                  </tr>
                @endforelse
              </tbody>
            </table>
          </div>

          <div class="p-4 border-t border-gray-200">
            {{ $jurnal->links() }}
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
@endsection

