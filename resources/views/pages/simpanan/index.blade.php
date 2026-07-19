@extends('layout.main')

@section('content')
@php
  $rupiah = fn ($value) => 'Rp ' . number_format((float) $value, 0, ',', '.');
  $hasFilter = collect($filters ?? [])->filter(fn ($value) => filled($value))->isNotEmpty();
@endphp

<div class="kbsm-business-page">
  @if (session('success'))
    <div class="kbsm-business-alert kbsm-business-alert--success">{{ session('success') }}</div>
  @endif

  @if ($errors->any())
    <div class="kbsm-business-alert kbsm-business-alert--danger">
      <ul>
        @foreach ($errors->all() as $error)
          <li>{{ $error }}</li>
        @endforeach
      </ul>
    </div>
  @endif

  <div class="kbsm-business-header kbsm-business-panel__header--action">
    <div>
      <p class="kbsm-business-eyebrow">Simpan Pinjam</p>
      <h1 class="kbsm-business-title">Transaksi Simpanan</h1>
      <p class="kbsm-business-subtitle">Daftar immutable untuk Simpanan Pokok, Wajib, dan Sukarela. Koreksi Sukarela dilakukan dengan audit trail, bukan edit/hapus.</p>
    </div>
    <a href="{{ route('simpanan.create') }}" class="kbsm-business-add-button">+ Transaksi Simpanan Sukarela</a>
  </div>

  <section class="kbsm-business-panel">
    <div class="kbsm-business-panel__header">
      <h2 class="kbsm-business-panel__title">Filter Transaksi</h2>
      <p class="kbsm-business-panel__copy">Filter bersifat read-only dan pagination mempertahankan parameter pencarian.</p>
    </div>
    <form method="GET" action="{{ route('simpanan.index') }}" class="kbsm-business-filter kbsm-business-filter--simpanan">
      <div class="kbsm-business-field">
        <label class="kbsm-business-label">Anggota</label>
        <select name="anggota_id" class="kbsm-business-control">
          <option value="">Semua Anggota</option>
          @foreach($anggota as $item)
            <option value="{{ $item->id }}" {{ (string) request('anggota_id') === (string) $item->id ? 'selected' : '' }}>
              {{ $item->nomor_anggota }} - {{ $item->karyawan?->nama ?? '-' }}
            </option>
          @endforeach
        </select>
      </div>

      <div class="kbsm-business-field">
        <label class="kbsm-business-label">Kategori Simpanan</label>
        <select name="kategori" class="kbsm-business-control">
          <option value="">Semua Kategori</option>
          @foreach($kategoriOptions as $value => $label)
            <option value="{{ $value }}" {{ request('kategori') === $value ? 'selected' : '' }}>{{ $label }}</option>
          @endforeach
        </select>
      </div>

      <div class="kbsm-business-field">
        <label class="kbsm-business-label">Jenis Transaksi</label>
        <select name="jenis_transaksi" class="kbsm-business-control">
          <option value="">Semua</option>
          @foreach($jenisTransaksiOptions as $value => $label)
            <option value="{{ $value }}" {{ request('jenis_transaksi') === $value ? 'selected' : '' }}>{{ $label }}</option>
          @endforeach
        </select>
      </div>

      <div class="kbsm-business-field">
        <label class="kbsm-business-label">Status</label>
        <select name="status" class="kbsm-business-control">
          <option value="">Semua Status</option>
          @foreach($statusOptions as $value => $label)
            <option value="{{ $value }}" {{ request('status') === $value ? 'selected' : '' }}>{{ $label }}</option>
          @endforeach
        </select>
      </div>

      <div class="kbsm-business-field">
        <label class="kbsm-business-label">Metode</label>
        <select name="metode_pembayaran" class="kbsm-business-control">
          <option value="">Semua Metode</option>
          @foreach($metodeOptions as $value => $label)
            <option value="{{ $value }}" {{ request('metode_pembayaran') === $value ? 'selected' : '' }}>{{ $label }}</option>
          @endforeach
        </select>
      </div>

      <div class="kbsm-business-field">
        <label class="kbsm-business-label">Tanggal Mulai</label>
        <input type="date" name="tanggal_mulai" value="{{ request('tanggal_mulai') }}" class="kbsm-business-control">
      </div>

      <div class="kbsm-business-field">
        <label class="kbsm-business-label">Tanggal Selesai</label>
        <input type="date" name="tanggal_selesai" value="{{ request('tanggal_selesai') }}" class="kbsm-business-control">
      </div>

      <div class="kbsm-business-filter__actions kbsm-business-filter__actions--split">
        <button class="kbsm-btn kbsm-btn--navy">Filter</button>
        <a href="{{ route('simpanan.index') }}" class="kbsm-btn kbsm-btn--outline-slate">Reset</a>
      </div>
    </form>
  </section>

  <section class="kbsm-business-summary kbsm-business-summary--simpanan">
    <article class="kbsm-business-summary-card kbsm-business-summary-card--green">
      <div class="kbsm-business-summary-icon" aria-hidden="true">
        <svg viewBox="0 0 24 24"><path d="M12 3l4 4h-3v7h-2V7H8l4-4Zm-7 9h2v5h10v-5h2v7H5v-7Z"/></svg>
      </div>
      <p class="kbsm-business-summary-label">Total Setoran Sukarela</p>
      <p class="kbsm-business-summary-value">{{ $rupiah($summary['setoran'] ?? 0) }}</p>
    </article>
    <article class="kbsm-business-summary-card kbsm-business-summary-card--gold">
      <div class="kbsm-business-summary-icon" aria-hidden="true">
        <svg viewBox="0 0 24 24"><path d="M12 21l-4-4h3v-7h2v7h3l-4 4ZM5 5h14v7h-2V7H7v5H5V5Z"/></svg>
      </div>
      <p class="kbsm-business-summary-label">Total Penarikan Sukarela</p>
      <p class="kbsm-business-summary-value">{{ $rupiah($summary['penarikan'] ?? 0) }}</p>
    </article>
    <article class="kbsm-business-summary-card kbsm-business-summary-card--navy">
      <div class="kbsm-business-summary-icon" aria-hidden="true">
        <svg viewBox="0 0 24 24"><path d="M4 7h14a2 2 0 0 1 2 2v2h-5a3 3 0 0 0 0 6h5v1a2 2 0 0 1-2 2H4V7Zm0-3h12v2H4a1 1 0 0 1 0-2Zm14 9h-3a1 1 0 0 0 0 2h3v-2Z"/></svg>
      </div>
      <p class="kbsm-business-summary-label">Saldo Sukarela Aktif</p>
      <p class="kbsm-business-summary-value">{{ $rupiah($summary['saldo_aktif'] ?? 0) }}</p>
    </article>
    <article class="kbsm-business-summary-card kbsm-business-summary-card--red">
      <div class="kbsm-business-summary-icon" aria-hidden="true">
        <svg viewBox="0 0 24 24"><path d="M12 2a10 10 0 1 0 10 10h-2a8 8 0 1 1-2.34-5.66L15 9h7V2l-2.93 2.93A9.97 9.97 0 0 0 12 2Zm-1 5h2v6h-2V7Zm0 8h2v2h-2v-2Z"/></svg>
      </div>
      <p class="kbsm-business-summary-label">Transaksi Dikoreksi</p>
      <p class="kbsm-business-summary-value">{{ number_format($summary['dikoreksi'] ?? 0, 0, ',', '.') }}</p>
    </article>
  </section>

  <section class="kbsm-business-panel">
    <div class="kbsm-business-panel__header">
      <h2 class="kbsm-business-panel__title">Daftar Transaksi Simpanan</h2>
      <p class="kbsm-business-panel__copy">Tidak ada tombol edit atau hapus. Transaksi Sukarela posted yang salah dapat dikoreksi penuh dengan alasan.</p>
    </div>

    <div class="kbsm-business-table-wrap">
      <table class="kbsm-business-table kbsm-business-table--simpanan">
        <thead>
          <tr>
            <th>Kode/Tanggal</th>
            <th>Anggota</th>
            <th>Jenis Simpanan</th>
            <th>Transaksi</th>
            <th>Metode/Dompet</th>
            <th class="kbsm-business-table__right">Nominal</th>
            <th class="kbsm-business-table__right">Saldo Setelah</th>
            <th>Status</th>
            <th>Posting</th>
            <th>Aksi</th>
          </tr>
        </thead>
        <tbody>
          @forelse($simpanan as $item)
            <tr>
              <td>
                <div class="kbsm-business-code">{{ $item->kode_transaksi ?: ('SIMP-' . $item->id) }}</div>
                <div class="kbsm-business-muted">{{ $item->tanggal?->format('d/m/Y') ?? '-' }}</div>
              </td>
              <td>
                <div class="kbsm-business-strong">{{ $item->anggota?->nomor_anggota ?? '-' }}</div>
                <div class="kbsm-business-muted">{{ $item->anggota?->karyawan?->nama ?? $item->karyawan?->nama ?? '-' }}</div>
              </td>
              <td>
                <div class="kbsm-business-strong">{{ $item->nama_jenis_snapshot ?? $item->jenisSimpanan?->nama_jenis ?? '-' }}</div>
                <div class="kbsm-business-muted">{{ $item->kode_jenis_snapshot ?? $item->jenisSimpanan?->kode ?? '-' }}</div>
              </td>
              <td>
                <span class="kbsm-status kbsm-status--navy">{{ $item->jenis_transaksi_label }}</span>
              </td>
              <td>
                <div class="kbsm-business-strong">{{ $metodeOptions[$item->metode_pembayaran] ?? str_replace('_', ' ', (string) $item->metode_pembayaran) }}</div>
                <div class="kbsm-business-muted">{{ $item->dompet?->nama_dompet ?? $item->mutasiKas?->dompet?->nama_dompet ?? '-' }}</div>
              </td>
              <td class="kbsm-business-amount">{{ $rupiah($item->jumlah) }}</td>
              <td class="kbsm-business-amount">{{ $item->saldo_sesudah_snapshot !== null ? $rupiah($item->saldo_sesudah_snapshot) : '-' }}</td>
              <td>
                <span class="kbsm-status {{ $item->status === 'reversed' ? 'kbsm-status--red' : 'kbsm-status--green' }}">
                  {{ $item->status_label }}
                </span>
              </td>
              <td>
                <div class="kbsm-business-muted">Mutasi: {{ $item->mutasiKas ? 'Ada' : '-' }}</div>
                <div class="kbsm-business-muted">Jurnal: {{ $item->jurnal ? 'Ada' : '-' }}</div>
              </td>
              <td>
                @if($item->isSimpananSukarela() && $item->status === \App\Models\Simpanan::STATUS_SETTLED && in_array($item->jenis_transaksi, [\App\Models\Simpanan::JENIS_SETORAN, \App\Models\Simpanan::JENIS_PENARIKAN], true))
                  <form method="POST" action="{{ route('simpanan.koreksi', $item) }}" class="kbsm-business-inline-actions">
                    @csrf
                    <textarea name="alasan" rows="2" required minlength="5" class="kbsm-business-control" placeholder="Alasan Koreksi"></textarea>
                    <button class="kbsm-btn kbsm-btn--outline-red">Koreksi Transaksi</button>
                  </form>
                @else
                  <span class="kbsm-business-muted">Tidak eligible</span>
                @endif
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="10" class="kbsm-business-empty">
                {{ $hasFilter ? 'Filter tidak menemukan transaksi Simpanan.' : 'Belum ada transaksi Simpanan.' }}
              </td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>

    <div class="kbsm-business-pagination">
      {{ $simpanan->links() }}
    </div>
  </section>
</div>
@endsection
