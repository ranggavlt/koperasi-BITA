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
            <h6>Tambah Transaksi Simpanan Manual</h6>
            <p class="text-sm text-slate-400">
              Simpanan Pokok dibuat otomatis saat Anggota dibuat. Form ini untuk setoran manual non-pokok.
            </p>
          </div>

          <button type="button" onclick="toggleForm()" id="btn-toggle-form"
            class="inline-block rounded-lg bg-gradient-to-tl from-slate-600 to-slate-300 px-4 py-2 text-xs font-bold uppercase text-white shadow-soft-md transition-all hover:scale-105">
            {{ $errors->any() ? 'Tutup Form' : '+ Tambah Data' }}
          </button>
        </div>

        <div id="form-container" class="flex-auto p-6 transition-all duration-300 {{ $errors->any() ? 'block' : 'hidden' }}">
          <form action="{{ route('simpanan.store') }}" method="POST">
            @csrf
            <input type="hidden" name="idempotency_key" value="{{ old('idempotency_key', 'simpanan:manual:' . \Illuminate\Support\Str::uuid()) }}">

            <div class="flex flex-wrap -mx-3">
              <div class="w-full max-w-full px-3 md:w-4/12">
                <label class="mb-2 ml-1 block text-xs font-bold uppercase text-slate-700">Anggota</label>
                <select name="anggota_id"
                  class="focus:shadow-soft-primary-outline text-sm leading-5.6 ease-soft block w-full rounded-lg border border-solid border-gray-300 bg-white px-3 py-2 text-gray-700 focus:border-fuchsia-300 focus:outline-none">
                  <option value="">-- Pilih Anggota --</option>
                  @foreach($anggota as $item)
                    <option value="{{ $item->id }}" {{ old('anggota_id') == $item->id ? 'selected' : '' }}>
                      {{ $item->nomor_anggota }} - {{ $item->karyawan->nama ?? '-' }}
                    </option>
                  @endforeach
                </select>
              </div>

              <div class="w-full max-w-full px-3 md:w-4/12 mt-4 md:mt-0">
                <label class="mb-2 ml-1 block text-xs font-bold uppercase text-slate-700">Jenis Simpanan</label>
                <select name="jenis_simpanan_id"
                  class="focus:shadow-soft-primary-outline text-sm leading-5.6 ease-soft block w-full rounded-lg border border-solid border-gray-300 bg-white px-3 py-2 text-gray-700 focus:border-fuchsia-300 focus:outline-none">
                  <option value="">-- Pilih Jenis Simpanan --</option>
                  @foreach($jenis as $item)
                    <option value="{{ $item->id }}" {{ old('jenis_simpanan_id') == $item->id ? 'selected' : '' }}>
                      {{ $item->nama_jenis }}
                    </option>
                  @endforeach
                </select>
              </div>

              <div class="w-full max-w-full px-3 md:w-4/12 mt-4 md:mt-0">
                <label class="mb-2 ml-1 block text-xs font-bold uppercase text-slate-700">Dompet Penerimaan</label>
                <select name="dompet_id"
                  class="focus:shadow-soft-primary-outline text-sm leading-5.6 ease-soft block w-full rounded-lg border border-solid border-gray-300 bg-white px-3 py-2 text-gray-700 focus:border-fuchsia-300 focus:outline-none">
                  <option value="">-- Pilih Dompet --</option>
                  @foreach($dompet as $item)
                    <option value="{{ $item->id }}" {{ old('dompet_id') == $item->id ? 'selected' : '' }}>
                      {{ $item->nama_dompet }} ({{ strtoupper($item->jenis_dompet ?? '-') }})
                    </option>
                  @endforeach
                </select>
              </div>

              <div class="w-full max-w-full px-3 mt-4 md:w-4/12">
                <label class="mb-2 ml-1 block text-xs font-bold uppercase text-slate-700">Tanggal</label>
                <input type="date" name="tanggal" value="{{ old('tanggal', now()->format('Y-m-d')) }}"
                  class="focus:shadow-soft-primary-outline text-sm leading-5.6 ease-soft block w-full rounded-lg border border-solid border-gray-300 bg-white px-3 py-2 text-gray-700 focus:border-fuchsia-300 focus:outline-none">
              </div>

              <div class="w-full max-w-full px-3 mt-4 md:w-4/12">
                <label class="mb-2 ml-1 block text-xs font-bold uppercase text-slate-700">Jumlah</label>
                <input type="number" name="jumlah" min="1" step="1" value="{{ old('jumlah') }}"
                  class="focus:shadow-soft-primary-outline text-sm leading-5.6 ease-soft block w-full rounded-lg border border-solid border-gray-300 bg-white px-3 py-2 text-gray-700 focus:border-fuchsia-300 focus:outline-none"
                  placeholder="0">
              </div>

              <div class="w-full max-w-full px-3 mt-4 md:w-4/12">
                <label class="mb-2 ml-1 block text-xs font-bold uppercase text-slate-700">Status</label>
                <input type="text" value="Settled Tunai/Bank"
                  class="text-sm leading-5.6 ease-soft block w-full rounded-lg border border-solid border-gray-200 bg-gray-100 px-3 py-2 text-gray-500"
                  readonly>
              </div>

              <div class="w-full max-w-full px-3 mt-4">
                <label class="mb-2 ml-1 block text-xs font-bold uppercase text-slate-700">Keterangan</label>
                <textarea name="keterangan" rows="3"
                  class="focus:shadow-soft-primary-outline text-sm leading-5.6 ease-soft block w-full rounded-lg border border-solid border-gray-300 bg-white px-3 py-2 text-gray-700 transition-all focus:border-fuchsia-300 focus:outline-none"
                  placeholder="Tambahkan keterangan bila diperlukan">{{ old('keterangan') }}</textarea>
              </div>
            </div>

            <div class="mt-6 flex gap-2">
              <button type="submit"
                class="inline-block rounded-lg bg-gradient-to-tl from-purple-700 to-pink-500 px-6 py-3 text-xs font-bold uppercase text-white shadow-soft-md transition-all">
                Simpan Transaksi
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
          <h6>Data Transaksi Simpanan</h6>
          <p class="text-sm text-slate-400">Termasuk Simpanan Pokok otomatis dan status settlement payroll.</p>
        </div>

        <div class="flex-auto px-0 pt-0 pb-2">
          <div style="overflow-x: auto;" class="p-0">
            <table class="items-center w-full mb-0 align-top border-gray-200 text-slate-500">
              <thead class="align-bottom">
                <tr>
                  <th class="px-6 py-3 text-left text-xxs font-bold uppercase text-slate-400 opacity-70">Anggota</th>
                  <th class="px-6 py-3 text-left text-xxs font-bold uppercase text-slate-400 opacity-70">Jenis</th>
                  <th class="px-6 py-3 text-center text-xxs font-bold uppercase text-slate-400 opacity-70">Tanggal</th>
                  <th class="px-6 py-3 text-center text-xxs font-bold uppercase text-slate-400 opacity-70">Jumlah</th>
                  <th class="px-6 py-3 text-center text-xxs font-bold uppercase text-slate-400 opacity-70">Status</th>
                  <th class="px-6 py-3 text-left text-xxs font-bold uppercase text-slate-400 opacity-70">Keterangan</th>
                  <th class="px-6 py-3 text-left text-xxs font-bold uppercase text-slate-400 opacity-70">Koreksi</th>
                </tr>
              </thead>
              <tbody>
                @forelse($simpanan as $item)
                  <tr>
                    <td class="p-2 align-middle bg-transparent border-b whitespace-nowrap shadow-transparent">
                      <div class="flex items-center px-4 py-2">
                        <div class="mr-4 flex shrink-0 h-9 w-9 items-center justify-center rounded-xl bg-gradient-to-tl from-purple-700 to-pink-500 text-xs font-bold text-white">
                          {{ $simpanan->firstItem() + $loop->index }}
                        </div>
                        <div class="flex flex-col justify-center">
                          <h6 class="mb-0 text-sm leading-normal">{{ $item->anggota?->nomor_anggota ?? '-' }}</h6>
                          <p class="mb-0 text-xs leading-tight text-slate-400">{{ $item->anggota?->karyawan?->nama ?? $item->karyawan->nama ?? '-' }}</p>
                        </div>
                      </div>
                    </td>

                    <td class="p-2 align-middle bg-transparent border-b whitespace-nowrap shadow-transparent">
                      <p class="mb-0 text-xs font-semibold leading-tight">{{ $item->nama_jenis_snapshot ?? $item->jenisSimpanan->nama_jenis ?? '-' }}</p>
                      <p class="mb-0 text-xs leading-tight text-slate-400">{{ $item->kode_jenis_snapshot ?? $item->jenisSimpanan->kode ?? '-' }}</p>
                    </td>

                    <td class="p-2 text-center align-middle bg-transparent border-b whitespace-nowrap shadow-transparent">
                      <span class="text-xs font-semibold leading-tight text-slate-400">{{ \Carbon\Carbon::parse($item->tanggal)->format('d/m/Y') }}</span>
                    </td>

                    <td class="p-2 text-center align-middle bg-transparent border-b whitespace-nowrap shadow-transparent">
                      <span class="inline-block rounded-1.8 bg-gradient-to-tl from-green-600 to-lime-400 px-2.5 py-1.4 text-xs font-bold uppercase text-white">
                        Rp {{ number_format($item->jumlah, 0, ',', '.') }}
                      </span>
                    </td>

                    <td class="p-2 text-center align-middle bg-transparent border-b whitespace-nowrap shadow-transparent">
                      <span class="inline-block rounded-full bg-slate-100 px-3 py-1 text-xs font-bold text-slate-600">
                        {{ str_replace('_', ' ', $item->status ?? 'settled') }}
                      </span>
                      <p class="mt-1 mb-0 text-xs text-slate-400">{{ str_replace('_', ' ', $item->metode_pembayaran ?? '-') }}</p>
                    </td>

                    <td class="p-2 align-middle bg-transparent border-b shadow-transparent">
                      <p class="mb-0 text-xs font-semibold leading-tight text-slate-500">{{ $item->keterangan ?: '-' }}</p>
                    </td>

                    <td class="p-2 align-middle bg-transparent border-b shadow-transparent">
                      @if($item->isSimpananPokok() && ! in_array($item->status, ['reversed','settled_cash'], true))
                        <form method="POST" action="{{ route('simpanan.koreksi', $item) }}" class="space-y-2">
                          @csrf
                          <input type="number" name="nominal_pengganti" min="1" placeholder="Nominal pengganti (opsional)"
                            class="w-full rounded-lg border border-gray-300 px-2 py-1 text-xs">
                          <textarea name="alasan" rows="2" required minlength="5" placeholder="Alasan koreksi"
                            class="w-full rounded-lg border border-gray-300 px-2 py-1 text-xs"></textarea>
                          <button class="rounded-lg bg-amber-600 px-3 py-2 text-xs font-bold uppercase text-white">Koreksi Penuh</button>
                        </form>
                      @else
                        <span class="text-xs text-slate-400">Tidak eligible</span>
                      @endif
                    </td>
                  </tr>
                @empty
                  <tr>
                    <td colspan="7" class="p-4 text-center text-sm text-slate-400">
                      Belum ada data transaksi simpanan.
                    </td>
                  </tr>
                @endforelse
              </tbody>
            </table>
          </div>

          <div class="p-4 border-t border-gray-200">
            {{ $simpanan->links() }}
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
