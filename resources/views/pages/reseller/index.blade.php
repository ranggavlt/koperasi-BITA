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

  {{-- CARD: FORM --}}
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
                <input type="text" name="nama_reseller"
                  value="{{ old('nama_reseller', $data->nama_reseller ?? '') }}"
                  class="focus:shadow-soft-primary-outline text-sm leading-5.6 ease-soft block w-full appearance-none rounded-lg border border-solid border-gray-300 bg-white bg-clip-padding px-3 py-2 font-normal text-gray-700 transition-all focus:border-fuchsia-300 focus:outline-none"
                  placeholder="Nama reseller">
              </div>

              <div class="w-full max-w-full px-3 md:w-4/12">
                <label class="mb-2 ml-1 block text-xs font-bold uppercase text-slate-700">Telepon</label>
                <input type="text" name="telepon"
                  value="{{ old('telepon', $data->telepon ?? '') }}"
                  class="focus:shadow-soft-primary-outline text-sm leading-5.6 ease-soft block w-full appearance-none rounded-lg border border-solid border-gray-300 bg-white bg-clip-padding px-3 py-2 font-normal text-gray-700 transition-all focus:border-fuchsia-300 focus:outline-none"
                  placeholder="08xxxx">
              </div>

              <div class="w-full max-w-full px-3 md:w-4/12">
                <label class="mb-2 ml-1 block text-xs font-bold uppercase text-slate-700">Alamat</label>
                <input type="text" name="alamat"
                  value="{{ old('alamat', $data->alamat ?? '') }}"
                  class="focus:shadow-soft-primary-outline text-sm leading-5.6 ease-soft block w-full appearance-none rounded-lg border border-solid border-gray-300 bg-white bg-clip-padding px-3 py-2 font-normal text-gray-700 transition-all focus:border-fuchsia-300 focus:outline-none"
                  placeholder="Alamat reseller">
              </div>
            </div>

            <div class="mt-6 flex flex-wrap gap-2">
              <button type="submit"
                class="inline-block rounded-lg bg-gradient-to-tl from-purple-700 to-pink-500 px-6 py-3 text-xs font-bold uppercase text-white shadow-soft-md transition-all">
                {{ isset($data) ? 'Update' : 'Simpan' }}
              </button>

              @if(isset($data))
                <a href="{{ route('reseller.index') }}"
                  class="inline-block rounded-lg bg-gradient-to-tl from-slate-600 to-slate-300 px-6 py-3 text-xs font-bold uppercase text-white shadow-soft-md transition-all">
                  Batal
                </a>
              @endif
            </div>

          </form>
        </div>
      </div>
    </div>
  </div>

  {{-- CARD: TABLE --}}
  <div class="flex flex-wrap -mx-3">
    <div class="flex-none w-full max-w-full px-3">
      <div class="relative flex flex-col min-w-0 mb-6 break-words bg-white border-0 shadow-soft-xl rounded-2xl bg-clip-border">
        <div class="p-6 pb-0 mb-0 bg-white rounded-t-2xl">
          <h6>Daftar Reseller</h6>
          <p class="text-sm text-slate-400">Kelola reseller untuk produk konsinyasi</p>
        </div>

        <div class="flex-auto px-0 pt-0 pb-2">
          <div class="p-0 overflow-x-auto">
            <table class="items-center w-full mb-0 align-top border-gray-200 text-slate-500">
              <thead class="align-bottom">
                <tr>
                  <th class="px-6 py-3 text-left text-xxs font-bold uppercase text-slate-400 opacity-70 border-b border-gray-200">
                    Nama
                  </th>
                  <th class="px-6 py-3 text-left text-xxs font-bold uppercase text-slate-400 opacity-70 border-b border-gray-200">
                    Telepon
                  </th>
                  <th class="px-6 py-3 text-left text-xxs font-bold uppercase text-slate-400 opacity-70 border-b border-gray-200">
                    Alamat
                  </th>
                  <th class="px-6 py-3 text-center text-xxs font-bold uppercase text-slate-400 opacity-70 border-b border-gray-200">
                    Aksi
                  </th>
                </tr>
              </thead>

              <tbody>
                @forelse($reseller as $r)
                  <tr>
                    {{-- NAMA --}}
                    <td class="p-2 align-middle bg-transparent border-b whitespace-nowrap shadow-transparent">
                      <div class="flex items-center px-4 py-2">
                        
                        {{-- KOTAK NOMOR URUT --}}
                        <div class="mr-4 flex shrink-0 h-9 w-9 items-center justify-center rounded-xl bg-gradient-to-tl from-purple-700 to-pink-500 text-xs font-bold text-white">
                          {{ $reseller->firstItem() + $loop->index }}
                        </div>
                        
                        <div class="flex flex-col justify-center">
                          <h6 class="mb-0 text-sm leading-normal text-slate-700">{{ $r->nama_reseller }}</h6>
                          <p class="mb-0 text-xs leading-tight text-slate-400">
                            ID: {{ $r->id }}
                          </p>
                        </div>
                        
                      </div>
                    </td>

                    {{-- TELEPON --}}
                    <td class="p-2 align-middle bg-transparent border-b whitespace-nowrap shadow-transparent">
                      <span class="px-4 text-sm text-slate-600">
                        {{ $r->telepon ?? '-' }}
                      </span>
                    </td>

                    {{-- ALAMAT --}}
                    <td class="p-2 align-middle bg-transparent border-b shadow-transparent">
                      <div class="px-4 max-w-[520px]">
                        <span class="block text-sm text-slate-600 truncate" title="{{ $r->alamat ?? '-' }}">
                          {{ $r->alamat ?? '-' }}
                        </span>
                      </div>
                    </td>

                    {{-- AKSI --}}
                    <td class="p-2 align-middle bg-transparent border-b whitespace-nowrap shadow-transparent">
                      <div class="flex items-center justify-center gap-2 px-4">
                        <a href="{{ route('reseller.edit', $r->id) }}"
                           class="inline-block rounded-lg bg-gradient-to-tl from-blue-600 to-cyan-400 px-4 py-2 text-xs font-bold uppercase text-white shadow-soft-md transition-all">
                          Edit
                        </a>

                        <form method="POST" action="{{ route('reseller.destroy', $r->id) }}"
                              onsubmit="return confirm('Hapus reseller ini?')">
                          @csrf
                          @method('DELETE')
                          <button type="submit"
                            class="inline-block rounded-lg bg-gradient-to-tl from-red-600 to-rose-400 px-4 py-2 text-xs font-bold uppercase text-white shadow-soft-md transition-all">
                            Hapus
                          </button>
                        </form>
                      </div>
                    </td>
                  </tr>
                @empty
                  <tr>
                    <td colspan="4" class="p-6 text-center text-sm text-slate-400">
                      Belum ada reseller.
                    </td>
                  </tr>
                @endforelse
              </tbody>
            </table>
          </div>

          {{-- PAGINATION LINKS --}}
          <div class="p-4 border-t border-gray-200">
            {{ $reseller->links() }}
          </div>

        </div>
      </div>
    </div>
  </div>

</div>
@endsection