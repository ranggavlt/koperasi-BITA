@extends('layout.main')

@section('content')
<div class="w-full px-6 py-6 mx-auto">

  @if (session('success'))
    <div class="mb-4 rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700">
      {{ session('success') }}
    </div>
  @endif

  @if ($errors->any())
    <div class="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
      <ul class="mb-0 list-disc pl-5">
        @foreach ($errors->all() as $error)
          <li>{{ $error }}</li>
        @endforeach
      </ul>
    </div>
  @endif

  {{-- Form Panel --}}
  <div class="mb-6 overflow-hidden rounded-2xl border border-slate-100 bg-white shadow-soft-xl">
    <div class="flex flex-wrap items-center justify-between gap-4 border-b border-slate-100 p-6">
      <div>
        <h1 class="text-lg font-bold text-slate-700">Tambah Pinjaman Anggota</h1>
        <p class="mt-1 text-sm text-slate-400">Catat pinjaman yang sudah disetujui dan langsung dicairkan dari Dompet Koperasi</p>
      </div>
      <button type="button" onclick="toggleForm()" id="btn-toggle-form"
        class="kbsm-btn kbsm-btn--slate">
        {{ $errors->any() ? 'Tutup Form' : '+ Tambah Data' }}
      </button>
    </div>

    <div id="form-container" class="p-6 transition-all duration-300 {{ $errors->any() ? 'block' : 'hidden' }}">
      <form action="{{ route('pinjaman.store') }}" method="POST">
        @csrf
        <div class="grid gap-4 md:grid-cols-2">

          {{-- Anggota — full width karena dropdown penting --}}
          <div class="md:col-span-2">
            <label class="mb-2 block text-xs font-bold uppercase text-slate-700">Anggota Aktif</label>
            <select name="anggota_id" id="anggota_id"
              class="kbsm-focus focus:shadow-soft-primary-outline text-sm leading-5.6 ease-soft block w-full rounded-lg border border-solid border-gray-300 bg-white px-3 py-2 text-gray-700 focus:border-fuchsia-300 focus:outline-none">
              <option value="">-- Pilih Anggota --</option>
              @foreach($anggota as $item)
                <option value="{{ $item->id }}"
                  data-plafon="{{ (int) $item->plafon_pinjaman }}"
                  {{ old('anggota_id') == $item->id ? 'selected' : '' }}>
                  {{ $item->nomor_anggota }} - {{ $item->karyawan->nama ?? '-' }} | Plafon Rp {{ number_format($item->plafon_pinjaman, 0, ',', '.') }}
                </option>
              @endforeach
            </select>
            <p class="mt-1 text-xs text-slate-400">Yang tampil hanya Anggota aktif, Karyawan aktif, dan belum punya Pinjaman aktif.</p>
          </div>

          {{-- Tanggal Pencairan --}}
          <div>
            <label class="mb-2 block text-xs font-bold uppercase text-slate-700">Tanggal Pencairan</label>
            <input type="date" name="tanggal_pinjaman"
              value="{{ old('tanggal_pinjaman', now(config('app.timezone'))->format('Y-m-d')) }}"
              class="kbsm-focus focus:shadow-soft-primary-outline text-sm leading-5.6 ease-soft block w-full rounded-lg border border-solid border-gray-300 bg-white px-3 py-2 text-gray-700 focus:border-fuchsia-300 focus:outline-none">
          </div>

          {{-- Bunga (readonly) --}}
          <div>
            <label class="mb-2 block text-xs font-bold uppercase text-slate-700">Bunga</label>
            <input type="text" value="0%"
              class="text-sm leading-5.6 ease-soft block w-full rounded-lg border border-solid border-gray-200 bg-gray-100 px-3 py-2 text-gray-500"
              readonly>
            <input type="hidden" name="bunga_persen" value="0">
          </div>

          {{-- Dompet Sumber Dana --}}
          <div>
            <label class="mb-2 block text-xs font-bold uppercase text-slate-700">Dompet Sumber Dana</label>
            <select name="dompet_id"
              class="kbsm-focus focus:shadow-soft-primary-outline text-sm leading-5.6 ease-soft block w-full rounded-lg border border-solid border-gray-300 bg-white px-3 py-2 text-gray-700 focus:border-fuchsia-300 focus:outline-none">
              <option value="">-- Pilih Dompet --</option>
              @foreach($dompet as $item)
                <option value="{{ $item->id }}" {{ old('dompet_id') == $item->id ? 'selected' : '' }}>
                  {{ $item->nama_dompet }} | Saldo Rp {{ number_format($item->saldo, 0, ',', '.') }} | {{ $item->akun ? $item->akun->kode_akun : 'COA belum dipetakan' }}
                </option>
              @endforeach
            </select>
          </div>

          {{-- Jumlah Pinjaman --}}
          <div>
            <label class="mb-2 block text-xs font-bold uppercase text-slate-700">Jumlah Pinjaman</label>
            <input type="number" name="jumlah_pinjaman" min="1" max="5000000" step="1"
              value="{{ old('jumlah_pinjaman') }}"
              class="kbsm-focus focus:shadow-soft-primary-outline text-sm leading-5.6 ease-soft block w-full rounded-lg border border-solid border-gray-300 bg-white px-3 py-2 text-gray-700 focus:border-fuchsia-300 focus:outline-none"
              placeholder="Maksimal 5000000">
            <p class="mt-1 text-xs text-slate-400">Maksimal sistem Rp5.000.000 dan tetap dibatasi plafon Anggota.</p>
          </div>

          {{-- Tenor --}}
          <div>
            <label class="mb-2 block text-xs font-bold uppercase text-slate-700">Tenor (Bulan)</label>
            <input type="number" name="tenor_bulan" min="1" max="12" step="1"
              value="{{ old('tenor_bulan') }}"
              class="kbsm-focus focus:shadow-soft-primary-outline text-sm leading-5.6 ease-soft block w-full rounded-lg border border-solid border-gray-300 bg-white px-3 py-2 text-gray-700 focus:border-fuchsia-300 focus:outline-none"
              placeholder="1-12 bulan">
          </div>

          {{-- Keterangan — full width --}}
          <div class="md:col-span-2">
            <label class="mb-2 block text-xs font-bold uppercase text-slate-700">Keterangan</label>
            <textarea name="keterangan" rows="3"
              class="kbsm-focus focus:shadow-soft-primary-outline text-sm leading-5.6 ease-soft block w-full rounded-lg border border-solid border-gray-300 bg-white px-3 py-2 text-gray-700 transition-all focus:border-fuchsia-300 focus:outline-none"
              placeholder="Tambahkan catatan persetujuan di luar aplikasi bila diperlukan">{{ old('keterangan') }}</textarea>
          </div>

        </div>

        <div class="mt-6 flex gap-3">
          <button type="submit" class="kbsm-btn kbsm-btn--green">Cairkan Pinjaman</button>
        </div>
      </form>
    </div>
  </div>

  {{-- Data Table --}}
  <div class="mb-6 overflow-hidden rounded-2xl border border-slate-100 bg-white shadow-soft-xl">
    <div class="border-b border-slate-100 p-6">
      <h2 class="text-lg font-bold text-slate-700">Data Pinjaman</h2>
      <p class="mt-1 text-sm text-slate-400">Pinjaman aktif langsung memiliki jadwal cicilan otomatis</p>
    </div>

    <div style="overflow-x: auto;" class="">
      <table class="items-center w-full mb-0 align-top border-gray-200 text-slate-500">
        <thead class="align-bottom">
          <tr>
            <th class="px-6 py-3 text-left text-xxs font-bold uppercase text-slate-400 opacity-70">Kode &amp; Anggota</th>
            <th class="px-6 py-3 text-center text-xxs font-bold uppercase text-slate-400 opacity-70">Tanggal</th>
            <th class="px-6 py-3 text-left text-xxs font-bold uppercase text-slate-400 opacity-70">Dompet</th>
            <th class="px-6 py-3 text-center text-xxs font-bold uppercase text-slate-400 opacity-70">Pokok</th>
            <th class="px-6 py-3 text-center text-xxs font-bold uppercase text-slate-400 opacity-70">Plafon Snapshot</th>
            <th class="px-6 py-3 text-center text-xxs font-bold uppercase text-slate-400 opacity-70">Tenor</th>
            <th class="px-6 py-3 text-center text-xxs font-bold uppercase text-slate-400 opacity-70">Sisa</th>
            <th class="px-6 py-3 text-center text-xxs font-bold uppercase text-slate-400 opacity-70">Status</th>
            <th class="px-6 py-3 text-center text-xxs font-bold uppercase text-slate-400 opacity-70">Detail</th>
          </tr>
        </thead>
        <tbody>
          @forelse($pinjaman as $item)
            <tr>
              <td class="p-2 align-middle bg-transparent border-b whitespace-nowrap shadow-transparent">
                <div class="flex items-center px-4 py-2">
                  <div class="mr-4 flex shrink-0 h-9 w-9 items-center justify-center rounded-xl bg-gradient-to-tl from-emerald-700 to-teal-500 text-xs font-bold text-white">
                    {{ $pinjaman->firstItem() + $loop->index }}
                  </div>
                  <div class="flex flex-col justify-center">
                    <h6 class="mb-0 text-sm leading-normal">{{ $item->kode_pinjaman ?? 'PJM-' . $item->id }}</h6>
                    <p class="mb-0 text-xs leading-tight text-slate-400">
                      {{ $item->anggota->nomor_anggota ?? '-' }} - {{ $item->anggota->karyawan->nama ?? $item->karyawan->nama ?? '-' }}
                    </p>
                  </div>
                </div>
              </td>

              <td class="p-2 text-center align-middle bg-transparent border-b whitespace-nowrap shadow-transparent">
                <span class="text-xs font-semibold leading-tight text-slate-400">
                  {{ $item->tanggal_pinjaman ? $item->tanggal_pinjaman->format('d/m/Y') : '-' }}
                </span>
              </td>

              <td class="p-2 align-middle bg-transparent border-b shadow-transparent">
                <p class="mb-0 text-xs font-bold text-slate-600">{{ $item->dompet->nama_dompet ?? '-' }}</p>
                <p class="mb-0 text-xs text-slate-400">{{ $item->dompet?->akun?->kode_akun ?? '-' }}</p>
              </td>

              <td class="p-2 text-center align-middle bg-transparent border-b whitespace-nowrap shadow-transparent">Rp {{ number_format($item->jumlah_pinjaman, 0, ',', '.') }}</td>
              <td class="p-2 text-center align-middle bg-transparent border-b whitespace-nowrap shadow-transparent">Rp {{ number_format($item->plafon_pinjaman_snapshot ?? 0, 0, ',', '.') }}</td>
              <td class="p-2 text-center align-middle bg-transparent border-b whitespace-nowrap shadow-transparent">{{ $item->tenor_bulan }} bulan</td>
              <td class="p-2 text-center align-middle bg-transparent border-b whitespace-nowrap shadow-transparent">Rp {{ number_format($item->sisa_pinjaman, 0, ',', '.') }}</td>

              <td class="p-2 text-center align-middle bg-transparent border-b whitespace-nowrap shadow-transparent">
                <span class="kbsm-status {{ $item->status === 'aktif' ? 'kbsm-status--amber' : 'kbsm-status--slate' }}">
                  {{ $item->status === 'aktif' ? 'Aktif' : 'Lunas' }}
                </span>
              </td>

              <td class="p-2 text-center align-middle bg-transparent border-b whitespace-nowrap shadow-transparent">
                <a href="{{ route('pinjaman.show', $item) }}" class="kbsm-btn kbsm-btn--slate kbsm-btn--sm">
                  Jadwal
                </a>
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
