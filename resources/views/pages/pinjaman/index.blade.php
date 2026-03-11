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
            <h6>Tambah Transaksi Pinjaman Karyawan</h6>
            <p class="text-sm text-slate-400">
              Catat pinjaman karyawan anggota koperasi pada halaman yang sama
            </p>
          </div>

          <button type="button" onclick="toggleForm()" id="btn-toggle-form"
            class="inline-block rounded-lg bg-gradient-to-tl from-slate-600 to-slate-300 px-4 py-2 text-xs font-bold uppercase text-white shadow-soft-md transition-all hover:scale-105">
            {{ $errors->any() ? 'Tutup Form' : '+ Tambah Data' }}
          </button>
        </div>

        <div id="form-container" class="flex-auto p-6 transition-all duration-300 {{ $errors->any() ? 'block' : 'hidden' }}">
          <form action="{{ route('pinjaman.store') }}" method="POST">
            @csrf

            <div class="flex flex-wrap -mx-3">
              <div class="w-full max-w-full px-3 md:w-6/12">
                <label class="mb-2 ml-1 block text-xs font-bold uppercase text-slate-700">
                  Karyawan
                </label>
                <select name="karyawan_id"
                  class="focus:shadow-soft-primary-outline text-sm leading-5.6 ease-soft block w-full rounded-lg border border-solid border-gray-300 bg-white px-3 py-2 text-gray-700 focus:border-fuchsia-300 focus:outline-none">
                  <option value="">-- Pilih Karyawan --</option>
                  @foreach($karyawan as $item)
                    <option value="{{ $item->id }}" {{ old('karyawan_id') == $item->id ? 'selected' : '' }}>
                      {{ $item->nama }}
                    </option>
                  @endforeach
                </select>
              </div>

              <div class="w-full max-w-full px-3 md:w-3/12">
                <label class="mb-2 ml-1 block text-xs font-bold uppercase text-slate-700">
                  Tanggal Pinjaman
                </label>
                <input type="date" name="tanggal_pinjaman"
                  value="{{ old('tanggal_pinjaman', now()->format('Y-m-d')) }}"
                  class="focus:shadow-soft-primary-outline text-sm leading-5.6 ease-soft block w-full rounded-lg border border-solid border-gray-300 bg-white px-3 py-2 text-gray-700 focus:border-fuchsia-300 focus:outline-none">
              </div>

              <div class="w-full max-w-full px-3 md:w-3/12">
                <label class="mb-2 ml-1 block text-xs font-bold uppercase text-slate-700">
                  Status
                </label>
                <input type="text"
                  value="Aktif"
                  class="text-sm leading-5.6 ease-soft block w-full rounded-lg border border-solid border-gray-200 bg-gray-100 px-3 py-2 text-gray-500"
                  readonly>
              </div>

              <div class="w-full max-w-full px-3 mt-4 md:w-4/12">
                <label class="mb-2 ml-1 block text-xs font-bold uppercase text-slate-700">
                  Jumlah Pinjaman
                </label>
                <input type="number" name="jumlah_pinjaman" min="0" step="0.01"
                  value="{{ old('jumlah_pinjaman') }}"
                  class="focus:shadow-soft-primary-outline text-sm leading-5.6 ease-soft block w-full rounded-lg border border-solid border-gray-300 bg-white px-3 py-2 text-gray-700 focus:border-fuchsia-300 focus:outline-none"
                  placeholder="0">
              </div>

              <div class="w-full max-w-full px-3 mt-4 md:w-4/12">
                <label class="mb-2 ml-1 block text-xs font-bold uppercase text-slate-700">
                  Bunga (%)
                </label>
                <input type="number" name="bunga_persen" min="0" step="0.01"
                  value="{{ old('bunga_persen', 0) }}"
                  class="focus:shadow-soft-primary-outline text-sm leading-5.6 ease-soft block w-full rounded-lg border border-solid border-gray-300 bg-white px-3 py-2 text-gray-700 focus:border-fuchsia-300 focus:outline-none"
                  placeholder="0">
              </div>

              <div class="w-full max-w-full px-3 mt-4 md:w-4/12">
                <label class="mb-2 ml-1 block text-xs font-bold uppercase text-slate-700">
                  Tenor (Bulan)
                </label>
                <input type="number" name="tenor_bulan" min="1"
                  value="{{ old('tenor_bulan') }}"
                  class="focus:shadow-soft-primary-outline text-sm leading-5.6 ease-soft block w-full rounded-lg border border-solid border-gray-300 bg-white px-3 py-2 text-gray-700 focus:border-fuchsia-300 focus:outline-none"
                  placeholder="12">
              </div>

              <div class="w-full max-w-full px-3 mt-4">
                <label class="mb-2 ml-1 block text-xs font-bold uppercase text-slate-700">
                  Keterangan
                </label>
                <textarea name="keterangan" rows="3"
                  class="focus:shadow-soft-primary-outline text-sm leading-5.6 ease-soft block w-full rounded-lg border border-solid border-gray-300 bg-white px-3 py-2 text-gray-700 transition-all focus:border-fuchsia-300 focus:outline-none"
                  placeholder="Tambahkan keterangan bila diperlukan">{{ old('keterangan') }}</textarea>
              </div>
            </div>

            <div class="mt-6 flex gap-2">
              <button type="submit"
                class="inline-block rounded-lg bg-gradient-to-tl from-purple-700 to-pink-500 px-6 py-3 text-xs font-bold uppercase text-white shadow-soft-md transition-all">
                Simpan Pinjaman
              </button>
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
          <h6>Data Pinjaman</h6>
          <p class="text-sm text-slate-400">Daftar transaksi pinjaman karyawan anggota koperasi</p>
        </div>

        <div class="flex-auto px-0 pt-0 pb-2">
          <div class="p-0 overflow-x-auto">
            <table class="items-center w-full mb-0 align-top border-gray-200 text-slate-500">
              <thead class="align-bottom">
                <tr>
                  <th class="px-6 py-3 text-left text-xxs font-bold uppercase text-slate-400 opacity-70">Karyawan</th>
                  <th class="px-6 py-3 text-center text-xxs font-bold uppercase text-slate-400 opacity-70">Tanggal</th>
                  <th class="px-6 py-3 text-center text-xxs font-bold uppercase text-slate-400 opacity-70">Jumlah</th>
                  <th class="px-6 py-3 text-center text-xxs font-bold uppercase text-slate-400 opacity-70">Bunga</th>
                  <th class="px-6 py-3 text-center text-xxs font-bold uppercase text-slate-400 opacity-70">Tenor</th>
                  <th class="px-6 py-3 text-center text-xxs font-bold uppercase text-slate-400 opacity-70">Sisa Pinjaman</th>
                  <th class="px-6 py-3 text-center text-xxs font-bold uppercase text-slate-400 opacity-70">Status</th>
                  <th class="px-6 py-3 text-left text-xxs font-bold uppercase text-slate-400 opacity-70">Keterangan</th>
                  <th class="px-6 py-3 text-center text-xxs font-bold uppercase text-slate-400 opacity-70">Aksi</th>
                </tr>
              </thead>
              <tbody>
                @forelse($pinjaman as $item)
                  <tr>
                    <td class="p-2 align-middle bg-transparent border-b whitespace-nowrap shadow-transparent">
                      <div class="flex items-center px-4 py-2">
                        <div class="mr-4 flex shrink-0 h-9 w-9 items-center justify-center rounded-xl bg-gradient-to-tl from-purple-700 to-pink-500 text-xs font-bold text-white">
                          {{ $pinjaman->firstItem() + $loop->index }}
                        </div>

                        <div class="flex flex-col justify-center">
                          <h6 class="mb-0 text-sm leading-normal">{{ $item->karyawan->nama ?? '-' }}</h6>
                          <p class="mb-0 text-xs leading-tight text-slate-400">
                            Dibuat: {{ $item->created_at ? $item->created_at->format('d/m/Y') : '-' }}
                          </p>
                        </div>
                      </div>
                    </td>

                    <td class="p-2 text-center align-middle bg-transparent border-b whitespace-nowrap shadow-transparent">
                      <span class="text-xs font-semibold leading-tight text-slate-400">
                        {{ \Carbon\Carbon::parse($item->tanggal_pinjaman)->format('d/m/Y') }}
                      </span>
                    </td>

                    <td class="p-2 text-center align-middle bg-transparent border-b whitespace-nowrap shadow-transparent">
                      <span class="text-xs font-semibold leading-tight text-slate-400">
                        Rp {{ number_format($item->jumlah_pinjaman, 0, ',', '.') }}
                      </span>
                    </td>

                    <td class="p-2 text-center align-middle bg-transparent border-b whitespace-nowrap shadow-transparent">
                      <span class="inline-block rounded-1.8 bg-gradient-to-tl from-blue-600 to-cyan-400 px-2.5 py-1.4 text-xs font-bold uppercase text-white">
                        {{ number_format($item->bunga_persen ?? 0, 2, ',', '.') }}%
                      </span>
                    </td>

                    <td class="p-2 text-center align-middle bg-transparent border-b whitespace-nowrap shadow-transparent">
                      <span class="inline-block rounded-1.8 bg-gradient-to-tl from-green-600 to-lime-400 px-2.5 py-1.4 text-xs font-bold uppercase text-white">
                        {{ $item->tenor_bulan }} Bulan
                      </span>
                    </td>

                    <td class="p-2 text-center align-middle bg-transparent border-b whitespace-nowrap shadow-transparent">
                      <span class="text-xs font-semibold leading-tight text-slate-400">
                        Rp {{ number_format($item->sisa_pinjaman, 0, ',', '.') }}
                      </span>
                    </td>

                    <td class="p-2 text-center align-middle bg-transparent border-b whitespace-nowrap shadow-transparent">
                      @if($item->status === 'aktif')
                        <span class="inline-block rounded-1.8 bg-gradient-to-tl from-yellow-500 to-orange-400 px-2.5 py-1.4 text-xs font-bold uppercase text-white">
                          Aktif
                        </span>
                      @else
                        <span class="inline-block rounded-1.8 bg-gradient-to-tl from-slate-600 to-slate-300 px-2.5 py-1.4 text-xs font-bold uppercase text-white">
                          Lunas
                        </span>
                      @endif
                    </td>

                    <td class="p-2 align-middle bg-transparent border-b shadow-transparent">
                      <p class="mb-0 text-xs font-semibold leading-tight text-slate-500">
                        {{ $item->keterangan ?: '-' }}
                      </p>
                    </td>

                    <td class="p-2 text-center align-middle bg-transparent border-b whitespace-nowrap shadow-transparent">
                      <div class="flex items-center justify-center gap-2 px-4">
                        <form action="{{ route('pinjaman.destroy', $item->id) }}" method="POST"
                              onsubmit="return confirm('Yakin ingin menghapus data ini?')">
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
                    <td colspan="9" class="p-4 text-center text-sm text-slate-400">
                      Belum ada data pinjaman.
                    </td>
                  </tr>
                @endforelse
              </tbody>
            </table>
          </div>

          <div class="p-4 border-t border-gray-200">
            {{ $pinjaman->links() }}
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
