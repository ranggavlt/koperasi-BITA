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

  <div class="mb-6">
    <h2 class="text-xl font-bold text-slate-700">SHU Koperasi</h2>
    <p class="text-sm text-slate-400">Kelola periode SHU koperasi, lalu lanjutkan input transaksi pendapatan dan biaya dari halaman detail.</p>
  </div>

  <div class="flex flex-wrap -mx-3">
    <div class="flex-none w-full max-w-full px-3">
      <div class="relative mb-6 flex min-w-0 flex-col break-words rounded-2xl border-0 bg-white bg-clip-border shadow-soft-xl">
        <div class="mb-0 flex items-center justify-between rounded-t-2xl bg-white p-6 pb-0">
          <div>
            <h6>Tambah Periode SHU</h6>
            <p class="text-sm text-slate-400">Buat header periode SHU dulu, kemudian transaksi SHU dimasukkan dari halaman detail.</p>
          </div>

          <button type="button" onclick="toggleSection('shu-create-form', 'btn-toggle-shu-create')"
            id="btn-toggle-shu-create"
            class="inline-block rounded-lg bg-gradient-to-tl from-slate-600 to-slate-300 px-4 py-2 text-xs font-bold uppercase text-white shadow-soft-md transition-all hover:scale-105">
            {{ $errors->any() ? 'Tutup Form' : '+ Tambah Data' }}
          </button>
        </div>

        <div id="shu-create-form" class="flex-auto p-6 transition-all duration-300 {{ $errors->any() ? 'block' : 'hidden' }}">
          <form action="{{ route('shu-koperasi.store') }}" method="POST">
            @csrf

            <div class="flex flex-wrap -mx-3">
              <div class="w-full max-w-full px-3 md:w-6/12">
                <label class="mb-2 ml-1 block text-xs font-bold uppercase text-slate-700">Judul SHU</label>
                <input type="text" name="judul" value="{{ old('judul', 'SHU Tahun ' . now()->year) }}"
                  class="focus:shadow-soft-primary-outline block w-full appearance-none rounded-lg border border-solid border-gray-300 bg-white bg-clip-padding px-3 py-2 text-sm font-normal text-gray-700 transition-all focus:border-fuchsia-300 focus:outline-none"
                  placeholder="Contoh: SHU Tahun 2026">
              </div>

              <div class="mt-4 w-full max-w-full px-3 md:mt-0 md:w-3/12">
                <label class="mb-2 ml-1 block text-xs font-bold uppercase text-slate-700">Tanggal Mulai</label>
                <input type="date" name="tanggal_mulai" value="{{ old('tanggal_mulai', now()->startOfYear()->format('Y-m-d')) }}"
                  class="focus:shadow-soft-primary-outline block w-full rounded-lg border border-solid border-gray-300 bg-white px-3 py-2 text-sm text-gray-700 focus:border-fuchsia-300 focus:outline-none">
              </div>

              <div class="mt-4 w-full max-w-full px-3 md:mt-0 md:w-3/12">
                <label class="mb-2 ml-1 block text-xs font-bold uppercase text-slate-700">Tanggal Selesai</label>
                <input type="date" name="tanggal_selesai" value="{{ old('tanggal_selesai', now()->endOfYear()->format('Y-m-d')) }}"
                  class="focus:shadow-soft-primary-outline block w-full rounded-lg border border-solid border-gray-300 bg-white px-3 py-2 text-sm text-gray-700 focus:border-fuchsia-300 focus:outline-none">
              </div>

              <div class="mt-4 w-full max-w-full px-3 md:w-4/12">
                <label class="mb-2 ml-1 block text-xs font-bold uppercase text-slate-700">Dana Cadangan (%)</label>
                <input type="number" name="persen_dana_cadangan" min="0" max="100" step="0.01" value="{{ old('persen_dana_cadangan', 40) }}"
                  class="focus:shadow-soft-primary-outline block w-full rounded-lg border border-solid border-gray-300 bg-white px-3 py-2 text-sm text-gray-700 focus:border-fuchsia-300 focus:outline-none">
              </div>

              <div class="mt-4 w-full max-w-full px-3 md:w-4/12">
                <label class="mb-2 ml-1 block text-xs font-bold uppercase text-slate-700">SHU Anggota (%)</label>
                <input type="number" name="persen_shu_anggota" min="0" max="100" step="0.01" value="{{ old('persen_shu_anggota', 40) }}"
                  class="focus:shadow-soft-primary-outline block w-full rounded-lg border border-solid border-gray-300 bg-white px-3 py-2 text-sm text-gray-700 focus:border-fuchsia-300 focus:outline-none">
              </div>

              <div class="mt-4 w-full max-w-full px-3 md:w-4/12">
                <label class="mb-2 ml-1 block text-xs font-bold uppercase text-slate-700">Pengurus (%)</label>
                <input type="number" name="persen_pengurus" min="0" max="100" step="0.01" value="{{ old('persen_pengurus', 10) }}"
                  class="focus:shadow-soft-primary-outline block w-full rounded-lg border border-solid border-gray-300 bg-white px-3 py-2 text-sm text-gray-700 focus:border-fuchsia-300 focus:outline-none">
              </div>

              <div class="mt-4 w-full max-w-full px-3 md:w-4/12">
                <label class="mb-2 ml-1 block text-xs font-bold uppercase text-slate-700">Dana Sosial (%)</label>
                <input type="number" name="persen_dana_sosial" min="0" max="100" step="0.01" value="{{ old('persen_dana_sosial', 5) }}"
                  class="focus:shadow-soft-primary-outline block w-full rounded-lg border border-solid border-gray-300 bg-white px-3 py-2 text-sm text-gray-700 focus:border-fuchsia-300 focus:outline-none">
              </div>

              <div class="mt-4 w-full max-w-full px-3 md:w-4/12">
                <label class="mb-2 ml-1 block text-xs font-bold uppercase text-slate-700">Dana Pendidikan (%)</label>
                <input type="number" name="persen_dana_pendidikan" min="0" max="100" step="0.01" value="{{ old('persen_dana_pendidikan', 5) }}"
                  class="focus:shadow-soft-primary-outline block w-full rounded-lg border border-solid border-gray-300 bg-white px-3 py-2 text-sm text-gray-700 focus:border-fuchsia-300 focus:outline-none">
              </div>

              <div class="mt-4 w-full max-w-full px-3 md:w-6/12">
                <label class="mb-2 ml-1 block text-xs font-bold uppercase text-slate-700">Jasa Modal dari SHU Anggota (%)</label>
                <input type="number" name="persen_jasa_modal" min="0" max="100" step="0.01" value="{{ old('persen_jasa_modal', 50) }}"
                  class="focus:shadow-soft-primary-outline block w-full rounded-lg border border-solid border-gray-300 bg-white px-3 py-2 text-sm text-gray-700 focus:border-fuchsia-300 focus:outline-none">
              </div>

              <div class="mt-4 w-full max-w-full px-3 md:w-6/12">
                <label class="mb-2 ml-1 block text-xs font-bold uppercase text-slate-700">Jasa Usaha dari SHU Anggota (%)</label>
                <input type="number" name="persen_jasa_usaha" min="0" max="100" step="0.01" value="{{ old('persen_jasa_usaha', 50) }}"
                  class="focus:shadow-soft-primary-outline block w-full rounded-lg border border-solid border-gray-300 bg-white px-3 py-2 text-sm text-gray-700 focus:border-fuchsia-300 focus:outline-none">
              </div>

              <div class="mt-4 w-full max-w-full px-3">
                <label class="mb-2 ml-1 block text-xs font-bold uppercase text-slate-700">Keterangan</label>
                <textarea name="keterangan" rows="3"
                  class="focus:shadow-soft-primary-outline block w-full rounded-lg border border-solid border-gray-300 bg-white px-3 py-2 text-sm text-gray-700 transition-all focus:border-fuchsia-300 focus:outline-none"
                  placeholder="Catatan periode SHU ini">{{ old('keterangan') }}</textarea>
              </div>
            </div>

            <div class="mt-6">
              <button type="submit"
                class="inline-block rounded-lg bg-gradient-to-tl from-purple-700 to-pink-500 px-6 py-3 text-xs font-bold uppercase text-white shadow-soft-md transition-all hover:scale-105">
                Simpan Periode SHU
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>

  <div class="flex flex-wrap -mx-3">
    <div class="flex-none w-full max-w-full px-3">
      <div class="relative flex min-w-0 flex-col break-words rounded-2xl border-0 bg-white bg-clip-border shadow-soft-xl">
        <div class="mb-0 rounded-t-2xl bg-white p-6 pb-0">
          <h6>Data Periode SHU</h6>
          <p class="text-sm text-slate-400">Daftar periode SHU koperasi yang sudah dibuat.</p>
        </div>

        <div class="flex-auto px-0 pt-0 pb-2">
          <div style="overflow-x: auto;" class="p-0">
            <table class="items-center mb-0 w-full align-top border-gray-200 text-slate-500">
              <thead class="align-bottom">
                <tr>
                  <th class="px-6 py-3 text-left text-xxs font-bold uppercase text-slate-400 opacity-70">Periode SHU</th>
                  <th class="px-6 py-3 text-center text-xxs font-bold uppercase text-slate-400 opacity-70">Pendapatan</th>
                  <th class="px-6 py-3 text-center text-xxs font-bold uppercase text-slate-400 opacity-70">Biaya</th>
                  <th class="px-6 py-3 text-center text-xxs font-bold uppercase text-slate-400 opacity-70">SHU</th>
                  <th class="px-6 py-3 text-center text-xxs font-bold uppercase text-slate-400 opacity-70">Transaksi</th>
                  <th class="px-6 py-3 text-center text-xxs font-bold uppercase text-slate-400 opacity-70">Aksi</th>
                </tr>
              </thead>
              <tbody>
                @forelse($data as $item)
                  <tr>
                    <td class="border-b bg-transparent p-2 align-middle whitespace-nowrap shadow-transparent">
                      <div class="flex items-center px-4 py-2">
                        <div class="mr-4 flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-gradient-to-tl from-purple-700 to-pink-500 text-xs font-bold text-white">
                          {{ $data->firstItem() + $loop->index }}
                        </div>

                        <div class="flex flex-col justify-center">
                          <h6 class="mb-0 text-sm leading-normal">{{ $item->judul }}</h6>
                          <p class="mb-0 text-xs leading-tight text-slate-400">
                            {{ $item->tanggal_mulai->format('d/m/Y') }} - {{ $item->tanggal_selesai->format('d/m/Y') }}
                          </p>
                          <p class="mb-0 text-xs leading-tight text-slate-400">
                            Dihitung: {{ $item->dihitung_pada ? $item->dihitung_pada->format('d/m/Y H:i') : 'Belum' }}
                          </p>
                        </div>
                      </div>
                    </td>

                    <td class="border-b bg-transparent p-2 text-center align-middle whitespace-nowrap shadow-transparent">
                      <span class="inline-block rounded-1.8 bg-gradient-to-tl from-green-600 to-lime-400 px-2.5 py-1.4 text-xs font-bold uppercase text-white">
                        Rp {{ number_format((float) $item->total_pendapatan, 0, ',', '.') }}
                      </span>
                    </td>

                    <td class="border-b bg-transparent p-2 text-center align-middle whitespace-nowrap shadow-transparent">
                      <span class="inline-block rounded-1.8 bg-gradient-to-tl from-red-600 to-rose-400 px-2.5 py-1.4 text-xs font-bold uppercase text-white">
                        Rp {{ number_format((float) $item->total_biaya, 0, ',', '.') }}
                      </span>
                    </td>

                    <td class="border-b bg-transparent p-2 text-center align-middle whitespace-nowrap shadow-transparent">
                      <span class="inline-block rounded-1.8 {{ (float) $item->shu_total >= 0 ? 'bg-gradient-to-tl from-slate-700 to-slate-500 text-white' : 'bg-gradient-to-tl from-yellow-500 to-orange-400 text-white' }} px-2.5 py-1.4 text-xs font-bold uppercase">
                        Rp {{ number_format((float) $item->shu_total, 0, ',', '.') }}
                      </span>
                    </td>

                    <td class="border-b bg-transparent p-2 text-center align-middle whitespace-nowrap shadow-transparent">
                      <span class="text-xs font-semibold leading-tight text-slate-400">
                        {{ $item->transaksi_count }} transaksi
                      </span>
                    </td>

                    <td class="border-b bg-transparent p-2 text-center align-middle whitespace-nowrap shadow-transparent">
                      <div class="flex items-center justify-center gap-2 px-4">
                        <a href="{{ route('shu-koperasi.show', $item) }}"
                          class="inline-block rounded-lg bg-gradient-to-tl from-blue-600 to-cyan-400 px-4 py-2 text-xs font-bold uppercase text-white shadow-soft-md transition-all hover:scale-105">
                          Detail
                        </a>

                        <form action="{{ route('shu-koperasi.destroy', $item) }}" method="POST"
                          onsubmit="return confirm('Yakin ingin menghapus periode SHU ini?')">
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
                      Belum ada periode SHU koperasi.
                    </td>
                  </tr>
                @endforelse
              </tbody>
            </table>
          </div>

          <div class="border-t border-gray-200 p-4">
            {{ $data->links() }}
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
  function toggleSection(sectionId, buttonId) {
    const section = document.getElementById(sectionId);
    const button = document.getElementById(buttonId);

    if (!section || !button) {
      return;
    }

    if (section.classList.contains('hidden')) {
      section.classList.remove('hidden');
      section.classList.add('block');
      button.innerHTML = 'Tutup Form';
    } else {
      section.classList.add('hidden');
      section.classList.remove('block');
      button.innerHTML = '+ Tambah Data';
    }
  }
</script>
@endsection
