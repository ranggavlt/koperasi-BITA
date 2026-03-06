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
        <div class="p-6 pb-0 mb-0 bg-white rounded-t-2xl">
          <h6>{{ isset($data) ? 'Edit Produk' : 'Tambah Produk' }}</h6>
          <p class="text-sm text-slate-400">
            Isi data produk untuk kebutuhan POS koperasi
          </p>
        </div>

        <div class="flex-auto p-6">
          <form action="{{ isset($data) ? route('produk.update', $data->id) : route('produk.store') }}" method="POST">
            @csrf
            @if(isset($data))
              @method('PUT')
            @endif

            <div class="flex flex-wrap -mx-3">
              <div class="w-full max-w-full px-3 md:w-6/12">
                <label class="mb-2 ml-1 block text-xs font-bold uppercase text-slate-700">
                  Nama Produk
                </label>
                <input type="text" name="nama_produk"
                  value="{{ old('nama_produk', $data->nama_produk ?? '') }}"
                  class="focus:shadow-soft-primary-outline text-sm leading-5.6 ease-soft block w-full appearance-none rounded-lg border border-solid border-gray-300 bg-white bg-clip-padding px-3 py-2 font-normal text-gray-700 transition-all focus:border-fuchsia-300 focus:outline-none"
                  placeholder="Masukkan nama produk">
              </div>

              <div class="w-full max-w-full px-3 md:w-6/12">
                <label class="mb-2 ml-1 block text-xs font-bold uppercase text-slate-700">
                  Kategori
                </label>
                <select name="kategori_id"
                  class="focus:shadow-soft-primary-outline text-sm leading-5.6 ease-soft block w-full rounded-lg border border-solid border-gray-300 bg-white px-3 py-2 text-gray-700 focus:border-fuchsia-300 focus:outline-none">
                  <option value="">-- Pilih Kategori --</option>
                  @foreach($kategori as $item)
                    <option value="{{ $item->id }}"
                      {{ old('kategori_id', $data->kategori_id ?? '') == $item->id ? 'selected' : '' }}>
                      {{ $item->nama_kategori }}
                    </option>
                  @endforeach
                </select>
              </div>

              <div class="w-full max-w-full px-3 mt-4 md:w-4/12">
                <label class="mb-2 ml-1 block text-xs font-bold uppercase text-slate-700">
                  Harga Beli
                </label>
                <input type="number" name="harga_beli"
                  value="{{ old('harga_beli', $data->harga_beli ?? 0) }}"
                  class="focus:shadow-soft-primary-outline text-sm leading-5.6 ease-soft block w-full rounded-lg border border-solid border-gray-300 bg-white px-3 py-2 text-gray-700 focus:border-fuchsia-300 focus:outline-none"
                  placeholder="0">
              </div>

              <div class="w-full max-w-full px-3 mt-4 md:w-4/12">
                <label class="mb-2 ml-1 block text-xs font-bold uppercase text-slate-700">
                  Harga Jual
                </label>
                <input type="number" name="harga_jual"
                  value="{{ old('harga_jual', $data->harga_jual ?? '') }}"
                  class="focus:shadow-soft-primary-outline text-sm leading-5.6 ease-soft block w-full rounded-lg border border-solid border-gray-300 bg-white px-3 py-2 text-gray-700 focus:border-fuchsia-300 focus:outline-none"
                  placeholder="0">
              </div>

              <div class="w-full max-w-full px-3 mt-4 md:w-4/12">
                <label class="mb-2 ml-1 block text-xs font-bold uppercase text-slate-700">
                  Stok
                </label>
                <input type="number" name="stok"
                  value="{{ old('stok', $data->stok ?? 0) }}"
                  class="focus:shadow-soft-primary-outline text-sm leading-5.6 ease-soft block w-full rounded-lg border border-solid border-gray-300 bg-white px-3 py-2 text-gray-700 focus:border-fuchsia-300 focus:outline-none"
                  placeholder="0">
              </div>
            </div>

            <div class="mt-6 flex gap-2">
              <button type="submit"
                class="inline-block rounded-lg bg-gradient-to-tl from-purple-700 to-pink-500 px-6 py-3 text-xs font-bold uppercase text-white shadow-soft-md transition-all">
                {{ isset($data) ? 'Update Produk' : 'Simpan Produk' }}
              </button>

              @if(isset($data))
                <a href="{{ route('produk.index') }}"
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
          <h6>Data Produk</h6>
          <p class="text-sm text-slate-400">Daftar master data produk POS</p>
        </div>

        <div class="flex-auto px-0 pt-0 pb-2">
          <div class="p-0 overflow-x-auto">
            <table class="items-center w-full mb-0 align-top border-gray-200 text-slate-500">
              <thead class="align-bottom">
                <tr>
                  <th class="px-6 py-3 text-left text-xxs font-bold uppercase text-slate-400 opacity-70">Produk</th>
                  <th class="px-6 py-3 text-left text-xxs font-bold uppercase text-slate-400 opacity-70">Kategori</th>
                  <th class="px-6 py-3 text-center text-xxs font-bold uppercase text-slate-400 opacity-70">Harga Beli</th>
                  <th class="px-6 py-3 text-center text-xxs font-bold uppercase text-slate-400 opacity-70">Harga Jual</th>
                  <th class="px-6 py-3 text-center text-xxs font-bold uppercase text-slate-400 opacity-70">Stok</th>
                  <th class="px-6 py-3 text-center text-xxs font-bold uppercase text-slate-400 opacity-70">Aksi</th>
                </tr>
              </thead>
              <tbody>
                @forelse($produk as $item)
                  <tr>
                    <td class="p-2 align-middle bg-transparent border-b whitespace-nowrap shadow-transparent">
                      <div class="flex px-2 py-1">
                        <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-gradient-to-tl from-purple-700 to-pink-500 text-xs font-bold text-white">
                          {{ strtoupper(substr($item->nama_produk, 0, 1)) }}
                        </div>
                        <div class="flex flex-col justify-center ml-3">
                          <h6 class="mb-0 text-sm leading-normal">{{ $item->nama_produk }}</h6>
                          <p class="mb-0 text-xs leading-tight text-slate-400">
                            Dibuat: {{ $item->created_at ? $item->created_at->format('d/m/Y') : '-' }}
                          </p>
                        </div>
                      </div>
                    </td>

                    <td class="p-2 align-middle bg-transparent border-b whitespace-nowrap shadow-transparent">
                      <p class="mb-0 text-xs font-semibold leading-tight">
                        {{ $item->kategori->nama_kategori ?? '-' }}
                      </p>
                    </td>

                    <td class="p-2 text-center align-middle bg-transparent border-b whitespace-nowrap shadow-transparent">
                      <span class="text-xs font-semibold leading-tight text-slate-400">
                        Rp {{ number_format($item->harga_beli, 0, ',', '.') }}
                      </span>
                    </td>

                    <td class="p-2 text-center align-middle bg-transparent border-b whitespace-nowrap shadow-transparent">
                      <span class="text-xs font-semibold leading-tight text-slate-400">
                        Rp {{ number_format($item->harga_jual, 0, ',', '.') }}
                      </span>
                    </td>

                    <td class="p-2 text-center align-middle bg-transparent border-b whitespace-nowrap shadow-transparent">
                      @if($item->stok > 10)
                        <span class="inline-block rounded-1.8 bg-gradient-to-tl from-green-600 to-lime-400 px-2.5 py-1.4 text-xs font-bold uppercase text-white">
                          {{ $item->stok }}
                        </span>
                      @elseif($item->stok > 0)
                        <span class="inline-block rounded-1.8 bg-gradient-to-tl from-yellow-500 to-orange-400 px-2.5 py-1.4 text-xs font-bold uppercase text-white">
                          {{ $item->stok }}
                        </span>
                      @else
                        <span class="inline-block rounded-1.8 bg-gradient-to-tl from-red-600 to-rose-400 px-2.5 py-1.4 text-xs font-bold uppercase text-white">
                          Habis
                        </span>
                      @endif
                    </td>

                    <td class="p-2 text-center align-middle bg-transparent border-b whitespace-nowrap shadow-transparent">
                      <div class="flex items-center justify-center gap-2">
                        <a href="{{ route('produk.edit', $item->id) }}"
                          class="text-xs font-semibold leading-tight text-blue-500">
                          Edit
                        </a>

                        <form action="{{ route('produk.destroy', $item->id) }}" method="POST"
                          onsubmit="return confirm('Yakin ingin menghapus produk ini?')">
                          @csrf
                          @method('DELETE')
                          <button type="submit" class="text-xs font-semibold leading-tight text-red-500">
                            Hapus
                          </button>
                        </form>
                      </div>
                    </td>
                  </tr>
                @empty
                  <tr>
                    <td colspan="6" class="p-4 text-center text-sm text-slate-400">
                      Belum ada data produk.
                    </td>
                  </tr>
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