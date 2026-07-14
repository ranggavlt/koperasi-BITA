@extends('layout.main')

@section('content')
<div class="w-full px-6 py-6 mx-auto">
  <div class="mb-6 rounded-2xl bg-white p-6 shadow-soft-xl">
    <h6 class="text-slate-700">Outstanding Cash Mantan Karyawan</h6>
    <p class="text-sm text-slate-400">POS dan Simpanan Pokok yang awalnya payroll tetapi harus diselesaikan tunai.</p>
    <form class="mt-4 flex flex-wrap items-end gap-3" method="GET">
      <select name="status" class="rounded-lg border border-gray-300 px-3 py-2 text-sm">
        <option value="">Semua status</option>
        @foreach(['outstanding_cash','settled_cash'] as $status)
          <option value="{{ $status }}" @selected(request('status') === $status)>{{ $status }}</option>
        @endforeach
      </select>
      <select name="anggota_id" class="rounded-lg border border-gray-300 px-3 py-2 text-sm">
        <option value="">Semua Anggota</option>
        @foreach($anggotaOptions as $anggota)
          <option value="{{ $anggota->id }}" @selected(request('anggota_id') == $anggota->id)>{{ $anggota->nomor_anggota }} - {{ $anggota->karyawan?->nama }}</option>
        @endforeach
      </select>
      <button class="rounded-lg bg-gradient-to-tl from-emerald-600 to-slate-800 px-6 py-3 text-xs font-bold uppercase text-white">Filter</button>
    </form>
  </div>

  <div class="mb-6 grid gap-4 md:grid-cols-3">
    <div class="rounded-2xl bg-white p-4 shadow-soft-xl">
      <p class="text-xs font-bold uppercase text-slate-400">Total Outstanding</p>
      <h5>Rp {{ number_format($summary['total_outstanding'], 0, ',', '.') }}</h5>
    </div>
    <div class="rounded-2xl bg-white p-4 shadow-soft-xl">
      <p class="text-xs font-bold uppercase text-slate-400">Total Dibayar</p>
      <h5>Rp {{ number_format($summary['total_dibayar'], 0, ',', '.') }}</h5>
    </div>
    <div class="rounded-2xl bg-white p-4 shadow-soft-xl">
      <p class="text-xs font-bold uppercase text-slate-400">Sumber</p>
      <h5>{{ $summary['jumlah_sumber'] }}</h5>
    </div>
  </div>

  <div class="rounded-2xl bg-white shadow-soft-xl">
    <div class="overflow-x-auto p-0">
      <table class="mb-0 w-full text-sm text-slate-600">
        <thead>
          <tr class="text-left text-xxs uppercase text-slate-400">
            <th class="px-6 py-3">Anggota</th>
            <th class="px-6 py-3">Sumber</th>
            <th class="px-6 py-3">Kode</th>
            <th class="px-6 py-3">Nominal</th>
            <th class="px-6 py-3">Sisa</th>
            <th class="px-6 py-3">Status</th>
            <th class="px-6 py-3">Aksi</th>
          </tr>
        </thead>
        <tbody>
          @forelse($rows as $row)
            <tr>
              <td class="border-b px-6 py-3">{{ $row->anggota?->nomor_anggota }} - {{ $row->karyawan?->nama }}</td>
              <td class="border-b px-6 py-3">{{ $row->kelompok }}</td>
              <td class="border-b px-6 py-3">{{ $row->kode_transaksi }}</td>
              <td class="border-b px-6 py-3">Rp {{ number_format($row->nominal_awal, 0, ',', '.') }}</td>
              <td class="border-b px-6 py-3">Rp {{ number_format($row->sisa, 0, ',', '.') }}</td>
              <td class="border-b px-6 py-3">{{ $row->status }}</td>
              <td class="border-b px-6 py-3">
                @if($row->status === 'outstanding_cash')
                  <form method="POST" action="{{ route('outstanding-cash.pay-source') }}" class="flex flex-wrap items-center gap-2">
                    @csrf
                    <input type="hidden" name="source_type" value="{{ $row->source_type }}">
                    <input type="hidden" name="source_id" value="{{ $row->source_id }}">
                    <select name="dompet_id" class="rounded-lg border border-gray-300 px-2 py-1 text-xs" required>
                      @foreach($dompetKas as $dompet)
                        <option value="{{ $dompet->id }}">{{ $dompet->nama_dompet }}</option>
                      @endforeach
                    </select>
                    <button class="rounded-lg bg-emerald-600 px-3 py-2 text-xs font-bold uppercase text-white">Bayar Penuh</button>
                  </form>
                @else
                  <span class="text-xs text-slate-400">Selesai</span>
                @endif
              </td>
            </tr>
          @empty
            <tr><td colspan="7" class="p-6 text-center text-slate-400">Tidak ada outstanding cash.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
</div>
@endsection
