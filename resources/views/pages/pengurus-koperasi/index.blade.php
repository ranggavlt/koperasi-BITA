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
      <div class="relative flex flex-col min-w-0 mb-6 break-words rounded-2xl border-0 bg-white bg-clip-border shadow-soft-xl">
        <div class="mb-0 flex items-center justify-between rounded-t-2xl bg-white p-6 pb-0">
          <div>
            <h6>{{ isset($data) ? 'Edit Pengurus Koperasi' : 'Tambah Pengurus Koperasi' }}</h6>
            <p class="text-sm text-slate-400">Kelola data pengurus aktif koperasi perusahaan</p>
          </div>

          @if (! isset($data))
            <button type="button" onclick="toggleForm()" id="btn-toggle-form"
              class="inline-block rounded-lg bg-gradient-to-tl from-slate-600 to-slate-300 px-4 py-2 text-xs font-bold uppercase text-white shadow-soft-md transition-all hover:scale-105">
              {{ $errors->any() ? 'Tutup Form' : '+ Tambah Data' }}
            </button>
          @endif
        </div>

        <div id="form-container" class="flex-auto p-6 transition-all duration-300 {{ (isset($data) || $errors->any()) ? 'block' : 'hidden' }}">
          <form action="{{ isset($data) ? route('pengurus-koperasi.update', $data->id) : route('pengurus-koperasi.store') }}" method="POST">
            @csrf
            @if (isset($data))
              @method('PUT')
            @endif

            <div class="flex flex-wrap -mx-3">
              <div class="w-full max-w-full px-3 md:w-6/12">
                <label class="mb-2 ml-1 block text-xs font-bold uppercase text-slate-700">Nama Lengkap</label>
                <input type="text" name="nama"
                  value="{{ old('nama', $data->nama ?? '') }}"
                  class="focus:shadow-soft-primary-outline text-sm leading-5.6 ease-soft block w-full rounded-lg border border-solid border-gray-300 bg-white px-3 py-2 text-gray-700 transition-all focus:border-fuchsia-300 focus:outline-none"
                  placeholder="Nama pengurus koperasi">
              </div>

              <div class="mt-4 w-full max-w-full px-3 md:mt-0 md:w-6/12">
                <label class="mb-2 ml-1 block text-xs font-bold uppercase text-slate-700">Jabatan</label>
                <input type="text" name="jabatan"
                  value="{{ old('jabatan', $data->jabatan ?? '') }}"
                  class="focus:shadow-soft-primary-outline text-sm leading-5.6 ease-soft block w-full rounded-lg border border-solid border-gray-300 bg-white px-3 py-2 text-gray-700 transition-all focus:border-fuchsia-300 focus:outline-none"
                  placeholder="Contoh: Ketua, Bendahara, Sekretaris">
              </div>

              <div class="mt-4 w-full max-w-full px-3 md:w-6/12">
                <label class="mb-2 ml-1 block text-xs font-bold uppercase text-slate-700">Email</label>
                <input type="email" name="email"
                  value="{{ old('email', $data->email ?? '') }}"
                  class="focus:shadow-soft-primary-outline text-sm leading-5.6 ease-soft block w-full rounded-lg border border-solid border-gray-300 bg-white px-3 py-2 text-gray-700 transition-all focus:border-fuchsia-300 focus:outline-none"
                  placeholder="email@example.com">
              </div>

              <div class="mt-4 w-full max-w-full px-3 md:w-6/12">
                <label class="mb-2 ml-1 block text-xs font-bold uppercase text-slate-700">Telepon / WhatsApp</label>
                <input type="text" name="telepon"
                  value="{{ old('telepon', $data->telepon ?? '') }}"
                  class="focus:shadow-soft-primary-outline text-sm leading-5.6 ease-soft block w-full rounded-lg border border-solid border-gray-300 bg-white px-3 py-2 text-gray-700 transition-all focus:border-fuchsia-300 focus:outline-none"
                  placeholder="08xxxxxxxxx">
              </div>
            </div>

            <div class="mt-6 flex gap-2">
              <button type="submit"
                class="inline-block rounded-lg bg-gradient-to-tl from-purple-700 to-pink-500 px-6 py-3 text-xs font-bold uppercase text-white shadow-soft-md transition-all">
                {{ isset($data) ? 'Update Pengurus' : 'Simpan Pengurus' }}
              </button>

              @if (isset($data))
                <a href="{{ route('pengurus-koperasi.index') }}"
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
      <div class="relative flex flex-col min-w-0 mb-6 break-words rounded-2xl border-0 bg-white bg-clip-border shadow-soft-xl">
        <div class="mb-0 rounded-t-2xl bg-white p-6 pb-0">
          <h6>Daftar Pengurus Koperasi</h6>
          <p class="text-sm text-slate-400">Data pengelola koperasi terpisah dari data karyawan anggota</p>
        </div>

        <div class="flex-auto px-0 pt-0 pb-2">
          <div class="overflow-x-auto p-0">
            <table class="mb-0 w-full items-center align-top text-slate-500">
              <thead class="align-bottom">
                <tr>
                  <th class="border-b border-gray-200 px-6 py-3 text-left text-xxs font-bold uppercase text-slate-400 opacity-70">Nama</th>
                  <th class="border-b border-gray-200 px-6 py-3 text-left text-xxs font-bold uppercase text-slate-400 opacity-70">Kontak</th>
                  <th class="border-b border-gray-200 px-6 py-3 text-center text-xxs font-bold uppercase text-slate-400 opacity-70">Jabatan</th>
                  <th class="border-b border-gray-200 px-6 py-3 text-center text-xxs font-bold uppercase text-slate-400 opacity-70">Aksi</th>
                </tr>
              </thead>
              <tbody>
                @forelse($pengurusKoperasi as $item)
                  <tr>
                    <td class="whitespace-nowrap border-b p-2 align-middle shadow-transparent">
                      <div class="flex items-center px-4 py-2">
                        <div class="mr-4 flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-gradient-to-tl from-purple-700 to-pink-500 text-xs font-bold text-white">
                          {{ $pengurusKoperasi->firstItem() + $loop->index }}
                        </div>
                        <div class="flex flex-col justify-center">
                          <h6 class="mb-0 text-sm leading-normal text-slate-700">{{ $item->nama }}</h6>
                        </div>
                      </div>
                    </td>
                    <td class="whitespace-nowrap border-b p-2 align-middle shadow-transparent">
                      <div class="px-4">
                        <p class="mb-0 text-sm font-semibold leading-tight text-slate-600">{{ $item->email ?: '-' }}</p>
                        <p class="mb-0 text-xs leading-tight text-slate-400">{{ $item->telepon ?: '-' }}</p>
                      </div>
                    </td>
                    <td class="whitespace-nowrap border-b p-2 text-center align-middle shadow-transparent">
                      <span class="text-xs font-semibold leading-tight text-slate-500">{{ $item->jabatan }}</span>
                    </td>
                    <td class="whitespace-nowrap border-b p-2 text-center align-middle shadow-transparent">
                      <div class="flex items-center justify-center gap-2">
                        <a href="{{ route('pengurus-koperasi.edit', $item->id) }}"
                          class="inline-block rounded-lg bg-gradient-to-tl from-blue-600 to-cyan-400 px-4 py-2 text-xs font-bold uppercase text-white shadow-soft-md transition-all">
                          Edit
                        </a>

                        <form method="POST" action="{{ route('pengurus-koperasi.destroy', $item->id) }}"
                          onsubmit="return confirm('Yakin ingin menghapus data pengurus koperasi ini?')">
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
                      Belum ada data pengurus koperasi.
                    </td>
                  </tr>
                @endforelse
              </tbody>
            </table>
          </div>

          <div class="border-t border-gray-200 p-4">
            {{ $pengurusKoperasi->links() }}
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
