@extends('layout.main')

@section('content')
@php
  $formAction = $editData ? route('sewa-printer.update', $editData) : route('sewa-printer.store');
  $detailRows = collect(old('details', $editData?->details?->map(fn($d) => [
    'jenis_model_printer' => $d->jenis_model_printer,
    'spesifikasi_kebutuhan' => $d->spesifikasi_kebutuhan,
    'kuantitas' => (int) $d->kuantitas,
    'harga_vendor_per_unit' => (int) $d->harga_vendor_per_unit,
  ])->all() ?? []))->values();

  if ($detailRows->isEmpty()) {
    $detailRows = collect([[
      'jenis_model_printer' => '',
      'spesifikasi_kebutuhan' => '',
      'kuantitas' => 1,
      'harga_vendor_per_unit' => '',
    ]]);
  }
@endphp

<div class="kbsm-business-page">
  @if (session('success'))
    <div class="kbsm-business-alert kbsm-business-alert--success">{{ session('success') }}</div>
  @endif
  @if ($errors->any())
    <div class="kbsm-business-alert kbsm-business-alert--danger">
      <ul class="mb-0 list-disc pl-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
    </div>
  @endif

  <div class="kbsm-business-header kbsm-business-form-header">
    <div>
      <p class="kbsm-business-eyebrow">Usaha Koperasi</p>
      <h1 class="kbsm-business-title">{{ $editData ? 'Edit Draft Sewa Hardware' : 'Tambah Sewa Hardware' }}</h1>
      <p class="kbsm-business-subtitle">Finance mencatat kebutuhan Karyawan, menyimpan snapshot vendor eksternal, dan sistem menghitung margin koperasi tetap 15% dari harga vendor.</p>
    </div>
    <a href="{{ route('sewa-printer.index') }}" class="kbsm-business-back-link">Kembali ke Daftar Sewa Hardware</a>
  </div>

  <section class="kbsm-business-panel">
    <div class="kbsm-business-panel__header">
      <h2 class="kbsm-business-panel__title">{{ $editData ? 'Edit Draft Sewa Hardware' : 'Buat Draft Sewa Hardware' }}</h2>
      <p class="kbsm-business-panel__copy">Printer vendor tidak dicatat sebagai aset koperasi. Total dihitung ulang oleh server dengan Rupiah bulat.</p>
    </div>

    <form method="POST" action="{{ $formAction }}" class="kbsm-business-form" data-sewa-printer-form>
      @csrf
      @if($editData) @method('PUT') @endif

      <section class="kbsm-business-section">
        <h3 class="kbsm-business-section__title">Pemohon dan Kebutuhan</h3>
        <div class="kbsm-business-grid">
          <div class="kbsm-business-field">
            <label class="kbsm-business-label">Pemohon Printer Aktif</label>
            <select name="karyawan_id" required class="kbsm-business-control">
              <option value="">Pilih Karyawan aktif</option>
              @foreach($karyawanOptions as $karyawan)
                <option value="{{ $karyawan->id }}" {{ (string) old('karyawan_id', $editData?->karyawan_id) === (string) $karyawan->id ? 'selected' : '' }}>{{ $karyawan->nama }} - {{ $karyawan->jabatan }}</option>
              @endforeach
            </select>
          </div>
          <div class="kbsm-business-field">
            <label class="kbsm-business-label">Perusahaan</label>
            <div class="kbsm-business-readonly">{{ config('koperasi.nama_perusahaan_penyewa', 'Bita Enarcon Engineering') }}</div>
          </div>
          <div class="kbsm-business-field kbsm-business-field--full">
            <label class="kbsm-business-label">Kebutuhan Printer</label>
            <textarea name="kebutuhan" rows="3" maxlength="1000" class="kbsm-business-control" placeholder="Contoh: printer warna untuk event/site office sementara">{{ old('kebutuhan', $editData?->kebutuhan) }}</textarea>
          </div>
        </div>
      </section>

      <section class="kbsm-business-section">
        <h3 class="kbsm-business-section__title">Periode Sewa</h3>
        <div class="kbsm-business-grid">
          <div class="kbsm-business-field">
            <label class="kbsm-business-label">Tanggal Mulai</label>
            <input type="date" name="mulai_tanggal" required value="{{ old('mulai_tanggal', $editData?->mulai_tanggal?->toDateString()) }}" class="kbsm-business-control">
          </div>
          <div class="kbsm-business-field">
            <label class="kbsm-business-label">Tanggal Selesai</label>
            <input type="date" name="selesai_tanggal" required value="{{ old('selesai_tanggal', $editData?->selesai_tanggal?->toDateString()) }}" class="kbsm-business-control">
          </div>
        </div>
      </section>

      <section class="kbsm-business-section">
        <h3 class="kbsm-business-section__title">Identitas Vendor</h3>
        <p class="kbsm-business-section__copy">Vendor disimpan sebagai snapshot transaksi. Tidak ada Master Vendor.</p>
        <div class="kbsm-business-grid">
          <div class="kbsm-business-field">
            <label class="kbsm-business-label">Nama Vendor</label>
            <input name="vendor_nama" required maxlength="150" value="{{ old('vendor_nama', $editData?->vendor_nama) }}" class="kbsm-business-control" placeholder="CV Vendor Printer">
          </div>
          <div class="kbsm-business-field">
            <label class="kbsm-business-label">Nomor Kontak</label>
            <input name="vendor_kontak" required maxlength="80" value="{{ old('vendor_kontak', $editData?->vendor_kontak) }}" class="kbsm-business-control" placeholder="0812...">
          </div>
          <div class="kbsm-business-field kbsm-business-field--full">
            <label class="kbsm-business-label">Alamat Vendor</label>
            <textarea name="vendor_alamat" required rows="3" maxlength="1000" class="kbsm-business-control" placeholder="Alamat lengkap vendor">{{ old('vendor_alamat', $editData?->vendor_alamat) }}</textarea>
          </div>
        </div>
      </section>

      <section class="kbsm-business-section">
        <h3 class="kbsm-business-section__title">Detail Printer</h3>
        <p class="kbsm-business-section__copy">Tambah baris sesuai kebutuhan. Margin 15% dihitung per unit menggunakan pembulatan Rupiah half-up.</p>
        <div class="kbsm-business-table-wrap">
          <table class="kbsm-business-detail-table">
            <thead>
              <tr>
                <th>Jenis/Model Printer</th>
                <th>Spesifikasi</th>
                <th>Kuantitas</th>
                <th>Harga Vendor/Unit</th>
                <th>Margin/Unit</th>
                <th>Tagihan/Unit</th>
                <th>Aksi</th>
              </tr>
            </thead>
            <tbody data-printer-detail-body>
              @foreach($detailRows as $i => $row)
                <tr data-printer-row>
                  <td><input name="details[{{ $i }}][jenis_model_printer]" required maxlength="150" value="{{ $row['jenis_model_printer'] ?? '' }}" class="kbsm-business-control" placeholder="Canon / Epson / HP"></td>
                  <td><textarea name="details[{{ $i }}][spesifikasi_kebutuhan]" rows="2" maxlength="1000" class="kbsm-business-control" placeholder="A3, duplex, warna, scan">{{ $row['spesifikasi_kebutuhan'] ?? '' }}</textarea></td>
                  <td><input type="number" min="1" name="details[{{ $i }}][kuantitas]" value="{{ $row['kuantitas'] ?? 1 }}" class="kbsm-business-control" data-printer-qty></td>
                  <td><input type="number" min="1" name="details[{{ $i }}][harga_vendor_per_unit]" value="{{ $row['harga_vendor_per_unit'] ?? '' }}" class="kbsm-business-control" data-printer-price placeholder="1000000"></td>
                  <td class="kbsm-business-strong" data-printer-margin>Rp 0</td>
                  <td class="kbsm-business-strong" data-printer-total>Rp 0</td>
                  <td><button type="button" class="kbsm-btn kbsm-btn--outline-red kbsm-btn--sm" data-printer-remove>Hapus</button></td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
        <div class="kbsm-business-detail-actions">
          <button type="button" class="kbsm-btn kbsm-btn--outline-green" data-printer-add>+ Tambah Printer</button>
        </div>
      </section>

      <section class="kbsm-business-section">
        <h3 class="kbsm-business-section__title">Harga Vendor dan Margin</h3>
        <div class="kbsm-business-summary">
          <div class="kbsm-business-summary-card kbsm-business-summary-card--gold">
            <p class="kbsm-business-summary-label">Total Harga Vendor</p>
            <p class="kbsm-business-summary-value" data-printer-total-vendor>Rp 0</p>
          </div>
          <div class="kbsm-business-summary-card kbsm-business-summary-card--green">
            <p class="kbsm-business-summary-label">Total Margin 15%</p>
            <p class="kbsm-business-summary-value" data-printer-total-margin>Rp 0</p>
          </div>
          <div class="kbsm-business-summary-card kbsm-business-summary-card--navy">
            <p class="kbsm-business-summary-label">Total Tagihan Perusahaan</p>
            <p class="kbsm-business-summary-value" data-printer-grand-total>Rp 0</p>
          </div>
        </div>
      </section>

      <section class="kbsm-business-section">
        <h3 class="kbsm-business-section__title">Pembayaran Vendor dan Pembayaran Perusahaan</h3>
        <p class="kbsm-business-section__copy">Pelunasan dilakukan setelah kontrak dikonfirmasi. Draft hanya menyimpan kebutuhan, vendor, dan snapshot harga.</p>
        <div class="kbsm-business-actions">
          <button class="kbsm-btn kbsm-btn--navy">{{ $editData ? 'Simpan Draft' : 'Buat Draft' }}</button>
          @if($editData)
            <a href="{{ route('sewa-printer.index') }}" class="kbsm-btn kbsm-btn--outline-slate">Batal Edit</a>
          @endif
        </div>
      </section>
    </form>
  </section>
</div>

<script>
  document.addEventListener('DOMContentLoaded', () => {
    const form = document.querySelector('[data-sewa-printer-form]');
    if (!form) return;

    const rupiah = (value) => new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(value || 0);
    const body = form.querySelector('[data-printer-detail-body]');
    const addButton = form.querySelector('[data-printer-add]');
    const totalVendor = form.querySelector('[data-printer-total-vendor]');
    const totalMargin = form.querySelector('[data-printer-total-margin]');
    const grandTotal = form.querySelector('[data-printer-grand-total]');

    const rowTemplate = () => {
      const row = document.createElement('tr');
      row.setAttribute('data-printer-row', '');
      row.innerHTML = `
        <td><input required maxlength="150" class="kbsm-business-control" placeholder="Canon / Epson / HP" data-name="jenis_model_printer"></td>
        <td><textarea rows="2" maxlength="1000" class="kbsm-business-control" placeholder="A3, duplex, warna, scan" data-name="spesifikasi_kebutuhan"></textarea></td>
        <td><input type="number" min="1" value="1" class="kbsm-business-control" data-printer-qty data-name="kuantitas"></td>
        <td><input type="number" min="1" class="kbsm-business-control" data-printer-price data-name="harga_vendor_per_unit" placeholder="1000000"></td>
        <td class="kbsm-business-strong" data-printer-margin>Rp 0</td>
        <td class="kbsm-business-strong" data-printer-total>Rp 0</td>
        <td><button type="button" class="kbsm-btn kbsm-btn--outline-red kbsm-btn--sm" data-printer-remove>Hapus</button></td>
      `;
      return row;
    };

    const reindex = () => {
      body.querySelectorAll('[data-printer-row]').forEach((row, index) => {
        row.querySelectorAll('[data-name]').forEach((input) => {
          input.name = `details[${index}][${input.dataset.name}]`;
        });
      });
    };

    const refresh = () => {
      let vendor = 0;
      let margin = 0;

      const rows = body.querySelectorAll('[data-printer-row]');
      rows.forEach((row) => {
        const qty = parseInt(row.querySelector('[data-printer-qty]')?.value || '0', 10) || 0;
        const price = parseInt(row.querySelector('[data-printer-price]')?.value || '0', 10) || 0;
        const marginUnit = Math.floor(((price * 15) + 50) / 100);
        const tagihanUnit = price + marginUnit;

        vendor += price * qty;
        margin += marginUnit * qty;
        row.querySelector('[data-printer-margin]').textContent = rupiah(marginUnit);
        row.querySelector('[data-printer-total]').textContent = rupiah(tagihanUnit);
        row.querySelector('[data-printer-remove]').disabled = rows.length <= 1;
      });

      totalVendor.textContent = rupiah(vendor);
      totalMargin.textContent = rupiah(margin);
      grandTotal.textContent = rupiah(vendor + margin);
    };

    body.addEventListener('input', refresh);
    body.addEventListener('click', (event) => {
      const button = event.target.closest('[data-printer-remove]');
      if (!button) return;

      const rows = body.querySelectorAll('[data-printer-row]');
      if (rows.length <= 1) return;

      button.closest('[data-printer-row]').remove();
      reindex();
      refresh();
    });

    addButton.addEventListener('click', () => {
      body.appendChild(rowTemplate());
      reindex();
      refresh();
    });

    body.querySelectorAll('[data-printer-row]').forEach((row, index) => {
      row.querySelectorAll('input, textarea').forEach((input) => {
        const match = input.name.match(/\[([^\]]+)\]$/);
        if (match) input.dataset.name = match[1];
      });
    });
    reindex();
    refresh();
  });
</script>
@endsection
