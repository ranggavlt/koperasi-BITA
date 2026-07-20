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

  <div class="mb-6 rounded-2xl border border-slate-100 bg-white p-6 shadow-soft-xl">
    <div class="mb-4">
      <h1 class="text-lg font-bold text-slate-700">Filter Pinjaman</h1>
      <p class="mt-1 text-sm text-slate-400">Cari pengajuan dan Pinjaman berdasarkan status, Anggota, dan tanggal pengajuan.</p>
    </div>

    <form method="GET" action="{{ route('pinjaman.index') }}" class="grid gap-4 md:grid-cols-2">
      <div>
        <label class="mb-2 block text-xs font-bold uppercase text-slate-600">Status</label>
        <select name="status" class="kbsm-focus block w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm">
          <option value="">Semua Status</option>
          @foreach($statusOptions as $status => $label)
            <option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ $label }}</option>
          @endforeach
        </select>
      </div>
      <div>
        <label class="mb-2 block text-xs font-bold uppercase text-slate-600">Anggota</label>
        <select name="anggota_id" class="kbsm-focus block w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm">
          <option value="">Semua Anggota</option>
          @foreach($anggotaFilter as $item)
            <option value="{{ $item->id }}" @selected((string)($filters['anggota_id'] ?? '') === (string)$item->id)>
              {{ $item->nomor_anggota }} - {{ $item->karyawan->nama ?? '-' }}
            </option>
          @endforeach
        </select>
      </div>
      <div>
        <label class="mb-2 block text-xs font-bold uppercase text-slate-600">Tanggal Mulai</label>
        <input type="date" name="tanggal_mulai" value="{{ $filters['tanggal_mulai'] ?? '' }}"
          class="kbsm-focus block w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm">
      </div>
      <div>
        <label class="mb-2 block text-xs font-bold uppercase text-slate-600">Tanggal Selesai</label>
        <input type="date" name="tanggal_selesai" value="{{ $filters['tanggal_selesai'] ?? '' }}"
          class="kbsm-focus block w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm">
      </div>
      <div class="flex flex-wrap items-center gap-3 md:col-span-2">
        <button class="kbsm-btn kbsm-btn--navy">Filter</button>
        <a href="{{ route('pinjaman.index') }}" class="kbsm-btn kbsm-btn--outline-slate">Reset</a>
      </div>
    </form>
  </div>

  <div class="overflow-hidden rounded-2xl border border-slate-100 bg-white shadow-soft-xl">
    <div class="flex flex-wrap items-center justify-between gap-4 border-b border-slate-100 p-6">
      <div>
        <h2 class="text-lg font-bold text-slate-700">Daftar Pinjaman</h2>
        <p class="mt-1 text-sm text-slate-400">Lifecycle: draft, diajukan, disetujui, cair, lalu lunas.</p>
      </div>
      <a href="{{ route('pinjaman.create') }}" class="kbsm-btn kbsm-btn--navy">+ Buat Pengajuan</a>
    </div>

    <div style="overflow-x:auto;">
      <table class="items-center w-full mb-0 align-top border-gray-200 text-slate-500">
        <thead class="align-bottom bg-slate-800">
          <tr>
            <th class="px-6 py-3 text-left text-xxs font-bold uppercase text-white">Kode</th>
            <th class="px-6 py-3 text-left text-xxs font-bold uppercase text-white">Anggota</th>
            <th class="px-6 py-3 text-center text-xxs font-bold uppercase text-white">Tanggal Pengajuan</th>
            <th class="px-6 py-3 text-center text-xxs font-bold uppercase text-white">Nominal</th>
            <th class="px-6 py-3 text-center text-xxs font-bold uppercase text-white">Tenor</th>
            <th class="px-6 py-3 text-center text-xxs font-bold uppercase text-white">Status</th>
            <th class="px-6 py-3 text-left text-xxs font-bold uppercase text-white">Audit/Progres</th>
            <th class="px-6 py-3 text-center text-xxs font-bold uppercase text-white">Aksi</th>
          </tr>
        </thead>
        <tbody>
          @forelse($pinjaman as $item)
            <tr>
              <td class="border-b p-4 align-top">
                <p class="mb-0 text-sm font-bold text-slate-700">{{ $item->kode_pinjaman }}</p>
                <p class="mb-0 text-xs text-slate-400">{{ $item->dompet->nama_dompet ?? 'Belum dicairkan' }}</p>
              </td>
              <td class="border-b p-4 align-top">
                <p class="mb-0 text-sm font-semibold text-slate-700">{{ $item->anggota->nomor_anggota ?? '-' }}</p>
                <p class="mb-0 text-xs text-slate-400">{{ $item->anggota->karyawan->nama ?? $item->karyawan->nama ?? '-' }}</p>
              </td>
              <td class="border-b p-4 text-center align-top">{{ optional($item->tanggal_pengajuan)->format('d/m/Y') ?? '-' }}</td>
              <td class="border-b p-4 text-center align-top">{{ $money($item->jumlah_pinjaman) }}</td>
              <td class="border-b p-4 text-center align-top">{{ $item->tenor_bulan }} bulan</td>
              <td class="border-b p-4 text-center align-top">
                <span class="kbsm-status {{ $statusClass[$item->status] ?? 'kbsm-status--slate' }}">{{ $item->status_label }}</span>
              </td>
              <td class="border-b p-4 align-top">
                <p class="mb-0 text-xs text-slate-500">
                  @if($item->disbursed_at)
                    Dicairkan {{ $item->disbursed_at->format('d/m/Y H:i') }}
                  @elseif($item->approved_at)
                    Disetujui {{ $item->approved_at->format('d/m/Y H:i') }}
                  @elseif($item->submitted_at)
                    Diajukan {{ $item->submitted_at->format('d/m/Y H:i') }}
                  @elseif($item->rejected_at)
                    Ditolak {{ $item->rejected_at->format('d/m/Y H:i') }}
                  @elseif($item->cancelled_at)
                    Dibatalkan {{ $item->cancelled_at->format('d/m/Y H:i') }}
                  @else
                    Draft dibuat {{ optional($item->created_at)->format('d/m/Y H:i') }}
                  @endif
                </p>
              </td>
              <td class="border-b p-4 align-top">
                <div class="flex flex-wrap justify-center gap-2">
                  @if($item->status === \App\Models\Pinjaman::STATUS_DRAFT)
                    <a href="{{ route('pinjaman.edit', $item) }}" class="kbsm-btn kbsm-btn--outline-slate kbsm-btn--sm">Edit</a>
                    <form method="POST" action="{{ route('pinjaman.submit', $item) }}">@csrf<button class="kbsm-btn kbsm-btn--green kbsm-btn--sm">Ajukan</button></form>
                    <form method="POST" action="{{ route('pinjaman.cancel', $item) }}" onsubmit="return confirm('Batalkan draft pengajuan ini?')">
                      @csrf
                      <input type="hidden" name="alasan" value="Dibatalkan dari daftar Pinjaman">
                      <button class="kbsm-btn kbsm-btn--outline-red kbsm-btn--sm">Batalkan</button>
                    </form>
                  @elseif($item->status === \App\Models\Pinjaman::STATUS_DIAJUKAN)
                    <form method="POST" action="{{ route('pinjaman.approve', $item) }}">@csrf<button class="kbsm-btn kbsm-btn--green kbsm-btn--sm">Setujui</button></form>
                    <a href="{{ route('pinjaman.show', $item) }}" class="kbsm-btn kbsm-btn--outline-red kbsm-btn--sm">Tolak/Batal</a>
                  @elseif($item->status === \App\Models\Pinjaman::STATUS_DISETUJUI)
                    <a href="{{ route('pinjaman.show', $item) }}" class="kbsm-btn kbsm-btn--navy kbsm-btn--sm">Cairkan</a>
                    <a href="{{ route('pinjaman.show', $item) }}" class="kbsm-btn kbsm-btn--outline-red kbsm-btn--sm">Batalkan</a>
                  @else
                    <a href="{{ route('pinjaman.show', $item) }}" class="kbsm-btn kbsm-btn--outline-slate kbsm-btn--sm">Detail</a>
                  @endif
                </div>
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="8" class="p-6 text-center text-sm text-slate-400">Belum ada data Pinjaman sesuai filter.</td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>

    <div class="border-t border-gray-200 p-4">
      {{ $pinjaman->links() }}
    </div>
  </div>
</div>
@endsection
