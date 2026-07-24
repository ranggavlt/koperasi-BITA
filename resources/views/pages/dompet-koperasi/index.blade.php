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
            <h6>{{ isset($data) ? 'Edit Dompet Koperasi' : 'Tambah Dompet Koperasi' }}</h6>
            <p class="text-sm text-slate-400">
              Isi master data dompet koperasi untuk pencatatan saldo kas internal
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
          <form action="{{ isset($data) ? route('dompet-koperasi.update', $data->id) : route('dompet-koperasi.store') }}" method="POST">
            @csrf
            @if(isset($data))
              @method('PUT')
            @endif

            <div class="flex flex-wrap -mx-3">
              <div class="w-full max-w-full px-3 md:w-3/12">
                <label class="mb-2 ml-1 block text-xs font-bold uppercase text-slate-700">
                  Nama Dompet
                </label>
                <input type="text" name="nama_dompet"
                  value="{{ old('nama_dompet', $data->nama_dompet ?? '') }}"
                  class="focus:shadow-soft-primary-outline text-sm leading-5.6 ease-soft block w-full appearance-none rounded-lg border border-solid border-gray-300 bg-white bg-clip-padding px-3 py-2 font-normal text-gray-700 transition-all focus:border-fuchsia-300 focus:outline-none"
                  placeholder="Masukkan nama dompet koperasi">
              </div>

              <div class="w-full max-w-full px-3 md:w-3/12">
                <label class="mb-2 ml-1 block text-xs font-bold uppercase text-slate-700">
                  Jenis Dompet
                </label>
                <select name="jenis_dompet"
                  class="focus:shadow-soft-primary-outline text-sm leading-5.6 ease-soft block w-full rounded-lg border border-solid border-gray-300 bg-white px-3 py-2 text-gray-700 focus:border-fuchsia-300 focus:outline-none">
                  <option value="kas" {{ old('jenis_dompet', $data->jenis_dompet ?? 'kas') === 'kas' ? 'selected' : '' }}>Kas</option>
                  <option value="bank" {{ old('jenis_dompet', $data->jenis_dompet ?? '') === 'bank' ? 'selected' : '' }}>Bank</option>
                </select>
                <label class="mt-3 flex items-center gap-2 text-xs font-semibold text-slate-600">
                  <input type="checkbox" name="is_default_penerimaan_payroll" value="1"
                    {{ old('is_default_penerimaan_payroll', $data->is_default_penerimaan_payroll ?? false) ? 'checked' : '' }}>
                  Default penerimaan payroll
                </label>
              </div>

              <div class="w-full max-w-full px-3 md:w-3/12">
                <label class="mb-2 ml-1 block text-xs font-bold uppercase text-slate-700">
                  Akun COA Dompet
                </label>
                <select name="akun_id"
                  class="focus:shadow-soft-primary-outline text-sm leading-5.6 ease-soft block w-full rounded-lg border border-solid border-gray-300 bg-white px-3 py-2 text-gray-700 focus:border-fuchsia-300 focus:outline-none">
                  <option value="">Pilih akun aset</option>
                  @foreach($akunAset as $akun)
                    <option value="{{ $akun->id }}" {{ (string) old('akun_id', $data->akun_id ?? '') === (string) $akun->id ? 'selected' : '' }}>
                      {{ $akun->kode_akun }} - {{ $akun->nama_akun }}
                    </option>
                  @endforeach
                </select>
                <p class="mt-1 text-xs text-slate-400">Dipakai sebagai akun kredit saat dana keluar dari Dompet.</p>
              </div>

              <div class="w-full max-w-full px-3 md:w-3/12">
                <label class="mb-2 ml-1 block text-xs font-bold uppercase text-slate-700">
                  Saldo Saat Ini
                </label>
                <input type="text"
                  value="Rp {{ number_format(old('saldo', $data->saldo ?? 0), 0, ',', '.') }}"
                  class="text-sm leading-5.6 ease-soft block w-full rounded-lg border border-solid border-gray-200 bg-gray-100 px-3 py-2 text-gray-500"
                  readonly>
                <p class="mt-1 text-xs text-slate-400">Saldo dibuat otomatis 0 saat dompet baru ditambahkan.</p>
              </div>
            </div>

            <div class="mt-6 flex gap-2">
              <button type="submit"
                class="inline-block rounded-lg bg-gradient-to-tl from-purple-700 to-pink-500 px-6 py-3 text-xs font-bold uppercase text-white shadow-soft-md transition-all">
                {{ isset($data) ? 'Update Dompet' : 'Simpan Dompet' }}
              </button>

              @if(isset($data))
                <a href="{{ route('dompet-koperasi.index') }}"
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
          <h6>Data Dompet Koperasi</h6>
          <p class="text-sm text-slate-400">Daftar dompet koperasi untuk pengelolaan saldo internal</p>
        </div>

        <div class="flex-auto px-0 pt-0 pb-2">
          <div style="overflow-x: auto;" class="p-0">
            <table class="items-center w-full mb-0 align-top border-gray-200 text-slate-500">
              <thead class="align-bottom">
                <tr>
                  <th class="px-6 py-3 text-left text-xxs font-bold uppercase text-slate-400 opacity-70">Dompet</th>
                  <th class="px-6 py-3 text-center text-xxs font-bold uppercase text-slate-400 opacity-70">Jenis</th>
                  <th class="px-6 py-3 text-left text-xxs font-bold uppercase text-slate-400 opacity-70">Akun COA</th>
                  <th class="px-6 py-3 text-center text-xxs font-bold uppercase text-slate-400 opacity-70">Saldo</th>
                  <th class="px-6 py-3 text-center text-xxs font-bold uppercase text-slate-400 opacity-70">Status</th>
                  <th class="px-6 py-3 text-center text-xxs font-bold uppercase text-slate-400 opacity-70">Aksi</th>
                </tr>
              </thead>
              <tbody>
                @forelse($dompetKoperasi as $item)
                  <tr>
                    <td class="p-2 align-middle bg-transparent border-b whitespace-nowrap shadow-transparent">
                      <div class="flex items-center px-4 py-2">
                        <div class="mr-4 flex shrink-0 h-9 w-9 items-center justify-center rounded-xl bg-gradient-to-tl from-purple-700 to-pink-500 text-xs font-bold text-white">
                          {{ $dompetKoperasi->firstItem() + $loop->index }}
                        </div>

                        <div class="flex flex-col justify-center">
                          <h6 class="mb-0 text-sm leading-normal">{{ $item->nama_dompet }}</h6>
                          <p class="mb-0 text-xs leading-tight text-slate-400">
                            Dibuat: {{ $item->created_at ? $item->created_at->format('d/m/Y') : '-' }}
                          </p>
                        </div>
                      </div>
                    </td>

                    <td class="p-2 text-center align-middle bg-transparent border-b whitespace-nowrap shadow-transparent">
                      <span class="inline-block rounded-lg bg-gradient-to-tl {{ $item->jenis_dompet === 'bank' ? 'from-blue-700 to-emerald-400' : 'from-slate-700 to-slate-400' }} px-2.5 py-1 text-xs font-bold uppercase text-white">
                        {{ strtoupper($item->jenis_dompet ?? 'kas') }}
                      </span>
                      @if($item->is_default_penerimaan_payroll)
                        <p class="mt-1 mb-0 text-xs font-bold text-emerald-600">Default payroll</p>
                      @endif
                    </td>

                    <td class="p-2 align-middle bg-transparent border-b shadow-transparent">
                      @if($item->akun)
                        <p class="mb-0 text-xs font-bold text-slate-600">{{ $item->akun->kode_akun }} - {{ $item->akun->nama_akun }}</p>
                        <p class="mb-0 text-xs text-slate-400">{{ $item->akun->kategori_label }} / {{ ucfirst($item->akun->posisi_saldo) }}</p>
                      @else
                        <span class="text-xs font-bold text-red-500">Belum dipetakan</span>
                      @endif
                    </td>

                    <td class="p-2 text-center align-middle bg-transparent border-b whitespace-nowrap shadow-transparent">
                      <span class="text-xs font-semibold leading-tight text-slate-400">
                        Rp {{ number_format($item->saldo, 0, ',', '.') }}
                      </span>
                    </td>

                    <td class="p-2 text-center align-middle bg-transparent border-b whitespace-nowrap shadow-transparent">
                      @if($item->saldo > 0)
                        <span class="inline-block rounded-1.8 bg-gradient-to-tl from-green-600 to-lime-400 px-2.5 py-1.4 text-xs font-bold uppercase text-white">
                          Aktif
                        </span>
                      @else
                        <span class="inline-block rounded-1.8 bg-gradient-to-tl from-slate-600 to-slate-300 px-2.5 py-1.4 text-xs font-bold uppercase text-white">
                          Kosong
                        </span>
                      @endif
                    </td>

                    <td class="p-2 text-center align-middle bg-transparent border-b whitespace-nowrap shadow-transparent">
                      <div class="flex items-center justify-center gap-2 px-4">
                        <a href="{{ route('dompet-koperasi.edit', $item->id) }}"
                           class="inline-block rounded-lg bg-gradient-to-tl from-blue-600 to-cyan-400 px-4 py-2 text-xs font-bold uppercase text-white shadow-soft-md transition-all hover:scale-105">
                          Edit
                        </a>

                        <form action="{{ route('dompet-koperasi.destroy', $item->id) }}" method="POST"
                              onsubmit="return confirm('Yakin ingin menghapus dompet ini?')">
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
                      Belum ada data dompet koperasi.
                    </td>
                  </tr>
                @endforelse
              </tbody>
            </table>
          </div>

          <div class="p-4 border-t border-gray-200">
            {{ $dompetKoperasi->links() }}
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
