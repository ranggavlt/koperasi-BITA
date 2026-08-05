@extends('layout.main')

@section('content')
<div class="w-full px-6 py-6 mx-auto">
  @if (session('success'))<div class="mb-4 rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700">{{ session('success') }}</div>@endif
  @if ($errors->any())<div class="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700"><ul class="mb-0 list-disc pl-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

  <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
    <div><p class="mb-1 text-xs font-medium tracking-wide text-green-600">Master Data</p><h1 class="text-lg font-bold text-slate-700 m-0">Struktur Organisasi Koperasi</h1><p class="mt-1 text-sm text-slate-400">Pengurus, Pengawas, dan Pembina hanya dapat dipilih dari Anggota dan Karyawan aktif.</p></div>
    @if(!isset($data))<button type="button" onclick="togglePengurusForm()" id="btn-toggle-pengurus" class="kbsm-btn kbsm-btn--navy">{{ $errors->any() ? 'Tutup Form' : '+ Tambah Pengurus' }}</button>@endif
  </div>

  <section id="pengurus-form" class="mb-6 rounded-2xl border border-slate-100 bg-white p-6 shadow-soft-xl {{ (isset($data) || $errors->any()) ? 'block' : 'hidden' }}">
    <h2 class="text-base font-bold text-slate-700 m-0">{{ isset($data) ? 'Edit Pengurus' : 'Jabatan Pengurus Baru' }}</h2>
    <p class="mb-5 text-sm text-slate-400">Satu Anggota dan satu jabatan organisasi hanya boleh memiliki satu record aktif.</p>
    <form method="POST" action="{{ isset($data) ? route('pengurus-koperasi.update', $data) : route('pengurus-koperasi.store') }}">
      @csrf
      @if(isset($data)) @method('PUT') @endif
      <div class="grid gap-4 md:grid-cols-2">
        <div>
          <label class="mb-2 block text-xs font-medium text-slate-600" for="anggota_id">Anggota aktif</label>
          <select id="anggota_id" name="anggota_id" required class="kbsm-focus w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm">
            <option value="">Pilih Anggota</option>
            @if(isset($data) && !$anggotaAktif->contains('id', $data->anggota_id))
              <option value="{{ $data->anggota_id }}" selected>{{ $data->anggota->nomor_anggota }} - {{ $data->anggota->karyawan->nama }} (histori)</option>
            @endif
            @foreach($anggotaAktif as $item)
              <option value="{{ $item->id }}" {{ (string) old('anggota_id', $data->anggota_id ?? '') === (string) $item->id ? 'selected' : '' }}>{{ $item->nomor_anggota }} - {{ $item->karyawan->nama }}</option>
            @endforeach
          </select>
        </div>
        <div>
          <label class="mb-2 block text-xs font-medium text-slate-600" for="jabatan">Jabatan organisasi</label>
          <select id="jabatan" name="jabatan" required class="kbsm-focus w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm">
            <option value="">Pilih jabatan</option>
            @foreach(\App\Models\PengurusKoperasi::JABATAN_PER_KELOMPOK as $kelompok => $daftar)<optgroup label="{{ ucfirst($kelompok) }}">@foreach($daftar as $item)<option value="{{ $item }}" {{ old('jabatan', $data->jabatan ?? '') === $item ? 'selected' : '' }}>{{ $item }}</option>@endforeach</optgroup>@endforeach
          </select>
        </div>
      </div>
      <div class="mt-6 flex gap-3"><button type="submit" class="rounded-xl bg-[#2f8f3a] px-6 py-3 text-xs font-bold uppercase text-white shadow-lg hover:bg-[#267832]">{{ isset($data) ? 'Simpan Perubahan' : 'Simpan Pengurus' }}</button>@if(isset($data))<a href="{{ route('pengurus-koperasi.index') }}" class="rounded-xl border border-slate-200 px-6 py-3 text-xs font-bold uppercase text-slate-500">Batal</a>@endif</div>
    </form>
  </section>

  <section class="overflow-hidden rounded-2xl border border-slate-100 bg-white shadow-soft-xl">
    <div class="border-b border-slate-100 p-6"><h2 class="text-base font-semibold text-slate-700 m-0">Daftar dan Histori Jabatan</h2><p class="text-sm text-slate-400">Identitas ditampilkan dari master Karyawan melalui Anggota dan menjadi sumber penerima SHU.</p></div>
    <div style="overflow-x: auto;" class="">
      <table class="w-full min-w-[850px] text-left text-sm">
        <thead class="bg-[#073b5c] text-xs text-white"><tr><th class="px-6 py-4">Nomor Anggota</th><th class="px-6 py-4">Nama</th><th class="px-6 py-4">Kelompok</th><th class="px-6 py-4">Jabatan</th><th class="px-6 py-4">Status</th><th class="px-6 py-4 text-center">Aksi</th></tr></thead>
        <tbody class="divide-y divide-slate-100">
          @forelse($pengurusKoperasi as $item)
            <tr class="hover:bg-slate-50"><td class="px-6 py-4 font-medium text-[#073b5c]">{{ $item->anggota->nomor_anggota }}</td><td class="px-6 py-4"><div class="font-medium text-slate-700">{{ $item->anggota->karyawan->nama }}</div><div class="text-xs text-slate-400">{{ $item->anggota->karyawan->jabatan }}</div></td><td class="px-6 py-4 text-slate-600">{{ $item->kelompok_label }}</td><td class="px-6 py-4 text-slate-600">{{ $item->jabatan }}</td><td class="px-6 py-4"><span class="rounded-full px-3 py-1 text-xs font-medium {{ $item->status === 'aktif' ? 'bg-green-100 text-green-700' : 'bg-slate-100 text-slate-600' }}">{{ ucfirst($item->status) }}</span></td><td class="px-6 py-4"><div class="flex justify-center gap-2"><a href="{{ route('pengurus-koperasi.edit', $item) }}" class="rounded-lg bg-[#073b5c] px-3 py-2 text-xs font-medium text-white">Edit</a>@if($item->status === 'aktif')<form method="POST" action="{{ route('pengurus-koperasi.deactivate', $item) }}">@csrf @method('PATCH')<button class="rounded-lg border border-amber-300 px-3 py-2 text-xs font-medium text-amber-700">Nonaktifkan</button></form>@elseif($item->anggota->status === 'aktif' && $item->anggota->karyawan->status_kerja === 'aktif')<form method="POST" action="{{ route('pengurus-koperasi.activate', $item) }}">@csrf @method('PATCH')<button class="rounded-lg bg-[#2f8f3a] px-3 py-2 text-xs font-medium text-white">Aktifkan</button></form>@endif</div></td></tr>
          @empty
            <tr><td colspan="6" class="px-6 py-10 text-center text-slate-400">Belum ada data struktur organisasi.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
    <div class="border-t border-slate-100 p-4">{{ $pengurusKoperasi->links() }}</div>
  </section>
</div>
<script>
  function togglePengurusForm() {
    const panel = document.getElementById('pengurus-form');
    const button = document.getElementById('btn-toggle-pengurus');
    panel.classList.toggle('hidden');
    button.textContent = panel.classList.contains('hidden') ? '+ Tambah Pengurus' : 'Tutup Form';
  }
</script>
@endsection
