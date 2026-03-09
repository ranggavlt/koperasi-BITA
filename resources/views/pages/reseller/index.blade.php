@extends('layout.main')
@section('content')
<div class="w-full px-6 py-6 mx-auto">

  @if (session('success'))
    <div class="mb-4 rounded-lg bg-green-100 px-4 py-3 text-sm text-green-700">
      {{ session('success') }}
    </div>
  @endif

  @if ($errors->any())
    <div class="mb-4 rounded-lg bg-red-100 px-4 py-3 text-sm text-red-700">
      <ul class="mb-0 list-disc pl-5">
        @foreach ($errors->all() as $error)
          <li>{{ $error }}</li>
        @endforeach
      </ul>
    </div>
  @endif

  {{-- FORM --}}
  <div class="flex flex-wrap -mx-3">
    <div class="flex-none w-full max-w-full px-3">
      <div class="relative flex flex-col min-w-0 mb-6 break-words bg-white border-0 shadow-soft-xl rounded-2xl bg-clip-border">
        <div class="p-6 pb-0 mb-0 bg-white rounded-t-2xl">
          <h6>{{ isset($data) ? 'Edit Reseller' : 'Tambah Reseller' }}</h6>
          <p class="text-sm text-slate-400">Data pemilik barang titipan (konsinyasi)</p>
        </div>

        <div class="flex-auto p-6">
          <form action="{{ isset($data) ? route('reseller.update', $data->id) : route('reseller.store') }}" method="POST">
            @csrf
            @if(isset($data)) @method('PUT') @endif

            <div class="flex flex-wrap -mx-3">
              <div class="w-full max-w-full px-3 md:w-4/12">
                <label class="mb-2 ml-1 block text-xs font-bold uppercase text-slate-700">Nama Reseller</label>
                <input type="text" name="nama_reseller" value="{{ old('nama_reseller', $data->nama_reseller ?? '') }}"
                  class="block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm"
                  placeholder="Nama reseller">
              </div>

              <div class="w-full max-w-full px-3 md:w-4/12">
                <label class="mb-2 ml-1 block text-xs font-bold uppercase text-slate-700">Telepon</label>
                <input type="text" name="telepon" value="{{ old('telepon', $data->telepon ?? '') }}"
                  class="block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm"
                  placeholder="08xxxx">
              </div>

              <div class="w-full max-w-full px-3 md:w-4/12">
                <label class="mb-2 ml-1 block text-xs font-bold uppercase text-slate-700">Alamat</label>
                <input type="text" name="alamat" value="{{ old('alamat', $data->alamat ?? '') }}"
                  class="block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm"
                  placeholder="Alamat reseller">
              </div>
            </div>

            <div class="mt-6 flex gap-2">
              <button class="rounded-lg bg-gradient-to-tl from-purple-700 to-pink-500 px-6 py-3 text-xs font-bold uppercase text-white">
                {{ isset($data) ? 'Update' : 'Simpan' }}
              </button>

              @if(isset($data))
                <a href="{{ route('reseller.index') }}"
                  class="rounded-lg bg-gradient-to-tl from-slate-600 to-slate-300 px-6 py-3 text-xs font-bold uppercase text-white">
                  Batal
                </a>
              @endif
            </div>

          </form>
        </div>
      </div>
    </div>
  </div>

  {{-- TABLE --}}
  <div class="flex flex-wrap -mx-3">
    <div class="flex-none w-full max-w-full px-3">
      <div class="relative flex flex-col min-w-0 mb-6 break-words bg-white border-0 shadow-soft-xl rounded-2xl bg-clip-border">
        <div class="p-6 pb-0 mb-0 bg-white rounded-t-2xl">
          <h6>Daftar Reseller</h6>
        </div>

        <div class="flex-auto px-0 pt-0 pb-2">
          <div class="p-0 overflow-x-auto">
            <table class="items-center w-full mb-0 align-top border-gray-200 text-slate-500">
              <thead>
                <tr>
                  <th class="px-6 py-3 text-left text-xxs font-bold uppercase text-slate-400 opacity-70">Nama</th>
                  <th class="px-6 py-3 text-left text-xxs font-bold uppercase text-slate-400 opacity-70">Telepon</th>
                  <th class="px-6 py-3 text-left text-xxs font-bold uppercase text-slate-400 opacity-70">Alamat</th>
                  <th class="px-6 py-3 text-center text-xxs font-bold uppercase text-slate-400 opacity-70">Aksi</th>
                </tr>
              </thead>
              <tbody>
                @forelse($reseller as $r)
                <tr>
                  <td class="p-2 border-b">{{ $r->nama_reseller }}</td>
                  <td class="p-2 border-b">{{ $r->telepon ?? '-' }}</td>
                  <td class="p-2 border-b">{{ $r->alamat ?? '-' }}</td>
                  <td class="p-2 border-b text-center">
                    <div class="flex items-center justify-center gap-2">
                      <a class="text-xs font-semibold text-blue-500" href="{{ route('reseller.edit', $r->id) }}">Edit</a>
                      <form method="POST" action="{{ route('reseller.destroy', $r->id) }}" onsubmit="return confirm('Hapus reseller ini?')">
                        @csrf @method('DELETE')
                        <button class="text-xs font-semibold text-red-500" type="submit">Hapus</button>
                      </form>
                    </div>
                  </td>
                </tr>
                @empty
                <tr><td colspan="4" class="p-4 text-center text-slate-400">Belum ada reseller.</td></tr>
                @endforelse
              </tbody>
            </table>
          </div>
        </div>

      </div>
    </div>
  </div>

</div>
@endsection