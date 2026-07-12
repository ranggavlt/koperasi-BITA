@extends('layout.main')

@section('content')
@php
  $money = fn($value) => 'Rp ' . number_format((float) $value, 0, ',', '.');
  $badge = fn(string $status) => match ($status) {
    'pending_review' => 'bg-amber-100 text-amber-700',
    'waiting_settlement' => 'bg-orange-100 text-orange-700',
    'ready_to_complete' => 'bg-blue-100 text-blue-700',
    'completed' => 'bg-green-100 text-green-700',
    'cancelled' => 'bg-slate-100 text-slate-600',
    default => 'bg-slate-100 text-slate-600',
  };
@endphp

<div class="w-full px-6 py-6 mx-auto">
  @if (session('success'))
    <div class="mb-4 rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700">{{ session('success') }}</div>
  @endif
  @if ($errors->any())
    <div class="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
      <ul class="mb-0 list-disc pl-5">
        @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
      </ul>
    </div>
  @endif

  <div class="mb-6">
    <p class="mb-1 text-xs font-bold uppercase tracking-widest text-green-600">Keanggotaan</p>
    <h1 class="text-2xl font-bold text-slate-700">Penyelesaian Keanggotaan</h1>
    <p class="mt-1 text-sm text-slate-400">Settlement Karyawan keluar: snapshot hak, offset kewajiban, refund, dan audit trail tanpa edit transaksi lama.</p>
  </div>

  <section class="mb-6 rounded-2xl border border-slate-100 bg-white p-6 shadow-soft-xl">
    <form class="grid gap-4 md:grid-cols-4" method="GET" action="{{ route('penyelesaian-keanggotaan.index') }}">
      <div>
        <label class="mb-2 block text-xs font-bold uppercase text-slate-600">Status</label>
        <select name="status" class="kbsm-focus w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm">
          <option value="">Semua status</option>
          @foreach($statuses as $status)
            <option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ str_replace('_', ' ', $status) }}</option>
          @endforeach
        </select>
      </div>
      <div class="md:col-span-2">
        <label class="mb-2 block text-xs font-bold uppercase text-slate-600">Anggota</label>
        <input name="anggota" value="{{ $filters['anggota'] ?? '' }}" placeholder="Nomor anggota / nama karyawan" class="kbsm-focus w-full rounded-xl border border-slate-200 px-4 py-3 text-sm">
      </div>
      <div class="flex items-end gap-2">
        <button class="rounded-xl bg-[#073b5c] px-5 py-3 text-xs font-bold uppercase text-white">Filter</button>
        <a href="{{ route('penyelesaian-keanggotaan.index') }}" class="rounded-xl border border-slate-200 px-5 py-3 text-xs font-bold uppercase text-slate-600">Reset</a>
      </div>
    </form>
  </section>

  <section class="rounded-2xl border border-slate-100 bg-white p-6 shadow-soft-xl">
    <div class="overflow-x-auto">
      <table class="w-full min-w-[1200px] text-left text-sm">
        <thead class="bg-slate-50 text-xs uppercase text-slate-500">
          <tr>
            <th class="px-4 py-3">Kode</th>
            <th class="px-4 py-3">Anggota</th>
            <th class="px-4 py-3">Siklus</th>
            <th class="px-4 py-3">Hak</th>
            <th class="px-4 py-3">Kewajiban</th>
            <th class="px-4 py-3">Offset</th>
            <th class="px-4 py-3">Refund</th>
            <th class="px-4 py-3">Status</th>
            <th class="px-4 py-3">Aksi</th>
          </tr>
        </thead>
        <tbody>
          @forelse($penyelesaianList as $penyelesaian)
            <tr class="border-t border-slate-100 align-top">
              <td class="px-4 py-4">
                <div class="font-bold text-[#073b5c]">{{ $penyelesaian->kode_penyelesaian }}</div>
                <div class="text-xs text-slate-400">{{ $penyelesaian->tanggal_keluar?->format('d/m/Y') }}</div>
              </td>
              <td class="px-4 py-4">
                <div class="font-semibold text-slate-700">{{ $penyelesaian->anggota?->nomor_anggota }}</div>
                <div class="text-xs text-slate-500">{{ $penyelesaian->anggota?->karyawan?->nama }}</div>
              </td>
              <td class="px-4 py-4">#{{ $penyelesaian->siklus?->siklus_ke ?? '-' }}</td>
              <td class="px-4 py-4">
                <div>{{ $money($penyelesaian->total_hak_anggota) }}</div>
                <div class="text-xs text-slate-400">Pokok {{ $money($penyelesaian->simpanan_pokok_snapshot) }} · Kredit {{ $money($penyelesaian->kredit_refund_snapshot) }}</div>
              </td>
              <td class="px-4 py-4">
                <div>{{ $money($penyelesaian->total_kewajiban_awal) }}</div>
                <div class="text-xs text-slate-400">Sisa {{ $money($penyelesaian->sisa_kewajiban) }}</div>
              </td>
              <td class="px-4 py-4">{{ $money($penyelesaian->total_offset) }}</td>
              <td class="px-4 py-4">
                <div>{{ $money($penyelesaian->total_refund) }}</div>
                @if($penyelesaian->dompetRefund)
                  <div class="text-xs text-slate-400">{{ $penyelesaian->dompetRefund->nama_dompet }}</div>
                @endif
              </td>
              <td class="px-4 py-4">
                <span class="rounded-full px-3 py-1 text-xs font-bold {{ $badge($penyelesaian->status) }}">{{ str_replace('_', ' ', $penyelesaian->status) }}</span>
              </td>
              <td class="px-4 py-4">
                <div class="flex flex-col gap-2">
                  @if($penyelesaian->status !== 'completed')
                    <form method="POST" action="{{ route('penyelesaian-keanggotaan.refresh', $penyelesaian) }}">
                      @csrf
                      <button class="w-full rounded-lg border border-slate-200 px-3 py-2 text-xs font-bold uppercase text-slate-600">Refresh</button>
                    </form>
                  @endif
                  @if(in_array($penyelesaian->status, ['pending_review', 'waiting_settlement'], true) && (float) $penyelesaian->total_offset <= 0)
                    <form method="POST" action="{{ route('penyelesaian-keanggotaan.process-offset', $penyelesaian) }}">
                      @csrf
                      <button class="w-full rounded-lg bg-[#073b5c] px-3 py-2 text-xs font-bold uppercase text-white">Proses Offset</button>
                    </form>
                  @endif
                  @if($penyelesaian->status === 'ready_to_complete' && (float) $penyelesaian->total_refund > 0 && ! $penyelesaian->mutasiKas()->exists())
                    <form method="POST" action="{{ route('penyelesaian-keanggotaan.refund', $penyelesaian) }}" class="grid gap-2">
                      @csrf
                      <select name="dompet_id" required class="kbsm-focus rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs">
                        <option value="">Dompet refund</option>
                        @foreach($dompetOptions as $dompet)
                          <option value="{{ $dompet->id }}">{{ $dompet->nama_dompet }} · {{ $dompet->akun?->kode_akun ?? 'tanpa COA' }}</option>
                        @endforeach
                      </select>
                      <input name="alasan" required minlength="5" placeholder="Alasan refund" class="kbsm-focus rounded-lg border border-slate-200 px-3 py-2 text-xs">
                      <button class="rounded-lg bg-amber-500 px-3 py-2 text-xs font-bold uppercase text-white">Refund</button>
                    </form>
                  @endif
                  @if($penyelesaian->status === 'ready_to_complete' && ((float) $penyelesaian->total_refund <= 0 || $penyelesaian->mutasiKas()->exists()))
                    <form method="POST" action="{{ route('penyelesaian-keanggotaan.complete', $penyelesaian) }}">
                      @csrf
                      <button class="w-full rounded-lg bg-green-600 px-3 py-2 text-xs font-bold uppercase text-white">Complete</button>
                    </form>
                  @endif
                </div>
              </td>
            </tr>
            <tr class="border-t border-slate-50 bg-slate-50/50">
              <td colspan="9" class="px-4 py-3">
                <div class="grid gap-2 md:grid-cols-3">
                  @forelse($penyelesaian->details as $detail)
                    <div class="rounded-xl border border-slate-100 bg-white p-3 text-xs">
                      <div class="font-bold uppercase text-slate-600">{{ $detail->kategori_sumber }}</div>
                      <div class="text-slate-400">{{ class_basename($detail->source_type) }} #{{ $detail->source_id }}</div>
                      <div class="mt-2 grid grid-cols-2 gap-1">
                        <span>Awal</span><strong>{{ $money($detail->nominal_kewajiban_awal) }}</strong>
                        <span>Offset</span><strong>{{ $money($detail->nominal_offset) }}</strong>
                        <span>Sisa</span><strong>{{ $money($detail->nominal_sisa) }}</strong>
                      </div>
                    </div>
                  @empty
                    <div class="text-xs text-slate-400">Belum ada kewajiban tersnapshot.</div>
                  @endforelse
                </div>
              </td>
            </tr>
          @empty
            <tr><td colspan="9" class="px-4 py-8 text-center text-slate-400">Belum ada penyelesaian keanggotaan.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
    <div class="mt-4">{{ $penyelesaianList->links() }}</div>
  </section>
</div>
@endsection
