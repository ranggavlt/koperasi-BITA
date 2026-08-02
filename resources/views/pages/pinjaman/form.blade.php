@extends('layout.main')

@section('content')
@php
  $editing = (bool) $pinjaman;
  $selectedDompetId = (string) old('dompet_id', $pinjaman?->dompet_id);
  $selectedDompet = $dompet->firstWhere('id', (int) $selectedDompetId);
@endphp

<div class="w-full px-6 py-6 mx-auto">
  @if ($errors->any())
    <div class="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
      <ul class="mb-0 list-disc pl-5">
        @foreach ($errors->all() as $error)
          <li>{{ $error }}</li>
        @endforeach
      </ul>
    </div>
  @endif

  <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
    <div>
      <h1 class="text-lg font-bold text-slate-700">{{ $editing ? 'Edit Pengajuan Pinjaman' : 'Buat & Cairkan Pinjaman' }}</h1>
      <p class="mt-1 text-sm text-slate-400">Pinjaman yang dibuat akan otomatis disetujui, dicairkan, dan jadwal cicilannya akan terbentuk.</p>
    </div>
    <a href="{{ route('pinjaman.index') }}" class="kbsm-btn kbsm-btn--outline-slate">Kembali</a>
  </div>

  <div class="rounded-2xl border border-slate-100 bg-white p-6 shadow-soft-xl">
    <form method="POST" action="{{ $editing ? route('pinjaman.update', $pinjaman) : route('pinjaman.store') }}">
      @csrf
      @if($editing)
        @method('PUT')
      @endif

      <div class="grid gap-4 md:grid-cols-2">
        <div class="md:col-span-2">
          <label class="mb-2 block text-xs font-bold uppercase text-slate-600">Anggota Aktif</label>
          <select name="anggota_id" class="kbsm-focus block w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm">
            <option value="">-- Pilih Anggota --</option>
            @foreach($anggota as $item)
              <option value="{{ $item->id }}" @selected((string) old('anggota_id', $pinjaman?->anggota_id) === (string) $item->id)>
                {{ $item->nomor_anggota }} - {{ $item->karyawan->nama ?? '-' }} | Plafon Rp {{ number_format((float) $item->plafon_pinjaman, 0, ',', '.') }}
              </option>
            @endforeach
          </select>
          <p class="mt-1 text-xs text-slate-400">Yang tampil hanya Anggota aktif, Karyawan aktif, dan tidak memiliki proses Pinjaman terbuka.</p>
        </div>

        <div>
          <label class="mb-2 block text-xs font-bold uppercase text-slate-600">Tanggal Pengajuan</label>
          <input type="date" name="tanggal_pengajuan"
            value="{{ old('tanggal_pengajuan', optional($pinjaman?->tanggal_pengajuan)->format('Y-m-d') ?? now(config('app.timezone'))->format('Y-m-d')) }}"
            class="kbsm-focus block w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm">
        </div>

        <div>
          <label class="mb-2 block text-xs font-bold uppercase text-slate-600">Bunga</label>
          <input type="text" value="0%" readonly class="block w-full rounded-lg border border-gray-200 bg-gray-100 px-3 py-2 text-sm text-slate-500">
        </div>

        <div>
          <label class="mb-2 block text-xs font-bold uppercase text-slate-600">Nominal Pengajuan</label>
          <div class="relative flex items-center">
            <span class="absolute left-3 text-sm text-slate-500 font-medium pointer-events-none">Rp</span>
            <input type="number" name="jumlah_pinjaman" min="1" max="5000000" step="1"
              value="{{ old('jumlah_pinjaman', $pinjaman?->jumlah_pinjaman ? (int) $pinjaman->jumlah_pinjaman : '') }}"
              class="kbsm-focus block w-full rounded-lg border border-gray-300 bg-white pl-9 pr-3 py-2 text-sm"
              placeholder="0">
          </div>
          <p class="mt-1 text-xs text-slate-400">Maksimal sistem Rp5.000.000 dan tetap dibatasi plafon Anggota.</p>
        </div>

        <div>
          <label class="mb-2 block text-xs font-bold uppercase text-slate-600">Tenor</label>
          <select name="tenor_bulan" class="kbsm-focus block w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm">
            <option value="">-- Pilih Tenor --</option>
            @foreach([1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12] as $t)
              <option value="{{ $t }}" @selected(old('tenor_bulan', $pinjaman?->tenor_bulan) == $t)>{{ $t }} Bulan</option>
            @endforeach
          </select>
        </div>

        <div>
          <label class="mb-2 block text-xs font-bold uppercase text-slate-600">Biaya Admin</label>
          <input type="text" value="Rp 50.000" readonly class="block w-full rounded-lg border border-gray-200 bg-gray-100 px-3 py-2 text-sm text-slate-500">
          <input type="hidden" name="biaya_admin" value="50000">
        </div>

        <div>
          <label class="mb-2 block text-xs font-bold uppercase text-slate-600">Opsi Bayar Admin</label>
          <select name="cara_bayar_admin" class="kbsm-focus block w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm">
            <option value="potong_pinjaman" @selected(old('cara_bayar_admin', $pinjaman?->cara_bayar_admin) === 'potong_pinjaman')>Potong Pinjaman (Cair dipotong 50rb)</option>
            <option value="tunai" @selected(old('cara_bayar_admin', $pinjaman?->cara_bayar_admin) === 'tunai')>Bayar Tunai (Cair Bulat/Utuh)</option>
          </select>
        </div>

        <div class="md:col-span-2">
          <label class="mb-2 block text-xs font-bold uppercase text-slate-600">Tujuan/Keterangan Pinjaman</label>
          <textarea name="keterangan" rows="4"
            class="kbsm-focus block w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm"
            placeholder="Contoh: kebutuhan pendidikan, kesehatan, atau kebutuhan keluarga">{{ old('keterangan', $pinjaman?->keterangan) }}</textarea>
        </div>
        <div>
          <label class="mb-2 block text-xs font-bold uppercase text-slate-600">Sumber Dana (Dompet)</label>
          <div class="pinjaman-dompet-picker" data-dompet-picker>
            <input type="hidden" name="dompet_id" value="{{ $selectedDompetId }}" data-dompet-input>
            <button type="button" class="pinjaman-dompet-picker__trigger" data-dompet-trigger aria-expanded="false" aria-haspopup="listbox">
              <span data-dompet-label>{{ $selectedDompet?->nama_dompet ?? '-- Pilih Kas/Bank --' }}</span>
              <svg class="pinjaman-dompet-picker__chevron" viewBox="0 0 20 20" aria-hidden="true"><path d="m5 7.5 5 5 5-5" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" /></svg>
            </button>
            <div class="pinjaman-dompet-picker__options hidden" data-dompet-options role="listbox" aria-label="Pilih sumber dana">
              @forelse($dompet as $d)
                <button type="button" class="pinjaman-dompet-picker__option {{ (string) $d->id === $selectedDompetId ? 'is-selected' : '' }}" data-dompet-option data-dompet-id="{{ $d->id }}" data-dompet-label="{{ $d->nama_dompet }}" role="option" aria-selected="{{ (string) $d->id === $selectedDompetId ? 'true' : 'false' }}">
                  <span class="pinjaman-dompet-picker__option-name">{{ $d->nama_dompet }}</span>
                  <span class="pinjaman-dompet-picker__option-meta">{{ strtoupper($d->jenis_dompet) }} &bull; Saldo Rp {{ number_format((float) $d->saldo, 0, ',', '.') }}</span>
                </button>
              @empty
                <p class="pinjaman-dompet-picker__empty">Belum ada Dompet Kas/Bank. Tambahkan melalui menu Dompet Koperasi.</p>
              @endforelse
            </div>
          </div>
          <p class="mt-1 text-xs text-slate-400">Pilih Kas atau Bank yang digunakan untuk mencairkan Pinjaman.</p>
        </div>
      </div>

      <div class="mt-6 flex flex-wrap gap-3">
        <button class="kbsm-btn kbsm-btn--navy">{{ $editing ? 'Simpan' : 'Buat & Cairkan Pinjaman' }}</button>
        <a href="{{ route('pinjaman.index') }}" class="kbsm-btn kbsm-btn--outline-slate">Batal</a>
      </div>
    </form>
  </div>
</div>

<script>
  document.addEventListener('DOMContentLoaded', () => {
    const pickers = Array.from(document.querySelectorAll('[data-dompet-picker]'));

    const closePicker = (picker) => {
      const trigger = picker.querySelector('[data-dompet-trigger]');
      const options = picker.querySelector('[data-dompet-options]');

      options.classList.add('hidden');
      trigger.setAttribute('aria-expanded', 'false');
    };

    pickers.forEach((picker) => {
      const trigger = picker.querySelector('[data-dompet-trigger]');
      const options = picker.querySelector('[data-dompet-options]');
      const input = picker.querySelector('[data-dompet-input]');
      const label = picker.querySelector('[data-dompet-label]');

      trigger.addEventListener('click', () => {
        const willOpen = options.classList.contains('hidden');
        pickers.forEach(closePicker);

        if (willOpen) {
          options.classList.remove('hidden');
          trigger.setAttribute('aria-expanded', 'true');
        }
      });

      options.querySelectorAll('[data-dompet-option]').forEach((option) => {
        option.addEventListener('click', () => {
          input.value = option.dataset.dompetId;
          label.textContent = option.dataset.dompetLabel;

          options.querySelectorAll('[data-dompet-option]').forEach((item) => {
            const selected = item === option;
            item.classList.toggle('is-selected', selected);
            item.setAttribute('aria-selected', selected ? 'true' : 'false');
          });

          closePicker(picker);
        });
      });
    });

    document.addEventListener('click', (event) => {
      pickers.filter((picker) => !picker.contains(event.target)).forEach(closePicker);
    });

    document.addEventListener('keydown', (event) => {
      if (event.key === 'Escape') {
        pickers.forEach(closePicker);
      }
    });
  });
</script>
@endsection
