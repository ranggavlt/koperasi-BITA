@extends('layout.main')

@section('content')
@php
  $detail = $editData?->details?->first();
  $formAction = $editData ? route('beban-operasional.update', $editData) : route('beban-operasional.store');
  $selectedAkunId = old('akun_id', $detail?->akun_id);
  $selectedDompetId = old('dompet_id', $editData?->dompet_id);
  $nominal = old('nominal', $detail ? (int) $detail->nominal : null);
@endphp

<div class="kbsm-business-page">
  @if (session('success'))
    <div class="kbsm-business-alert kbsm-business-alert--success">{{ session('success') }}</div>
  @endif
  @if ($errors->any())
    <div class="kbsm-business-alert kbsm-business-alert--danger">
      <ul>
        @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
      </ul>
    </div>
  @endif

  <div class="kbsm-business-header kbsm-business-form-header">
    <div>
      <p class="kbsm-business-eyebrow">Operasional</p>
      <h1 class="kbsm-business-title">{{ $editData ? 'Edit Draft Beban Operasional' : 'Input Beban Operasional' }}</h1>
      <p class="kbsm-business-subtitle">Transaksi posted akan mencatat Debit pada akun Beban dan Kredit pada Dompet Kas/Bank yang dipilih.</p>
    </div>
    <a href="{{ route('beban-operasional.index') }}" class="kbsm-business-back-link">Kembali ke Daftar Beban Operasional</a>
  </div>

  <section class="kbsm-business-panel">
    <div class="kbsm-business-panel__header">
      <h2 class="kbsm-business-panel__title">{{ $editData ? 'Edit Draft Beban Operasional' : 'Simpan Draft Beban Operasional' }}</h2>
      <p class="kbsm-business-panel__copy">Satu transaksi berisi satu akun Beban, satu nominal, dan satu sumber pembayaran Kas/Bank. Draft tidak membuat Mutasi Kas atau Jurnal.</p>
    </div>

    <form method="POST" action="{{ $formAction }}" class="kbsm-business-form" data-beban-operasional-form>
      @csrf
      @if($editData) @method('PUT') @endif

      <section class="kbsm-business-section">
        <h3 class="kbsm-business-section__title">Detail Beban</h3>
        <div class="kbsm-business-grid">
          <div class="kbsm-business-field">
            <label class="kbsm-business-label">Tanggal Beban</label>
            <input type="date" name="tanggal_beban" required value="{{ old('tanggal_beban', $editData?->tanggal_beban?->toDateString() ?? now()->toDateString()) }}" class="kbsm-business-control">
          </div>
          <div class="kbsm-business-field">
            <label class="kbsm-business-label">Nominal</label>
            <div class="kbsm-money-input">
              <span>Rp</span>
              <input type="number" min="1" name="nominal" required value="{{ $nominal }}" class="kbsm-business-control" placeholder="250000">
            </div>
          </div>
          <div class="kbsm-business-field">
            <label class="kbsm-business-label">Akun Beban</label>
            <select name="akun_id" required class="kbsm-business-control">
              <option value="">Pilih akun Beban</option>
              @foreach($akunOptions as $akun)
                <option value="{{ $akun->id }}" {{ (string) $selectedAkunId === (string) $akun->id ? 'selected' : '' }}>{{ $akun->kode_akun }} - {{ $akun->nama_akun }}</option>
              @endforeach
            </select>
          </div>
          <div class="kbsm-business-field">
            <label class="kbsm-business-label">Dibayar dari Kas/Bank</label>
            <select name="dompet_id" required class="kbsm-business-control">
              <option value="">Pilih Dompet Kas/Bank</option>
              @foreach($dompetOptions as $dompet)
                <option value="{{ $dompet->id }}" {{ (string) $selectedDompetId === (string) $dompet->id ? 'selected' : '' }}>{{ $dompet->nama_dompet }} ({{ $dompet->jenis_dompet }}) - {{ $dompet->akun?->kode_akun }} {{ $dompet->akun?->nama_akun }}</option>
              @endforeach
            </select>
          </div>
          <div class="kbsm-business-field">
            <label class="kbsm-business-label">Nomor Referensi</label>
            <input name="nomor_referensi" maxlength="50" value="{{ old('nomor_referensi', $editData?->nomor_referensi) }}" class="kbsm-business-control" placeholder="Opsional, mis. INV/OPS/001">
          </div>
          <div class="kbsm-business-field">
            <label class="kbsm-business-label">Keterangan/Memo</label>
            <textarea name="keterangan" required rows="3" maxlength="1000" class="kbsm-business-control" placeholder="Contoh: ATK dan administrasi kantor">{{ old('keterangan', $editData?->keterangan ?? $detail?->keterangan) }}</textarea>
          </div>
        </div>
      </section>

      <section class="kbsm-business-section">
        <h3 class="kbsm-business-section__title">Lifecycle Aman</h3>
        <p class="kbsm-business-section__copy">Simpan sebagai draft terlebih dahulu. Posting dilakukan dari daftar dan akan membuat Mutasi Kas keluar serta Jurnal berimbang. Posted dan reversed tidak dapat diedit/hapus.</p>
        <div class="kbsm-business-actions">
          <button class="kbsm-btn kbsm-btn--navy">{{ $editData ? 'Simpan Draft' : 'Simpan Draft' }}</button>
          <a href="{{ route('beban-operasional.index') }}" class="kbsm-btn kbsm-btn--outline-slate">Batal</a>
        </div>
      </section>
    </form>
  </section>
</div>
@endsection
