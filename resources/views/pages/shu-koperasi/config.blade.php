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

  <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
    <div>
      <h2 class="text-xl font-bold text-slate-700 m-0">Konfigurasi Default SHU</h2>
      <p class="mt-1 text-sm text-slate-400">Atur nilai default persentase pembagian SHU yang akan digunakan saat membuat periode SHU baru.</p>
    </div>
    <a href="{{ route('shu-koperasi.index') }}" class="rounded-lg border border-slate-200 bg-white px-4 py-2 text-xs font-bold uppercase text-slate-600 hover:bg-slate-50 transition-all">Kembali ke Daftar SHU</a>
  </div>

  <div class="flex flex-wrap -mx-3">
    <div class="flex-none w-full max-w-full px-3">
      <div class="relative mb-6 flex min-w-0 flex-col break-words rounded-2xl border-0 bg-white bg-clip-border shadow-soft-xl">
        <div class="flex-auto p-6">
          <form action="{{ route('shu-config.update') }}" method="POST">
            @csrf

            <h3 class="mb-4 text-base font-bold text-slate-700">Pembagian SHU Total (100%)</h3>
            <div class="grid gap-4 md:grid-cols-3">
              <div>
                <label class="mb-2 block text-xs font-bold uppercase text-slate-700">Dana Cadangan (%)</label>
                <input type="number" name="persen_dana_cadangan" min="0" max="100" step="0.01" value="{{ old('persen_dana_cadangan', (float) $shuConfig->persen_dana_cadangan) }}"
                  class="focus:shadow-soft-primary-outline block w-full rounded-lg border border-solid border-gray-300 bg-white px-3 py-2 text-sm text-gray-700 focus:border-fuchsia-300 focus:outline-none">
              </div>

              <div>
                <label class="mb-2 block text-xs font-bold uppercase text-slate-700">SHU Anggota (%)</label>
                <input type="number" name="persen_anggota" min="0" max="100" step="0.01" value="{{ old('persen_anggota', (float) $shuConfig->persen_anggota) }}"
                  class="focus:shadow-soft-primary-outline block w-full rounded-lg border border-solid border-gray-300 bg-white px-3 py-2 text-sm text-gray-700 focus:border-fuchsia-300 focus:outline-none">
              </div>

              <div>
                <label class="mb-2 block text-xs font-bold uppercase text-slate-700">Pengurus (%)</label>
                <input type="number" name="persen_pengurus" min="0" max="100" step="0.01" value="{{ old('persen_pengurus', (float) $shuConfig->persen_pengurus) }}"
                  class="focus:shadow-soft-primary-outline block w-full rounded-lg border border-solid border-gray-300 bg-white px-3 py-2 text-sm text-gray-700 focus:border-fuchsia-300 focus:outline-none">
              </div>

              <div>
                <label class="mb-2 block text-xs font-bold uppercase text-slate-700">Dana Sosial (%)</label>
                <input type="number" name="persen_dana_sosial" min="0" max="100" step="0.01" value="{{ old('persen_dana_sosial', (float) $shuConfig->persen_dana_sosial) }}"
                  class="focus:shadow-soft-primary-outline block w-full rounded-lg border border-solid border-gray-300 bg-white px-3 py-2 text-sm text-gray-700 focus:border-fuchsia-300 focus:outline-none">
              </div>

              <div>
                <label class="mb-2 block text-xs font-bold uppercase text-slate-700">Dana Pendidikan (%)</label>
                <input type="number" name="persen_dana_pendidikan" min="0" max="100" step="0.01" value="{{ old('persen_dana_pendidikan', (float) $shuConfig->persen_dana_pendidikan) }}"
                  class="focus:shadow-soft-primary-outline block w-full rounded-lg border border-solid border-gray-300 bg-white px-3 py-2 text-sm text-gray-700 focus:border-fuchsia-300 focus:outline-none">
              </div>

              <div>
                <label class="mb-2 block text-xs font-bold uppercase text-slate-700">Pembina (%)</label>
                <input type="number" name="persen_pembina" min="0" max="100" step="0.01" value="{{ old('persen_pembina', (float) $shuConfig->persen_pembina) }}"
                  class="focus:shadow-soft-primary-outline block w-full rounded-lg border border-solid border-gray-300 bg-white px-3 py-2 text-sm text-gray-700 focus:border-fuchsia-300 focus:outline-none">
              </div>

              <div>
                <label class="mb-2 block text-xs font-bold uppercase text-slate-700">Pengawas (%)</label>
                <input type="number" name="persen_pengawas" min="0" max="100" step="0.01" value="{{ old('persen_pengawas', (float) $shuConfig->persen_pengawas) }}"
                  class="focus:shadow-soft-primary-outline block w-full rounded-lg border border-solid border-gray-300 bg-white px-3 py-2 text-sm text-gray-700 focus:border-fuchsia-300 focus:outline-none">
              </div>
            </div>

            <hr class="my-6 border-slate-200">

            <h3 class="mb-4 text-base font-bold text-slate-700">Porsi Jasa dari SHU Anggota (Total 100%)</h3>
            <div class="grid gap-4 md:grid-cols-2">
              <div>
                <label class="mb-2 block text-xs font-bold uppercase text-slate-700">Jasa Modal (dari Simpanan) (%)</label>
                <input type="number" name="persen_jasa_modal" min="0" max="100" step="0.01" value="{{ old('persen_jasa_modal', (float) $shuConfig->persen_jasa_modal) }}"
                  class="focus:shadow-soft-primary-outline block w-full rounded-lg border border-solid border-gray-300 bg-white px-3 py-2 text-sm text-gray-700 focus:border-fuchsia-300 focus:outline-none">
              </div>

              <div>
                <label class="mb-2 block text-xs font-bold uppercase text-slate-700">Jasa Usaha (dari Pembelian Waserba) (%)</label>
                <input type="number" name="persen_jasa_usaha" min="0" max="100" step="0.01" value="{{ old('persen_jasa_usaha', (float) $shuConfig->persen_jasa_usaha) }}"
                  class="focus:shadow-soft-primary-outline block w-full rounded-lg border border-solid border-gray-300 bg-white px-3 py-2 text-sm text-gray-700 focus:border-fuchsia-300 focus:outline-none">
              </div>
            </div>

            <div class="mt-6 flex gap-3">
              <button type="submit" class="rounded-xl bg-[#2f8f3a] px-6 py-3 text-xs font-bold uppercase text-white shadow-lg hover:bg-[#267832] transition-all">
                Simpan Pengaturan
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
</div>
@endsection
