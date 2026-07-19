@extends('layout.main')

@section('content')
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
      <p class="mb-1 text-xs font-bold uppercase tracking-widest text-green-600">Master Data</p>
      <h1 class="text-2xl font-bold text-slate-700">Karyawan</h1>
      <p class="mt-1 text-sm text-slate-400">Sumber identitas utama Karyawan perusahaan dan status kerjanya.</p>
    </div>
    @if (!isset($data))
      <button type="button" onclick="toggleMasterForm()" id="btn-toggle-form"
        class="kbsm-btn kbsm-btn--navy">
        {{ $errors->any() ? 'Tutup Form' : '+ Tambah Karyawan' }}
      </button>
    @endif
  </div>

  <section id="form-container" class="mb-6 rounded-2xl border border-slate-100 bg-white p-6 shadow-soft-xl {{ (isset($data) || $errors->any()) ? 'block' : 'hidden' }}">
    <div class="mb-5">
      <h2 class="font-bold text-slate-700">{{ isset($data) ? 'Edit Karyawan' : 'Karyawan Baru' }}</h2>
      <p class="text-sm text-slate-400">Keanggotaan dikelola pada halaman Anggota, bukan melalui form ini.</p>
    </div>

    <form action="{{ isset($data) ? route('karyawan.update', $data) : route('karyawan.store') }}" method="POST">
      @csrf
      @if(isset($data)) @method('PUT') @endif
      <div class="grid gap-4 md:grid-cols-2">
        <div>
          <label class="mb-2 block text-xs font-bold uppercase text-slate-600" for="nama">Nama lengkap</label>
          <input id="nama" name="nama" type="text" value="{{ old('nama', $data->nama ?? '') }}" required
            class="kbsm-focus w-full rounded-xl border border-slate-200 px-4 py-3 text-sm" />
        </div>
        <div>
          <label class="mb-2 block text-xs font-bold uppercase text-slate-600" for="jabatan">Jabatan perusahaan</label>
          <input id="jabatan" name="jabatan" type="text" value="{{ old('jabatan', $data->jabatan ?? '') }}" required
            class="kbsm-focus w-full rounded-xl border border-slate-200 px-4 py-3 text-sm" />
        </div>
        <div>
          <label class="mb-2 block text-xs font-bold uppercase text-slate-600" for="email">Email</label>
          <input id="email" name="email" type="email" value="{{ old('email', $data->email ?? '') }}" required
            class="kbsm-focus w-full rounded-xl border border-slate-200 px-4 py-3 text-sm" />
        </div>
        <div>
          <label class="mb-2 block text-xs font-bold uppercase text-slate-600" for="telepon">Telepon / WhatsApp</label>
          <input id="telepon" name="telepon" type="text" value="{{ old('telepon', $data->telepon ?? '') }}"
            class="kbsm-focus w-full rounded-xl border border-slate-200 px-4 py-3 text-sm" />
        </div>
        <div>
          <label class="mb-2 block text-xs font-bold uppercase text-slate-600" for="status_kerja">Status kerja</label>
          <select id="status_kerja" name="status_kerja" required onchange="syncTanggalBerhenti()"
            class="kbsm-focus w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm">
            <option value="aktif" {{ old('status_kerja', $data->status_kerja ?? 'aktif') === 'aktif' ? 'selected' : '' }}>Aktif</option>
            <option value="berhenti" {{ old('status_kerja', $data->status_kerja ?? '') === 'berhenti' ? 'selected' : '' }}>Berhenti</option>
          </select>
        </div>
        <div id="tanggal-berhenti-field">
          <label class="mb-2 block text-xs font-bold uppercase text-slate-600" for="tanggal_berhenti">Tanggal berhenti</label>
          <input id="tanggal_berhenti" name="tanggal_berhenti" type="date"
            value="{{ old('tanggal_berhenti', isset($data) && $data->tanggal_berhenti ? $data->tanggal_berhenti->format('Y-m-d') : '') }}"
            class="kbsm-focus w-full rounded-xl border border-slate-200 px-4 py-3 text-sm" />
          <p class="mt-1 text-xs text-slate-400">Wajib ketika status diubah menjadi berhenti.</p>
        </div>
      </div>
      <div class="mt-6 flex gap-3">
        <button class="rounded-xl bg-[#2f8f3a] px-6 py-3 text-xs font-bold uppercase text-white shadow-lg hover:bg-[#267832]" type="submit">
          {{ isset($data) ? 'Simpan Perubahan' : 'Simpan Karyawan' }}
        </button>
        @if(isset($data))
          <a href="{{ route('karyawan.index') }}" class="rounded-xl border border-slate-200 px-6 py-3 text-xs font-bold uppercase text-slate-500">Batal</a>
        @endif
      </div>
    </form>
  </section>

  <section class="overflow-hidden rounded-2xl border border-slate-100 bg-white shadow-soft-xl">
    <div class="border-b border-slate-100 p-6">
      <h2 class="font-bold text-slate-700">Daftar Karyawan</h2>
      <p class="text-sm text-slate-400">Status Anggota berasal dari relasi master Anggota.</p>
    </div>
    <div style="overflow-x: auto;">
      <table class="w-full min-w-[1150px] text-left text-sm">
        <thead class="bg-[#073b5c] text-xs uppercase text-white">
          <tr><th class="px-6 py-4">Karyawan</th><th class="px-6 py-4">Kontak</th><th class="px-6 py-4">Jabatan</th><th class="px-6 py-4">Status kerja</th><th class="px-6 py-4">Keanggotaan</th><th class="px-6 py-4 text-center">Aksi</th></tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
          @forelse($karyawan as $item)
            <tr class="hover:bg-slate-50">
              <td class="px-6 py-4 font-semibold text-slate-700">{{ $item->nama }}</td>
              <td class="px-6 py-4"><div class="text-slate-600">{{ $item->email }}</div><div class="text-xs text-slate-400">{{ $item->telepon ?: '-' }}</div></td>
              <td class="px-6 py-4 text-slate-600">{{ $item->jabatan }}</td>
              <td class="px-6 py-4">
                <span class="rounded-full px-3 py-1 text-xs font-bold {{ $item->status_kerja === 'aktif' ? 'bg-green-100 text-green-700' : 'bg-slate-100 text-slate-600' }}">{{ ucfirst($item->status_kerja) }}</span>
                @if($item->tanggal_berhenti)<div class="mt-1 text-xs text-slate-400">{{ $item->tanggal_berhenti->format('d/m/Y') }}</div>@endif
              </td>
              <td class="px-6 py-4">
                @if($item->anggota)
                  <a href="{{ route('anggota.edit', $item->anggota) }}" class="font-semibold text-[#2f8f3a]">{{ $item->anggota->nomor_anggota }}</a>
                  <div class="text-xs text-slate-400">{{ ucfirst($item->anggota->status) }}</div>
                @else
                  <span class="text-slate-400">Nonanggota</span>
                @endif
              </td>

              <td class="px-6 py-4">
                <div class="flex flex-wrap justify-center gap-2">
                  <a href="{{ route('karyawan.edit', $item) }}" class="rounded-lg bg-[#073b5c] px-3 py-2 text-xs font-bold text-white">Edit</a>
                  @if($item->status_kerja === 'aktif' && !$item->anggota)
                    <a href="{{ route('anggota.index', ['karyawan_id' => $item->id]) }}" class="rounded-lg bg-[#2f8f3a] px-3 py-2 text-xs font-bold text-white">Daftarkan sebagai Anggota</a>
                  @endif
                </div>
              </td>
            </tr>
          @empty
            <tr><td colspan="6" class="px-6 py-10 text-center text-slate-400">Belum ada data Karyawan.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
    <div class="border-t border-slate-100 p-4">{{ $karyawan->links() }}</div>
  </section>
</div>

<script>
  function toggleMasterForm() {
    const panel = document.getElementById('form-container');
    const button = document.getElementById('btn-toggle-form');
    panel.classList.toggle('hidden');
    button.textContent = panel.classList.contains('hidden') ? '+ Tambah Karyawan' : 'Tutup Form';
  }
  function syncTanggalBerhenti() {
    const berhenti = document.getElementById('status_kerja').value === 'berhenti';
    const input = document.getElementById('tanggal_berhenti');
    input.required = berhenti;
    input.disabled = !berhenti;
    if (!berhenti) input.value = '';
  }
  document.addEventListener('DOMContentLoaded', syncTanggalBerhenti);
</script>
@endsection
