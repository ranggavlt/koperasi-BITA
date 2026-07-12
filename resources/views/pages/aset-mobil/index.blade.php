@extends('layout.main')

@section('content')
@php
  $editing = isset($data);
  $statusBadge = fn(string $status) => match ($status) {
    'tersedia' => 'bg-green-100 text-green-700',
    'digunakan_disewa' => 'bg-blue-100 text-blue-700',
    'perawatan' => 'bg-amber-100 text-amber-700',
    'nonaktif' => 'bg-slate-100 text-slate-600',
    default => 'bg-slate-100 text-slate-600',
  };
@endphp

<div class="w-full px-6 py-6 mx-auto">
  @if (session('success'))
    <div class="mb-4 rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700">{{ session('success') }}</div>
  @endif
  @if ($errors->any())
    <div class="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
      <ul class="mb-0 list-disc pl-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
    </div>
  @endif

  <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
    <div>
      <p class="mb-1 text-xs font-bold uppercase tracking-widest text-green-600">Master Aset Koperasi</p>
      <h1 class="text-2xl font-bold text-slate-700">Mobil Koperasi</h1>
      <p class="mt-1 text-sm text-slate-400">Pencatatan identitas mobil saja. Belum ada sewa, depresiasi, COA, jurnal, atau mutasi kas.</p>
    </div>
    @if (! $editing)
      <button type="button" onclick="toggleAsetMobilForm()" id="btn-toggle-aset-mobil" class="rounded-xl bg-[#073b5c] px-5 py-3 text-xs font-bold uppercase text-white shadow-lg hover:bg-[#052c46]">
        {{ $errors->any() ? 'Tutup Form' : '+ Tambah Mobil' }}
      </button>
    @endif
  </div>

  <section id="aset-mobil-form" class="mb-6 rounded-2xl border border-slate-100 bg-white p-6 shadow-soft-xl {{ ($editing || $errors->any()) ? 'block' : 'hidden' }}">
    <h2 class="font-bold text-slate-700">{{ $editing ? 'Edit Mobil '.$data->kode_aset : 'Tambah Mobil Koperasi' }}</h2>
    <p class="mb-5 text-sm text-slate-400">Kode mobil dibuat otomatis dengan format MBL-0001 dan tidak pernah digunakan ulang.</p>
    <form method="POST" action="{{ $editing ? route('aset-mobil.update', $data) : route('aset-mobil.store') }}">
      @csrf
      @if($editing) @method('PUT') @endif
      <div class="grid gap-4 md:grid-cols-3">
        <div>
          <label class="mb-2 block text-xs font-bold uppercase text-slate-600" for="plat_nomor">Plat nomor</label>
          <input id="plat_nomor" name="plat_nomor" required maxlength="30" value="{{ old('plat_nomor', $data->mobil->plat_nomor ?? '') }}" class="kbsm-focus w-full rounded-xl border border-slate-200 px-4 py-3 text-sm" placeholder="B 1234 KBS">
        </div>
        <div>
          <label class="mb-2 block text-xs font-bold uppercase text-slate-600" for="merek">Merek</label>
          <input id="merek" name="merek" required maxlength="100" value="{{ old('merek', $data->merek ?? '') }}" class="kbsm-focus w-full rounded-xl border border-slate-200 px-4 py-3 text-sm" placeholder="Toyota">
        </div>
        <div>
          <label class="mb-2 block text-xs font-bold uppercase text-slate-600" for="model">Model</label>
          <input id="model" name="model" required maxlength="100" value="{{ old('model', $data->model ?? '') }}" class="kbsm-focus w-full rounded-xl border border-slate-200 px-4 py-3 text-sm" placeholder="Avanza">
        </div>
        <div>
          <label class="mb-2 block text-xs font-bold uppercase text-slate-600" for="tahun">Tahun</label>
          <input id="tahun" name="tahun" type="number" min="1980" max="{{ now(config('app.timezone', 'Asia/Jakarta'))->year + 1 }}" required value="{{ old('tahun', $data->mobil->tahun ?? now(config('app.timezone', 'Asia/Jakarta'))->year) }}" class="kbsm-focus w-full rounded-xl border border-slate-200 px-4 py-3 text-sm">
        </div>
        <div>
          <label class="mb-2 block text-xs font-bold uppercase text-slate-600" for="warna">Warna</label>
          <input id="warna" name="warna" required maxlength="50" value="{{ old('warna', $data->mobil->warna ?? '') }}" class="kbsm-focus w-full rounded-xl border border-slate-200 px-4 py-3 text-sm" placeholder="Hitam">
        </div>
        <div>
          <label class="mb-2 block text-xs font-bold uppercase text-slate-600">Kode aset</label>
          <div class="rounded-xl border border-slate-100 bg-slate-50 px-4 py-3 text-sm font-semibold text-slate-500">{{ $editing ? $data->kode_aset : 'Otomatis setelah disimpan' }}</div>
        </div>
        <div class="md:col-span-3">
          <label class="mb-2 block text-xs font-bold uppercase text-slate-600" for="keterangan">Keterangan</label>
          <textarea id="keterangan" name="keterangan" rows="3" maxlength="1000" class="kbsm-focus w-full rounded-xl border border-slate-200 px-4 py-3 text-sm">{{ old('keterangan', $data->keterangan ?? '') }}</textarea>
        </div>
      </div>
      <div class="mt-6 flex gap-3">
        <button type="submit" class="rounded-xl bg-[#2f8f3a] px-6 py-3 text-xs font-bold uppercase text-white shadow-lg hover:bg-[#267832]">{{ $editing ? 'Simpan Perubahan' : 'Simpan Mobil' }}</button>
        @if($editing)
          <a href="{{ route('aset-mobil.index') }}" class="rounded-xl border border-slate-200 px-6 py-3 text-xs font-bold uppercase text-slate-500">Batal</a>
        @endif
      </div>
    </form>
  </section>

  <section class="overflow-hidden rounded-2xl border border-slate-100 bg-white shadow-soft-xl">
    <div class="border-b border-slate-100 p-6">
      <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
          <h2 class="font-bold text-slate-700">Daftar Mobil Koperasi</h2>
          <p class="text-sm text-slate-400">Filter berdasarkan kode, merek, model, plat, warna, atau status.</p>
        </div>
        <form method="GET" action="{{ route('aset-mobil.index') }}" class="flex flex-wrap items-end gap-3">
          <div>
            <label class="mb-1 block text-xs font-bold uppercase text-slate-500">Cari</label>
            <input name="q" value="{{ request('q') }}" class="kbsm-focus rounded-xl border border-slate-200 px-4 py-2 text-sm" placeholder="MBL / plat / merek">
          </div>
          <div>
            <label class="mb-1 block text-xs font-bold uppercase text-slate-500">Status</label>
            <select name="status" class="kbsm-focus rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm">
              <option value="">Semua status</option>
              @foreach($statuses as $value => $label)
                <option value="{{ $value }}" {{ request('status') === $value ? 'selected' : '' }}>{{ $label }}</option>
              @endforeach
            </select>
          </div>
          <button class="rounded-xl bg-[#073b5c] px-4 py-2 text-xs font-bold uppercase text-white">Terapkan</button>
        </form>
      </div>
    </div>

    <div class="overflow-x-auto">
      <table class="w-full min-w-[1180px] text-left text-sm">
        <thead class="bg-[#073b5c] text-xs uppercase text-white">
          <tr>
            <th class="px-6 py-4">Kode</th>
            <th class="px-6 py-4">Mobil</th>
            <th class="px-6 py-4">Plat</th>
            <th class="px-6 py-4">Tahun/Warna</th>
            <th class="px-6 py-4">Status</th>
            <th class="px-6 py-4">Audit</th>
            <th class="px-6 py-4 text-center">Aksi</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
          @forelse($asetMobil as $item)
            @php $guard = $deleteGuards[$item->id] ?? ['allowed' => false, 'reason' => 'Guard belum tersedia.']; @endphp
            <tr class="hover:bg-slate-50">
              <td class="px-6 py-4 font-bold text-[#073b5c]">{{ $item->kode_aset }}</td>
              <td class="px-6 py-4">
                <div class="font-semibold text-slate-700">{{ $item->merek }} {{ $item->model }}</div>
                <div class="max-w-xs text-xs text-slate-400">{{ $item->keterangan ?: '-' }}</div>
              </td>
              <td class="px-6 py-4 font-semibold text-slate-700">{{ $item->mobil->plat_nomor ?? '-' }}</td>
              <td class="px-6 py-4 text-slate-600">{{ $item->mobil->tahun ?? '-' }} / {{ $item->mobil->warna ?? '-' }}</td>
              <td class="px-6 py-4">
                <span class="rounded-full px-3 py-1 text-xs font-bold {{ $statusBadge($item->status) }}">{{ $item->status_label }}</span>
                @if($item->nonaktif_at)<div class="mt-1 text-xs text-slate-400">Nonaktif: {{ $item->nonaktif_at->format('d/m/Y H:i') }}</div>@endif
              </td>
              <td class="px-6 py-4 text-xs text-slate-500">
                <div>Dibuat: {{ $item->creator->name ?? '-' }}</div>
                <div>Diubah: {{ $item->updater->name ?? '-' }}</div>
                @if($item->nonaktifBy)<div>Nonaktif oleh: {{ $item->nonaktifBy->name }}</div>@endif
              </td>
              <td class="px-6 py-4">
                <div class="flex flex-wrap justify-center gap-2">
                  <a href="{{ route('aset-mobil.edit', $item) }}" class="rounded-lg bg-[#073b5c] px-3 py-2 text-xs font-bold text-white">Edit</a>
                  <form method="POST" action="{{ route('aset-mobil.status', $item) }}" class="flex gap-1">
                    @csrf @method('PATCH')
                    <select name="status" class="rounded-lg border border-slate-200 bg-white px-2 py-2 text-xs">
                      @foreach($statuses as $value => $label)
                        <option value="{{ $value }}" {{ $item->status === $value ? 'selected' : '' }}>{{ $label }}</option>
                      @endforeach
                    </select>
                    <button class="rounded-lg border border-green-300 px-3 py-2 text-xs font-bold text-green-700">Status</button>
                  </form>
                  @if($item->status !== 'nonaktif')
                    <form method="POST" action="{{ route('aset-mobil.nonaktifkan', $item) }}" onsubmit="return confirm('Nonaktifkan mobil koperasi ini?')">@csrf @method('PATCH')<button class="rounded-lg border border-amber-300 px-3 py-2 text-xs font-bold text-amber-700">Nonaktifkan</button></form>
                  @else
                    <form method="POST" action="{{ route('aset-mobil.aktifkan', $item) }}">@csrf @method('PATCH')<button class="rounded-lg bg-[#2f8f3a] px-3 py-2 text-xs font-bold text-white">Aktifkan</button></form>
                  @endif
                  @if($guard['allowed'])
                    <form method="POST" action="{{ route('aset-mobil.destroy', $item) }}" onsubmit="return confirm('Hapus permanen mobil ini? Kode aset tidak akan digunakan ulang.')">
                      @csrf @method('DELETE')
                      <input type="hidden" name="confirm_delete" value="1">
                      <button class="rounded-lg bg-red-600 px-3 py-2 text-xs font-bold text-white">Hapus</button>
                    </form>
                  @else
                    <span class="rounded-lg bg-slate-100 px-3 py-2 text-xs font-semibold text-slate-500" title="{{ $guard['reason'] ?? 'Tidak eligible hapus' }}">Hapus terkunci</span>
                  @endif
                </div>
              </td>
            </tr>
          @empty
            <tr><td colspan="7" class="px-6 py-10 text-center text-slate-400">Belum ada data mobil koperasi.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
    <div class="border-t border-slate-100 p-4">{{ $asetMobil->links() }}</div>
  </section>
</div>

<script>
  function toggleAsetMobilForm() {
    const panel = document.getElementById('aset-mobil-form');
    const button = document.getElementById('btn-toggle-aset-mobil');
    panel.classList.toggle('hidden');
    button.textContent = panel.classList.contains('hidden') ? '+ Tambah Mobil' : 'Tutup Form';
  }
</script>
@endsection
