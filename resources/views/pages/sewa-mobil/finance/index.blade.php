@extends('layout.main')

@section('content')
@php
  $badge = fn(string $status) => match ($status) {
    'draft'      => 'kbsm-status kbsm-status--slate',
    'diajukan'   => 'kbsm-status kbsm-status--blue',
    'disetujui'  => 'kbsm-status kbsm-status--green',
    'ditolak'    => 'kbsm-status kbsm-status--red',
    'berjalan'   => 'kbsm-status kbsm-status--amber',
    'selesai'    => 'kbsm-status kbsm-status--emerald',
    'dibatalkan' => 'kbsm-status kbsm-status--slate',
    default      => 'kbsm-status kbsm-status--slate',
  };
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

  <div class="kbsm-business-header">
    <div>
      <p class="kbsm-business-eyebrow">Usaha Koperasi</p>
      <h1 class="kbsm-business-title">Sewa Mobil</h1>
      <p class="kbsm-business-subtitle">Vendor dibayar lebih dahulu dari Kas Operasional. Perusahaan BEE/BBS/BKM ditagih terpisah melalui invoice.</p>
    </div>
  </div>

  <section class="kbsm-business-panel">
    <div class="kbsm-business-panel__header">
      <h2 class="kbsm-business-panel__title">Filter Sewa Mobil</h2>
      <p class="kbsm-business-panel__copy">Gunakan filter Karyawan dan rentang tanggal untuk meninjau kontrak vendor.</p>
    </div>
    <form method="GET" action="{{ route('sewa-mobil.finance.index') }}" class="kbsm-business-filter kbsm-business-filter--sewa">
      <div class="kbsm-business-field">
        <label class="kbsm-business-label">Karyawan</label>
        <select name="karyawan_id" class="kbsm-business-control">
          <option value="">Semua</option>
          @foreach($karyawanOptions as $karyawan)
            <option value="{{ $karyawan->id }}" {{ (string) request('karyawan_id') === (string) $karyawan->id ? 'selected' : '' }}>{{ $karyawan->nama }}</option>
          @endforeach
        </select>
      </div>
      <div class="kbsm-business-field">
        <label class="kbsm-business-label">Tanggal Dari</label>
        <input type="date" name="tanggal_dari" value="{{ request('tanggal_dari') }}" class="kbsm-business-control">
      </div>
      <div class="kbsm-business-field">
        <label class="kbsm-business-label">Tanggal Sampai</label>
        <input type="date" name="tanggal_sampai" value="{{ request('tanggal_sampai') }}" class="kbsm-business-control">
      </div>
      <div class="kbsm-business-filter__actions">
        <button class="kbsm-btn kbsm-btn--navy">Filter</button>
      </div>
    </form>
  </section>

  <section class="kbsm-business-panel">
    <div class="kbsm-business-panel__header kbsm-business-panel__header--action">
      <div>
        <h2 class="kbsm-business-panel__title">Daftar Sewa Mobil</h2>
        <p class="kbsm-business-panel__copy">Transaksi tidak dapat dihapus permanen. Koreksi dilakukan lewat pembatalan/refund sebelum berjalan bila eligible.</p>
      </div>
      <a href="{{ route('sewa-mobil.finance.create') }}" class="kbsm-business-add-button">+ TAMBAH SEWA MOBIL</a>
    </div>
    <div class="kbsm-business-table-wrap">
      <table class="kbsm-business-table">
        <thead>
          <tr>
            <th>Kode</th><th>Karyawan</th><th>Vendor/Kendaraan</th><th>Kegiatan</th><th>Periode</th><th>Nilai</th><th>Status B2B</th><th>Approval</th><th>Posting</th><th>Aksi</th>
          </tr>
        </thead>
        <tbody>
          @forelse($sewaMobil as $item)
            <tr>
              <td class="kbsm-business-code">{{ $item->kode_sewa ?: 'Draft' }}</td>
              <td>
                <div class="kbsm-business-strong">{{ $item->karyawan->nama }}</div>
                <div class="kbsm-business-muted">{{ $item->karyawan->jabatan }} / {{ $item->karyawan->status_kerja }}</div>
                <div class="kbsm-business-muted">Dicatat: {{ $item->recorder->name ?? $item->creator->name ?? '-' }}</div>
              </td>
              <td>
                <div class="kbsm-business-strong">{{ $item->vendor_nama_snapshot }}</div>
                <div class="kbsm-business-muted">{{ $item->kendaraan_jenis_snapshot }} — {{ $item->kendaraan_merk_tipe_snapshot }}</div>
                <div class="kbsm-business-muted">{{ $item->nomor_polisi_snapshot }}</div>
              </td>
              <td>
                <div class="kbsm-business-strong">{{ $item->nama_kegiatan }}</div>
                <div class="kbsm-business-muted">{{ $item->lokasi_kegiatan }}</div>
                <div class="kbsm-business-muted">{{ $item->keterangan ?: '-' }}</div>
              </td>
              <td>
                <div>{{ $item->tanggal_mulai?->format('d/m/Y') ?? '-' }}</div>
                <div>{{ $item->tanggal_selesai?->format('d/m/Y') ?? '-' }}</div>
              </td>
              <td>
                <div>Vendor: Rp {{ number_format((int) $item->harga_vendor_total, 0, ',', '.') }}</div>
                <div>Markup: Rp {{ number_format((int) $item->markup_total, 0, ',', '.') }}</div>
                <div class="kbsm-business-strong">Tagihan: Rp {{ number_format((int) $item->total_tagihan_perusahaan, 0, ',', '.') }}</div>
              </td>
              <td>
                <span class="{{ $badge($item->status) }}">{{ $item->status_label }}</span>
                <div class="kbsm-business-muted">Vendor: {{ $item->pembayaranVendor ? 'dibayar' : 'belum dibayar' }}</div>
                <div class="kbsm-business-muted">Invoice: {{ $item->invoiceDetail?->invoice?->nomor_invoice ?? 'belum dibuat' }}</div>
              </td>
              <td class="kbsm-business-muted">
                {{ $item->nama_pengurus_snapshot ?: '-' }}<br>
                {{ $item->jabatan_pengurus_snapshot ?: '' }}<br>
                @if($item->approved_at){{ $item->approved_at->format('d/m/Y H:i') }}@endif
              </td>
              <td class="kbsm-business-muted">
                <div>Jurnal: {{ $item->jurnal->count() + ($item->pembayaran?->jurnal?->count() ?? 0) }}</div>
                <div>Mutasi: {{ $item->pembayaran?->mutasiKas?->count() ?? 0 }}</div>
              </td>
              <td>
                <div class="kbsm-business-inline-actions">
                  @if($item->status === 'draft')
                    <div class="kbsm-business-inline-row">
                      <a href="{{ route('sewa-mobil.finance.edit', $item) }}" class="kbsm-btn kbsm-btn--outline-slate kbsm-btn--sm">Edit Draft</a>
                      <form method="POST" action="{{ route('sewa-mobil.finance.submit', $item) }}">@csrf<button class="kbsm-btn kbsm-btn--green kbsm-btn--sm">Ajukan</button></form>
                    </div>
                    <form method="POST" action="{{ route('sewa-mobil.finance.cancel', $item) }}" class="kbsm-business-inline-row" onsubmit="return confirm('Batalkan draft sewa mobil ini?')">
                      @csrf
                      <input name="alasan" required placeholder="Alasan pembatalan" class="kbsm-business-control">
                      <button class="kbsm-btn kbsm-btn--outline-red kbsm-btn--sm">Batalkan</button>
                    </form>
                  @endif

                  @if($item->status === 'diajukan')
                    <form method="POST" action="{{ route('sewa-mobil.finance.approve', $item) }}" class="kbsm-business-inline-row">
                      @csrf
                      <select name="pengurus_penyetuju_id" required class="kbsm-business-control">
                        <option value="">Pengurus</option>
                        @foreach($pengurusOptions as $pengurus)
                          <option value="{{ $pengurus->id }}">{{ $pengurus->jabatan }} - {{ $pengurus->anggota->karyawan->nama }}</option>
                        @endforeach
                      </select>
                      <button class="kbsm-btn kbsm-btn--green kbsm-btn--sm">Setujui</button>
                    </form>
                    <form method="POST" action="{{ route('sewa-mobil.finance.reject', $item) }}" class="kbsm-business-inline-row">
                      @csrf
                      <input name="alasan" required placeholder="Alasan penolakan" class="kbsm-business-control">
                      <button class="kbsm-btn kbsm-btn--outline-red kbsm-btn--sm">Tolak</button>
                    </form>
                  @endif

                  @if($item->status === 'disetujui' && !$item->pembayaranVendor)
                    <form method="POST" action="{{ route('sewa-mobil.finance.pay-vendor', $item) }}" class="kbsm-business-inline-row">
                      @csrf
                      <select name="dompet_id" required class="kbsm-business-control">
                        <option value="">Kas Operasional</option>
                        @foreach($kasOperasionalOptions as $dompet)
                          <option value="{{ $dompet->id }}">{{ $dompet->nama_dompet }}</option>
                        @endforeach
                      </select>
                      <input type="date" name="tanggal_bayar" required value="{{ now()->toDateString() }}" class="kbsm-business-control">
                      <button class="kbsm-btn kbsm-btn--navy kbsm-btn--sm">Bayar Vendor</button>
                    </form>
                  @endif

                  @if($item->status === 'disetujui' && $item->pembayaranVendor)
                    <form method="POST" action="{{ route('sewa-mobil.finance.start', $item) }}">@csrf<button class="kbsm-btn kbsm-btn--amber kbsm-btn--sm">Mulai</button></form>
                  @endif

                  @if($item->status === 'berjalan')
                    <form method="POST" action="{{ route('sewa-mobil.finance.complete', $item) }}">@csrf<button class="kbsm-btn kbsm-btn--green kbsm-btn--sm">Selesai</button></form>
                  @endif

                  @if($item->status === 'disetujui')
                    <form method="POST" action="{{ route('sewa-mobil.finance.cancel', $item) }}" class="kbsm-business-inline-row" onsubmit="return confirm('Batalkan/refund sewa ini jika eligible?')">
                      @csrf
                      <input name="alasan" required placeholder="Alasan pembatalan" class="kbsm-business-control">
                      <button class="kbsm-btn kbsm-btn--outline-red kbsm-btn--sm">Batalkan/Refund</button>
                    </form>
                  @endif
                </div>
              </td>
            </tr>
          @empty
            <tr><td colspan="10" class="kbsm-business-empty">Belum ada transaksi Sewa Mobil.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
    <div class="kbsm-business-pagination">{{ $sewaMobil->links() }}</div>
  </section>
</div>
@endsection
