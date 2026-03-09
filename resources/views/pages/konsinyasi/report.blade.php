@extends('layout.main')
@section('content')
<div class="w-full px-6 py-6 mx-auto">

  <div class="relative flex flex-col min-w-0 mb-6 break-words bg-white border-0 shadow-soft-xl rounded-2xl bg-clip-border">
    <div class="p-6 pb-0 mb-0 bg-white rounded-t-2xl">
      <h6>Laporan Konsinyasi</h6>
      <p class="text-sm text-slate-400">Rekap penjualan barang titipan per reseller</p>
    </div>

    <div class="p-6">
      <form class="flex flex-wrap gap-3 items-end" method="GET" action="{{ route('konsinyasi.report') }}">
        <div>
          <label class="block text-xs font-bold uppercase text-slate-700 mb-1">Mulai</label>
          <input type="date" name="mulai" value="{{ $mulai }}" class="rounded-lg border border-gray-300 px-3 py-2 text-sm">
        </div>
        <div>
          <label class="block text-xs font-bold uppercase text-slate-700 mb-1">Akhir</label>
          <input type="date" name="akhir" value="{{ $akhir }}" class="rounded-lg border border-gray-300 px-3 py-2 text-sm">
        </div>
        <div class="min-w-[240px]">
          <label class="block text-xs font-bold uppercase text-slate-700 mb-1">Reseller</label>
          <select name="reseller_id" class="rounded-lg border border-gray-300 px-3 py-2 text-sm w-full">
            <option value="">Semua Reseller</option>
            @foreach($reseller as $r)
              <option value="{{ $r->id }}" {{ (string)$resellerId === (string)$r->id ? 'selected' : '' }}>
                {{ $r->nama_reseller }}
              </option>
            @endforeach
          </select>
        </div>

        <button class="rounded-lg bg-gradient-to-tl from-purple-700 to-pink-500 px-6 py-3 text-xs font-bold uppercase text-white">
          Tampilkan
        </button>
      </form>
    </div>
  </div>

  <div class="relative flex flex-col min-w-0 mb-6 break-words bg-white border-0 shadow-soft-xl rounded-2xl bg-clip-border">
    <div class="flex-auto px-0 pt-0 pb-2">
      <div class="p-0 overflow-x-auto">
        <table class="items-center w-full mb-0 align-top border-gray-200 text-slate-500">
          <thead>
            <tr>
              <th class="px-6 py-3 text-left text-xxs font-bold uppercase text-slate-400 opacity-70">Reseller</th>
              <th class="px-6 py-3 text-center text-xxs font-bold uppercase text-slate-400 opacity-70">Qty Terjual</th>
              <th class="px-6 py-3 text-center text-xxs font-bold uppercase text-slate-400 opacity-70">Total Jual</th>
              <th class="px-6 py-3 text-center text-xxs font-bold uppercase text-slate-400 opacity-70">Total Setor</th>
              <th class="px-6 py-3 text-center text-xxs font-bold uppercase text-slate-400 opacity-70">Laba Koperasi</th>
            </tr>
          </thead>
          <tbody>
            @forelse($rekap as $row)
              <tr>
                <td class="p-2 border-b">{{ $row->nama_reseller }}</td>
                <td class="p-2 border-b text-center">{{ $row->total_qty }}</td>
                <td class="p-2 border-b text-center">Rp {{ number_format($row->total_jual, 0, ',', '.') }}</td>
                <td class="p-2 border-b text-center">Rp {{ number_format($row->total_setor, 0, ',', '.') }}</td>
                <td class="p-2 border-b text-center">Rp {{ number_format($row->laba_koperasi, 0, ',', '.') }}</td>
              </tr>
            @empty
              <tr>
                <td colspan="5" class="p-4 text-center text-slate-400">Belum ada transaksi konsinyasi pada periode ini.</td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>
  </div>

</div>
@endsection