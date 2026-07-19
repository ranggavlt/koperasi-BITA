@extends('layout.main')

@section('content')
<div class="w-full px-6 py-6 mx-auto">
  @if (session('success'))<div class="mb-4 rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700">{{ session('success') }}</div>@endif
  @if ($errors->any())
    <div class="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700"><ul class="mb-0 list-disc pl-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
  @endif

  <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
    <div>
      <p class="mb-1 text-xs font-bold uppercase tracking-widest text-green-600">Master Data</p>
      <h1 class="text-2xl font-bold text-slate-700">Anggota</h1>
      <p class="mt-1 text-sm text-slate-400">Keanggotaan Koperasi yang terhubung satu-ke-satu dengan Karyawan.</p>
    </div>
    @if (!isset($data))
      <button type="button" onclick="toggleAnggotaForm()" id="btn-toggle-anggota" class="kbsm-btn kbsm-btn--navy">
        {{ $errors->any() || request('karyawan_id') ? 'Tutup Form' : '+ Daftarkan Anggota' }}
      </button>
    @endif
  </div>

  <section id="anggota-form" class="mb-6 rounded-2xl border border-slate-100 bg-white p-6 shadow-soft-xl {{ (isset($data) || $errors->any() || request('karyawan_id')) ? 'block' : 'hidden' }}">
    <h2 class="font-bold text-slate-700">{{ isset($data) ? 'Edit Anggota '.$data->nomor_anggota : 'Pendaftaran Anggota' }}</h2>
    <p class="mb-5 text-sm text-slate-400">Anggota baru langsung aktif. Nomor dibuat otomatis setelah penyimpanan.</p>
    <form method="POST" action="{{ isset($data) ? route('anggota.update', $data) : route('anggota.store') }}">
      @csrf
      @if(isset($data)) @method('PUT') @endif
      <div class="grid gap-4 md:grid-cols-2">
        @if(!isset($data))
          <div>
            <label class="mb-2 block text-xs font-bold uppercase text-slate-600" for="karyawan_id">Karyawan aktif</label>
            <select id="karyawan_id" name="karyawan_id" required class="kbsm-focus w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm">
              <option value="">Pilih Karyawan yang belum menjadi Anggota</option>
              @foreach($karyawanTersedia as $item)
                <option value="{{ $item->id }}" {{ (string) old('karyawan_id', request('karyawan_id')) === (string) $item->id ? 'selected' : '' }}>{{ $item->nama }} — {{ $item->jabatan }}</option>
              @endforeach
            </select>
          </div>
        @else
          <div>
            <label class="mb-2 block text-xs font-bold uppercase text-slate-600">Karyawan</label>
            <div class="rounded-xl border border-slate-100 bg-slate-50 px-4 py-3 text-sm font-semibold text-slate-700">{{ $data->karyawan->nama }}</div>
          </div>
        @endif
        <div>
          <label class="mb-2 block text-xs font-bold uppercase text-slate-600" for="tanggal_bergabung">Tanggal bergabung</label>
          <input id="tanggal_bergabung" name="tanggal_bergabung" type="date" max="{{ today()->format('Y-m-d') }}" required
            value="{{ old('tanggal_bergabung', isset($data) && $data->tanggal_bergabung ? $data->tanggal_bergabung->format('Y-m-d') : today()->format('Y-m-d')) }}"
            class="kbsm-focus w-full rounded-xl border border-slate-200 px-4 py-3 text-sm" />
        </div>
        <div>
          <label class="mb-2 block text-xs font-bold uppercase text-slate-600" for="plafon_pinjaman">Plafon pinjaman</label>
          <input id="plafon_pinjaman" name="plafon_pinjaman" type="number" min="0" max="5000000" step="1000" required value="{{ old('plafon_pinjaman', $data->plafon_pinjaman ?? 0) }}"
            class="kbsm-focus w-full rounded-xl border border-slate-200 px-4 py-3 text-sm" />
          <p class="mt-1 text-xs text-slate-400">Rp0 sampai Rp5.000.000. Belum mengubah mesin Pinjaman.</p>
        </div>
        <div class="md:col-span-2">
          <label class="mb-2 block text-xs font-bold uppercase text-slate-600" for="alamat">Alamat rumah lengkap</label>
          <textarea id="alamat" name="alamat" rows="3" required class="kbsm-focus w-full rounded-xl border border-slate-200 px-4 py-3 text-sm">{{ old('alamat', $data->alamat ?? '') }}</textarea>
        </div>
      </div>
      <div class="mt-6 flex gap-3">
        <button type="submit" class="rounded-xl bg-[#2f8f3a] px-6 py-3 text-xs font-bold uppercase text-white shadow-lg hover:bg-[#267832]">{{ isset($data) ? 'Simpan Perubahan' : 'Simpan Anggota' }}</button>
        @if(isset($data))<a href="{{ route('anggota.index') }}" class="rounded-xl border border-slate-200 px-6 py-3 text-xs font-bold uppercase text-slate-500">Batal</a>@endif
      </div>
    </form>
  </section>

  <section class="overflow-hidden rounded-2xl border border-slate-100 bg-white shadow-soft-xl">
    <div class="border-b border-slate-100 p-6"><h2 class="font-bold text-slate-700">Daftar Anggota</h2><p class="text-sm text-slate-400">Data nonaktif tetap dipertahankan sebagai histori.</p></div>
    <div style="overflow-x: auto;" class="">
      <table class="w-full min-w-[1050px] text-left text-sm">
        <thead class="bg-[#073b5c] text-xs uppercase text-white"><tr><th class="px-6 py-4">Nomor</th><th class="px-6 py-4">Karyawan</th><th class="px-6 py-4">Bergabung</th><th class="px-6 py-4">Alamat</th><th class="px-6 py-4">Plafon</th><th class="px-6 py-4">Status</th><th class="px-6 py-4 text-center">Aksi</th></tr></thead>
        <tbody class="divide-y divide-slate-100">
          @forelse($anggota as $item)
            <tr class="hover:bg-slate-50">
              <td class="px-6 py-4 font-bold text-[#073b5c]">{{ $item->nomor_anggota }}</td>
              <td class="px-6 py-4"><div class="font-semibold text-slate-700">{{ $item->karyawan->nama }}</div><div class="text-xs text-slate-400">{{ $item->karyawan->jabatan }} · {{ ucfirst($item->karyawan->status_kerja) }}</div></td>
              <td class="px-6 py-4 text-slate-600">{{ $item->tanggal_bergabung->format('d/m/Y') }}</td>
              <td class="max-w-xs whitespace-normal px-6 py-4 text-slate-600">{{ $item->alamat }}</td>
              <td class="px-6 py-4 font-semibold text-slate-700">Rp {{ number_format((float) $item->plafon_pinjaman, 0, ',', '.') }}</td>
              <td class="px-6 py-4"><span class="rounded-full px-3 py-1 text-xs font-bold {{ $item->status === 'aktif' ? 'bg-green-100 text-green-700' : 'bg-slate-100 text-slate-600' }}">{{ ucfirst($item->status) }}</span>@if($item->tanggal_nonaktif)<div class="mt-1 text-xs text-slate-400">{{ $item->tanggal_nonaktif->format('d/m/Y') }}</div>@endif</td>
              <td class="px-6 py-4"><div class="flex flex-wrap justify-center gap-2">
                <a href="{{ route('anggota.edit', $item) }}" class="rounded-lg bg-[#073b5c] px-3 py-2 text-xs font-bold text-white">Edit</a>
                @if($item->status === 'aktif')
                  <form method="POST" action="{{ route('anggota.deactivate', $item) }}" onsubmit="return confirm('Nonaktifkan Anggota ini dan jabatan Pengurus aktif terkait?')">@csrf @method('PATCH')<button class="rounded-lg border border-amber-300 px-3 py-2 text-xs font-bold text-amber-700">Nonaktifkan</button></form>
                @elseif($item->karyawan->status_kerja === 'aktif')
                  <form method="POST" action="{{ route('anggota.activate', $item) }}">@csrf @method('PATCH')<button class="rounded-lg bg-[#2f8f3a] px-3 py-2 text-xs font-bold text-white">Aktifkan</button></form>
                @endif
              </div></td>
            </tr>
          @empty
            <tr><td colspan="7" class="px-6 py-10 text-center text-slate-400">Belum ada data Anggota.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
    <div class="border-t border-slate-100 p-4">{{ $anggota->links() }}</div>
  </section>
</div>
<script>
  function toggleAnggotaForm() {
    const panel = document.getElementById('anggota-form');
    const button = document.getElementById('btn-toggle-anggota');
    panel.classList.toggle('hidden');
    button.textContent = panel.classList.contains('hidden') ? '+ Daftarkan Anggota' : 'Tutup Form';
  }
</script>
@endsection
