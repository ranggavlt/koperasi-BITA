@extends('layout.main')

@section('content')
@php
  $editing = $mode === 'edit';
  $kategoriOptions = \App\Models\JenisSimpanan::KATEGORI;
  $selectedKategori = old('kategori', $jenisSimpanan?->kategori ?? \App\Models\JenisSimpanan::KATEGORI_POKOK);
  $kodeOptions = \App\Models\JenisSimpanan::KODE_BY_KATEGORI;
@endphp

<div class="kbsm-business-page">
  @if ($errors->any())
    <div class="kbsm-business-alert kbsm-business-alert--danger">
      <ul class="mb-0 list-disc pl-5">
        @foreach ($errors->all() as $error)
          <li>{{ $error }}</li>
        @endforeach
      </ul>
    </div>
  @endif

  <div class="kbsm-business-form-header">
    <div>
      <p class="kbsm-business-eyebrow">Master Data</p>
      <h1 class="kbsm-business-title">{{ $editing ? 'Edit Jenis Simpanan' : 'Tambah Jenis Simpanan' }}</h1>
      <p class="kbsm-business-subtitle">Kode sistem ditentukan dari kategori resmi, bukan dari nama bebas.</p>
    </div>
    <a href="{{ route('jenis-simpanan.index') }}" class="kbsm-business-back-link">Kembali</a>
  </div>

  <section class="kbsm-business-panel">
    <form method="POST" action="{{ $editing ? route('jenis-simpanan.update', $jenisSimpanan) : route('jenis-simpanan.store') }}" class="kbsm-business-form">
      @csrf
      @if($editing)
        @method('PUT')
      @endif

      <div class="kbsm-business-section">
        <h2 class="kbsm-business-section__title">Identitas Master</h2>
        <p class="kbsm-business-section__copy">Pilih kategori resmi agar kode stabil dan aturan interval dapat divalidasi.</p>

        <div class="kbsm-business-grid">
          <div class="kbsm-business-field">
            <label class="kbsm-business-label" for="nama_jenis">Nama Jenis</label>
            <input id="nama_jenis" name="nama_jenis" required maxlength="100" value="{{ old('nama_jenis', $jenisSimpanan->nama_jenis ?? '') }}" class="kbsm-business-control" placeholder="Contoh: Simpanan Wajib">
          </div>

          <div class="kbsm-business-field">
            <label class="kbsm-business-label" for="kategori">Kategori</label>
            <select id="kategori" name="kategori" class="kbsm-business-control" data-kode-target="kode_sistem">
              @foreach($kategoriOptions as $value => $label)
                <option value="{{ $value }}" @selected($selectedKategori === $value) data-kode="{{ $kodeOptions[$value] ?? '' }}">{{ $label }}</option>
              @endforeach
            </select>
          </div>

          <div class="kbsm-business-field">
            <label class="kbsm-business-label" for="kode_sistem">Kode Stabil</label>
            <input id="kode_sistem" value="{{ $kodeOptions[$selectedKategori] ?? '-' }}" class="kbsm-business-control" readonly>
            <p class="kbsm-business-muted">Kode diset otomatis dari kategori dan divalidasi server-side.</p>
          </div>

          <div class="kbsm-business-field">
            <label class="kbsm-business-label" for="akun_id">Akun COA</label>
            <select id="akun_id" name="akun_id" required class="kbsm-business-control">
              <option value="">Pilih akun pencatatan</option>
              @foreach($akunSimpanan as $akunItem)
                <option value="{{ $akunItem->id }}" @selected((string) old('akun_id', $jenisSimpanan?->akun_id ?? '') === (string) $akunItem->id)>
                  {{ $akunItem->kode_akun }} - {{ $akunItem->nama_akun }} ({{ $akunItem->kategori_label }})
                </option>
              @endforeach
            </select>
          </div>
        </div>
      </div>

      <div class="kbsm-business-section">
        <h2 class="kbsm-business-section__title">Konfigurasi Keuangan</h2>
        <p class="kbsm-business-section__copy">Nominal Pokok/Wajib wajib lebih dari nol. Sukarela memakai nominal transaksi pada SP-3.</p>

        <div class="kbsm-business-grid">
          <div class="kbsm-business-field">
            <label class="kbsm-business-label" for="nominal_default">Nominal Default</label>
            <div class="kbsm-money-input">
              <span>Rp</span>
              <input id="nominal_default" name="nominal_default" type="number" min="0" step="1" required value="{{ old('nominal_default', $jenisSimpanan?->nominal_default !== null ? (int) $jenisSimpanan->nominal_default : 0) }}" class="kbsm-business-control" placeholder="100000">
            </div>
          </div>

          <div class="kbsm-business-field" id="interval-field">
            <label class="kbsm-business-label" for="interval_bulan">Interval Wajib</label>
            <select id="interval_bulan" name="interval_bulan" class="kbsm-business-control">
              <option value="">Tidak memakai interval</option>
              @for($i = 1; $i <= 12; $i++)
                <option value="{{ $i }}" @selected((string) old('interval_bulan', $jenisSimpanan?->interval_bulan ?? '') === (string) $i)>Setiap {{ $i }} bulan</option>
              @endfor
            </select>
          </div>

          <div class="kbsm-business-field">
            <label class="kbsm-business-label" for="berlaku_mulai">Berlaku Mulai</label>
            <input id="berlaku_mulai" name="berlaku_mulai" type="date" required value="{{ old('berlaku_mulai', $jenisSimpanan?->berlaku_mulai?->toDateString() ?? now(config('app.timezone', 'Asia/Jakarta'))->toDateString()) }}" class="kbsm-business-control">
          </div>

          <div class="kbsm-business-field">
            <label class="kbsm-business-label" for="aktif">Status</label>
            <select id="aktif" name="aktif" class="kbsm-business-control">
              <option value="1" @selected((string) old('aktif', isset($jenisSimpanan) ? (int) $jenisSimpanan->aktif : 1) === '1')>Aktif</option>
              <option value="0" @selected((string) old('aktif', isset($jenisSimpanan) ? (int) $jenisSimpanan->aktif : 1) === '0')>Nonaktif</option>
            </select>
          </div>

          <div class="kbsm-business-field kbsm-business-field--full">
            <label class="kbsm-business-label" for="keterangan">Keterangan</label>
            <textarea id="keterangan" name="keterangan" rows="3" maxlength="1000" class="kbsm-business-control" placeholder="Catatan internal master simpanan">{{ old('keterangan', $jenisSimpanan->keterangan ?? '') }}</textarea>
          </div>

          @if($editing)
            <div class="kbsm-business-field kbsm-business-field--full">
              <label class="kbsm-business-label" for="alasan_perubahan">Alasan Perubahan</label>
              <textarea id="alasan_perubahan" name="alasan_perubahan" rows="3" maxlength="1000" class="kbsm-business-control" placeholder="Wajib jika master aktif/terpakai diubah">{{ old('alasan_perubahan') }}</textarea>
            </div>
          @endif
        </div>
      </div>

      <div class="kbsm-business-actions">
        <button type="submit" class="kbsm-btn kbsm-btn--green">{{ $editing ? 'Simpan Perubahan' : 'Simpan Master' }}</button>
        <a href="{{ route('jenis-simpanan.index') }}" class="kbsm-btn kbsm-btn--outline-slate">Batal</a>
      </div>
    </form>
  </section>
</div>

<script>
  (function () {
    const kategori = document.getElementById('kategori');
    const kode = document.getElementById('kode_sistem');
    const intervalField = document.getElementById('interval-field');
    const interval = document.getElementById('interval_bulan');

    function syncKategori() {
      const option = kategori.options[kategori.selectedIndex];
      kode.value = option ? option.dataset.kode : '-';
      const isWajib = kategori.value === 'wajib';
      intervalField.style.display = isWajib ? 'flex' : 'none';
      if (!isWajib) {
        interval.value = '';
      }
    }

    kategori.addEventListener('change', syncKategori);
    syncKategori();
  })();
</script>
@endsection
