@extends('layout.main')

@section('content')
@php
  $money = fn ($value) => 'Rp ' . number_format((float) $value, 0, ',', '.');
  $statusClass = [
    \App\Models\Pinjaman::STATUS_DRAFT => 'kbsm-status--slate',
    \App\Models\Pinjaman::STATUS_DIAJUKAN => 'kbsm-status--amber',
    \App\Models\Pinjaman::STATUS_DISETUJUI => 'kbsm-status--green',
    \App\Models\Pinjaman::STATUS_AKTIF => 'kbsm-status--amber',
    \App\Models\Pinjaman::STATUS_LUNAS => 'kbsm-status--green',
    \App\Models\Pinjaman::STATUS_DITOLAK => 'kbsm-status--red',
    \App\Models\Pinjaman::STATUS_DIBATALKAN => 'kbsm-status--slate',
  ];
  $cashAllowed = $pinjaman->status === \App\Models\Pinjaman::STATUS_AKTIF
    && (
      ($pinjaman->anggota?->status ?? null) !== \App\Models\Anggota::STATUS_AKTIF
      || ($pinjaman->anggota?->karyawan?->status_kerja ?? null) !== \App\Models\Karyawan::STATUS_AKTIF
    );
@endphp

<div class="w-full px-6 py-6 mx-auto">
  @if (session('success'))
    <div class="mb-4 rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700">
      {{ session('success') }}
    </div>
  @endif

  @if ($errors->any())
    <div class="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
      <ul class="mb-0 list-disc pl-5">
        @foreach ($errors->all() as $error)
          <li>{{ $error }}</li>
        @endforeach
      </ul>
    </div>
  @endif

  <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
    <div>
      <h1 class="text-lg font-bold text-slate-700">Detail Pinjaman {{ $pinjaman->kode_pinjaman }}</h1>
      <p class="mt-1 text-sm text-slate-400">
        {{ $pinjaman->anggota->nomor_anggota ?? '-' }} - {{ $pinjaman->anggota->karyawan->nama ?? '-' }}
      </p>
    </div>
    <a href="{{ route('pinjaman.index') }}" class="kbsm-btn kbsm-btn--outline-slate">Kembali</a>
  </div>

  <div class="mb-6 grid gap-4 md:grid-cols-4">
    <div class="rounded-xl bg-white p-4 shadow-soft-xl">
      <p class="mb-1 text-xs font-bold uppercase text-slate-400">Nominal</p>
      <p class="mb-0 text-sm font-bold text-slate-700">{{ $money($pinjaman->jumlah_pinjaman) }}</p>
    </div>
    <div class="rounded-xl bg-white p-4 shadow-soft-xl">
      <p class="mb-1 text-xs font-bold uppercase text-slate-400">Sisa</p>
      <p class="mb-0 text-sm font-bold text-slate-700">{{ $money($pinjaman->sisa_pinjaman) }}</p>
    </div>
    <div class="rounded-xl bg-white p-4 shadow-soft-xl">
      <p class="mb-1 text-xs font-bold uppercase text-slate-400">Status</p>
      <span class="kbsm-status {{ $statusClass[$pinjaman->status] ?? 'kbsm-status--slate' }}">{{ $pinjaman->status_label }}</span>
    </div>
    <div class="rounded-xl bg-white p-4 shadow-soft-xl">
      <p class="mb-1 text-xs font-bold uppercase text-slate-400">Jurnal</p>
      <p class="mb-0 text-sm font-bold text-slate-700">{{ $pinjaman->jurnal?->nomor_bukti ?? 'Belum ada' }}</p>
    </div>
  </div>

  <div class="mb-6 grid gap-6 lg:grid-cols-3">
    <div class="rounded-2xl border border-slate-100 bg-white p-6 shadow-soft-xl lg:col-span-2">
      <h2 class="mb-4 text-base font-bold text-slate-700">Informasi Pengajuan</h2>
      <div class="grid gap-4 md:grid-cols-2">
        <div>
          <p class="mb-1 text-xs font-bold uppercase text-slate-400">Tanggal Pengajuan</p>
          <p class="text-sm text-slate-700">{{ optional($pinjaman->tanggal_pengajuan)->format('d/m/Y') ?? '-' }}</p>
        </div>
        <div>
          <p class="mb-1 text-xs font-bold uppercase text-slate-400">Tanggal Pencairan</p>
          <p class="text-sm text-slate-700">{{ optional($pinjaman->tanggal_pinjaman)->format('d/m/Y') ?? '-' }}</p>
        </div>
        <div>
          <p class="mb-1 text-xs font-bold uppercase text-slate-400">Plafon Snapshot</p>
          <p class="text-sm text-slate-700">{{ $money($pinjaman->plafon_pinjaman_snapshot) }}</p>
        </div>
        <div>
          <p class="mb-1 text-xs font-bold uppercase text-slate-400">Tenor & Bunga</p>
          <p class="text-sm text-slate-700">{{ $pinjaman->tenor_bulan }} bulan · 0%</p>
        </div>
        <div>
          <p class="mb-1 text-xs font-bold uppercase text-slate-400">Dompet Pencairan</p>
          <p class="text-sm text-slate-700">{{ $pinjaman->dompet->nama_dompet ?? 'Belum dicairkan' }}</p>
        </div>
        <div>
          <p class="mb-1 text-xs font-bold uppercase text-slate-400">Mutasi Kas</p>
          <p class="text-sm text-slate-700">{{ $pinjaman->mutasiKas?->keterangan ?? 'Belum ada' }}</p>
        </div>
        <div class="md:col-span-2">
          <p class="mb-1 text-xs font-bold uppercase text-slate-400">Keterangan</p>
          <p class="text-sm text-slate-700">{{ $pinjaman->keterangan ?: '-' }}</p>
        </div>
        @if($pinjaman->rejection_reason || $pinjaman->cancellation_reason)
          <div class="md:col-span-2 rounded-xl border border-red-100 bg-red-50 p-4 text-sm text-red-700">
            {{ $pinjaman->rejection_reason ?: $pinjaman->cancellation_reason }}
          </div>
        @endif
      </div>
    </div>

    <div class="rounded-2xl border border-slate-100 bg-white p-6 shadow-soft-xl">
      <h2 class="mb-4 text-base font-bold text-slate-700">Timeline Audit</h2>
      <div class="space-y-3 text-sm text-slate-600">
        <p>Dibuat: {{ optional($pinjaman->created_at)->format('d/m/Y H:i') ?? '-' }}</p>
        <p>Diajukan: {{ optional($pinjaman->submitted_at)->format('d/m/Y H:i') ?? '-' }}</p>
        <p>Disetujui: {{ optional($pinjaman->approved_at)->format('d/m/Y H:i') ?? '-' }}</p>
        <p>Ditolak: {{ optional($pinjaman->rejected_at)->format('d/m/Y H:i') ?? '-' }}</p>
        <p>Dibatalkan: {{ optional($pinjaman->cancelled_at)->format('d/m/Y H:i') ?? '-' }}</p>
        <p>Dicairkan: {{ optional($pinjaman->disbursed_at)->format('d/m/Y H:i') ?? '-' }}</p>
      </div>
    </div>
  </div>

  @if(in_array($pinjaman->status, [\App\Models\Pinjaman::STATUS_DRAFT, \App\Models\Pinjaman::STATUS_DIAJUKAN, \App\Models\Pinjaman::STATUS_DISETUJUI], true))
    <div class="mb-6 rounded-2xl border border-slate-100 bg-white p-6 shadow-soft-xl">
      <h2 class="mb-2 text-base font-bold text-slate-700">Aksi Finance</h2>
      <p class="mb-4 text-sm text-slate-400">Aksi sebelum pencairan tidak membuat Mutasi Kas, Jurnal, Jadwal Cicilan, atau ledger payroll.</p>

      <div class="grid gap-4 lg:grid-cols-3">
        @if($pinjaman->status === \App\Models\Pinjaman::STATUS_DRAFT)
          <div class="rounded-xl border border-slate-100 p-4">
            <p class="mb-3 text-sm text-slate-600">Draft masih dapat diedit atau diajukan.</p>
            <div class="flex flex-wrap gap-2">
              <a href="{{ route('pinjaman.edit', $pinjaman) }}" class="kbsm-btn kbsm-btn--outline-slate">Edit Draft</a>
              <form method="POST" action="{{ route('pinjaman.submit', $pinjaman) }}">@csrf<button class="kbsm-btn kbsm-btn--green">Ajukan</button></form>
            </div>
          </div>
        @endif

        @if($pinjaman->status === \App\Models\Pinjaman::STATUS_DIAJUKAN)
          <div class="rounded-xl border border-emerald-100 bg-emerald-50 p-4">
            <p class="mb-3 text-sm text-emerald-800">Setujui pengajuan setelah validasi dokumen di luar aplikasi.</p>
            <form method="POST" action="{{ route('pinjaman.approve', $pinjaman) }}">@csrf<button class="kbsm-btn kbsm-btn--green">Setujui</button></form>
          </div>
          <form method="POST" action="{{ route('pinjaman.reject', $pinjaman) }}" class="rounded-xl border border-red-100 bg-red-50 p-4">
            @csrf
            <label class="mb-2 block text-xs font-bold uppercase text-red-700">Alasan Penolakan</label>
            <textarea name="alasan" rows="3" class="mb-3 block w-full rounded-lg border border-red-200 px-3 py-2 text-sm" required></textarea>
            <button class="kbsm-btn kbsm-btn--outline-red">Tolak</button>
          </form>
        @endif

        @if($pinjaman->status === \App\Models\Pinjaman::STATUS_DISETUJUI)
          <form method="POST" action="{{ route('pinjaman.disburse', $pinjaman) }}" class="rounded-xl border border-emerald-100 bg-emerald-50 p-4 lg:col-span-2">
            @csrf
            <h3 class="mb-3 text-sm font-bold text-emerald-900">Cairkan Pinjaman</h3>
            <div class="grid gap-3 md:grid-cols-2">
              <div>
                <label class="mb-2 block text-xs font-bold uppercase text-slate-600">Tanggal Pencairan</label>
                <input type="date" name="tanggal_pencairan" value="{{ now(config('app.timezone'))->format('Y-m-d') }}"
                  class="block w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm" required>
              </div>
              <div>
                <label class="mb-2 block text-xs font-bold uppercase text-slate-600">Dompet Sumber Dana</label>
                <select name="dompet_id" class="block w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm" required>
                  <option value="">-- Pilih Dompet --</option>
                  @foreach($dompet as $item)
                    <option value="{{ $item->id }}">{{ $item->nama_dompet }} · Saldo {{ $money($item->saldo) }} · {{ $item->akun?->kode_akun ?? 'COA?' }}</option>
                  @endforeach
                </select>
              </div>
            </div>
            <div class="my-4 rounded-lg bg-white p-3 text-sm text-slate-600">
              Estimasi cicilan: {{ $pinjaman->tenor_bulan }} bulan, total {{ $money($pinjaman->jumlah_pinjaman) }}, bunga 0%.
            </div>
            <button class="kbsm-btn kbsm-btn--navy" onclick="return confirm('Cairkan Pinjaman ini dan buat Mutasi/Jurnal/Jadwal?')">Cairkan Pinjaman</button>
          </form>
        @endif

        <form method="POST" action="{{ route('pinjaman.cancel', $pinjaman) }}" class="rounded-xl border border-red-100 p-4">
          @csrf
          <label class="mb-2 block text-xs font-bold uppercase text-red-700">Alasan Pembatalan</label>
          <textarea name="alasan" rows="3" class="mb-3 block w-full rounded-lg border border-red-200 px-3 py-2 text-sm" required></textarea>
          <button class="kbsm-btn kbsm-btn--outline-red">Batalkan</button>
        </form>
      </div>
    </div>
  @endif

  @if($cashAllowed)
    <div class="mb-6 rounded-2xl bg-gradient-to-br from-slate-900 to-emerald-700 p-6 text-white shadow-soft-xl">
      <h2 class="mb-1 text-base font-bold text-white">Pembayaran Tunai Mantan Karyawan</h2>
      <p class="text-sm text-emerald-50">Nominal tidak dapat diedit; sistem memakai jadwal unpaid paling awal atau seluruh sisa Pinjaman.</p>
      <div class="mt-4 grid gap-4 md:grid-cols-2">
        <form action="{{ route('pinjaman.cash-schedule', $pinjaman) }}" method="POST" class="rounded-xl bg-white/10 p-4">
          @csrf
          <label class="mb-2 block text-xs font-bold uppercase text-emerald-50">Dompet Kas penerimaan</label>
          <select name="dompet_id" class="mb-3 w-full rounded-lg border-0 px-3 py-2 text-sm text-slate-700">
            @foreach($dompetKas as $kas)
              <option value="{{ $kas->id }}">{{ $kas->nama_dompet }} · {{ $kas->akun?->kode_akun ?? 'COA?' }}</option>
            @endforeach
          </select>
          <button class="rounded-lg bg-white px-4 py-2 text-xs font-bold uppercase text-slate-900">Bayar Cicilan Terjadwal</button>
        </form>
        <form action="{{ route('pinjaman.cash-full', $pinjaman) }}" method="POST" class="rounded-xl bg-white/10 p-4"
          onsubmit="return confirm('Lunasi seluruh sisa pinjaman secara tunai?')">
          @csrf
          <label class="mb-2 block text-xs font-bold uppercase text-emerald-50">Dompet Kas penerimaan</label>
          <select name="dompet_id" class="mb-3 w-full rounded-lg border-0 px-3 py-2 text-sm text-slate-700">
            @foreach($dompetKas as $kas)
              <option value="{{ $kas->id }}">{{ $kas->nama_dompet }} · {{ $kas->akun?->kode_akun ?? 'COA?' }}</option>
            @endforeach
          </select>
          <button class="rounded-lg bg-emerald-100 px-4 py-2 text-xs font-bold uppercase text-emerald-900">Lunasi Seluruh Sisa Tunai</button>
        </form>
      </div>
    </div>
  @endif

  <div class="relative flex flex-col min-w-0 mb-6 break-words bg-white border-0 shadow-soft-xl rounded-2xl bg-clip-border">
    <div class="p-6 pb-0 mb-0 bg-white rounded-t-2xl">
      <h2 class="text-base font-bold text-slate-700">Jadwal Cicilan</h2>
      <p class="text-sm text-slate-400">Jadwal otomatis read-only; baru dibuat saat Pinjaman dicairkan.</p>
    </div>

    <div class="flex-auto px-0 pt-0 pb-2">
      <div style="overflow-x: auto;" class="p-0">
        <table class="items-center w-full mb-0 align-top border-gray-200 text-slate-500">
          <thead class="align-bottom">
            <tr>
              <th class="px-6 py-3 text-center text-xxs font-bold uppercase text-slate-400 opacity-70">Angsuran Ke</th>
              <th class="px-6 py-3 text-center text-xxs font-bold uppercase text-slate-400 opacity-70">Periode</th>
              <th class="px-6 py-3 text-center text-xxs font-bold uppercase text-slate-400 opacity-70">Nominal</th>
              <th class="px-6 py-3 text-center text-xxs font-bold uppercase text-slate-400 opacity-70">Status</th>
              <th class="px-6 py-3 text-center text-xxs font-bold uppercase text-slate-400 opacity-70">Metode</th>
              <th class="px-6 py-3 text-center text-xxs font-bold uppercase text-slate-400 opacity-70">Pembayaran</th>
            </tr>
          </thead>
          <tbody>
            @forelse($pinjaman->jadwalCicilan as $jadwal)
              <tr>
                <td class="p-2 text-center align-middle bg-transparent border-b whitespace-nowrap shadow-transparent">{{ $jadwal->angsuran_ke }}</td>
                <td class="p-2 text-center align-middle bg-transparent border-b whitespace-nowrap shadow-transparent">{{ $jadwal->periode->format('Y-m') }}</td>
                <td class="p-2 text-center align-middle bg-transparent border-b whitespace-nowrap shadow-transparent">{{ $money($jadwal->nominal_pokok) }}</td>
                <td class="p-2 text-center align-middle bg-transparent border-b whitespace-nowrap shadow-transparent"><span class="kbsm-status kbsm-status--slate">{{ $jadwal->status }}</span></td>
                <td class="p-2 text-center align-middle bg-transparent border-b whitespace-nowrap shadow-transparent">{{ $jadwal->metode_penyelesaian ?: '-' }}</td>
                <td class="p-2 text-center align-middle bg-transparent border-b whitespace-nowrap shadow-transparent">
                  @if($jadwal->cicilanPembayaran)
                    <p class="mb-0 text-xs font-bold text-slate-600">CIC-{{ $jadwal->cicilanPembayaran->id }}</p>
                    <p class="mb-0 text-xs text-slate-400">{{ optional($jadwal->cicilanPembayaran->tanggal_bayar)->format('Y-m-d') }}</p>
                  @else
                    -
                  @endif
                </td>
              </tr>
            @empty
              <tr>
                <td colspan="6" class="p-4 text-center text-sm text-slate-400">Belum ada jadwal cicilan.</td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
@endsection
