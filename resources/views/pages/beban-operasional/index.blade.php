@extends('layout.main')

@section('content')
@php
  $badge = fn(string $status) => match ($status) {
    'draft' => 'kbsm-status kbsm-status--slate',
    'posted' => 'kbsm-status kbsm-status--green',
    'reversed' => 'kbsm-status kbsm-status--amber',
    default => 'kbsm-status kbsm-status--slate',
  };
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

  <div class="kbsm-business-header">
    <div>
      <p class="kbsm-business-eyebrow">Operasional</p>
      <h1 class="kbsm-business-title">Beban Operasional</h1>
      <p class="kbsm-business-subtitle">Beban umum koperasi dibayar dari Dompet Kas/Bank. Posted immutable dan koreksi dilakukan melalui reversal penuh.</p>
    </div>
  </div>

  <section class="kbsm-business-panel">
    <div class="kbsm-business-panel__header">
      <h2 class="kbsm-business-panel__title">Filter Beban Operasional</h2>
      <p class="kbsm-business-panel__copy">Filter berdasarkan status, Dompet, akun Beban, dan rentang tanggal transaksi.</p>
    </div>
    <form method="GET" action="{{ route('beban-operasional.index') }}" class="kbsm-business-filter kbsm-business-filter--beban">
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
        <label class="kbsm-business-label">Dompet</label>
        <select name="dompet_id" class="kbsm-business-control">
          <option value="">Semua</option>
          @foreach($dompetOptions as $dompet)
            <option value="{{ $dompet->id }}" {{ (string) request('dompet_id') === (string) $dompet->id ? 'selected' : '' }}>{{ $dompet->nama_dompet }}</option>
          @endforeach
        </select>
      </div>
      <div class="kbsm-business-field">
        <label class="kbsm-business-label">Akun Beban</label>
        <select name="akun_id" class="kbsm-business-control">
          <option value="">Semua</option>
          @foreach($akunOptions as $akun)
            <option value="{{ $akun->id }}" {{ (string) request('akun_id') === (string) $akun->id ? 'selected' : '' }}>{{ $akun->kode_akun }} - {{ $akun->nama_akun }}</option>
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
      <div class="kbsm-business-filter__actions kbsm-business-filter__actions--split">
        <button class="kbsm-btn kbsm-btn--navy">Filter</button>
        @if(request()->query())
          <a href="{{ route('beban-operasional.index') }}" class="kbsm-btn kbsm-btn--outline-slate">Reset</a>
        @endif
      </div>
    </form>
  </section>

  <section class="kbsm-business-panel">
    <div class="kbsm-business-panel__header kbsm-business-panel__header--action">
      <div>
        <h2 class="kbsm-business-panel__title">Daftar Beban Operasional</h2>
        <p class="kbsm-business-panel__copy">Draft dapat diedit atau dibatalkan. Transaksi posted dikoreksi melalui reversal penuh.</p>
      </div>
      <a href="{{ route('beban-operasional.create') }}" class="kbsm-business-add-button">+ INPUT BEBAN</a>
    </div>
    <div class="kbsm-business-table-wrap">
      <table class="kbsm-business-table kbsm-business-table--beban">
        <thead>
          <tr>
            <th>Kode/Tanggal</th>
            <th>Akun Beban</th>
            <th>Keterangan/Referensi</th>
            <th>Dibayar Dari</th>
            <th class="kbsm-business-table__right">Nominal</th>
            <th>Status</th>
            <th>Posting/Audit</th>
            <th>Aksi</th>
          </tr>
        </thead>
        <tbody>
          @forelse($bebanOperasional as $item)
            @php $detail = $item->details->first(); @endphp
            <tr>
              <td>
                <div class="kbsm-business-code">{{ $item->kode_beban }}</div>
                <div class="kbsm-business-muted">{{ $item->tanggal_beban->format('d/m/Y') }}</div>
              </td>
              <td>
                <div class="kbsm-business-strong">{{ $detail?->kode_akun_snapshot ?: $detail?->akun?->kode_akun }}</div>
                <div class="kbsm-business-muted">{{ $detail?->nama_akun_snapshot ?: $detail?->akun?->nama_akun }}</div>
              </td>
              <td>
                <div class="kbsm-business-strong">{{ $item->keterangan ?: $detail?->keterangan }}</div>
                <div class="kbsm-business-muted">Ref: {{ $item->nomor_referensi ?: '-' }}</div>
              </td>
              <td>
                @if($item->dompet)
                  <div class="kbsm-business-strong">{{ $item->dompet->nama_dompet }}</div>
                  <div class="kbsm-business-muted">{{ $item->metode_pembayaran }} / {{ $item->dompet->akun?->kode_akun }} {{ $item->dompet->akun?->nama_akun }}</div>
                @else
                  <span class="kbsm-business-muted">Belum dipilih</span>
                @endif
              </td>
              <td class="kbsm-business-amount">Rp {{ number_format((float) $item->total_beban, 0, ',', '.') }}</td>
              <td>
                <span class="{{ $badge($item->status) }}">{{ $item->status_label }}</span>
                @if($item->alasan_reversal)
                  <div class="kbsm-business-muted">Alasan: {{ $item->alasan_reversal }}</div>
                @endif
              </td>
              <td class="kbsm-business-muted">
                <div>Mutasi: {{ $item->mutasiKas->count() }}</div>
                <div>Jurnal: {{ $item->jurnal->count() }}</div>
                <div>Posted: {{ $item->posted_at?->format('d/m/Y H:i') ?? '-' }}</div>
                <div>Reversal: {{ $item->reversal?->kode_reversal ?? '-' }}</div>
              </td>
              <td>
                <div class="kbsm-business-inline-actions">
                  @if($item->status === 'draft')
                    <div class="kbsm-business-inline-row">
                      <a href="{{ route('beban-operasional.edit', $item) }}" class="kbsm-btn kbsm-btn--outline-slate kbsm-btn--sm">Edit Draft</a>
                      <form method="POST" action="{{ route('beban-operasional.post', $item) }}" onsubmit="return confirm('Posting Beban Operasional ini? Saldo Dompet akan berkurang dan Jurnal akan dibuat.')">
                        @csrf
                        <button class="kbsm-btn kbsm-btn--green kbsm-btn--sm">Posting</button>
                      </form>
                    </div>
                    <form method="POST" action="{{ route('beban-operasional.cancel-draft', $item) }}" onsubmit="return confirm('Batalkan draft Beban Operasional ini?')">
                      @csrf
                      <button class="kbsm-btn kbsm-btn--outline-red kbsm-btn--sm">Batalkan Draft</button>
                    </form>
                  @elseif($item->status === 'posted')
                    <form method="POST" action="{{ route('beban-operasional.reverse', $item) }}" class="kbsm-business-inline-row" onsubmit="return confirm('Reversal penuh Beban Operasional ini? Proses tidak menghapus transaksi asli.')">
                      @csrf
                      <input name="alasan" required placeholder="Alasan reversal penuh" class="kbsm-business-control">
                      <button class="kbsm-btn kbsm-btn--outline-red kbsm-btn--sm">Reversal</button>
                    </form>
                  @else
                    <span class="kbsm-business-muted">Final - tidak ada aksi edit/hapus.</span>
                  @endif
                </div>
              </td>
            </tr>
          @empty
            <tr><td colspan="8" class="kbsm-business-empty">Belum ada Beban Operasional.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
    <div class="kbsm-business-pagination">{{ $bebanOperasional->links() }}</div>
  </section>
</div>
@endsection
