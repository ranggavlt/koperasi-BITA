@extends('layout.main')

@section('content')
@php
  $statusLabels = \App\Models\SewaMobil::statusLabels();
  $badge = fn(string $status) => match ($status) {
    'draft'      => 'kbsm-status kbsm-status--slate',
    'diajukan'   => 'kbsm-status kbsm-status--blue',
    'disetujui'  => 'kbsm-status kbsm-status--green',
    'ditolak'    => 'kbsm-status kbsm-status--red',
    'berjalan'   => 'kbsm-status kbsm-status--amber',
    'selesai'    => 'kbsm-status kbsm-status--emerald',
    'dibatalkan' => 'kbsm-status kbsm-status--slate',
    'refunded'   => 'kbsm-status kbsm-status--red',
    default      => 'kbsm-status kbsm-status--slate',
  };
  $paymentLabel = fn(?string $status) => match ($status) {
    'paid' => 'Lunas',
    'refunded' => 'Refunded',
    default => 'Belum Bayar',
  };
  $methodLabel = fn(?string $method) => match ($method) {
    'tunai' => 'Tunai',
    'transfer_bank' => 'Transfer Bank',
    default => '-',
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
      <p class="kbsm-business-subtitle">Finance mencatat sewa mobil vendor untuk Karyawan aktif BITA. Kendaraan dan vendor disimpan sebagai snapshot transaksi, bukan Master Mobil.</p>
    </div>
  </div>

  <section class="kbsm-business-panel">
    <div class="kbsm-business-panel__header">
      <h2 class="kbsm-business-panel__title">Filter Sewa Mobil</h2>
      <p class="kbsm-business-panel__copy">Filter berdasarkan Karyawan, status, vendor, plat, dan periode yang bersinggungan dengan tanggal pilihan.</p>
    </div>
    <form method="GET" action="{{ route('sewa-mobil.finance.index') }}" class="kbsm-business-filter kbsm-business-filter--sewa-mobil">
      <div class="kbsm-business-field">
        <label class="kbsm-business-label">Karyawan</label>
        <select name="karyawan_id" class="kbsm-business-control">
          <option value="">Semua Karyawan</option>
          @foreach($karyawanOptions as $karyawan)
            <option value="{{ $karyawan->id }}" {{ (string) request('karyawan_id') === (string) $karyawan->id ? 'selected' : '' }}>{{ $karyawan->nama }}</option>
          @endforeach
        </select>
      </div>
      <div class="kbsm-business-field">
        <label class="kbsm-business-label">Status</label>
        <select name="status" class="kbsm-business-control">
          <option value="">Semua Status</option>
          @foreach($statusLabels as $status => $label)
            <option value="{{ $status }}" {{ request('status') === $status ? 'selected' : '' }}>{{ $label }}</option>
          @endforeach
        </select>
      </div>
      <div class="kbsm-business-field">
        <label class="kbsm-business-label">Vendor</label>
        <input name="vendor" value="{{ request('vendor') }}" class="kbsm-business-control" placeholder="Nama vendor">
      </div>
      <div class="kbsm-business-field">
        <label class="kbsm-business-label">Plat Nomor</label>
        <input name="plat_nomor" value="{{ request('plat_nomor') }}" class="kbsm-business-control" placeholder="B 1234 KBS">
      </div>
      <div class="kbsm-business-field">
        <label class="kbsm-business-label">Tanggal Dari</label>
        <input type="date" name="tanggal_dari" value="{{ request('tanggal_dari') }}" class="kbsm-business-control">
      </div>
      <div class="kbsm-business-field">
        <label class="kbsm-business-label">Tanggal Sampai</label>
        <input type="date" name="tanggal_sampai" value="{{ request('tanggal_sampai') }}" class="kbsm-business-control">
      </div>
      <div class="kbsm-business-filter__actions kbsm-business-filter__actions--split">
        <button class="kbsm-btn kbsm-btn--navy">Filter</button>
        <a href="{{ route('sewa-mobil.finance.index') }}" class="kbsm-btn kbsm-btn--outline-slate">Reset</a>
      </div>
    </form>
  </section>

  <section class="kbsm-business-panel">
    <div class="kbsm-business-panel__header kbsm-business-panel__header--action">
      <div>
        <h2 class="kbsm-business-panel__title">Daftar Sewa Mobil</h2>
        <p class="kbsm-business-panel__copy">Tidak ada edit atau hapus transaksi setelah posting. Koreksi dilakukan melalui pembatalan/refund penuh jika masih eligible.</p>
      </div>
      <a href="{{ route('sewa-mobil.finance.create') }}" class="kbsm-business-add-button">+ TAMBAH SEWA MOBIL</a>
    </div>

    <div class="kbsm-business-table-wrap">
      <table class="kbsm-business-table">
        <thead>
          <tr>
            <th>Kode</th>
            <th>Pemohon & Vendor</th>
            <th>Kendaraan</th>
            <th>Kegiatan</th>
            <th>Periode</th>
            <th>Nominal</th>
            <th>Status</th>
            <th>Approval</th>
            <th>Posting</th>
            <th>Aksi</th>
          </tr>
        </thead>
        <tbody>
          @forelse($sewaMobil as $item)
            @php $action = $eligibility[$item->id]; @endphp
            <tr>
              <td class="kbsm-business-code">{{ $item->kode_sewa ?: 'Draft' }}</td>
              <td>
                <div class="kbsm-business-strong">{{ $item->karyawan->nama ?? '-' }}</div>
                <div class="kbsm-business-muted">{{ $item->karyawan->jabatan ?? '-' }} / {{ $item->karyawan->status_kerja ?? '-' }}</div>
                <div class="kbsm-business-muted">Vendor: {{ $item->vendor_nama ?: '-' }}</div>
                <div class="kbsm-business-muted">Dicatat: {{ $item->recorder->name ?? $item->creator->name ?? '-' }}</div>
              </td>
              <td>
                <div class="kbsm-business-strong">{{ $item->vehicle_label }}</div>
                <div class="kbsm-business-muted">Plat: {{ $item->plat_nomor_snapshot ?: 'Belum diisi' }}</div>
                <div class="kbsm-business-muted">Warna/Tahun: {{ $item->warna_kendaraan ?: '-' }} / {{ $item->tahun_kendaraan ?: '-' }}</div>
              </td>
              <td>
                <div class="kbsm-business-strong">{{ $item->nama_kegiatan }}</div>
                <div class="kbsm-business-muted">{{ $item->lokasi_kegiatan }}</div>
                <div class="kbsm-business-muted">{{ $item->keterangan ?: '-' }}</div>
              </td>
              <td>
                <div>{{ $item->tanggal_mulai?->format('d/m/Y') ?? '-' }}</div>
                <div>{{ $item->tanggal_selesai?->format('d/m/Y') ?? '-' }}</div>
                <div class="kbsm-business-muted">{{ (int) $item->jumlah_hari }} hari</div>
              </td>
              <td>
                <div class="kbsm-business-muted">Vendor: Rp {{ number_format((int) $item->total_harga_vendor, 0, ',', '.') }}</div>
                <div class="kbsm-business-muted">Markup: Rp {{ number_format((int) $item->total_markup, 0, ',', '.') }}</div>
                <div class="kbsm-business-strong">Tagihan: Rp {{ number_format((int) $item->total_tagihan_perusahaan, 0, ',', '.') }}</div>
                @if($item->pembayaran)
                  <div class="kbsm-business-muted">Terima: {{ $methodLabel($item->pembayaran->metode_penerimaan ?? $item->pembayaran->metode_pembayaran) }} / {{ $item->pembayaran->dompetPenerimaan->nama_dompet ?? $item->pembayaran->dompet->nama_dompet ?? '-' }}</div>
                  <div class="kbsm-business-muted">Vendor: {{ $methodLabel($item->pembayaran->metode_pembayaran_vendor) }} / {{ $item->pembayaran->dompetVendor->nama_dompet ?? '-' }}</div>
                @endif
              </td>
              <td>
                <span class="{{ $badge($item->status) }}">{{ $item->status_label }}</span>
                @if($action['is_overdue'])<span class="kbsm-status kbsm-status--gold">Lewat Jadwal</span>@endif
                <div class="rental-payment-state"><span>Vendor</span>{{ $action['vendor_status_label'] }}</div>
                <div class="rental-payment-state"><span>Perusahaan</span>{{ $action['company_status_label'] }}</div>
              </td>
              <td class="kbsm-business-muted">
                {{ $item->nama_pengurus_snapshot ?: '-' }}<br>
                {{ $item->jabatan_pengurus_snapshot ?: '' }}<br>
                @if($item->approved_at){{ $item->approved_at->format('d/m/Y H:i') }}@endif
              </td>
              <td class="kbsm-business-muted">
                <div>Jurnal: {{ $item->jurnal->count() + ($item->pembayaran?->jurnal?->count() ?? 0) }}</div>
                <div>Mutasi: {{ $item->pembayaran?->mutasiKas?->count() ?? 0 }}</div>
                @if($item->reversal)
                  <div>Reversal: {{ $item->reversal->kode_reversal }}</div>
                @endif
              </td>
              <td>
                <div class="kbsm-business-inline-actions">
                  @if($item->status === 'draft')
                    <div class="kbsm-business-inline-row">
                      <a href="{{ route('sewa-mobil.finance.edit', $item) }}" class="kbsm-btn kbsm-btn--outline-slate kbsm-btn--sm">Edit Draft</a>
                      <form method="POST" action="{{ route('sewa-mobil.finance.submit', $item) }}">@csrf<button class="kbsm-btn kbsm-btn--green kbsm-btn--sm">Ajukan</button></form>
                    </div>
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

                  @if($action['needs_invoice_before_vendor'])
                    <a href="{{ route('invoice-penagihan.create', ['perusahaan_id' => $item->karyawan->perusahaan_id]) }}" class="kbsm-btn kbsm-btn--outline-slate kbsm-btn--sm">Buat Invoice</a>
                  @endif

                  @if($action['can_pay_vendor'])
                    <details class="rental-action"><summary class="kbsm-btn kbsm-btn--navy kbsm-btn--sm">Bayar Vendor</summary><form method="POST" action="{{ route('sewa-mobil.vendor.pay',$item) }}">@csrf<input type="hidden" name="jumlah" value="{{ (int)$item->total_harga_vendor }}"><select name="metode" class="kbsm-business-control" required><option value="tunai">Tunai</option><option value="transfer_bank">Bank</option></select><select name="dompet_id" class="kbsm-business-control" required><option value="">Dompet sumber</option>@foreach($dompetOptions as $dompet)<option value="{{ $dompet->id }}">{{ $dompet->nama_dompet }} ({{ $dompet->jenis_dompet }})</option>@endforeach</select><input type="date" name="tanggal_bayar" value="{{ today()->toDateString() }}" class="kbsm-business-control" required><input name="nomor_referensi" placeholder="Referensi (opsional)" class="kbsm-business-control"><button class="kbsm-btn kbsm-btn--navy kbsm-btn--sm">Simpan Pembayaran Vendor</button></form></details>
                  @endif

                  @if($action['can_start'])
                    <form method="POST" action="{{ route('sewa-mobil.finance.start', $item) }}">@csrf<button class="kbsm-btn kbsm-btn--amber kbsm-btn--sm">Mulai</button></form>
                  @endif

                  @if($action['can_cancel'])
                    <details class="rental-action"><summary class="kbsm-btn kbsm-btn--outline-red kbsm-btn--sm">Batalkan Sewa</summary><form method="POST" action="{{ route('sewa-mobil.finance.cancel',$item) }}">@csrf<p>Belum ada pembayaran vendor. Pembatalan tidak mengubah transaksi kas.</p><textarea name="alasan" class="kbsm-business-control" placeholder="Alasan pembatalan" required></textarea><button class="kbsm-btn kbsm-btn--outline-red kbsm-btn--sm">Konfirmasi Pembatalan</button></form></details>
                  @endif
                  @if($action['can_request_vendor_refund'])
                    <details class="rental-action"><summary class="kbsm-btn kbsm-btn--outline-red kbsm-btn--sm">Proses Pengembalian Dana Vendor</summary><form method="POST" action="{{ route('sewa-mobil.vendor.refund-request',$item) }}">@csrf<p>Sewa belum berjalan. Sistem akan menunggu konfirmasi dana vendor benar-benar kembali.</p><textarea name="alasan" class="kbsm-business-control" required placeholder="Alasan pengembalian"></textarea><button class="kbsm-btn kbsm-btn--outline-red kbsm-btn--sm">Minta Pengembalian</button></form></details>
                  @endif
                  @if($action['waiting_vendor_refund'])
                    <span class="kbsm-status kbsm-status--gold">Menunggu Pengembalian Vendor</span>
                    <details class="rental-action"><summary class="kbsm-btn kbsm-btn--outline-slate kbsm-btn--sm">Konfirmasi Dana Kembali</summary><form method="POST" action="{{ route('sewa-mobil.vendor.refund-confirm',$item) }}">@csrf<input type="date" name="tanggal_pengembalian" value="{{ today()->toDateString() }}" class="kbsm-business-control" required><input name="nomor_referensi" class="kbsm-business-control" placeholder="Referensi (opsional)"><button class="kbsm-btn kbsm-btn--green kbsm-btn--sm">Dana Sudah Diterima</button></form></details>
                  @endif
                  @if($action['can_legacy_full_refund'])
                    <details class="rental-action"><summary class="kbsm-btn kbsm-btn--outline-red kbsm-btn--sm">Proses Refund Transaksi Lama</summary><form method="POST" action="{{ route('sewa-mobil.finance.cancel',$item) }}">@csrf<p>Pembayaran lama mencatat arus vendor dan perusahaan sekaligus. Keduanya akan dibalik penuh.</p><textarea name="alasan" class="kbsm-business-control" required></textarea><button class="kbsm-btn kbsm-btn--outline-red kbsm-btn--sm">Proses Refund Penuh</button></form></details>
                  @endif
                  @if($action['can_refund_company'])
                    <details class="rental-action"><summary class="kbsm-btn kbsm-btn--outline-red kbsm-btn--sm">Kembalikan Dana Perusahaan</summary><form method="POST" action="{{ route('sewa-mobil.company.refund',$item) }}">@csrf<select name="metode" class="kbsm-business-control"><option value="tunai">Tunai</option><option value="transfer_bank">Bank</option></select><select name="dompet_id" class="kbsm-business-control" required><option value="">Dompet sumber</option>@foreach($dompetOptions as $dompet)<option value="{{ $dompet->id }}">{{ $dompet->nama_dompet }}</option>@endforeach</select><input type="date" name="tanggal_pengembalian" value="{{ today()->toDateString() }}" class="kbsm-business-control" required><input name="nomor_referensi" class="kbsm-business-control" placeholder="Referensi (opsional)"><textarea name="alasan" class="kbsm-business-control" required placeholder="Alasan pengembalian"></textarea><button class="kbsm-btn kbsm-btn--outline-red kbsm-btn--sm">Simpan Pengembalian</button></form></details>
                  @endif
                  @if($action['can_complete'])
                    <form method="POST" action="{{ route('sewa-mobil.finance.complete', $item) }}">@csrf<button class="kbsm-btn kbsm-btn--green kbsm-btn--sm">Selesaikan Sewa</button></form>
                  @endif
                  @if($action['is_final'])
                    <details class="rental-action rental-action--readonly"><summary class="kbsm-btn kbsm-btn--outline-slate kbsm-btn--sm">Lihat Detail</summary><p>{{ $item->status_label }} pada {{ optional($item->completed_at ?? $item->cancelled_at ?? $item->refunded_at)->format('d/m/Y H:i') ?: '–' }}.</p></details>
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
