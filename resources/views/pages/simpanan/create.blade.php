@extends('layout.main')

@section('content')
@php
  $selectedAnggota = old('anggota_id');
  $selectedMetode = old('metode_pembayaran', \App\Models\Simpanan::METODE_TUNAI);
  $selectedDompet = old('dompet_id');
@endphp

<div class="kbsm-business-page">
  @if ($errors->any())
    <div class="kbsm-business-alert kbsm-business-alert--danger">
      <ul>
        @foreach ($errors->all() as $error)
          <li>{{ $error }}</li>
        @endforeach
      </ul>
    </div>
  @endif

  @if (! $jenisManasuka && ! $jenisWajib)
    <div class="kbsm-business-alert kbsm-business-alert--danger">
      Master Simpanan aktif belum dikonfigurasi. Aktifkan satu master Simpanan terlebih dahulu.
    </div>
  @endif

  <div class="kbsm-business-header kbsm-business-form-header">
    <div>
      <p class="kbsm-business-eyebrow">Simpan Pinjam</p>
      <h1 class="kbsm-business-title">Transaksi Simpanan</h1>
      <p class="kbsm-business-subtitle">Finance mencatat setoran atau penarikan Simpanan langsung melalui Kas/Bank. Transaksi posted tidak bisa diedit atau dihapus.</p>
    </div>
    <a href="{{ route('simpanan.index') }}" class="kbsm-business-back-link">Kembali ke Daftar Simpanan</a>
  </div>

  <section class="kbsm-business-panel">
    <div class="kbsm-business-panel__header">
      <h2 class="kbsm-business-panel__title">Form Transaksi Simpanan</h2>
      <p class="kbsm-business-panel__copy">Server tetap menghitung saldo akhir dan memvalidasi Dompet, COA, status Anggota, serta saldo tersedia.</p>
    </div>

    <form method="POST" action="{{ route('simpanan.store') }}" class="kbsm-business-form" data-simpanan-manasuka-form data-saldo-base="{{ url('/simpanan/saldo-manasuka') }}">
      @csrf
      <input type="hidden" name="idempotency_key" value="{{ old('idempotency_key', 'simpanan-manasuka:' . \Illuminate\Support\Str::uuid()) }}">

      <section class="kbsm-business-section">
        <h3 class="kbsm-business-section__title">Pemilik Saldo</h3>
        <p class="kbsm-business-section__copy">Hanya Anggota aktif dengan Karyawan aktif dan siklus keanggotaan aktif yang muncul di pilihan ini.</p>
        <div class="kbsm-business-grid">
          <div class="kbsm-business-field">
            <label class="kbsm-business-label">Anggota</label>
            <select name="anggota_id" required class="kbsm-business-control" data-anggota-select>
              <option value="">Pilih Anggota</option>
              @foreach($anggota as $item)
                <option value="{{ $item->id }}" {{ (string) $selectedAnggota === (string) $item->id ? 'selected' : '' }}>
                  {{ $item->nomor_anggota }} - {{ $item->karyawan?->nama ?? '-' }}
                </option>
              @endforeach
            </select>
          </div>
          <div class="kbsm-business-field">
            <label class="kbsm-business-label">Jenis Simpanan</label>
            <select name="jenis_simpanan_id" required class="kbsm-business-control">
              <option value="">Pilih Jenis</option>
              @if($jenisWajib)
                <option value="{{ $jenisWajib->id }}" {{ old('jenis_simpanan_id') == $jenisWajib->id ? 'selected' : '' }}>{{ $jenisWajib->nama_jenis }}</option>
              @endif
              @if($jenisManasuka)
                <option value="{{ $jenisManasuka->id }}" {{ old('jenis_simpanan_id') == $jenisManasuka->id ? 'selected' : '' }}>{{ $jenisManasuka->nama_jenis }}</option>
              @endif
            </select>
          </div>
          <div class="kbsm-business-field">
            <label class="kbsm-business-label">Saldo Tersedia (Manasuka)</label>
            <div class="kbsm-business-readonly" data-saldo-display>Rp 0</div>
          </div>
          <div class="kbsm-business-field">
            <label class="kbsm-business-label">Status Posting</label>
            <div class="kbsm-business-readonly">Posted langsung</div>
          </div>
        </div>
      </section>

      <section class="kbsm-business-section">
        <h3 class="kbsm-business-section__title">Transaksi dan Kas/Bank</h3>
        <div class="kbsm-business-grid">
          <div class="kbsm-business-field">
            <label class="kbsm-business-label">Jenis Transaksi</label>
            <select name="jenis_transaksi" required class="kbsm-business-control">
              <option value="{{ \App\Models\Simpanan::JENIS_SETORAN }}" {{ $selectedMetode && old('jenis_transaksi', \App\Models\Simpanan::JENIS_SETORAN) === \App\Models\Simpanan::JENIS_SETORAN ? 'selected' : '' }}>Setoran</option>
              <option value="{{ \App\Models\Simpanan::JENIS_PENARIKAN }}" {{ old('jenis_transaksi') === \App\Models\Simpanan::JENIS_PENARIKAN ? 'selected' : '' }}>Penarikan</option>
            </select>
          </div>
          <div class="kbsm-business-field">
            <label class="kbsm-business-label">Tanggal</label>
            <input type="date" name="tanggal" required value="{{ old('tanggal', now(config('app.timezone', 'Asia/Jakarta'))->toDateString()) }}" class="kbsm-business-control">
          </div>
          <div class="kbsm-business-field">
            <label class="kbsm-business-label">Metode</label>
            <select name="metode_pembayaran" required class="kbsm-business-control" data-metode-select>
              <option value="{{ \App\Models\Simpanan::METODE_TUNAI }}" {{ $selectedMetode === \App\Models\Simpanan::METODE_TUNAI ? 'selected' : '' }}>Tunai</option>
              <option value="{{ \App\Models\Simpanan::METODE_TRANSFER_BANK }}" {{ $selectedMetode === \App\Models\Simpanan::METODE_TRANSFER_BANK ? 'selected' : '' }}>Transfer Bank</option>
            </select>
          </div>
          <div class="kbsm-business-field">
            <label class="kbsm-business-label">Dompet Kas/Bank</label>
            <select name="dompet_id" required class="kbsm-business-control" data-dompet-select>
              <option value="">Pilih Dompet</option>
              @foreach($dompet as $item)
                <option value="{{ $item->id }}" data-jenis="{{ $item->jenis_dompet }}" {{ (string) $selectedDompet === (string) $item->id ? 'selected' : '' }}>
                  {{ $item->nama_dompet }} ({{ strtoupper($item->jenis_dompet ?? '-') }}) - {{ $item->akun?->kode_akun }} {{ $item->akun?->nama_akun }}
                </option>
              @endforeach
            </select>
          </div>
          <div class="kbsm-business-field">
            <label class="kbsm-business-label">Nominal</label>
            <div class="kbsm-money-input">
              <span>Rp</span>
              <input type="number" name="jumlah" min="1" step="1" required value="{{ old('jumlah') }}" class="kbsm-business-control" placeholder="100000">
            </div>
          </div>
          <div class="kbsm-business-field">
            <label class="kbsm-business-label">Nomor Referensi</label>
            <input type="text" name="nomor_referensi" maxlength="80" value="{{ old('nomor_referensi') }}" class="kbsm-business-control" placeholder="Opsional, mis. slip/bukti transfer">
          </div>
          <div class="kbsm-business-field kbsm-business-field--full">
            <label class="kbsm-business-label">Keterangan</label>
            <textarea name="keterangan" rows="3" maxlength="1000" class="kbsm-business-control" placeholder="Tambahkan keterangan bila diperlukan">{{ old('keterangan') }}</textarea>
          </div>
        </div>
      </section>

      <section class="kbsm-business-section">
        <h3 class="kbsm-business-section__title">Ringkasan Posting</h3>
        <div class="kbsm-business-summary kbsm-business-summary--form">
          <article class="kbsm-business-summary-card kbsm-business-summary-card--green">
            <p class="kbsm-business-summary-label">Setoran</p>
            <p class="kbsm-business-summary-value">Debit Dompet</p>
            <p class="kbsm-business-muted">Kredit Simpanan Manasuka.</p>
          </article>
          <article class="kbsm-business-summary-card kbsm-business-summary-card--gold">
            <p class="kbsm-business-summary-label">Penarikan</p>
            <p class="kbsm-business-summary-value">Kredit Dompet</p>
            <p class="kbsm-business-muted">Debit Simpanan Manasuka.</p>
          </article>
          <article class="kbsm-business-summary-card kbsm-business-summary-card--navy">
            <p class="kbsm-business-summary-label">Koreksi</p>
            <p class="kbsm-business-summary-value">Reversal Penuh</p>
            <p class="kbsm-business-muted">Tidak ada edit/hapus transaksi posted.</p>
          </article>
        </div>
        <div class="kbsm-business-actions">
          <button class="kbsm-btn kbsm-btn--navy" {{ ! $jenisManasuka ? 'disabled' : '' }}>Posting Transaksi</button>
          <a href="{{ route('simpanan.index') }}" class="kbsm-btn kbsm-btn--outline-slate">Batal</a>
        </div>
      </section>
    </form>
  </section>
</div>

<script>
  (function () {
    const form = document.querySelector('[data-simpanan-manasuka-form]');
    if (!form) return;

    const anggotaSelect = form.querySelector('[data-anggota-select]');
    const saldoDisplay = form.querySelector('[data-saldo-display]');
    const metodeSelect = form.querySelector('[data-metode-select]');
    const dompetSelect = form.querySelector('[data-dompet-select]');
    const saldoBase = form.dataset.saldoBase;

    function syncDompetOptions() {
      const expected = metodeSelect.value === '{{ \App\Models\Simpanan::METODE_TRANSFER_BANK }}' ? 'bank' : 'kas';
      Array.from(dompetSelect.options).forEach((option) => {
        if (!option.value) {
          option.hidden = false;
          return;
        }
        option.hidden = option.dataset.jenis !== expected;
      });

      const selected = dompetSelect.selectedOptions[0];
      if (selected && selected.hidden) {
        dompetSelect.value = '';
      }
    }

    async function loadSaldo() {
      if (!anggotaSelect.value) {
        saldoDisplay.textContent = 'Rp 0';
        return;
      }

      saldoDisplay.textContent = 'Memuat...';
      try {
        const response = await fetch(`${saldoBase}/${anggotaSelect.value}`, {
          headers: { 'Accept': 'application/json' }
        });
        const payload = await response.json();
        saldoDisplay.textContent = payload.saldo_formatted || 'Rp 0';
      } catch (error) {
        saldoDisplay.textContent = 'Saldo tidak tersedia';
      }
    }

    metodeSelect.addEventListener('change', syncDompetOptions);
    anggotaSelect.addEventListener('change', loadSaldo);
    syncDompetOptions();
    loadSaldo();
  })();
</script>
@endsection
