@extends('layout.main')

@section('content')
@php
  $editing = isset($data);
  $statusBadge = fn(string $status) => match ($status) {
    'tersedia'        => 'kbsm-status kbsm-status--green',
    'digunakan_disewa'=> 'kbsm-status kbsm-status--blue',
    'perawatan'       => 'kbsm-status kbsm-status--amber',
    'nonaktif'        => 'kbsm-status kbsm-status--slate',
    default           => 'kbsm-status kbsm-status--slate',
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
      <h1 class="text-2xl font-bold text-slate-700">Printer Koperasi</h1>
      <p class="mt-1 text-sm text-slate-400">Pencatatan identitas printer saja. Belum ada jasa print, sewa, depresiasi, COA, jurnal, atau mutasi kas.</p>
    </div>
    @if (! $editing)
      <button type="button" onclick="toggleAsetPrinterForm()" id="btn-toggle-aset-printer"
        class="kbsm-btn kbsm-btn--navy">
        {{ $errors->any() ? 'Tutup Form' : '+ Tambah Printer' }}
      </button>
    @endif
  </div>

  <section id="aset-printer-form" class="mb-6 rounded-2xl border border-slate-100 bg-white p-6 shadow-soft-xl {{ ($editing || $errors->any()) ? 'block' : 'hidden' }}">
    <h2 class="font-bold text-slate-700">{{ $editing ? 'Edit Printer '.$data->kode_aset : 'Tambah Printer Koperasi' }}</h2>
    <p class="mb-5 text-sm text-slate-400">Kode printer dibuat otomatis dengan format PRT-0001 dan tidak pernah digunakan ulang.</p>
    <form method="POST" action="{{ $editing ? route('aset-printer.update', $data) : route('aset-printer.store') }}">
      @csrf
      @if($editing) @method('PUT') @endif
      <div class="grid gap-4 md:grid-cols-3">
        <div>
          <label class="mb-2 block text-xs font-bold uppercase text-slate-600" for="nomor_seri">Nomor seri</label>
          <input id="nomor_seri" name="nomor_seri" required maxlength="100" value="{{ old('nomor_seri', $data->printer->nomor_seri ?? '') }}" class="kbsm-focus w-full rounded-xl border border-slate-200 px-4 py-3 text-sm" placeholder="KBS-PRN-001">
        </div>
        <div>
          <label class="mb-2 block text-xs font-bold uppercase text-slate-600" for="merek">Merek</label>
          <input id="merek" name="merek" required maxlength="100" value="{{ old('merek', $data->merek ?? '') }}" class="kbsm-focus w-full rounded-xl border border-slate-200 px-4 py-3 text-sm" placeholder="Epson">
        </div>
        <div>
          <label class="mb-2 block text-xs font-bold uppercase text-slate-600" for="model">Model</label>
          <input id="model" name="model" required maxlength="100" value="{{ old('model', $data->model ?? '') }}" class="kbsm-focus w-full rounded-xl border border-slate-200 px-4 py-3 text-sm" placeholder="L3210">
        </div>
        <div>
          <label class="mb-2 block text-xs font-bold uppercase text-slate-600" for="lokasi">Lokasi</label>
          <input id="lokasi" name="lokasi" required maxlength="150" value="{{ old('lokasi', $data->printer->lokasi ?? '') }}" class="kbsm-focus w-full rounded-xl border border-slate-200 px-4 py-3 text-sm" placeholder="Kantor Koperasi">
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
        <button type="submit" class="kbsm-btn kbsm-btn--green">{{ $editing ? 'Simpan Perubahan' : 'Simpan Printer' }}</button>
        @if($editing)
          <a href="{{ route('aset-printer.index') }}" class="kbsm-btn kbsm-btn--outline-slate">Batal</a>
        @endif
      </div>
    </form>
  </section>

  <section class="overflow-hidden rounded-2xl border border-slate-100 bg-white shadow-soft-xl">
    <div class="border-b border-slate-100 p-6">
      <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
          <h2 class="font-bold text-slate-700">Daftar Printer Koperasi</h2>
          <p class="text-sm text-slate-400">Filter berdasarkan kode, merek, model, nomor seri, lokasi, atau status.</p>
        </div>
        <form method="GET" action="{{ route('aset-printer.index') }}" class="flex flex-wrap items-end gap-3">
          <div>
            <label class="mb-1 block text-xs font-bold uppercase text-slate-500">Cari</label>
            <input name="q" value="{{ request('q') }}" class="kbsm-focus rounded-xl border border-slate-200 px-4 py-2 text-sm" placeholder="PRT / seri / merek">
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
          <button class="kbsm-btn kbsm-btn--navy kbsm-btn--sm">Terapkan</button>
        </form>
      </div>
    </div>

    <div class="overflow-x-auto">
      <table class="w-full min-w-[1120px] text-left text-sm">
        <thead class="kbsm-thead">
          <tr>
            <th class="px-6 py-4">Kode</th>
            <th class="px-6 py-4">Printer</th>
            <th class="px-6 py-4">Nomor Seri</th>
            <th class="px-6 py-4">Lokasi</th>
            <th class="px-6 py-4">Status</th>
            <th class="px-6 py-4">Audit</th>
            <th class="px-6 py-4 text-center">Aksi</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
          @forelse($asetPrinter as $item)
            @php $guard = $deleteGuards[$item->id] ?? ['allowed' => false, 'reason' => 'Guard belum tersedia.']; @endphp
            <tr class="hover:bg-slate-50">
              <td class="px-6 py-4 font-bold kbsm-text-navy">{{ $item->kode_aset }}</td>
              <td class="px-6 py-4">
                <div class="font-semibold text-slate-700">{{ $item->merek }} {{ $item->model }}</div>
                <div class="max-w-xs text-xs text-slate-400">{{ $item->keterangan ?: '-' }}</div>
              </td>
              <td class="px-6 py-4 font-semibold text-slate-700">{{ $item->printer->nomor_seri ?? '-' }}</td>
              <td class="px-6 py-4 text-slate-600">{{ $item->printer->lokasi ?? '-' }}</td>
              <td class="px-6 py-4">
                <span class="{{ $statusBadge($item->status) }}">{{ $item->status_label }}</span>
                @if($item->nonaktif_at)<div class="mt-1 text-xs text-slate-400">Nonaktif: {{ $item->nonaktif_at->format('d/m/Y H:i') }}</div>@endif
              </td>
              <td class="px-6 py-4 text-xs text-slate-500">
                <div>Dibuat: {{ $item->creator->name ?? '-' }}</div>
                <div>Diubah: {{ $item->updater->name ?? '-' }}</div>
                @if($item->nonaktifBy)<div>Nonaktif oleh: {{ $item->nonaktifBy->name }}</div>@endif
              </td>
              <td class="px-6 py-4">
                <div class="flex flex-wrap justify-center gap-2">
                  <a href="{{ route('aset-printer.edit', $item) }}" class="kbsm-btn kbsm-btn--navy kbsm-btn--sm">Edit</a>
                  <form method="POST" action="{{ route('aset-printer.status', $item) }}" class="flex gap-1">
                    @csrf @method('PATCH')
                    <select name="status" class="rounded-lg border border-slate-200 bg-white px-2 py-2 text-xs">
                      @foreach($statuses as $value => $label)
                        <option value="{{ $value }}" {{ $item->status === $value ? 'selected' : '' }}>{{ $label }}</option>
                      @endforeach
                    </select>
                    <button class="kbsm-btn kbsm-btn--outline-green kbsm-btn--sm">Status</button>
                  </form>
                  @if($item->status !== 'nonaktif')
                    <form method="POST" action="{{ route('aset-printer.nonaktifkan', $item) }}" onsubmit="return confirm('Nonaktifkan printer koperasi ini?')">@csrf @method('PATCH')<button class="kbsm-btn kbsm-btn--outline-amber kbsm-btn--sm">Nonaktifkan</button></form>
                  @else
                    <form method="POST" action="{{ route('aset-printer.aktifkan', $item) }}">@csrf @method('PATCH')<button class="kbsm-btn kbsm-btn--green kbsm-btn--sm">Aktifkan</button></form>
                  @endif
                  @if($guard['allowed'])
                    <form method="POST" action="{{ route('aset-printer.destroy', $item) }}" onsubmit="return confirm('Hapus permanen printer ini? Kode aset tidak akan digunakan ulang.')">
                      @csrf @method('DELETE')
                      <input type="hidden" name="confirm_delete" value="1">
                      <button class="kbsm-btn kbsm-btn--red kbsm-btn--sm">Hapus</button>
                    </form>
                  @else
                    <span class="rounded-lg bg-slate-100 px-3 py-2 text-xs font-semibold text-slate-500" title="{{ $guard['reason'] ?? 'Tidak eligible hapus' }}">Hapus terkunci</span>
                  @endif
                </div>
              </td>
            </tr>
          @empty
            <tr><td colspan="7" class="px-6 py-10 text-center text-slate-400">Belum ada data printer koperasi.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
    <div class="border-t border-slate-100 p-4">{{ $asetPrinter->links() }}</div>
  </section>
</div>

<script>
  function toggleAsetPrinterForm() {
    const panel = document.getElementById('aset-printer-form');
    const button = document.getElementById('btn-toggle-aset-printer');
    panel.classList.toggle('hidden');
    button.textContent = panel.classList.contains('hidden') ? '+ Tambah Printer' : 'Tutup Form';
  }
</script>
@endsection
