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

  <div class="mb-6 grid grid-cols-1 gap-4 md:grid-cols-5">
    @foreach($categories as $key => $label)
      <div class="rounded-2xl bg-white p-5 shadow-soft-xl">
        <p class="mb-1 text-xs font-bold uppercase text-slate-400">{{ $label }}</p>
        <p class="mb-0 text-2xl font-bold text-slate-700">{{ $ringkasan[$key] ?? 0 }}</p>
        <p class="mb-0 text-xs text-slate-400">akun terdaftar</p>
      </div>
    @endforeach
  </div>

  <div class="mb-6 rounded-2xl border border-blue-100 bg-blue-50 px-5 py-4 text-sm text-blue-800">
    <p class="mb-1 font-bold">COA dilindungi sebagai sumber pencatatan keuangan.</p>
    <p class="mb-0">
      Akun dapat ditambahkan, tetapi tidak dapat diedit atau dihapus dari aplikasi agar identitas akun pada jurnal historis tetap konsisten.
      Saldo normal ditentukan otomatis berdasarkan kategori.
    </p>
  </div>

  <div class="mb-6 rounded-2xl bg-white shadow-soft-xl">
    <div class="flex flex-wrap items-center justify-between gap-3 p-6 pb-3">
      <div>
        <h6 class="mb-1">Chart of Accounts</h6>
        <p class="mb-0 text-sm text-slate-400">Master akun resmi yang digunakan jurnal dan laporan koperasi.</p>
      </div>
      <button type="button" onclick="toggleAkunForm()" id="btn-toggle-akun"
        class="rounded-lg bg-gradient-to-tl from-purple-700 to-pink-500 px-4 py-2 text-xs font-bold uppercase text-white shadow-soft-md">
        {{ $errors->any() ? 'Tutup Form' : '+ Tambah Akun' }}
      </button>
    </div>

    <div id="akun-form" class="border-t border-gray-100 p-6 {{ $errors->any() ? 'block' : 'hidden' }}">
      <form method="POST" action="{{ route('akun.store') }}">
        @csrf
        <div class="flex flex-wrap -mx-3">
          <div class="w-full px-3 md:w-3/12">
            <label class="mb-2 ml-1 block text-xs font-bold uppercase text-slate-700">Kode Akun</label>
            <input type="text" name="kode_akun" inputmode="numeric" value="{{ old('kode_akun') }}"
              placeholder="Contoh: 107"
              class="block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm text-gray-700 focus:border-fuchsia-300 focus:outline-none">
          </div>
          <div class="mt-4 w-full px-3 md:mt-0 md:w-4/12">
            <label class="mb-2 ml-1 block text-xs font-bold uppercase text-slate-700">Nama Akun</label>
            <input type="text" name="nama_akun" value="{{ old('nama_akun') }}"
              placeholder="Masukkan nama akun"
              class="block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm text-gray-700 focus:border-fuchsia-300 focus:outline-none">
          </div>
          <div class="mt-4 w-full px-3 md:mt-0 md:w-3/12">
            <label class="mb-2 ml-1 block text-xs font-bold uppercase text-slate-700">Kategori</label>
            <select name="kategori"
              class="block w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-700 focus:border-fuchsia-300 focus:outline-none">
              <option value="">Pilih kategori</option>
              @foreach($categories as $key => $label)
                <option value="{{ $key }}" {{ old('kategori') === $key ? 'selected' : '' }}>{{ $label }}</option>
              @endforeach
            </select>
          </div>
          <div class="mt-4 w-full px-3 md:w-2/12 md:self-end">
            <button type="submit"
              class="w-full rounded-lg bg-gradient-to-tl from-purple-700 to-pink-500 px-4 py-2.5 text-xs font-bold uppercase text-white shadow-soft-md">
              Simpan Akun
            </button>
          </div>
          <div class="mt-4 w-full px-3">
            <label class="mb-2 ml-1 block text-xs font-bold uppercase text-slate-700">Keterangan</label>
            <textarea name="keterangan" rows="2" placeholder="Jelaskan fungsi akun agar mudah ditelusuri"
              class="block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm text-gray-700 focus:border-fuchsia-300 focus:outline-none">{{ old('keterangan') }}</textarea>
          </div>
        </div>
      </form>
    </div>

    <div class="border-t border-gray-100 p-6">
      <form method="GET" action="{{ route('akun.index') }}" class="flex flex-wrap items-end -mx-3">
        <div class="w-full px-3 md:w-5/12">
          <label class="mb-2 ml-1 block text-xs font-bold uppercase text-slate-700">Cari Akun</label>
          <input type="search" name="search" value="{{ $search }}" placeholder="Kode atau nama akun"
            class="block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm text-gray-700 focus:border-fuchsia-300 focus:outline-none">
        </div>
        <div class="mt-4 w-full px-3 md:mt-0 md:w-4/12">
          <label class="mb-2 ml-1 block text-xs font-bold uppercase text-slate-700">Kategori</label>
          <select name="kategori"
            class="block w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-700 focus:border-fuchsia-300 focus:outline-none">
            <option value="">Semua kategori</option>
            @foreach($categories as $key => $label)
              <option value="{{ $key }}" {{ $kategori === $key ? 'selected' : '' }}>{{ $label }}</option>
            @endforeach
          </select>
        </div>
        <div class="mt-4 flex w-full gap-2 px-3 md:mt-0 md:w-3/12">
          <button type="submit" class="flex-1 rounded-lg bg-slate-700 px-4 py-2 text-xs font-bold uppercase text-white">Filter</button>
          <a href="{{ route('akun.index') }}" class="rounded-lg bg-slate-200 px-4 py-2 text-xs font-bold uppercase text-slate-700">Reset</a>
        </div>
      </form>
    </div>

    <div class="overflow-x-auto border-t border-gray-100">
      <table class="w-full text-slate-500">
        <thead>
          <tr>
            <th class="px-6 py-3 text-left text-xxs font-bold uppercase text-slate-400">Kode</th>
            <th class="px-6 py-3 text-left text-xxs font-bold uppercase text-slate-400">Nama Akun</th>
            <th class="px-6 py-3 text-center text-xxs font-bold uppercase text-slate-400">Kategori</th>
            <th class="px-6 py-3 text-center text-xxs font-bold uppercase text-slate-400">Saldo Normal</th>
            <th class="px-6 py-3 text-center text-xxs font-bold uppercase text-slate-400">Sumber</th>
            <th class="px-6 py-3 text-left text-xxs font-bold uppercase text-slate-400">Keterangan</th>
          </tr>
        </thead>
        <tbody>
          @forelse($akun as $item)
            <tr>
              <td class="border-t border-gray-100 px-6 py-4 text-sm font-bold text-slate-700">{{ $item->kode_akun }}</td>
              <td class="border-t border-gray-100 px-6 py-4 text-sm font-semibold text-slate-700">{{ $item->nama_akun }}</td>
              <td class="border-t border-gray-100 px-6 py-4 text-center">
                <span class="rounded-lg bg-slate-100 px-3 py-1 text-xs font-bold uppercase text-slate-600">{{ $item->kategori_label }}</span>
              </td>
              <td class="border-t border-gray-100 px-6 py-4 text-center text-xs font-bold uppercase text-slate-500">{{ $item->posisi_saldo }}</td>
              <td class="border-t border-gray-100 px-6 py-4 text-center">
                @if($item->is_sistem)
                  <span class="rounded-lg bg-blue-100 px-3 py-1 text-xs font-bold uppercase text-blue-700">Sistem</span>
                @else
                  <span class="rounded-lg bg-green-100 px-3 py-1 text-xs font-bold uppercase text-green-700">Tambahan</span>
                @endif
              </td>
              <td class="border-t border-gray-100 px-6 py-4 text-xs text-slate-500">{{ $item->keterangan ?: '-' }}</td>
            </tr>
          @empty
            <tr>
              <td colspan="6" class="border-t border-gray-100 p-8 text-center text-sm text-slate-400">Belum ada akun yang sesuai dengan filter.</td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>

    <div class="border-t border-gray-100 p-4">
      {{ $akun->links() }}
    </div>
  </div>
</div>

<script>
  function toggleAkunForm() {
    const form = document.getElementById('akun-form');
    const button = document.getElementById('btn-toggle-akun');
    form.classList.toggle('hidden');
    button.textContent = form.classList.contains('hidden') ? '+ Tambah Akun' : 'Tutup Form';
  }
</script>
@endsection
