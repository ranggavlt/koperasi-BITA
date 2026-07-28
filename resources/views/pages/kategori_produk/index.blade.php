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

  {{-- CARD 1: FORM --}}
  <div class="flex flex-wrap -mx-3">
    <div class="flex-none w-full max-w-full px-3">
      <div class="relative flex flex-col min-w-0 mb-6 break-words bg-white border-0 shadow-soft-xl rounded-2xl bg-clip-border">
        
        {{-- HEADER FORM & TOMBOL TOGGLE --}}
        <div class="p-6 pb-0 mb-0 bg-white rounded-t-2xl flex justify-between items-center">
          <div>
            <h6>{{ isset($data) ? 'Edit Kategori' : 'Tambah Kategori' }}</h6>
            <p class="text-sm text-slate-400">Kelompokkan produkmu agar lebih rapi</p>
          </div>
          
          {{-- Tombol Toggle hanya muncul jika BUKAN mode Edit --}}
          @if(!isset($data))
            <button type="button" onclick="toggleForm()" id="btn-toggle-form"
              class="inline-block rounded-lg bg-gradient-to-tl from-slate-600 to-slate-300 px-4 py-2 text-xs font-bold uppercase text-white shadow-soft-md transition-all hover:scale-105">
              {{ $errors->any() ? 'Tutup Form' : '+ Tambah Data' }}
            </button>
          @endif
        </div>

        {{-- BODY FORM (Bisa di-hidden/ditampilkan) --}}
        <div id="form-container" class="flex-auto p-6 transition-all duration-300 {{ (isset($data) || $errors->any()) ? 'block' : 'hidden' }}">
          <form action="{{ isset($data) ? route('kategori-produk.update', $data->id) : route('kategori-produk.store') }}" method="POST">
            @csrf
            @if(isset($data))
              @method('PUT')
            @endif

            <div class="flex flex-wrap -mx-3">
              
              {{-- Nama Kategori --}}
              <div class="w-full max-w-full px-3 md:w-5/12">
                <label class="mb-2 ml-1 block text-xs font-bold uppercase text-slate-700">Nama Kategori</label>
                <input type="text" name="nama_kategori"
                  value="{{ old('nama_kategori', $data->nama_kategori ?? '') }}"
                  class="focus:shadow-soft-primary-outline text-sm leading-5.6 ease-soft block w-full appearance-none rounded-lg border border-solid border-gray-300 bg-white bg-clip-padding px-3 py-2 font-normal text-gray-700 transition-all focus:border-fuchsia-300 focus:outline-none"
                  placeholder="Contoh: Minuman, Makanan Ringan, dll">
              </div>

              {{-- Deskripsi --}}
              <div class="w-full max-w-full px-3 md:w-7/12 mt-4 md:mt-0">
                <label class="mb-2 ml-1 block text-xs font-bold uppercase text-slate-700">Deskripsi (Opsional)</label>
                <input type="text" name="deskripsi"
                  value="{{ old('deskripsi', $data->deskripsi ?? '') }}"
                  class="focus:shadow-soft-primary-outline text-sm leading-5.6 ease-soft block w-full appearance-none rounded-lg border border-solid border-gray-300 bg-white bg-clip-padding px-3 py-2 font-normal text-gray-700 transition-all focus:border-fuchsia-300 focus:outline-none"
                  placeholder="Penjelasan singkat mengenai kategori ini">
              </div>

            </div>

            <div class="mt-6 flex gap-2">
              <button type="submit"
                class="inline-block rounded-lg bg-gradient-to-tl from-purple-700 to-pink-500 px-6 py-3 text-xs font-bold uppercase text-white shadow-soft-md transition-all">
                {{ isset($data) ? 'Update Kategori' : 'Simpan Kategori' }}
              </button>

              @if(isset($data))
                <a href="{{ route('kategori-produk.index') }}"
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

  {{-- CARD 2: TABEL --}}
  <div class="flex flex-wrap -mx-3">
    <div class="flex-none w-full max-w-full px-3">
      <div class="relative flex flex-col min-w-0 mb-6 break-words bg-white border-0 shadow-soft-xl rounded-2xl bg-clip-border">
        <div class="p-6 pb-0 mb-0 bg-white rounded-t-2xl">
          <h6>Daftar Kategori Produk</h6>
        </div>

        <div class="flex-auto px-0 pt-0 pb-2">
          <div style="overflow-x: auto;" class="p-0">
            <table class="items-center w-full mb-0 align-top border-gray-200 text-slate-500">
              <thead class="align-bottom">
                <tr>
                  <th class="px-6 py-3 text-left text-xxs font-bold uppercase text-slate-400 opacity-70 border-b border-gray-200">
                    Nama Kategori
                  </th>
                  <th class="px-6 py-3 text-left text-xxs font-bold uppercase text-slate-400 opacity-70 border-b border-gray-200">
                    Deskripsi
                  </th>
                  <th class="px-6 py-3 text-center text-xxs font-bold uppercase text-slate-400 opacity-70 border-b border-gray-200">
                    Aksi
                  </th>
                </tr>
              </thead>

              <tbody>
                @forelse($kategori as $item)
                  <tr>
                    {{-- NAMA & PENOMORAN --}}
                    <td class="p-2 align-middle bg-transparent border-b whitespace-nowrap shadow-transparent">
                      <div class="flex items-center px-4 py-2">
                        
                        {{-- KOTAK NOMOR URUT --}}
                        <div class="mr-4 flex shrink-0 h-9 w-9 items-center justify-center rounded-xl bg-gradient-to-tl from-purple-700 to-pink-500 text-xs font-bold text-white">
                          {{ $kategori->firstItem() + $loop->index }}
                        </div>
                        
                        <div class="flex flex-col justify-center">
                          <h6 class="mb-0 text-sm leading-normal text-slate-700">{{ $item->nama_kategori }}</h6>
                        </div>
                        
                      </div>
                    </td>

                    {{-- DESKRIPSI --}}
                    <td class="p-2 align-middle bg-transparent border-b shadow-transparent">
                      <div class="px-4 max-w-[520px]">
                        <span class="block text-sm text-slate-600 truncate" title="{{ $item->deskripsi ?? '-' }}">
                          {{ $item->deskripsi ?? '-' }}
                        </span>
                      </div>
                    </td>

                    {{-- AKSI --}}
                    <td class="p-2 text-center align-middle bg-transparent border-b whitespace-nowrap shadow-transparent">
                      <div class="flex items-center justify-center gap-2">
                        <a href="{{ route('kategori-produk.edit', $item->id) }}"
                           class="inline-block rounded-lg bg-gradient-to-tl from-blue-600 to-cyan-400 px-4 py-2 text-xs font-bold uppercase text-white shadow-soft-md transition-all">
                          Edit
                        </a>

                        <form method="POST" action="{{ route('kategori-produk.destroy', $item->id) }}"
                              onsubmit="return confirm('Yakin ingin menghapus kategori ini?')">
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
                    <td colspan="3" class="p-6 text-center text-sm text-slate-400">
                      Belum ada data kategori.
                    </td>
                  </tr>
                @endforelse
              </tbody>
            </table>
          </div>

          {{-- PAGINATION LINKS --}}
          <div class="p-4 border-t border-gray-200">
            {{ $kategori->links() }}
          </div>

        </div>
      </div>
    </div>
  </div>

</div>

{{-- SCRIPT UNTUK BUKA TUTUP FORM --}}
<script>
  function toggleForm() {
    const formContainer = document.getElementById('form-container');
    const btnToggle = document.getElementById('btn-toggle-form');

    if (formContainer.classList.contains('hidden')) {
      formContainer.classList.remove('hidden');
      formContainer.classList.add('block');
      btnToggle.innerHTML = 'Tutup Form';
    } else {
      formContainer.classList.add('hidden');
      formContainer.classList.remove('block');
      btnToggle.innerHTML = '+ Tambah Data';
    }
  }
</script>
@endsection
