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
      <p class="mb-1 text-xs font-bold uppercase tracking-widest text-slate-500">Master Data</p>
      <h1 class="text-2xl font-bold text-slate-700">Vendor Sewa</h1>
      <p class="mt-1 text-sm text-slate-400">Kelola daftar Vendor / Supplier untuk Aset Koperasi (B2B).</p>
    </div>
  </div>

  <section class="mb-6 rounded-2xl border border-slate-100 bg-white p-6 shadow-soft-xl">
    <h2 class="text-base font-bold text-slate-700 m-0">{{ isset($data) ? 'Edit Vendor' : 'Tambah Vendor Baru' }}</h2>
    <form method="POST" action="{{ isset($data) ? route('vendor.update', $data) : route('vendor.store') }}">
      @csrf
      @if(isset($data)) @method('PUT') @endif
      <div class="grid gap-4 md:grid-cols-3 mt-4">
        <div>
          <label class="mb-2 block text-xs font-bold uppercase text-slate-600">Nama Vendor</label>
          <input type="text" name="nama" required maxlength="100" value="{{ old('nama', $data->nama ?? '') }}" class="w-full rounded-xl border border-slate-200 px-4 py-3 text-sm" placeholder="PT Contoh Vendor">
        </div>
        <div>
          <label class="mb-2 block text-xs font-bold uppercase text-slate-600">Kontak</label>
          <input type="text" name="kontak" maxlength="50" value="{{ old('kontak', $data->kontak ?? '') }}" class="w-full rounded-xl border border-slate-200 px-4 py-3 text-sm" placeholder="08123456789">
        </div>
        <div>
          <label class="mb-2 block text-xs font-bold uppercase text-slate-600">Alamat</label>
          <input type="text" name="alamat" maxlength="255" value="{{ old('alamat', $data->alamat ?? '') }}" class="w-full rounded-xl border border-slate-200 px-4 py-3 text-sm" placeholder="Jl. Raya No 1">
        </div>
      </div>
      <div class="mt-4 flex gap-3">
        <button type="submit" class="rounded-xl bg-[#073b5c] px-6 py-3 text-xs font-bold uppercase text-white hover:bg-slate-800">
            {{ isset($data) ? 'Simpan Perubahan' : 'Simpan Vendor' }}
        </button>
        @if(isset($data))
            <a href="{{ route('vendor.index') }}" class="rounded-xl border border-slate-200 px-6 py-3 text-xs font-bold uppercase text-slate-500">Batal</a>
        @endif
      </div>
    </form>
  </section>

  <section class="overflow-hidden rounded-2xl border border-slate-100 bg-white shadow-soft-xl">
    <div class="border-b border-slate-100 p-6">
      <h2 class="text-base font-bold text-slate-700 m-0">Daftar Vendor</h2>
    </div>

    <div style="overflow-x: auto;" class="">
      <table class="w-full min-w-[800px] text-left text-sm">
        <thead class="bg-slate-50">
          <tr>
            <th class="px-6 py-4 font-bold text-slate-500">Nama Vendor</th>
            <th class="px-6 py-4 font-bold text-slate-500">Kontak</th>
            <th class="px-6 py-4 font-bold text-slate-500">Alamat</th>
            <th class="px-6 py-4 text-center font-bold text-slate-500">Aksi</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
          @forelse($vendors as $vendor)
            <tr class="hover:bg-slate-50">
              <td class="px-6 py-4 font-bold text-slate-700">{{ $vendor->nama }}</td>
              <td class="px-6 py-4 text-slate-600">{{ $vendor->kontak ?? '-' }}</td>
              <td class="px-6 py-4 text-slate-600">{{ $vendor->alamat ?? '-' }}</td>
              <td class="px-6 py-4 text-center">
                <div class="flex justify-center gap-2">
                    <a href="{{ route('vendor.edit', $vendor) }}" class="rounded-lg bg-[#073b5c] px-3 py-2 text-xs font-bold text-white">Edit</a>
                    <form action="{{ route('vendor.destroy', $vendor) }}" method="POST" onsubmit="return confirm('Hapus vendor ini?')">
                        @csrf @method('DELETE')
                        <button type="submit" class="rounded-lg bg-red-500 px-3 py-2 text-xs font-bold text-white">Hapus</button>
                    </form>
                </div>
              </td>
            </tr>
          @empty
            <tr><td colspan="4" class="px-6 py-10 text-center text-slate-400">Belum ada vendor.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
    <div class="border-t border-slate-100 p-4">{{ $vendors->links() }}</div>
  </section>
</div>
@endsection
