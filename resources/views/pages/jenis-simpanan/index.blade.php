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

  <div class="flex flex-wrap -mx-3">
    <div class="flex-none w-full max-w-full px-3">
      <div class="relative flex flex-col min-w-0 mb-6 break-words bg-white border-0 shadow-soft-xl rounded-2xl bg-clip-border">
        <div class="p-6 pb-0 mb-0 bg-white rounded-t-2xl flex justify-between items-center">
          <div>
            <h6>{{ isset($data) ? 'Edit Jenis Simpanan' : 'Tambah Jenis Simpanan' }}</h6>
            <p class="text-sm text-slate-400">
              Isi master data jenis simpanan untuk kebutuhan operasional koperasi
            </p>
          </div>

          @if(!isset($data))
            <button type="button" onclick="toggleForm()" id="btn-toggle-form"
              class="inline-block rounded-lg bg-gradient-to-tl from-slate-600 to-slate-300 px-4 py-2 text-xs font-bold uppercase text-white shadow-soft-md transition-all hover:scale-105">
              {{ $errors->any() ? 'Tutup Form' : '+ Tambah Data' }}
            </button>
          @endif
        </div>

        <div id="form-container" class="flex-auto p-6 transition-all duration-300 {{ (isset($data) || $errors->any()) ? 'block' : 'hidden' }}">
          <form action="{{ isset($data) ? route('jenis-simpanan.update', $data->id) : route('jenis-simpanan.store') }}" method="POST">
            @csrf
            @if(isset($data))
              @method('PUT')
            @endif

            <div class="flex flex-wrap -mx-3">
              <div class="w-full max-w-full px-3 md:w-6/12">
                <label class="mb-2 ml-1 block text-xs font-bold uppercase text-slate-700">
                  Nama Jenis Simpanan
                </label>
                <input type="text" name="nama_jenis"
                  value="{{ old('nama_jenis', $data->nama_jenis ?? '') }}"
                  class="focus:shadow-soft-primary-outline text-sm leading-5.6 ease-soft block w-full appearance-none rounded-lg border border-solid border-gray-300 bg-white bg-clip-padding px-3 py-2 font-normal text-gray-700 transition-all focus:border-fuchsia-300 focus:outline-none"
                  placeholder="Masukkan nama jenis simpanan">
              </div>

              <div class="w-full max-w-full px-3 md:w-6/12">
                <label class="mb-2 ml-1 block text-xs font-bold uppercase text-slate-700">
                  Status Simpanan
                </label>
                @php
                  $wajibVal = old('wajib', isset($data) ? (int) $data->wajib : 0);
                @endphp
                <select name="wajib"
                  class="focus:shadow-soft-primary-outline text-sm leading-5.6 ease-soft block w-full rounded-lg border border-solid border-gray-300 bg-white px-3 py-2 text-gray-700 focus:border-fuchsia-300 focus:outline-none">
                  <option value="0" {{ (string) $wajibVal === '0' ? 'selected' : '' }}>Sukarela</option>
                  <option value="1" {{ (string) $wajibVal === '1' ? 'selected' : '' }}>Wajib</option>
                </select>
              </div>

              <div class="w-full max-w-full px-3 mt-4 md:w-6/12">
                <label class="mb-2 ml-1 block text-xs font-bold uppercase text-slate-700">
                  Akun COA
                </label>
                <select name="akun_id"
                  class="focus:shadow-soft-primary-outline text-sm leading-5.6 ease-soft block w-full rounded-lg border border-solid border-gray-300 bg-white px-3 py-2 text-gray-700 focus:border-fuchsia-300 focus:outline-none">
                  <option value="">Pilih akun pencatatan</option>
                  @foreach($akunSimpanan as $akunItem)
                    <option value="{{ $akunItem->id }}" {{ (string) old('akun_id', $data->akun_id ?? '') === (string) $akunItem->id ? 'selected' : '' }}>
                      {{ $akunItem->kode_akun }} - {{ $akunItem->nama_akun }} ({{ $akunItem->kategori_label }})
                    </option>
                  @endforeach
                </select>
                <p class="mt-1 text-xs text-slate-400">Simpanan pokok/wajib menggunakan ekuitas; simpanan yang dapat ditarik menggunakan kewajiban.</p>
              </div>

              <div class="w-full max-w-full px-3 mt-4 md:w-6/12">
                <label class="mb-2 ml-1 block text-xs font-bold uppercase text-slate-700">
                  Nominal Default
                </label>
                <input type="number" name="nominal_default" step="0.01" min="0"
                  value="{{ old('nominal_default', $data->nominal_default ?? '') }}"
                  class="focus:shadow-soft-primary-outline text-sm leading-5.6 ease-soft block w-full rounded-lg border border-solid border-gray-300 bg-white px-3 py-2 text-gray-700 focus:border-fuchsia-300 focus:outline-none"
                  placeholder="0">
              </div>

              <div class="w-full max-w-full px-3 mt-4 md:w-6/12">
                <label class="mb-2 ml-1 block text-xs font-bold uppercase text-slate-700">
                  Keterangan
                </label>
                <textarea name="keterangan" rows="3"
                  class="focus:shadow-soft-primary-outline text-sm leading-5.6 ease-soft block w-full rounded-lg border border-solid border-gray-300 bg-white px-3 py-2 text-gray-700 transition-all focus:border-fuchsia-300 focus:outline-none"
                  placeholder="Tambahkan keterangan bila diperlukan">{{ old('keterangan', $data->keterangan ?? '') }}</textarea>
              </div>
            </div>

            <div class="mt-6 flex gap-2">
              <button type="submit"
                class="inline-block rounded-lg bg-gradient-to-tl from-purple-700 to-pink-500 px-6 py-3 text-xs font-bold uppercase text-white shadow-soft-md transition-all">
                {{ isset($data) ? 'Update Jenis' : 'Simpan Jenis' }}
              </button>

              @if(isset($data))
                <a href="{{ route('jenis-simpanan.index') }}"
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

  <div class="flex flex-wrap -mx-3">
    <div class="flex-none w-full max-w-full px-3">
      <div class="relative flex flex-col min-w-0 mb-6 break-words bg-white border-0 shadow-soft-xl rounded-2xl bg-clip-border">
        <div class="p-6 pb-0 mb-0 bg-white rounded-t-2xl">
          <h6>Data Jenis Simpanan</h6>
          <p class="text-sm text-slate-400">Daftar master data jenis simpanan koperasi</p>
        </div>

        <div class="flex-auto px-0 pt-0 pb-2">
          <div class="p-0 overflow-x-auto">
            <table class="items-center w-full mb-0 align-top border-gray-200 text-slate-500">
              <thead class="align-bottom">
                <tr>
                  <th class="px-6 py-3 text-left text-xxs font-bold uppercase text-slate-400 opacity-70">Jenis Simpanan</th>
                  <th class="px-6 py-3 text-center text-xxs font-bold uppercase text-slate-400 opacity-70">Status</th>
                  <th class="px-6 py-3 text-left text-xxs font-bold uppercase text-slate-400 opacity-70">Akun COA</th>
                  <th class="px-6 py-3 text-left text-xxs font-bold uppercase text-slate-400 opacity-70">Keterangan</th>
                  <th class="px-6 py-3 text-center text-xxs font-bold uppercase text-slate-400 opacity-70">Nominal Default</th>
                  <th class="px-6 py-3 text-center text-xxs font-bold uppercase text-slate-400 opacity-70">Aksi</th>
                </tr>
              </thead>
              <tbody>
                @forelse($jenisSimpanan as $item)
                  <tr>
                    <td class="p-2 align-middle bg-transparent border-b whitespace-nowrap shadow-transparent">
                      <div class="flex items-center px-4 py-2">
                        <div class="mr-4 flex shrink-0 h-9 w-9 items-center justify-center rounded-xl bg-gradient-to-tl from-purple-700 to-pink-500 text-xs font-bold text-white">
                          {{ $jenisSimpanan->firstItem() + $loop->index }}
                        </div>

                        <div class="flex flex-col justify-center">
                          <h6 class="mb-0 text-sm leading-normal">{{ $item->nama_jenis }}</h6>
                          <p class="mb-0 text-xs leading-tight text-slate-400">
                            Dibuat: {{ $item->created_at ? $item->created_at->format('d/m/Y') : '-' }}
                          </p>
                        </div>
                      </div>
                    </td>

                    <td class="p-2 text-center align-middle bg-transparent border-b whitespace-nowrap shadow-transparent">
                      @if($item->wajib)
                        <span class="inline-block rounded-1.8 bg-gradient-to-tl from-green-600 to-lime-400 px-2.5 py-1.4 text-xs font-bold uppercase text-white">
                          Wajib
                        </span>
                      @else
                        <span class="inline-block rounded-1.8 bg-gradient-to-tl from-slate-600 to-slate-300 px-2.5 py-1.4 text-xs font-bold uppercase text-white">
                          Sukarela
                        </span>
                      @endif
                    </td>

                    <td class="p-2 align-middle bg-transparent border-b shadow-transparent">
                      @if($item->akun)
                        <p class="mb-0 text-xs font-bold text-slate-600">{{ $item->akun->kode_akun }} - {{ $item->akun->nama_akun }}</p>
                        <p class="mb-0 text-xs text-slate-400">{{ $item->akun->kategori_label }}</p>
                      @else
                        <span class="text-xs font-bold text-red-500">Belum dipetakan</span>
                      @endif
                    </td>

                    <td class="p-2 align-middle bg-transparent border-b shadow-transparent">
                      <p class="mb-0 text-xs font-semibold leading-tight text-slate-500">
                        {{ $item->keterangan ?: '-' }}
                      </p>
                    </td>

                    <td class="p-2 text-center align-middle bg-transparent border-b whitespace-nowrap shadow-transparent">
                      <span class="text-xs font-semibold leading-tight text-slate-400">
                        {{ $item->nominal_default !== null ? 'Rp ' . number_format($item->nominal_default, 0, ',', '.') : '-' }}
                      </span>
                    </td>

                    <td class="p-2 text-center align-middle bg-transparent border-b whitespace-nowrap shadow-transparent">
                      <div class="flex items-center justify-center gap-2 px-4">
                        <a href="{{ route('jenis-simpanan.edit', $item->id) }}"
                          class="inline-block rounded-lg bg-gradient-to-tl from-blue-600 to-cyan-400 px-4 py-2 text-xs font-bold uppercase text-white shadow-soft-md transition-all hover:scale-105">
                          Edit
                        </a>

                        <form action="{{ route('jenis-simpanan.destroy', $item->id) }}" method="POST"
                          onsubmit="return confirm('Yakin ingin menghapus jenis simpanan ini?')">
                          @csrf
                          @method('DELETE')
                          <button type="submit"
                            class="inline-block rounded-lg bg-gradient-to-tl from-red-600 to-rose-400 px-4 py-2 text-xs font-bold uppercase text-white shadow-soft-md transition-all hover:scale-105">
                            Hapus
                          </button>
                        </form>
                      </div>
                    </td>
                  </tr>
                @empty
                  <tr>
                    <td colspan="6" class="p-4 text-center text-sm text-slate-400">
                      Belum ada data jenis simpanan.
                    </td>
                  </tr>
                @endforelse
              </tbody>
            </table>
          </div>

          <div class="p-4 border-t border-gray-200">
            {{ $jenisSimpanan->links() }}
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

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
