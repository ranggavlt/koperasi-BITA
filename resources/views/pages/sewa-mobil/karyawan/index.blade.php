@extends('layout.main')

@section('content')
@php
  $editing = isset($data);
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

  <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
    <div>
      <p class="mb-1 text-xs font-bold uppercase tracking-widest text-green-600">Karyawan</p>
      <h1 class="text-2xl font-bold text-slate-700">Pengajuan Sewa Mobil</h1>
      <p class="mt-1 text-sm text-slate-400">Buat draft, ajukan, dan pantau status sewa mobil milik Anda sendiri.</p>
    </div>
    @if(! $editing)
      <button type="button" onclick="toggleSewaForm()" id="btn-toggle-sewa"
        class="kbsm-btn kbsm-btn--navy">{{ $errors->any() ? 'Tutup Form' : '+ Draft Sewa Mobil' }}</button>
    @endif
  </div>

  <section id="sewa-form" class="mb-6 rounded-2xl border border-slate-100 bg-white p-6 shadow-soft-xl {{ ($editing || $errors->any()) ? 'block' : 'hidden' }}">
    <h2 class="font-bold text-slate-700">{{ $editing ? 'Edit Draft' : 'Draft Pengajuan Baru' }}</h2>
    <p class="mb-5 text-sm text-slate-400">Penyewa/pembayar selalu {{ config('koperasi.nama_perusahaan_penyewa', 'Bita Enarcon Engineering') }}. Tarif akan diisi Finance.</p>
    <form method="POST" action="{{ $editing ? route('sewa-mobil.karyawan.update', $data) : route('sewa-mobil.karyawan.store') }}">
      @csrf
      @if($editing) @method('PUT') @endif
      <div class="grid gap-4 md:grid-cols-2">
        <div>
          <label class="mb-2 block text-xs font-bold uppercase text-slate-600">Mobil</label>
          <select name="aset_koperasi_id" required class="kbsm-focus w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm">
            <option value="">Pilih mobil</option>
            @foreach($mobilOptions as $mobil)
              <option value="{{ $mobil->id }}" {{ (string) old('aset_koperasi_id', $data->aset_koperasi_id ?? '') === (string) $mobil->id ? 'selected' : '' }}>
                {{ $mobil->kode_aset }} - {{ $mobil->merek }} {{ $mobil->model }} / {{ $mobil->mobil->plat_nomor ?? '-' }} ({{ $mobil->status_label }})
              </option>
            @endforeach
          </select>
        </div>
        <div>
          <label class="mb-2 block text-xs font-bold uppercase text-slate-600">Nama kegiatan</label>
          <input name="nama_kegiatan" required maxlength="150" value="{{ old('nama_kegiatan', $data->nama_kegiatan ?? '') }}" class="kbsm-focus w-full rounded-xl border border-slate-200 px-4 py-3 text-sm">
        </div>
        <div>
          <label class="mb-2 block text-xs font-bold uppercase text-slate-600">Lokasi kegiatan</label>
          <input name="lokasi_kegiatan" required maxlength="150" value="{{ old('lokasi_kegiatan', $data->lokasi_kegiatan ?? '') }}" class="kbsm-focus w-full rounded-xl border border-slate-200 px-4 py-3 text-sm">
        </div>
        <div>{{-- placeholder to keep 2-col alignment --}}</div>
        <div>
          <label class="mb-2 block text-xs font-bold uppercase text-slate-600">Mulai</label>
          <input type="datetime-local" name="mulai_at" required value="{{ old('mulai_at', isset($data) ? $data->mulai_at->format('Y-m-d\TH:i') : '') }}" class="kbsm-focus w-full rounded-xl border border-slate-200 px-4 py-3 text-sm">
        </div>
        <div>
          <label class="mb-2 block text-xs font-bold uppercase text-slate-600">Selesai</label>
          <input type="datetime-local" name="selesai_at" required value="{{ old('selesai_at', isset($data) ? $data->selesai_at->format('Y-m-d\TH:i') : '') }}" class="kbsm-focus w-full rounded-xl border border-slate-200 px-4 py-3 text-sm">
        </div>
        <div class="md:col-span-2">
          <label class="mb-2 block text-xs font-bold uppercase text-slate-600">Keterangan</label>
          <textarea name="keterangan" rows="3" maxlength="1000" class="kbsm-focus w-full rounded-xl border border-slate-200 px-4 py-3 text-sm">{{ old('keterangan', $data->keterangan ?? '') }}</textarea>
        </div>
      </div>
      <div class="mt-6 flex gap-3">
        <button class="kbsm-btn kbsm-btn--green">Simpan Draft</button>
        @if($editing)<a href="{{ route('sewa-mobil.karyawan.index') }}" class="kbsm-btn kbsm-btn--outline-slate">Batal</a>@endif
      </div>
    </form>
  </section>

  <section class="overflow-hidden rounded-2xl border border-slate-100 bg-white shadow-soft-xl">
    <div class="border-b border-slate-100 p-6"><h2 class="font-bold text-slate-700">Daftar Pengajuan Saya</h2><p class="text-sm text-slate-400">Finance mencatat approval Pengurus dan pembayaran perusahaan setelah pengajuan diajukan.</p></div>
    <div class="overflow-x-auto">
      <table class="w-full min-w-[1180px] text-left text-sm">
        <thead class="kbsm-thead"><tr><th class="px-6 py-4">Kode</th><th class="px-6 py-4">Mobil</th><th class="px-6 py-4">Kegiatan</th><th class="px-6 py-4">Jadwal</th><th class="px-6 py-4">Status</th><th class="px-6 py-4">Tarif/Pembayaran</th><th class="px-6 py-4">Approval</th><th class="px-6 py-4 text-center">Aksi</th></tr></thead>
        <tbody class="divide-y divide-slate-100">
          @forelse($sewaMobil as $item)
            <tr class="hover:bg-slate-50">
              <td class="px-6 py-4 font-bold kbsm-text-navy">{{ $item->kode_sewa ?: 'Belum diajukan' }}</td>
              <td class="px-6 py-4"><div class="font-semibold text-slate-700">{{ $item->aset->kode_aset }} - {{ $item->aset->merek }} {{ $item->aset->model }}</div><div class="text-xs text-slate-400">{{ $item->aset->mobil->plat_nomor ?? '-' }}</div></td>
              <td class="px-6 py-4"><div class="font-semibold text-slate-700">{{ $item->nama_kegiatan }}</div><div class="text-xs text-slate-400">{{ $item->lokasi_kegiatan }}</div></td>
              <td class="px-6 py-4 text-slate-600">{{ $item->mulai_at->format('d/m/Y H:i') }}<br>{{ $item->selesai_at->format('d/m/Y H:i') }}</td>
              <td class="px-6 py-4">
                <span class="{{ $badge($item->status) }}">{{ $item->status_label }}</span>
                @if($item->needs_finance_review)<div class="mt-1 text-xs font-semibold text-amber-600">Review Finance</div>@endif
              </td>
              <td class="px-6 py-4"><div class="font-semibold text-slate-700">Rp {{ number_format((float) $item->tarif_total, 0, ',', '.') }}</div><div class="text-xs text-slate-400">{{ $item->status_pembayaran }}</div></td>
              <td class="px-6 py-4 text-xs text-slate-500">{{ $item->nama_pengurus_snapshot ?: '-' }}<br>{{ $item->jabatan_pengurus_snapshot ?: '' }}</td>
              <td class="px-6 py-4">
                <div class="flex flex-wrap justify-center gap-2">
                  @if($item->status === 'draft')
                    <a href="{{ route('sewa-mobil.karyawan.edit', $item) }}" class="kbsm-btn kbsm-btn--navy kbsm-btn--sm">Edit</a>
                    <form method="POST" action="{{ route('sewa-mobil.karyawan.submit', $item) }}">@csrf<button class="kbsm-btn kbsm-btn--green kbsm-btn--sm">Ajukan</button></form>
                  @endif
                  @if(in_array($item->status, ['draft','diajukan'], true))
                    <form method="POST" action="{{ route('sewa-mobil.karyawan.cancel', $item) }}" class="flex gap-1" onsubmit="return confirm('Batalkan pengajuan ini?')">
                      @csrf
                      <input name="alasan" required placeholder="Alasan" class="w-28 rounded-lg border border-slate-200 px-2 py-2 text-xs">
                      <button class="kbsm-btn kbsm-btn--outline-red kbsm-btn--sm">Batalkan</button>
                    </form>
                  @endif
                </div>
              </td>
            </tr>
          @empty
            <tr><td colspan="8" class="px-6 py-10 text-center text-slate-400">Belum ada pengajuan Sewa Mobil.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
    <div class="border-t border-slate-100 p-4">{{ $sewaMobil->links() }}</div>
  </section>
</div>
<script>
  function toggleSewaForm() {
    const panel = document.getElementById('sewa-form');
    const button = document.getElementById('btn-toggle-sewa');
    panel.classList.toggle('hidden');
    button.textContent = panel.classList.contains('hidden') ? '+ Draft Sewa Mobil' : 'Tutup Form';
  }
</script>
@endsection
