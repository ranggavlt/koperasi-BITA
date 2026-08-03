@extends('layout.main')

@section('content')
@php
  $formAction = $editData ? route('sewa-mobil.finance.update', $editData) : route('sewa-mobil.finance.store');
  $selectedKaryawanId = old('karyawan_id', $editData?->karyawan_id);
  $hargaVendor = (int) old('total_harga_vendor', $editData?->total_harga_vendor ?? 0);
  $markup = (int) old('total_markup', $editData?->total_markup ?? 0);
  $tagihan = $hargaVendor + $markup;
  $previewHari = (int) old('jumlah_hari', $editData?->jumlah_hari ?? 0);
@endphp

<div class="kbsm-business-page">
  @if($errors->any())<div class="kbsm-business-alert kbsm-business-alert--danger"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
  <div class="kbsm-business-header kbsm-business-form-header">
    <div>
      <p class="kbsm-business-eyebrow">Usaha Koperasi</p>
      <h1 class="kbsm-business-title">{{ $editData ? 'Edit Draft Sewa Mobil' : 'Tambah Sewa Mobil' }}</h1>
      <p class="kbsm-business-subtitle">Finance mencatat kebutuhan Karyawan BITA, menyimpan snapshot vendor dan kendaraan, lalu server menghitung total tagihan dari harga vendor plus markup.</p>
    </div>
    <a href="{{ route('sewa-mobil.finance.index') }}" class="kbsm-business-back-link">Kembali ke Daftar Sewa Mobil</a>
  </div>
  <section class="kbsm-business-panel">
    <div class="kbsm-business-panel__header">
      <h2 class="kbsm-business-panel__title">{{ $editData ? 'Edit Draft Sewa Mobil' : 'Buat Draft Sewa Mobil' }}</h2>
      <p class="kbsm-business-panel__copy">Kode SWM dibuat saat draft diajukan. Plat boleh kosong saat draft, tetapi wajib lengkap sebelum disetujui Pengurus.</p>
    </div>

    <form method="POST" action="{{ $formAction }}" class="kbsm-business-form" data-sewa-mobil-form>
      @csrf
      @if($editData) @method('PUT') @endif

      <section class="kbsm-business-section">
        <h3 class="kbsm-business-section__title">Pemohon</h3>
        <p class="kbsm-business-section__copy">Sewa Mobil hanya dicatat oleh Finance untuk Karyawan aktif perusahaan BITA.</p>
        <div class="kbsm-business-grid">
          <div class="kbsm-business-field">
            <label class="kbsm-business-label">Karyawan</label>
            <select name="karyawan_id" required class="kbsm-business-control">
              <option value="">Pilih Karyawan aktif</option>
              @foreach($karyawanOptions as $karyawan)
                <option value="{{ $karyawan->id }}" {{ (string) $selectedKaryawanId === (string) $karyawan->id ? 'selected' : '' }}>{{ $karyawan->nama }} - {{ $karyawan->jabatan }}</option>
              @endforeach
            </select>
          </div>
          <div class="kbsm-business-field">
            <label class="kbsm-business-label">Perusahaan Penyewa</label>
            <div class="kbsm-business-readonly">{{ config('koperasi.nama_perusahaan_penyewa', 'Bita Enarcon Engineering') }}</div>
          </div>
        </div>
      </section>

      <section class="kbsm-business-section">
        <h3 class="kbsm-business-section__title">Periode Sewa</h3>
        <p class="kbsm-business-section__copy">Tanggal dihitung inklusif hanya sebagai informasi durasi; tagihan tidak dikalikan per hari.</p>
        <div class="kbsm-business-grid">
          <div class="kbsm-business-field">
            <label class="kbsm-business-label">Tanggal Mulai</label>
            <input type="date" name="tanggal_mulai" required value="{{ old('tanggal_mulai', $editData?->tanggal_mulai?->toDateString()) }}" class="kbsm-business-control" data-sewa-mobil-start>
          </div>
          <div class="kbsm-business-field">
            <label class="kbsm-business-label">Tanggal Selesai</label>
            <input type="date" name="tanggal_selesai" required value="{{ old('tanggal_selesai', $editData?->tanggal_selesai?->toDateString()) }}" class="kbsm-business-control" data-sewa-mobil-end>
          </div>
        </div>
      </section>

      <section class="kbsm-business-section">
        <h3 class="kbsm-business-section__title">Identitas Vendor</h3>
        <p class="kbsm-business-section__copy">Vendor disimpan sebagai snapshot transaksi. Tidak ada Master Vendor dan tidak ada relasi ke Master Mobil.</p>
        <div class="kbsm-business-grid">
          <div class="kbsm-business-field">
            <label class="kbsm-business-label">Nama Vendor</label>
            <input name="vendor_nama" required maxlength="150" value="{{ old('vendor_nama', $editData?->vendor_nama) }}" class="kbsm-business-control" placeholder="CV Rental Mobil">
          </div>
          <div class="kbsm-business-field">
            <label class="kbsm-business-label">Nomor Kontak Vendor</label>
            <input name="vendor_kontak" required maxlength="80" value="{{ old('vendor_kontak', $editData?->vendor_kontak) }}" class="kbsm-business-control" placeholder="0812...">
          </div>
          <div class="kbsm-business-field kbsm-business-field--full">
            <label class="kbsm-business-label">Alamat Vendor</label>
            <textarea name="vendor_alamat" required rows="3" maxlength="1000" class="kbsm-business-control" placeholder="Alamat lengkap vendor">{{ old('vendor_alamat', $editData?->vendor_alamat) }}</textarea>
          </div>
        </div>
      </section>

      <section class="kbsm-business-section">
        <h3 class="kbsm-business-section__title">Detail Kendaraan</h3>
        <p class="kbsm-business-section__copy">Kendaraan vendor dicatat sebagai snapshot, bukan aset koperasi. Plat dipakai untuk pemeriksaan bentrok jadwal saat approval.</p>
        <div class="kbsm-business-grid">
          <div class="kbsm-business-field">
            <label class="kbsm-business-label">Jenis Kendaraan</label>
            <input name="jenis_kendaraan" required maxlength="80" value="{{ old('jenis_kendaraan', $editData?->jenis_kendaraan) }}" class="kbsm-business-control" placeholder="MPV / SUV / Minibus">
          </div>
          <div class="kbsm-business-field">
            <label class="kbsm-business-label">Merek</label>
            <input name="merek_kendaraan" required maxlength="80" value="{{ old('merek_kendaraan', $editData?->merek_kendaraan) }}" class="kbsm-business-control" placeholder="Toyota">
          </div>
          <div class="kbsm-business-field">
            <label class="kbsm-business-label">Model</label>
            <input name="model_kendaraan" required maxlength="120" value="{{ old('model_kendaraan', $editData?->model_kendaraan) }}" class="kbsm-business-control" placeholder="Innova Reborn">
          </div>
          <div class="kbsm-business-field">
            <label class="kbsm-business-label">Plat Nomor</label>
            <input name="plat_nomor_snapshot" maxlength="30" value="{{ old('plat_nomor_snapshot', $editData?->plat_nomor_snapshot) }}" class="kbsm-business-control" placeholder="B 1234 KBS">
          </div>
          <div class="kbsm-business-field">
            <label class="kbsm-business-label">Tahun</label>
            <input type="number" name="tahun_kendaraan" required min="1980" max="{{ now()->addYear()->year }}" value="{{ old('tahun_kendaraan', $editData?->tahun_kendaraan) }}" class="kbsm-business-control" placeholder="{{ now()->year }}">
          </div>
          <div class="kbsm-business-field">
            <label class="kbsm-business-label">Warna</label>
            <input name="warna_kendaraan" required maxlength="80" value="{{ old('warna_kendaraan', $editData?->warna_kendaraan) }}" class="kbsm-business-control" placeholder="Hitam">
          </div>
          <div class="kbsm-business-field kbsm-business-field--full">
            <label class="kbsm-business-label">Keterangan Kendaraan</label>
            <textarea name="keterangan_kendaraan" rows="3" maxlength="1000" class="kbsm-business-control" placeholder="Catatan armada, fasilitas sopir, atau kebutuhan khusus">{{ old('keterangan_kendaraan', $editData?->keterangan_kendaraan) }}</textarea>
          </div>
        </div>
      </section>

      <section class="kbsm-business-section">
        <h3 class="kbsm-business-section__title">Harga Vendor dan Margin</h3>
        <p class="kbsm-business-section__copy">Masukkan nominal total untuk seluruh periode. Server menyimpan snapshot final dalam Rupiah bulat.</p>
        <div class="kbsm-business-grid">
          <div class="kbsm-business-field">
            <label class="kbsm-business-label">Total Harga Vendor</label>
            <input type="number" min="1" name="total_harga_vendor" required value="{{ $hargaVendor ?: '' }}" class="kbsm-business-control" data-sewa-mobil-vendor-price placeholder="1200000">
          </div>
          <div class="kbsm-business-field">
            <label class="kbsm-business-label">Total Markup Koperasi</label>
            <input type="number" min="1" name="total_markup" required value="{{ $markup ?: '' }}" class="kbsm-business-control" data-sewa-mobil-markup placeholder="225000">
          </div>
        </div>
        <div class="kbsm-business-summary kbsm-business-summary--form">
          <div class="kbsm-business-summary-card kbsm-business-summary-card--green">
            <p class="kbsm-business-summary-label">Jumlah Hari</p>
            <p class="kbsm-business-summary-value" data-sewa-mobil-days>{{ $previewHari ?: '-' }}</p>
          </div>
          <div class="kbsm-business-summary-card kbsm-business-summary-card--gold">
            <p class="kbsm-business-summary-label">Total Vendor</p>
            <p class="kbsm-business-summary-value" data-sewa-mobil-vendor-summary>Rp {{ number_format($hargaVendor, 0, ',', '.') }}</p>
          </div>
          <div class="kbsm-business-summary-card kbsm-business-summary-card--navy">
            <p class="kbsm-business-summary-label">Total Tagihan Perusahaan</p>
            <p class="kbsm-business-summary-value" data-sewa-mobil-total>Rp {{ number_format($tagihan, 0, ',', '.') }}</p>
          </div>
        </div>
      </section>

      <section class="kbsm-business-section">
        <h3 class="kbsm-business-section__title">Kegiatan dan Keterangan</h3>
        <div class="kbsm-business-grid">
          <div class="kbsm-business-field">
            <label class="kbsm-business-label">Nama Kegiatan</label>
            <input name="nama_kegiatan" required maxlength="150" value="{{ old('nama_kegiatan', $editData?->nama_kegiatan) }}" class="kbsm-business-control" placeholder="Kunjungan proyek">
          </div>
          <div class="kbsm-business-field">
            <label class="kbsm-business-label">Lokasi Kegiatan</label>
            <input name="lokasi_kegiatan" required maxlength="150" value="{{ old('lokasi_kegiatan', $editData?->lokasi_kegiatan) }}" class="kbsm-business-control" placeholder="Area Jakarta">
          </div>
          <div class="kbsm-business-field kbsm-business-field--full">
            <label class="kbsm-business-label">Keterangan</label>
            <textarea name="keterangan" rows="3" maxlength="1000" class="kbsm-business-control" placeholder="Catatan tambahan">{{ old('keterangan', $editData?->keterangan) }}</textarea>
          </div>
        </div>
      </section>

      <section class="kbsm-business-section">
        <h3 class="kbsm-business-section__title">Pembayaran dan Approval</h3>
        <p class="kbsm-business-section__copy">Approval Pengurus dan pembayaran penuh dicatat setelah draft diajukan. Tidak ada self-service Karyawan atau pemotongan payroll.</p>
        <div class="kbsm-business-actions">
          <button type="submit" class="kbsm-btn kbsm-btn--navy">{{ $editData ? 'Simpan Draft' : 'Buat Draft' }}</button>
          @if($editData)
            <a href="{{ route('sewa-mobil.finance.index') }}" class="kbsm-btn kbsm-btn--outline-slate">Batal Edit</a>
          @endif
        </div>
      </section>
    </form>
  </section>
</div>

<script>
  document.addEventListener('DOMContentLoaded', () => {
    const form = document.querySelector('[data-sewa-mobil-form]');
    if (!form) return;

    const rupiah = (value) => new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(value || 0);
    const start = form.querySelector('[data-sewa-mobil-start]');
    const end = form.querySelector('[data-sewa-mobil-end]');
    const vendorInput = form.querySelector('[data-sewa-mobil-vendor-price]');
    const markupInput = form.querySelector('[data-sewa-mobil-markup]');
    const days = form.querySelector('[data-sewa-mobil-days]');
    const vendorSummary = form.querySelector('[data-sewa-mobil-vendor-summary]');
    const total = form.querySelector('[data-sewa-mobil-total]');

    const diffDaysInclusive = () => {
      if (!start.value || !end.value) return 0;
      const startDate = new Date(`${start.value}T00:00:00+07:00`);
      const endDate = new Date(`${end.value}T00:00:00+07:00`);
      if (Number.isNaN(startDate.getTime()) || Number.isNaN(endDate.getTime()) || endDate < startDate) return 0;
      return Math.floor((endDate - startDate) / 86400000) + 1;
    };

    const numeric = (input) => parseInt(input?.value || '0', 10) || 0;

    const refresh = () => {
      const jumlahHari = diffDaysInclusive();
      const hargaVendor = numeric(vendorInput);
      const markup = numeric(markupInput);
      days.textContent = jumlahHari || '-';
      vendorSummary.textContent = rupiah(hargaVendor);
      total.textContent = rupiah(hargaVendor + markup);
    };

    [start, end, vendorInput, markupInput].forEach((input) => input?.addEventListener('input', refresh));
    refresh();
  });
</script>
@endsection
