@extends('layout.main')

@section('content')
@php
  $badge = fn(string $status) => match ($status) {
    'draft'        => 'kbsm-status kbsm-status--slate',
    'dikonfirmasi' => 'kbsm-status kbsm-status--green',
    'berjalan'     => 'kbsm-status kbsm-status--amber',
    'selesai'      => 'kbsm-status kbsm-status--emerald',
    'dibatalkan'   => 'kbsm-status kbsm-status--slate',
    'refunded'      => 'kbsm-status kbsm-status--slate',
    default        => 'kbsm-status kbsm-status--slate',
  };
  $paymentLabel = fn(?string $value) => match ($value) {
    'belum_bayar' => 'Belum Bayar',
    'paid' => 'Paid',
    'refunded' => 'Refunded',
    default => $value ? ucfirst(str_replace('_', ' ', $value)) : '-',
  };
  $methodLabel = fn(?string $value) => match ($value) {
    'tunai' => 'Tunai',
    'transfer_bank' => 'Transfer Bank',
    default => $value ? ucfirst(str_replace('_', ' ', $value)) : '-',
  };
  $hardwareTypeLabel = fn(?string $value) => $jenisHardwareOptions[$value] ?? ucfirst((string) $value);
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
      <h1 class="kbsm-business-title">Sewa Hardware</h1>
      <p class="kbsm-business-subtitle">Finance mencatat kebutuhan Karyawan, menyimpan snapshot vendor eksternal, dan sistem menghitung margin koperasi tetap 15% dari harga vendor.</p>
    </div>
  </div>

  <section class="kbsm-business-panel">
    <div class="kbsm-business-panel__header">
      <h2 class="kbsm-business-panel__title">Filter Sewa Hardware</h2>
      <p class="kbsm-business-panel__copy">Filter transaksi vendor-based berdasarkan status, karyawan, dan rentang tanggal yang overlap dengan periode sewa.</p>
    </div>
    <form method="GET" action="{{ route('sewa-hardware.index') }}" class="kbsm-business-filter kbsm-business-filter--sewa">
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
        @if(request()->hasAny(['status', 'karyawan_id', 'tanggal_dari', 'tanggal_sampai']))
          <a href="{{ route('sewa-hardware.index') }}" class="kbsm-btn kbsm-btn--outline-slate">Reset</a>
        @endif
      </div>
    </form>
  </section>

  <section class="kbsm-business-panel">
    <div class="kbsm-business-panel__header kbsm-business-panel__header--action">
      <div>
        <h2 class="kbsm-business-panel__title">Daftar Sewa Hardware</h2>
        <p class="kbsm-business-panel__copy">Kontrak paid, berjalan, selesai, dan refunded bersifat immutable. Pembatalan hanya tersedia sebelum paid; refund penuh hanya sebelum berjalan.</p>
      </div>
      <a href="{{ route('sewa-hardware.create') }}" class="kbsm-business-add-button">+ Buat Sewa Hardware</a>
    </div>
    <div class="kbsm-business-table-wrap">
      <table class="kbsm-business-table">
        <thead>
          <tr>
            <th>Kode</th><th>Pemohon/Vendor</th><th>Periode</th><th>Detail Hardware</th><th>Nominal</th><th>Status</th><th>Pembayaran</th><th>Posting</th><th>Aksi</th>
          </tr>
        </thead>
        <tbody>
          @forelse($sewaHardware as $item)
            @php $action = $eligibility[$item->id]; @endphp
            <tr>
              <td class="kbsm-business-code">{{ $item->kode_sewa }}</td>
              <td>
                <div class="kbsm-business-strong">{{ $item->karyawan?->nama ?? '-' }}</div>
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
                      <span class="kbsm-business-strong">{{ $detail->kuantitas }} x {{ $hardwareTypeLabel($detail->jenis_hardware) }} - {{ $detail->nama_model_hardware }}</span>
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
                @if($action['is_overdue'])<span class="kbsm-status kbsm-status--gold">Lewat Jadwal</span>@endif
                <div class="rental-payment-state"><span>Vendor</span>{{ $action['vendor_status_label'] }}</div>
                <div class="rental-payment-state"><span>Perusahaan</span>{{ $action['company_status_label'] }}</div>
              </td>
              <td class="kbsm-business-muted">
                @if($item->pembayaran)
                  Terima: {{ $methodLabel($item->pembayaran->metode_penerimaan) }} / {{ $item->pembayaran->dompetPenerimaan->nama_dompet ?? '-' }}<br>
                  Vendor: {{ $methodLabel($item->pembayaran->metode_pembayaran_vendor) }} / {{ $item->pembayaran->dompetVendor->nama_dompet ?? '-' }}<br>
                  {{ $item->pembayaran->paid_at->format('d/m/Y H:i') }}
                  @if($item->pembayaran->status === 'refunded')
                    <br>Refund: {{ $item->pembayaran->refunded_at?->format('d/m/Y H:i') ?? '-' }}
                  @endif
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
                      <a href="{{ route('sewa-hardware.edit', $item) }}" class="kbsm-btn kbsm-btn--outline-slate kbsm-btn--sm">Edit Draft</a>
                      <form method="POST" action="{{ route('sewa-hardware.confirm', $item) }}">@csrf<button class="kbsm-btn kbsm-btn--green kbsm-btn--sm">Konfirmasi</button></form>
                    </div>
                  @endif

                  @if($action['needs_invoice_before_vendor'])
                    <a href="{{ route('invoice-penagihan.create',['perusahaan_id'=>$item->karyawan->perusahaan_id]) }}" class="kbsm-btn kbsm-btn--outline-slate kbsm-btn--sm">Buat Invoice</a>
                  @endif

                  @if($action['can_pay_vendor'])
                    <details class="rental-action"><summary class="kbsm-btn kbsm-btn--navy kbsm-btn--sm">Bayar Vendor</summary><form method="POST" action="{{ route('sewa-hardware.vendor.pay',$item) }}">@csrf<input type="hidden" name="jumlah" value="{{ (int)$item->total_harga_vendor }}"><select name="metode" class="kbsm-business-control" required><option value="tunai">Tunai</option><option value="transfer_bank">Bank</option></select><select name="dompet_id" class="kbsm-business-control" required><option value="">Dompet sumber</option>@foreach($dompetOptions as $dompet)<option value="{{ $dompet->id }}">{{ $dompet->nama_dompet }} ({{ $dompet->jenis_dompet }})</option>@endforeach</select><input type="date" name="tanggal_bayar" value="{{ today()->toDateString() }}" class="kbsm-business-control" required><input name="nomor_referensi" placeholder="Referensi (opsional)" class="kbsm-business-control"><button class="kbsm-btn kbsm-btn--navy kbsm-btn--sm">Simpan Pembayaran Vendor</button></form></details>
                  @endif
                  @if($action['can_start'])
                    <form method="POST" action="{{ route('sewa-hardware.start', $item) }}">@csrf<button class="kbsm-btn kbsm-btn--amber kbsm-btn--sm">Mulai</button></form>
                  @endif
                  @if($action['can_cancel'])
                    <details class="rental-action"><summary class="kbsm-btn kbsm-btn--outline-red kbsm-btn--sm">Batalkan Sewa</summary><form method="POST" action="{{ route('sewa-hardware.cancel',$item) }}">@csrf<p>Belum ada pembayaran vendor. Pembatalan tidak mengubah transaksi kas.</p><textarea name="alasan" class="kbsm-business-control" required placeholder="Alasan pembatalan"></textarea><button class="kbsm-btn kbsm-btn--outline-red kbsm-btn--sm">Konfirmasi Pembatalan</button></form></details>
                  @endif
                  @if($action['can_request_vendor_refund'])
                    <details class="rental-action"><summary class="kbsm-btn kbsm-btn--outline-red kbsm-btn--sm">Proses Pengembalian Dana Vendor</summary><form method="POST" action="{{ route('sewa-hardware.vendor.refund-request',$item) }}">@csrf<p>Sistem akan menunggu dana vendor benar-benar diterima kembali.</p><textarea name="alasan" class="kbsm-business-control" required></textarea><button class="kbsm-btn kbsm-btn--outline-red kbsm-btn--sm">Minta Pengembalian</button></form></details>
                  @endif
                  @if($action['waiting_vendor_refund'])
                    <span class="kbsm-status kbsm-status--gold">Menunggu Pengembalian Vendor</span><details class="rental-action"><summary class="kbsm-btn kbsm-btn--outline-slate kbsm-btn--sm">Konfirmasi Dana Kembali</summary><form method="POST" action="{{ route('sewa-hardware.vendor.refund-confirm',$item) }}">@csrf<input type="date" name="tanggal_pengembalian" value="{{ today()->toDateString() }}" class="kbsm-business-control" required><input name="nomor_referensi" class="kbsm-business-control" placeholder="Referensi (opsional)"><button class="kbsm-btn kbsm-btn--green kbsm-btn--sm">Dana Sudah Diterima</button></form></details>
                  @endif
                  @if($action['can_legacy_full_refund'])
                    <details class="rental-action"><summary class="kbsm-btn kbsm-btn--outline-red kbsm-btn--sm">Proses Refund Transaksi Lama</summary><form method="POST" action="{{ route('sewa-hardware.refund',$item) }}">@csrf<p>Arus vendor dan perusahaan lama akan dibalik penuh.</p><textarea name="alasan" class="kbsm-business-control" required></textarea><button class="kbsm-btn kbsm-btn--outline-red kbsm-btn--sm">Proses Refund Penuh</button></form></details>
                  @endif
                  @if($action['can_refund_company'])
                    <details class="rental-action"><summary class="kbsm-btn kbsm-btn--outline-red kbsm-btn--sm">Kembalikan Dana Perusahaan</summary><form method="POST" action="{{ route('sewa-hardware.company.refund',$item) }}">@csrf<select name="metode" class="kbsm-business-control"><option value="tunai">Tunai</option><option value="transfer_bank">Bank</option></select><select name="dompet_id" class="kbsm-business-control" required><option value="">Dompet sumber</option>@foreach($dompetOptions as $dompet)<option value="{{ $dompet->id }}">{{ $dompet->nama_dompet }}</option>@endforeach</select><input type="date" name="tanggal_pengembalian" value="{{ today()->toDateString() }}" class="kbsm-business-control" required><input name="nomor_referensi" class="kbsm-business-control" placeholder="Referensi (opsional)"><textarea name="alasan" class="kbsm-business-control" required></textarea><button class="kbsm-btn kbsm-btn--outline-red kbsm-btn--sm">Simpan Pengembalian</button></form></details>
                  @endif
                  @if($action['can_complete'])
                    <form method="POST" action="{{ route('sewa-hardware.complete', $item) }}">@csrf<button class="kbsm-btn kbsm-btn--green kbsm-btn--sm">Selesaikan Sewa</button></form>
                  @endif
                  @if($action['is_final'])
                    <details class="rental-action rental-action--readonly"><summary class="kbsm-btn kbsm-btn--outline-slate kbsm-btn--sm">Lihat Detail</summary><p>{{ $item->status_label }} pada {{ optional($item->completed_at ?? $item->cancelled_at ?? $item->refunded_at)->format('d/m/Y H:i') ?: '–' }}.</p></details>
                  @endif
                </div>
              </td>
            </tr>
          @empty
            <tr><td colspan="9" class="kbsm-business-empty">Belum ada transaksi Sewa Hardware.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
    <div class="kbsm-business-pagination">{{ $sewaHardware->links() }}</div>
  </section>
</div>
@endsection
