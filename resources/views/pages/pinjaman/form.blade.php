@extends('layout.main')

@section('content')
@php
  $editing = (bool) $pinjaman;
@endphp

<div class="w-full px-6 py-6 mx-auto">
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
      <h1 class="text-lg font-bold text-slate-700">{{ $editing ? 'Edit Draft Pengajuan Pinjaman' : 'Buat Pengajuan Pinjaman' }}</h1>
      <p class="mt-1 text-sm text-slate-400">Form ini hanya membuat draft. Dompet dan tanggal pencairan dipilih setelah pengajuan disetujui.</p>
    </div>
    <a href="{{ route('pinjaman.index') }}" class="kbsm-btn kbsm-btn--outline-slate">Kembali</a>
  </div>

  <div class="rounded-2xl border border-slate-100 bg-white p-6 shadow-soft-xl">
    <form method="POST" action="{{ $editing ? route('pinjaman.update', $pinjaman) : route('pinjaman.store') }}">
      @csrf
      @if($editing)
        @method('PUT')
      @endif

      <div class="grid gap-4 md:grid-cols-2">
        <div class="md:col-span-2">
          <label class="mb-2 block text-xs font-bold uppercase text-slate-600">Anggota Aktif</label>
          <select name="anggota_id" class="kbsm-focus block w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm">
            <option value="">-- Pilih Anggota --</option>
            @foreach($anggota as $item)
              <option value="{{ $item->id }}" @selected((string) old('anggota_id', $pinjaman?->anggota_id) === (string) $item->id)>
                {{ $item->nomor_anggota }} - {{ $item->karyawan->nama ?? '-' }} | Plafon Rp {{ number_format((float) $item->plafon_pinjaman, 0, ',', '.') }}
              </option>
            @endforeach
          </select>
          <p class="mt-1 text-xs text-slate-400">Yang tampil hanya Anggota aktif, Karyawan aktif, dan tidak memiliki proses Pinjaman terbuka.</p>
        </div>

        <div>
          <label class="mb-2 block text-xs font-bold uppercase text-slate-600">Tanggal Pengajuan</label>
          <input type="date" name="tanggal_pengajuan"
            value="{{ old('tanggal_pengajuan', optional($pinjaman?->tanggal_pengajuan)->format('Y-m-d') ?? now(config('app.timezone'))->format('Y-m-d')) }}"
            class="kbsm-focus block w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm">
        </div>

        <div>
          <label class="mb-2 block text-xs font-bold uppercase text-slate-600">Bunga</label>
          <input type="text" value="0%" readonly class="block w-full rounded-lg border border-gray-200 bg-gray-100 px-3 py-2 text-sm text-slate-500">
        </div>

        <div>
          <label class="mb-2 block text-xs font-bold uppercase text-slate-600">Nominal Pengajuan</label>
          <input type="number" name="jumlah_pinjaman" min="1" max="5000000" step="1"
            value="{{ old('jumlah_pinjaman', $pinjaman?->jumlah_pinjaman ? (int) $pinjaman->jumlah_pinjaman : '') }}"
            class="kbsm-focus block w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm"
            placeholder="Maksimal 5000000">
          <p class="mt-1 text-xs text-slate-400">Maksimal sistem Rp5.000.000 dan tetap dibatasi plafon Anggota.</p>
        </div>

        <div>
          <label class="mb-2 block text-xs font-bold uppercase text-slate-600">Tenor</label>
          <input type="number" name="tenor_bulan" min="1" max="12" step="1"
            value="{{ old('tenor_bulan', $pinjaman?->tenor_bulan) }}"
            class="kbsm-focus block w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm"
            placeholder="1-12 bulan">
        </div>

        <div>
          <label class="mb-2 block text-xs font-bold uppercase text-slate-600">Biaya Admin</label>
          <input type="text" value="Rp 50.000" readonly class="block w-full rounded-lg border border-gray-200 bg-gray-100 px-3 py-2 text-sm text-slate-500">
          <input type="hidden" name="biaya_admin" value="50000">
        </div>

        <div>
          <label class="mb-2 block text-xs font-bold uppercase text-slate-600">Opsi Bayar Admin</label>
          <select name="cara_bayar_admin" class="kbsm-focus block w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm">
            <option value="potong_pinjaman" @selected(old('cara_bayar_admin', $pinjaman?->cara_bayar_admin) === 'potong_pinjaman')>Potong Pinjaman (Cair dipotong 50rb)</option>
            <option value="tunai" @selected(old('cara_bayar_admin', $pinjaman?->cara_bayar_admin) === 'tunai')>Bayar Tunai (Cair Bulat/Utuh)</option>
          </select>
        </div>

        <div class="md:col-span-2">
          <label class="mb-2 block text-xs font-bold uppercase text-slate-600">Tujuan/Keterangan Pinjaman</label>
          <textarea name="keterangan" rows="4"
            class="kbsm-focus block w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm"
            placeholder="Contoh: kebutuhan pendidikan, kesehatan, atau kebutuhan keluarga">{{ old('keterangan', $pinjaman?->keterangan) }}</textarea>
        </div>
      </div>

      <div class="mt-6 flex flex-wrap gap-3">
        <button class="kbsm-btn kbsm-btn--navy">{{ $editing ? 'Simpan Draft' : 'Buat Draft' }}</button>
        <a href="{{ route('pinjaman.index') }}" class="kbsm-btn kbsm-btn--outline-slate">Batal</a>
      </div>
    </form>
  </div>
</div>
@endsection
