@extends('layout.main')

@section('content')
<div class="w-full px-6 py-6 mx-auto">
  <div class="mb-6 rounded-2xl bg-white p-6 shadow-soft-xl">
    <h6 class="text-slate-700">Riwayat Koreksi Transaksi</h6>
    <p class="text-sm text-slate-400">Transaksi asli tetap immutable; koreksi dicatat sebagai reversal penuh yang traceable.</p>
  </div>

  <div class="rounded-2xl bg-white shadow-soft-xl">
    <div style="overflow-x: auto;" class="p-0">
      <table class="mb-0 w-full text-sm text-slate-600">
        <thead>
          <tr class="text-left text-xxs uppercase text-slate-400">
            <th class="px-6 py-3">Kode</th>
            <th class="px-6 py-3">Jenis</th>
            <th class="px-6 py-3">Sumber</th>
            <th class="px-6 py-3">Nominal</th>
            <th class="px-6 py-3">Status</th>
            <th class="px-6 py-3">Alasan</th>
            <th class="px-6 py-3">Diproses</th>
          </tr>
        </thead>
        <tbody>
          @forelse($reversals as $reversal)
            <tr>
              <td class="border-b px-6 py-3 font-semibold">{{ $reversal->kode_reversal }}</td>
              <td class="border-b px-6 py-3">{{ $reversal->jenis_reversal }}</td>
              <td class="border-b px-6 py-3">{{ class_basename($reversal->source_type) }} #{{ $reversal->source_id }}</td>
              <td class="border-b px-6 py-3">Rp {{ number_format($reversal->nominal, 0, ',', '.') }}</td>
              <td class="border-b px-6 py-3">{{ $reversal->status }}</td>
              <td class="border-b px-6 py-3">{{ $reversal->alasan }}</td>
              <td class="border-b px-6 py-3">{{ optional($reversal->processed_at)->format('d/m/Y H:i') }}</td>
            </tr>
          @empty
            <tr><td colspan="7" class="p-6 text-center text-slate-400">Belum ada reversal.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
    <div class="p-4">{{ $reversals->links() }}</div>
  </div>
</div>
@endsection
