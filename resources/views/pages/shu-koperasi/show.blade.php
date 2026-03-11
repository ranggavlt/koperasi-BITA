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

  <div class="mb-6 flex flex-wrap items-start justify-between gap-4">
    <div>
      <a href="{{ route('shu-koperasi.index') }}" class="text-sm font-semibold text-slate-500 hover:text-slate-700">
        Kembali ke daftar SHU
      </a>
      <h2 class="mt-2 text-xl font-bold text-slate-700">{{ $shuKoperasi->judul }}</h2>
      <p class="text-sm text-slate-400">
        Periode {{ $shuKoperasi->tanggal_mulai->format('d/m/Y') }} - {{ $shuKoperasi->tanggal_selesai->format('d/m/Y') }}
      </p>
      <p class="text-xs text-slate-400">
        Snapshot terakhir:
        {{ $shuKoperasi->dihitung_pada ? $shuKoperasi->dihitung_pada->format('d/m/Y H:i') : 'Belum dihitung' }}
      </p>
    </div>

    <form action="{{ route('shu-koperasi.refresh', $shuKoperasi) }}" method="POST">
      @csrf
      <button type="submit"
        class="inline-block rounded-lg bg-gradient-to-tl from-blue-600 to-cyan-400 px-5 py-2 text-xs font-bold uppercase text-white shadow-soft-md transition-all hover:scale-105">
        Hitung Ulang
      </button>
    </form>
  </div>

  <div class="mb-6 grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
    <div class="rounded-2xl bg-white p-5 shadow-soft-xl">
      <p class="text-xs font-bold uppercase text-slate-400">Total Pendapatan</p>
      <h4 class="mt-2 text-lg font-bold text-green-600">Rp {{ number_format((float) $shuKoperasi->total_pendapatan, 0, ',', '.') }}</h4>
    </div>

    <div class="rounded-2xl bg-white p-5 shadow-soft-xl">
      <p class="text-xs font-bold uppercase text-slate-400">Total Biaya</p>
      <h4 class="mt-2 text-lg font-bold text-rose-500">Rp {{ number_format((float) $shuKoperasi->total_biaya, 0, ',', '.') }}</h4>
    </div>

    <div class="rounded-2xl bg-white p-5 shadow-soft-xl">
      <p class="text-xs font-bold uppercase text-slate-400">SHU Total</p>
      <h4 class="mt-2 text-lg font-bold {{ (float) $shuKoperasi->shu_total >= 0 ? 'text-slate-700' : 'text-red-600' }}">
        Rp {{ number_format((float) $shuKoperasi->shu_total, 0, ',', '.') }}
      </h4>
      <p class="mt-1 text-xs text-slate-400">
        @if ((float) $shuKoperasi->shu_total < 0)
          SHU negatif tidak dibagikan ke pos pembagian.
        @else
          SHU positif siap dibagi sesuai persentase.
        @endif
      </p>
    </div>

    <div class="rounded-2xl bg-white p-5 shadow-soft-xl">
      <p class="text-xs font-bold uppercase text-slate-400">SHU Anggota</p>
      <h4 class="mt-2 text-lg font-bold text-slate-700">Rp {{ number_format((float) $shuKoperasi->nominal_shu_anggota, 0, ',', '.') }}</h4>
      <p class="mt-1 text-xs text-slate-400">
        Jasa Modal {{ number_format((float) $shuKoperasi->persen_jasa_modal, 2, ',', '.') }}% |
        Jasa Usaha {{ number_format((float) $shuKoperasi->persen_jasa_usaha, 2, ',', '.') }}%
      </p>
    </div>
  </div>

  <div class="mb-6 grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-5">
    <div class="rounded-2xl bg-white p-4 shadow-soft-xl">
      <p class="text-xs font-bold uppercase text-slate-400">Dana Cadangan</p>
      <h5 class="mt-2 font-bold text-slate-700">Rp {{ number_format((float) $shuKoperasi->nominal_dana_cadangan, 0, ',', '.') }}</h5>
      <p class="text-xs text-slate-400">{{ number_format((float) $shuKoperasi->persen_dana_cadangan, 2, ',', '.') }}%</p>
    </div>

    <div class="rounded-2xl bg-white p-4 shadow-soft-xl">
      <p class="text-xs font-bold uppercase text-slate-400">Pengurus</p>
      <h5 class="mt-2 font-bold text-slate-700">Rp {{ number_format((float) $shuKoperasi->nominal_pengurus, 0, ',', '.') }}</h5>
      <p class="text-xs text-slate-400">
        {{ number_format((float) $shuKoperasi->persen_pengurus, 2, ',', '.') }}%
        @if ($jumlahPengurus > 0)
          | estimasi merata Rp {{ number_format($estimasiPengurus, 0, ',', '.') }}/pengurus
        @endif
      </p>
    </div>

    <div class="rounded-2xl bg-white p-4 shadow-soft-xl">
      <p class="text-xs font-bold uppercase text-slate-400">Dana Sosial</p>
      <h5 class="mt-2 font-bold text-slate-700">Rp {{ number_format((float) $shuKoperasi->nominal_dana_sosial, 0, ',', '.') }}</h5>
      <p class="text-xs text-slate-400">{{ number_format((float) $shuKoperasi->persen_dana_sosial, 2, ',', '.') }}%</p>
    </div>

    <div class="rounded-2xl bg-white p-4 shadow-soft-xl">
      <p class="text-xs font-bold uppercase text-slate-400">Dana Pendidikan</p>
      <h5 class="mt-2 font-bold text-slate-700">Rp {{ number_format((float) $shuKoperasi->nominal_dana_pendidikan, 0, ',', '.') }}</h5>
      <p class="text-xs text-slate-400">{{ number_format((float) $shuKoperasi->persen_dana_pendidikan, 2, ',', '.') }}%</p>
    </div>

    <div class="rounded-2xl bg-white p-4 shadow-soft-xl">
      <p class="text-xs font-bold uppercase text-slate-400">Basis Anggota</p>
      <h5 class="mt-2 font-bold text-slate-700">
        Modal Rp {{ number_format((float) $shuKoperasi->total_bobot_modal, 0, ',', '.') }}
      </h5>
      <p class="text-xs text-slate-400">
        Usaha Rp {{ number_format((float) $shuKoperasi->total_bobot_usaha, 0, ',', '.') }}
      </p>
    </div>
  </div>

  <div class="grid grid-cols-1 gap-6 xl:grid-cols-2">
    <div class="relative flex min-w-0 flex-col break-words rounded-2xl border-0 bg-white bg-clip-border shadow-soft-xl">
      <div class="mb-0 flex items-center justify-between rounded-t-2xl bg-white p-6 pb-0">
        <div>
          <h6>Konfigurasi Periode SHU</h6>
          <p class="text-sm text-slate-400">Update judul, periode, dan komposisi pembagian SHU.</p>
        </div>

        <button type="button" onclick="toggleSection('shu-config-form', 'btn-toggle-shu-config')"
          id="btn-toggle-shu-config"
          class="inline-block rounded-lg bg-gradient-to-tl from-slate-600 to-slate-300 px-4 py-2 text-xs font-bold uppercase text-white shadow-soft-md transition-all hover:scale-105">
          {{ $errors->any() ? 'Tutup Form' : 'Ubah Data' }}
        </button>
      </div>

      <div id="shu-config-form" class="flex-auto p-6 transition-all duration-300 {{ $errors->any() ? 'block' : 'hidden' }}">
        <form action="{{ route('shu-koperasi.update', $shuKoperasi) }}" method="POST">
          @csrf
          @method('PUT')

          <div class="flex flex-wrap -mx-3">
            <div class="w-full max-w-full px-3 md:w-6/12">
              <label class="mb-2 ml-1 block text-xs font-bold uppercase text-slate-700">Judul SHU</label>
              <input type="text" name="judul" value="{{ old('judul', $shuKoperasi->judul) }}"
                class="focus:shadow-soft-primary-outline block w-full rounded-lg border border-solid border-gray-300 bg-white px-3 py-2 text-sm text-gray-700 focus:border-fuchsia-300 focus:outline-none">
            </div>

            <div class="mt-4 w-full max-w-full px-3 md:mt-0 md:w-3/12">
              <label class="mb-2 ml-1 block text-xs font-bold uppercase text-slate-700">Tanggal Mulai</label>
              <input type="date" name="tanggal_mulai" value="{{ old('tanggal_mulai', $shuKoperasi->tanggal_mulai->format('Y-m-d')) }}"
                class="focus:shadow-soft-primary-outline block w-full rounded-lg border border-solid border-gray-300 bg-white px-3 py-2 text-sm text-gray-700 focus:border-fuchsia-300 focus:outline-none">
            </div>

            <div class="mt-4 w-full max-w-full px-3 md:mt-0 md:w-3/12">
              <label class="mb-2 ml-1 block text-xs font-bold uppercase text-slate-700">Tanggal Selesai</label>
              <input type="date" name="tanggal_selesai" value="{{ old('tanggal_selesai', $shuKoperasi->tanggal_selesai->format('Y-m-d')) }}"
                class="focus:shadow-soft-primary-outline block w-full rounded-lg border border-solid border-gray-300 bg-white px-3 py-2 text-sm text-gray-700 focus:border-fuchsia-300 focus:outline-none">
            </div>

            <div class="mt-4 w-full max-w-full px-3 md:w-4/12">
              <label class="mb-2 ml-1 block text-xs font-bold uppercase text-slate-700">Dana Cadangan (%)</label>
              <input type="number" name="persen_dana_cadangan" min="0" max="100" step="0.01" value="{{ old('persen_dana_cadangan', $shuKoperasi->persen_dana_cadangan) }}"
                class="focus:shadow-soft-primary-outline block w-full rounded-lg border border-solid border-gray-300 bg-white px-3 py-2 text-sm text-gray-700 focus:border-fuchsia-300 focus:outline-none">
            </div>

            <div class="mt-4 w-full max-w-full px-3 md:w-4/12">
              <label class="mb-2 ml-1 block text-xs font-bold uppercase text-slate-700">SHU Anggota (%)</label>
              <input type="number" name="persen_shu_anggota" min="0" max="100" step="0.01" value="{{ old('persen_shu_anggota', $shuKoperasi->persen_shu_anggota) }}"
                class="focus:shadow-soft-primary-outline block w-full rounded-lg border border-solid border-gray-300 bg-white px-3 py-2 text-sm text-gray-700 focus:border-fuchsia-300 focus:outline-none">
            </div>

            <div class="mt-4 w-full max-w-full px-3 md:w-4/12">
              <label class="mb-2 ml-1 block text-xs font-bold uppercase text-slate-700">Pengurus (%)</label>
              <input type="number" name="persen_pengurus" min="0" max="100" step="0.01" value="{{ old('persen_pengurus', $shuKoperasi->persen_pengurus) }}"
                class="focus:shadow-soft-primary-outline block w-full rounded-lg border border-solid border-gray-300 bg-white px-3 py-2 text-sm text-gray-700 focus:border-fuchsia-300 focus:outline-none">
            </div>

            <div class="mt-4 w-full max-w-full px-3 md:w-4/12">
              <label class="mb-2 ml-1 block text-xs font-bold uppercase text-slate-700">Dana Sosial (%)</label>
              <input type="number" name="persen_dana_sosial" min="0" max="100" step="0.01" value="{{ old('persen_dana_sosial', $shuKoperasi->persen_dana_sosial) }}"
                class="focus:shadow-soft-primary-outline block w-full rounded-lg border border-solid border-gray-300 bg-white px-3 py-2 text-sm text-gray-700 focus:border-fuchsia-300 focus:outline-none">
            </div>

            <div class="mt-4 w-full max-w-full px-3 md:w-4/12">
              <label class="mb-2 ml-1 block text-xs font-bold uppercase text-slate-700">Dana Pendidikan (%)</label>
              <input type="number" name="persen_dana_pendidikan" min="0" max="100" step="0.01" value="{{ old('persen_dana_pendidikan', $shuKoperasi->persen_dana_pendidikan) }}"
                class="focus:shadow-soft-primary-outline block w-full rounded-lg border border-solid border-gray-300 bg-white px-3 py-2 text-sm text-gray-700 focus:border-fuchsia-300 focus:outline-none">
            </div>

            <div class="mt-4 w-full max-w-full px-3 md:w-6/12">
              <label class="mb-2 ml-1 block text-xs font-bold uppercase text-slate-700">Jasa Modal dari SHU Anggota (%)</label>
              <input type="number" name="persen_jasa_modal" min="0" max="100" step="0.01" value="{{ old('persen_jasa_modal', $shuKoperasi->persen_jasa_modal) }}"
                class="focus:shadow-soft-primary-outline block w-full rounded-lg border border-solid border-gray-300 bg-white px-3 py-2 text-sm text-gray-700 focus:border-fuchsia-300 focus:outline-none">
            </div>

            <div class="mt-4 w-full max-w-full px-3 md:w-6/12">
              <label class="mb-2 ml-1 block text-xs font-bold uppercase text-slate-700">Jasa Usaha dari SHU Anggota (%)</label>
              <input type="number" name="persen_jasa_usaha" min="0" max="100" step="0.01" value="{{ old('persen_jasa_usaha', $shuKoperasi->persen_jasa_usaha) }}"
                class="focus:shadow-soft-primary-outline block w-full rounded-lg border border-solid border-gray-300 bg-white px-3 py-2 text-sm text-gray-700 focus:border-fuchsia-300 focus:outline-none">
            </div>

            <div class="mt-4 w-full max-w-full px-3">
              <label class="mb-2 ml-1 block text-xs font-bold uppercase text-slate-700">Keterangan</label>
              <textarea name="keterangan" rows="3"
                class="focus:shadow-soft-primary-outline block w-full rounded-lg border border-solid border-gray-300 bg-white px-3 py-2 text-sm text-gray-700 focus:border-fuchsia-300 focus:outline-none">{{ old('keterangan', $shuKoperasi->keterangan) }}</textarea>
            </div>
          </div>

          <div class="mt-6">
            <button type="submit"
              class="inline-block rounded-lg bg-gradient-to-tl from-purple-700 to-pink-500 px-6 py-3 text-xs font-bold uppercase text-white shadow-soft-md transition-all hover:scale-105">
              Simpan Konfigurasi
            </button>
          </div>
        </form>
      </div>
    </div>

    <div class="relative flex min-w-0 flex-col break-words rounded-2xl border-0 bg-white bg-clip-border shadow-soft-xl">
      <div class="mb-0 flex items-center justify-between rounded-t-2xl bg-white p-6 pb-0">
        <div>
          <h6>Tambah Transaksi SHU</h6>
          <p class="text-sm text-slate-400">Input pendapatan dan biaya SHU untuk periode ini.</p>
        </div>

        <button type="button" onclick="toggleSection('shu-transaction-form', 'btn-toggle-shu-transaction')"
          id="btn-toggle-shu-transaction"
          class="inline-block rounded-lg bg-gradient-to-tl from-slate-600 to-slate-300 px-4 py-2 text-xs font-bold uppercase text-white shadow-soft-md transition-all hover:scale-105">
          {{ $errors->any() ? 'Tutup Form' : '+ Tambah Data' }}
        </button>
      </div>

      <div id="shu-transaction-form" class="flex-auto p-6 transition-all duration-300 {{ $errors->any() ? 'block' : 'hidden' }}">
        <form action="{{ route('shu-koperasi.transaksi.store', $shuKoperasi) }}" method="POST">
          @csrf

          <div class="flex flex-wrap -mx-3">
            <div class="w-full max-w-full px-3 md:w-4/12">
              <label class="mb-2 ml-1 block text-xs font-bold uppercase text-slate-700">Jenis</label>
              <select name="jenis"
                class="focus:shadow-soft-primary-outline block w-full rounded-lg border border-solid border-gray-300 bg-white px-3 py-2 text-sm text-gray-700 focus:border-fuchsia-300 focus:outline-none">
                <option value="pendapatan" {{ old('jenis') === 'pendapatan' ? 'selected' : '' }}>Pendapatan</option>
                <option value="biaya" {{ old('jenis') === 'biaya' ? 'selected' : '' }}>Biaya</option>
              </select>
            </div>

            <div class="mt-4 w-full max-w-full px-3 md:mt-0 md:w-4/12">
              <label class="mb-2 ml-1 block text-xs font-bold uppercase text-slate-700">Tanggal</label>
              <input type="date" name="tanggal"
                value="{{ old('tanggal', $shuKoperasi->tanggal_selesai->format('Y-m-d')) }}"
                min="{{ $shuKoperasi->tanggal_mulai->format('Y-m-d') }}"
                max="{{ $shuKoperasi->tanggal_selesai->format('Y-m-d') }}"
                class="focus:shadow-soft-primary-outline block w-full rounded-lg border border-solid border-gray-300 bg-white px-3 py-2 text-sm text-gray-700 focus:border-fuchsia-300 focus:outline-none">
            </div>

            <div class="mt-4 w-full max-w-full px-3 md:mt-0 md:w-4/12">
              <label class="mb-2 ml-1 block text-xs font-bold uppercase text-slate-700">Jumlah</label>
              <input type="number" name="jumlah" min="0" step="0.01" value="{{ old('jumlah') }}"
                class="focus:shadow-soft-primary-outline block w-full rounded-lg border border-solid border-gray-300 bg-white px-3 py-2 text-sm text-gray-700 focus:border-fuchsia-300 focus:outline-none"
                placeholder="0">
            </div>

            <div class="mt-4 w-full max-w-full px-3">
              <label class="mb-2 ml-1 block text-xs font-bold uppercase text-slate-700">Keterangan</label>
              <textarea name="keterangan" rows="3"
                class="focus:shadow-soft-primary-outline block w-full rounded-lg border border-solid border-gray-300 bg-white px-3 py-2 text-sm text-gray-700 transition-all focus:border-fuchsia-300 focus:outline-none"
                placeholder="Contoh: Pendapatan bunga pinjaman / biaya operasional koperasi">{{ old('keterangan') }}</textarea>
            </div>
          </div>

          <div class="mt-6">
            <button type="submit"
              class="inline-block rounded-lg bg-gradient-to-tl from-purple-700 to-pink-500 px-6 py-3 text-xs font-bold uppercase text-white shadow-soft-md transition-all hover:scale-105">
              Simpan Transaksi SHU
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <div class="flex flex-wrap -mx-3">
    <div class="flex-none w-full max-w-full px-3">
      <div class="relative mb-6 flex min-w-0 flex-col break-words rounded-2xl border-0 bg-white bg-clip-border shadow-soft-xl">
        <div class="mb-0 rounded-t-2xl bg-white p-6 pb-0">
          <h6>Data Transaksi SHU</h6>
          <p class="text-sm text-slate-400">Daftar transaksi pendapatan dan biaya SHU dalam periode ini.</p>
        </div>

        <div class="flex-auto px-0 pt-0 pb-2">
          <div class="overflow-x-auto p-0">
            <table class="items-center mb-0 w-full align-top border-gray-200 text-slate-500">
              <thead class="align-bottom">
                <tr>
                  <th class="px-6 py-3 text-left text-xxs font-bold uppercase text-slate-400 opacity-70">Transaksi</th>
                  <th class="px-6 py-3 text-center text-xxs font-bold uppercase text-slate-400 opacity-70">Jenis</th>
                  <th class="px-6 py-3 text-center text-xxs font-bold uppercase text-slate-400 opacity-70">Jumlah</th>
                  <th class="px-6 py-3 text-center text-xxs font-bold uppercase text-slate-400 opacity-70">Aksi</th>
                </tr>
              </thead>
              <tbody>
                @forelse($shuKoperasi->transaksi as $transaksi)
                  <tr>
                    <td class="border-b bg-transparent p-2 align-middle whitespace-nowrap shadow-transparent">
                      <div class="flex items-center px-4 py-2">
                        <div class="mr-4 flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-gradient-to-tl {{ $transaksi->jenis === 'pendapatan' ? 'from-green-600 to-lime-400' : 'from-red-600 to-rose-400' }} text-xs font-bold text-white">
                          {{ $loop->iteration }}
                        </div>

                        <div class="flex flex-col justify-center">
                          <h6 class="mb-0 text-sm leading-normal">{{ $transaksi->keterangan ?: 'Tanpa keterangan' }}</h6>
                          <p class="mb-0 text-xs leading-tight text-slate-400">
                            Tanggal: {{ $transaksi->tanggal->format('d/m/Y') }}
                          </p>
                        </div>
                      </div>
                    </td>

                    <td class="border-b bg-transparent p-2 text-center align-middle whitespace-nowrap shadow-transparent">
                      <span class="inline-block rounded-1.8 {{ $transaksi->jenis === 'pendapatan' ? 'bg-gradient-to-tl from-green-600 to-lime-400' : 'bg-gradient-to-tl from-red-600 to-rose-400' }} px-2.5 py-1.4 text-xs font-bold uppercase text-white">
                        {{ $transaksi->jenis }}
                      </span>
                    </td>

                    <td class="border-b bg-transparent p-2 text-center align-middle whitespace-nowrap shadow-transparent">
                      <span class="text-xs font-semibold leading-tight text-slate-400">
                        Rp {{ number_format((float) $transaksi->jumlah, 0, ',', '.') }}
                      </span>
                    </td>

                    <td class="border-b bg-transparent p-2 text-center align-middle whitespace-nowrap shadow-transparent">
                      <div class="flex items-center justify-center gap-2 px-4">
                        <form action="{{ route('shu-koperasi.transaksi.destroy', [$shuKoperasi, $transaksi]) }}" method="POST"
                          onsubmit="return confirm('Yakin ingin menghapus transaksi SHU ini?')">
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
                    <td colspan="4" class="p-4 text-center text-sm text-slate-400">
                      Belum ada transaksi SHU pada periode ini.
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

  <div class="flex flex-wrap -mx-3">
    <div class="flex-none w-full max-w-full px-3">
      <div class="relative flex min-w-0 flex-col break-words rounded-2xl border-0 bg-white bg-clip-border shadow-soft-xl">
        <div class="mb-0 rounded-t-2xl bg-white p-6 pb-0">
          <h6>Pembagian SHU untuk Anggota</h6>
          <p class="text-sm text-slate-400">Jasa Modal diambil dari total simpanan anggota, Jasa Usaha diambil dari total transaksi penjualan anggota pada periode yang sama.</p>
        </div>

        <div class="flex-auto px-0 pt-0 pb-2">
          <div class="overflow-x-auto p-0">
            <table class="items-center mb-0 w-full align-top border-gray-200 text-slate-500">
              <thead class="align-bottom">
                <tr>
                  <th class="px-6 py-3 text-left text-xxs font-bold uppercase text-slate-400 opacity-70">Anggota</th>
                  <th class="px-6 py-3 text-center text-xxs font-bold uppercase text-slate-400 opacity-70">Total Simpanan</th>
                  <th class="px-6 py-3 text-center text-xxs font-bold uppercase text-slate-400 opacity-70">Total Usaha</th>
                  <th class="px-6 py-3 text-center text-xxs font-bold uppercase text-slate-400 opacity-70">Jasa Modal</th>
                  <th class="px-6 py-3 text-center text-xxs font-bold uppercase text-slate-400 opacity-70">Jasa Usaha</th>
                  <th class="px-6 py-3 text-center text-xxs font-bold uppercase text-slate-400 opacity-70">Total SHU</th>
                </tr>
              </thead>
              <tbody>
                @forelse($shuKoperasi->anggotaPembagian as $item)
                  <tr>
                    <td class="border-b bg-transparent p-2 align-middle whitespace-nowrap shadow-transparent">
                      <div class="flex items-center px-4 py-2">
                        <div class="mr-4 flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-gradient-to-tl from-purple-700 to-pink-500 text-xs font-bold text-white">
                          {{ $loop->iteration }}
                        </div>

                        <div class="flex flex-col justify-center">
                          <h6 class="mb-0 text-sm leading-normal">{{ $item->karyawan->nama ?? 'Anggota tidak ditemukan' }}</h6>
                          <p class="mb-0 text-xs leading-tight text-slate-400">
                            {{ $item->karyawan->jabatan ?? 'Anggota koperasi' }}
                          </p>
                        </div>
                      </div>
                    </td>

                    <td class="border-b bg-transparent p-2 text-center align-middle whitespace-nowrap shadow-transparent">
                      <span class="text-xs font-semibold leading-tight text-slate-400">
                        Rp {{ number_format((float) $item->total_simpanan, 0, ',', '.') }}
                      </span>
                    </td>

                    <td class="border-b bg-transparent p-2 text-center align-middle whitespace-nowrap shadow-transparent">
                      <span class="text-xs font-semibold leading-tight text-slate-400">
                        Rp {{ number_format((float) $item->total_transaksi_usaha, 0, ',', '.') }}
                      </span>
                    </td>

                    <td class="border-b bg-transparent p-2 text-center align-middle whitespace-nowrap shadow-transparent">
                      <span class="inline-block rounded-1.8 bg-gradient-to-tl from-blue-600 to-cyan-400 px-2.5 py-1.4 text-xs font-bold uppercase text-white">
                        Rp {{ number_format((float) $item->nominal_jasa_modal, 0, ',', '.') }}
                      </span>
                    </td>

                    <td class="border-b bg-transparent p-2 text-center align-middle whitespace-nowrap shadow-transparent">
                      <span class="inline-block rounded-1.8 bg-gradient-to-tl from-indigo-600 to-sky-400 px-2.5 py-1.4 text-xs font-bold uppercase text-white">
                        Rp {{ number_format((float) $item->nominal_jasa_usaha, 0, ',', '.') }}
                      </span>
                    </td>

                    <td class="border-b bg-transparent p-2 text-center align-middle whitespace-nowrap shadow-transparent">
                      <span class="inline-block rounded-1.8 bg-gradient-to-tl from-slate-700 to-slate-500 px-2.5 py-1.4 text-xs font-bold uppercase text-white">
                        Rp {{ number_format((float) $item->nominal_shu, 0, ',', '.') }}
                      </span>
                    </td>
                  </tr>
                @empty
                  <tr>
                    <td colspan="6" class="p-4 text-center text-sm text-slate-400">
                      Belum ada snapshot pembagian SHU anggota.
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
