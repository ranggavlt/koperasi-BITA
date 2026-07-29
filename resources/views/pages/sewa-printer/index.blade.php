@extends('layout.main')

@section('content')
@php
  $badge = fn(string $status) => match ($status) {
    'draft'        => 'kbsm-status kbsm-status--slate',
    'dikonfirmasi' => 'kbsm-status kbsm-status--green',
    'berjalan'     => 'kbsm-status kbsm-status--amber',
    'selesai'      => 'kbsm-status kbsm-status--emerald',
    'dibatalkan'   => 'kbsm-status kbsm-status--slate',
    default        => 'kbsm-status kbsm-status--slate',
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
      <h1 class="kbsm-business-title">Sewa Printer</h1>
      <p class="kbsm-business-subtitle">Finance mencatat kebutuhan Karyawan, menyimpan snapshot vendor eksternal, dan sistem menghitung margin koperasi tetap 15% dari harga vendor.</p>
    </div>
  </div>

  <section class="kbsm-business-panel">
    <div class="kbsm-business-panel__header">
      <h2 class="kbsm-business-panel__title">Filter Sewa Printer</h2>
      <p class="kbsm-business-panel__copy">Filter transaksi vendor-based berdasarkan status, karyawan, dan rentang tanggal yang overlap dengan periode sewa.</p>
    </div>
    <form method="GET" action="{{ route('sewa-printer.index') }}" class="kbsm-business-filter kbsm-business-filter--sewa">
      <div class="kbsm-business-field">
        <label class="kbsm-business-label">Status</label>
        <select name="status" class="kbsm-business-control">
          <option value="">Semua</option>
          @foreach($statuses as $value => $label)
            <option value="{{ $value }}" {{ request('status') === $value ? 'selected' : '' }}>{{ $label }}</option>
          @endforeach
        </select>
      </div>
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
        <h2 class="kbsm-business-panel__title">Daftar Sewa Printer</h2>
        <p class="kbsm-business-panel__copy">Kontrak paid, berjalan, dan selesai tidak dapat diedit/hapus. Pembatalan hanya tersedia sebelum paid.</p>
      </div>
      <a href="{{ route('sewa-printer.create') }}" class="kbsm-business-add-button">+ TAMBAH SEWA PRINTER</a>
    </div>
    <div class="kbsm-business-table-wrap">
      <table class="kbsm-business-table">
        <thead>
          <tr>
            <th>Kode</th><th>Pemohon/Vendor</th><th>Periode</th><th>Detail Printer</th><th>Nominal</th><th>Status</th><th>Pembayaran</th><th>Posting</th><th>Aksi</th>
          </tr>
        </thead>
        <tbody>
          @forelse($sewaPrinter as $item)
            <tr>
              <td class="kbsm-business-code">{{ $item->kode_sewa }}</td>
              <td>
                <div class="kbsm-business-strong">{{ $item->karyawanPic?->nama ?? '-' }}</div>
                <div class="kbsm-business-muted">{{ $item->nama_perusahaan_snapshot }}</div>
                <div class="kbsm-business-muted">Vendor: {{ $item->vendor_nama }} / {{ $item->vendor_kontak }}</div>
                <div class="kbsm-business-muted">{{ $item->vendor_alamat }}</div>
              </td>
              <td>
                <div>{{ $item->mulai_tanggal->format('d/m/Y') }}</div>
                <div>{{ $item->selesai_tanggal->format('d/m/Y') }}</div>
              </td>
              <td>
                <div class="kbsm-business-muted">{{ $item->kebutuhan ?: '-' }}</div>
                <ul class="mt-2 space-y-1">
                  @foreach($item->details as $detail)
                    <li>
                      <span class="kbsm-business-strong">{{ $detail->kuantitas }} x {{ $detail->jenis_model_printer }}</span>
                      <span class="kbsm-business-muted"> @ Rp {{ number_format((int) $detail->harga_vendor_per_unit, 0, ',', '.') }} + margin Rp {{ number_format((int) $detail->margin_per_unit, 0, ',', '.') }}</span>
                    </li>
                  @endforeach
                </ul>
              </td>
              <td>
                <div>Vendor: Rp {{ number_format((int) $item->total_harga_vendor, 0, ',', '.') }}</div>
                <div>Margin: Rp {{ number_format((int) $item->total_margin, 0, ',', '.') }}</div>
                <div class="kbsm-business-strong">Tagihan: Rp {{ number_format((int) $item->total_tagihan_perusahaan, 0, ',', '.') }}</div>
              </td>
              <td>
                <span class="{{ $badge($item->status) }}">{{ $item->status_label }}</span>
                <div class="kbsm-business-muted">Payment: {{ $item->status_pembayaran }}</div>
              </td>
              <td class="kbsm-business-muted">
                @if($item->pembayaran)
                  Terima: {{ $item->pembayaran->metode_penerimaan }} / {{ $item->pembayaran->dompetPenerimaan->nama_dompet ?? '-' }}<br>
                  Vendor: {{ $item->pembayaran->metode_pembayaran_vendor }} / {{ $item->pembayaran->dompetVendor->nama_dompet ?? '-' }}<br>
                  {{ $item->pembayaran->paid_at->format('d/m/Y H:i') }}
                @else
                  Belum bayar
                @endif
              </td>
              <td class="kbsm-business-muted">
                <div>Jurnal kontrak: {{ $item->jurnal->count() }}</div>
                <div>Jurnal bayar: {{ $item->pembayaran?->jurnal?->count() ?? 0 }}</div>
                <div>Mutasi: {{ $item->pembayaran?->mutasiKas?->count() ?? 0 }}</div>
              </td>
              <td>
                <div class="kbsm-business-inline-actions">
                  @if($item->status === 'draft')
                    <div class="kbsm-business-inline-row">
                      <a href="{{ route('sewa-printer.edit', $item) }}" class="kbsm-btn kbsm-btn--outline-slate kbsm-btn--sm">Edit Draft</a>
                      <form method="POST" action="{{ route('sewa-printer.confirm', $item) }}">@csrf<button class="kbsm-btn kbsm-btn--green kbsm-btn--sm">Konfirmasi</button></form>
                    </div>
                    <form method="POST" action="{{ route('sewa-printer.cancel', $item) }}" class="kbsm-business-inline-row" onsubmit="return confirm('Batalkan draft kontrak ini?')">
                      @csrf
                      <input name="alasan" required placeholder="Alasan pembatalan" class="kbsm-business-control">
                      <button class="kbsm-btn kbsm-btn--outline-red kbsm-btn--sm">Batalkan</button>
                    </form>
                  @endif

                  @if($item->status === 'dikonfirmasi' && $item->status_pembayaran === 'belum_bayar')
                    <form method="POST" action="{{ route('sewa-printer.pay', $item) }}" class="kbsm-business-inline-row">
                      @csrf
                      <select name="metode_penerimaan" required class="kbsm-business-control">
                        <option value="tunai">Terima Tunai</option>
                        <option value="transfer_bank">Terima Transfer Bank</option>
                      </select>
                      <select name="dompet_penerimaan_id" required class="kbsm-business-control">
                        <option value="">Dompet Penerimaan</option>
                        @foreach($dompetOptions as $dompet)
                          <option value="{{ $dompet->id }}">{{ $dompet->nama_dompet }} ({{ $dompet->jenis_dompet }})</option>
                        @endforeach
                      </select>
                      <select name="metode_pembayaran_vendor" required class="kbsm-business-control">
                        <option value="tunai">Bayar Vendor Tunai</option>
                        <option value="transfer_bank">Bayar Vendor Transfer Bank</option>
                      </select>
                      <select name="dompet_vendor_id" required class="kbsm-business-control">
                        <option value="">Dompet Vendor</option>
                        @foreach($dompetOptions as $dompet)
                          <option value="{{ $dompet->id }}">{{ $dompet->nama_dompet }} ({{ $dompet->jenis_dompet }})</option>
                        @endforeach
                      </select>
                      <input type="number" name="jumlah_diterima" readonly value="{{ (int) $item->total_tagihan_perusahaan }}" class="kbsm-business-control">
                      <input type="number" name="jumlah_bayar_vendor" readonly value="{{ (int) $item->total_harga_vendor }}" class="kbsm-business-control">
                      <button class="kbsm-btn kbsm-btn--navy kbsm-btn--sm">Catat Pelunasan</button>
                    </form>
                    <form method="POST" action="{{ route('sewa-printer.cancel', $item) }}" class="kbsm-business-inline-row" onsubmit="return confirm('Batalkan kontrak yang belum dibayar ini?')">
                      @csrf
                      <input name="alasan" required placeholder="Alasan pembatalan" class="kbsm-business-control">
                      <button class="kbsm-btn kbsm-btn--outline-red kbsm-btn--sm">Batalkan</button>
                    </form>
                  @endif

                  @if($item->status === 'dikonfirmasi' && $item->status_pembayaran === 'paid')
                    <form method="POST" action="{{ route('sewa-printer.start', $item) }}">@csrf<button class="kbsm-btn kbsm-btn--amber kbsm-btn--sm">Mulai</button></form>
                  @endif

                  @if($item->status === 'berjalan')
                    <form method="POST" action="{{ route('sewa-printer.complete', $item) }}">@csrf<button class="kbsm-btn kbsm-btn--green kbsm-btn--sm">Selesai</button></form>
                  @endif
                </div>
              </td>
            </tr>
          @empty
            <tr><td colspan="9" class="kbsm-business-empty">Belum ada transaksi Sewa Printer.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
    <div class="kbsm-business-pagination">{{ $sewaPrinter->links() }}</div>
  </section>
</div>
@endsection
