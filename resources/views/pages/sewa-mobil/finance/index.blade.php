@extends('layout.main')

@section('content')
@php
  $badge = fn(string $status) => match ($status) {
    'draft'      => 'kbsm-status kbsm-status--slate',
    'diajukan'   => 'kbsm-status kbsm-status--blue',
    'disetujui'  => 'kbsm-status kbsm-status--green',
    'ditolak'    => 'kbsm-status kbsm-status--red',
    'berjalan'   => 'kbsm-status kbsm-status--amber',
    'selesai'    => 'kbsm-status kbsm-status--emerald',
    'dibatalkan' => 'kbsm-status kbsm-status--slate',
    default      => 'kbsm-status kbsm-status--slate',
  };
@endphp

<div class="w-full px-6 py-6 mx-auto">
  @if (session('success'))<div class="mb-4 rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700">{{ session('success') }}</div>@endif
  @if ($errors->any())
    <div class="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700"><ul class="mb-0 list-disc pl-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
  @endif

  <div class="mb-6">
    <p class="mb-1 text-xs font-bold uppercase tracking-widest text-green-600">Finance</p>
    <h1 class="text-2xl font-bold text-slate-700">Sewa Mobil</h1>
    <p class="mt-1 text-sm text-slate-400">Approval Pengurus dicatat oleh Finance, pembayaran diterima penuh dimuka, pendapatan diakui saat kegiatan selesai.</p>
  </div>

  <section class="mb-6 rounded-2xl border border-slate-100 bg-white p-6 shadow-soft-xl">
    <form method="GET" action="{{ route('sewa-mobil.finance.index') }}" class="grid gap-4 md:grid-cols-5">
      <div>
        <label class="mb-2 block text-xs font-bold uppercase text-slate-600">Status</label>
        <select name="status" class="kbsm-focus w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm">
          <option value="">Semua</option>
          @foreach($statuses as $value => $label)
            <option value="{{ $value }}" {{ request('status') === $value ? 'selected' : '' }}>{{ $label }}</option>
          @endforeach
        </select>
      </div>
      <div>
        <label class="mb-2 block text-xs font-bold uppercase text-slate-600">Mobil</label>
        <select name="aset_koperasi_id" class="kbsm-focus w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm">
          <option value="">Semua</option>
          @foreach($mobilOptions as $mobil)
            <option value="{{ $mobil->id }}" {{ (string) request('aset_koperasi_id') === (string) $mobil->id ? 'selected' : '' }}>{{ $mobil->kode_aset }} - {{ $mobil->mobil->plat_nomor ?? '-' }}</option>
          @endforeach
        </select>
      </div>
      <div>
        <label class="mb-2 block text-xs font-bold uppercase text-slate-600">Karyawan</label>
        <select name="karyawan_id" class="kbsm-focus w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm">
          <option value="">Semua</option>
          @foreach($karyawanOptions as $karyawan)
            <option value="{{ $karyawan->id }}" {{ (string) request('karyawan_id') === (string) $karyawan->id ? 'selected' : '' }}>{{ $karyawan->nama }}</option>
          @endforeach
        </select>
      </div>
      <div>
        <label class="mb-2 block text-xs font-bold uppercase text-slate-600">Periode mulai</label>
        <input type="month" name="periode" value="{{ request('periode') }}" class="kbsm-focus w-full rounded-xl border border-slate-200 px-4 py-3 text-sm">
      </div>
      <div class="flex items-end">
        <button class="kbsm-btn kbsm-btn--navy kbsm-btn--full">Filter</button>
      </div>
    </form>
  </section>

  <section class="overflow-hidden rounded-2xl border border-slate-100 bg-white shadow-soft-xl">
    <div class="border-b border-slate-100 p-6"><h2 class="font-bold text-slate-700">Daftar Sewa Mobil</h2><p class="text-sm text-slate-400">Transaksi tidak dapat dihapus permanen. Gunakan pembatalan/refund sebelum berjalan jika eligible.</p></div>
    <div style="overflow-x: auto;" class="">
      <table class="w-full min-w-[1600px] text-left text-sm">
        <thead class="kbsm-thead">
          <tr>
            <th class="px-6 py-4">Kode</th><th class="px-6 py-4">Pemohon</th><th class="px-6 py-4">Mobil</th><th class="px-6 py-4">Kegiatan</th><th class="px-6 py-4">Jadwal</th><th class="px-6 py-4">Status</th><th class="px-6 py-4">Tarif/Bayar</th><th class="px-6 py-4">Approval</th><th class="px-6 py-4">Posting</th><th class="px-6 py-4 text-center">Aksi</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
          @forelse($sewaMobil as $item)
            <tr class="align-top hover:bg-slate-50">
              <td class="px-6 py-4 font-bold kbsm-text-navy">{{ $item->kode_sewa ?: 'Draft' }}</td>
              <td class="px-6 py-4"><div class="font-semibold text-slate-700">{{ $item->karyawan->nama }}</div><div class="text-xs text-slate-400">{{ $item->karyawan->jabatan }} / {{ $item->karyawan->status_kerja }}</div>@if($item->needs_finance_review)<div class="mt-1 text-xs font-bold text-amber-600">Review Finance</div>@endif</td>
              <td class="px-6 py-4"><div class="font-semibold text-slate-700">{{ $item->aset->kode_aset }} - {{ $item->aset->merek }} {{ $item->aset->model }}</div><div class="text-xs text-slate-400">{{ $item->aset->mobil->plat_nomor ?? '-' }} / {{ $item->aset->status_label }}</div></td>
              <td class="px-6 py-4"><div class="font-semibold text-slate-700">{{ $item->nama_kegiatan }}</div><div class="text-xs text-slate-400">{{ $item->lokasi_kegiatan }}</div><div class="text-xs text-slate-400">Penyewa: {{ $item->nama_perusahaan_snapshot }}</div></td>
              <td class="px-6 py-4 text-slate-600">{{ $item->mulai_at->format('d/m/Y H:i') }}<br>{{ $item->selesai_at->format('d/m/Y H:i') }}</td>
              <td class="px-6 py-4"><span class="{{ $badge($item->status) }}">{{ $item->status_label }}</span><div class="mt-2 text-xs text-slate-400">Payment: {{ $item->status_pembayaran }}</div></td>
              <td class="px-6 py-4"><div class="font-semibold text-slate-700">Rp {{ number_format((float) $item->tarif_total, 0, ',', '.') }}</div>@if($item->pembayaran)<div class="text-xs text-slate-400">{{ $item->pembayaran->metode_pembayaran }} / {{ $item->pembayaran->dompet->nama_dompet ?? '-' }}</div>@endif</td>
              <td class="px-6 py-4 text-xs text-slate-500">{{ $item->nama_pengurus_snapshot ?: '-' }}<br>{{ $item->jabatan_pengurus_snapshot ?: '' }}<br>@if($item->approved_at){{ $item->approved_at->format('d/m/Y H:i') }}@endif</td>
              <td class="px-6 py-4 text-xs text-slate-500">
                <div>Jurnal: {{ $item->jurnal->count() + ($item->pembayaran?->jurnal?->count() ?? 0) }}</div>
                <div>Mutasi: {{ $item->pembayaran?->mutasiKas?->count() ?? 0 }}</div>
              </td>
              <td class="px-6 py-4">
                <div class="flex max-w-[520px] flex-col gap-2">
                  @if($item->status === 'diajukan')
                    <form method="POST" action="{{ route('sewa-mobil.finance.approve', $item) }}" class="grid gap-2 md:grid-cols-3">
                      @csrf
                      <input type="number" name="tarif_total" min="1" required placeholder="Tarif" class="rounded-lg border border-slate-200 px-3 py-2 text-xs">
                      <select name="pengurus_penyetuju_id" required class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs">
                        <option value="">Pengurus</option>
                        @foreach($pengurusOptions as $pengurus)
                          <option value="{{ $pengurus->id }}">{{ $pengurus->jabatan }} - {{ $pengurus->anggota->karyawan->nama }}</option>
                        @endforeach
                      </select>
                      <button class="kbsm-btn kbsm-btn--green kbsm-btn--sm">Setujui</button>
                    </form>
                    <form method="POST" action="{{ route('sewa-mobil.finance.reject', $item) }}" class="flex gap-2">
                      @csrf
                      <input name="alasan" required placeholder="Alasan penolakan" class="min-w-0 flex-1 rounded-lg border border-slate-200 px-3 py-2 text-xs">
                      <button class="kbsm-btn kbsm-btn--outline-red kbsm-btn--sm">Tolak</button>
                    </form>
                  @endif

                  @if($item->status === 'disetujui' && $item->status_pembayaran === 'belum_bayar')
                    <form method="POST" action="{{ route('sewa-mobil.finance.pay', $item) }}" class="grid gap-2 md:grid-cols-4">
                      @csrf
                      <select name="metode_pembayaran" required class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs">
                        <option value="tunai">Tunai</option>
                        <option value="transfer_bank">Transfer Bank</option>
                      </select>
                      <select name="dompet_id" required class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs">
                        <option value="">Dompet</option>
                        @foreach($dompetOptions as $dompet)
                          <option value="{{ $dompet->id }}">{{ $dompet->nama_dompet }} ({{ $dompet->jenis_dompet }})</option>
                        @endforeach
                      </select>
                      <input type="number" name="jumlah_bayar" min="1" required value="{{ (int) $item->tarif_total }}" class="rounded-lg border border-slate-200 px-3 py-2 text-xs">
                      <button class="kbsm-btn kbsm-btn--navy kbsm-btn--sm">Catat Bayar</button>
                    </form>
                  @endif

                  @if($item->status === 'disetujui' && $item->status_pembayaran === 'paid')
                    <form method="POST" action="{{ route('sewa-mobil.finance.start', $item) }}">@csrf<button class="kbsm-btn kbsm-btn--amber kbsm-btn--sm">Mulai</button></form>
                  @endif

                  @if($item->status === 'berjalan')
                    <form method="POST" action="{{ route('sewa-mobil.finance.complete', $item) }}">@csrf<button class="kbsm-btn kbsm-btn--green kbsm-btn--sm">Selesai</button></form>
                  @endif

                  @if(in_array($item->status, ['disetujui'], true))
                    <form method="POST" action="{{ route('sewa-mobil.finance.cancel', $item) }}" class="flex gap-2" onsubmit="return confirm('Batalkan/refund sewa ini jika eligible?')">
                      @csrf
                      <input name="alasan" required placeholder="Alasan pembatalan" class="min-w-0 flex-1 rounded-lg border border-slate-200 px-3 py-2 text-xs">
                      <button class="kbsm-btn kbsm-btn--outline-red kbsm-btn--sm">Batalkan/Refund</button>
                    </form>
                  @endif
                </div>
              </td>
            </tr>
          @empty
            <tr><td colspan="10" class="px-6 py-10 text-center text-slate-400">Belum ada transaksi Sewa Mobil.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
    <div class="border-t border-slate-100 p-4">{{ $sewaMobil->links() }}</div>
  </section>
</div>
@endsection
