@extends('layout.main')

@section('content')
@php
  $kategoriOptions = \App\Models\JenisSimpanan::KATEGORI;
  $statusClass = fn ($aktif) => $aktif ? 'kbsm-status kbsm-status--green' : 'kbsm-status kbsm-status--slate';
@endphp

<div class="kbsm-business-page">
  @if (session('success'))
    <div class="kbsm-business-alert kbsm-business-alert--success">{{ session('success') }}</div>
  @endif

  @if ($errors->any())
    <div class="kbsm-business-alert kbsm-business-alert--danger">
      <ul class="mb-0 list-disc pl-5">
        @foreach ($errors->all() as $error)
          <li>{{ $error }}</li>
        @endforeach
      </ul>
    </div>
  @endif

  <div class="kbsm-business-header">
    <div>
      <p class="kbsm-business-eyebrow">Master Data</p>
      <h1 class="kbsm-business-title">Jenis Simpanan</h1>
      <p class="kbsm-business-subtitle">Kelola master resmi Simpanan Pokok, Wajib, dan Sukarela. Snapshot transaksi lama tidak ikut berubah.</p>
    </div>
  </div>

  <section class="kbsm-business-panel">
    <div class="kbsm-business-panel__header">
      <h2 class="kbsm-business-panel__title">Filter Jenis Simpanan</h2>
      <p class="kbsm-business-panel__copy">Gunakan filter kategori dan status tanpa mengubah data.</p>
    </div>

    <form method="GET" action="{{ route('jenis-simpanan.index') }}" class="kbsm-business-filter kbsm-business-filter--compact">
      <div class="kbsm-business-field">
        <label class="kbsm-business-label" for="kategori">Kategori</label>
        <select id="kategori" name="kategori" class="kbsm-business-control">
          <option value="">Semua Kategori</option>
          @foreach($kategoriOptions as $value => $label)
            <option value="{{ $value }}" @selected(request('kategori') === $value)>{{ $label }}</option>
          @endforeach
        </select>
      </div>

      <div class="kbsm-business-field">
        <label class="kbsm-business-label" for="status">Status</label>
        <select id="status" name="status" class="kbsm-business-control">
          <option value="">Semua Status</option>
          <option value="aktif" @selected(request('status') === 'aktif')>Aktif</option>
          <option value="nonaktif" @selected(request('status') === 'nonaktif')>Nonaktif</option>
        </select>
      </div>

      <div class="kbsm-business-filter__actions kbsm-business-filter__actions--split">
        <button class="kbsm-btn kbsm-btn--navy">Filter</button>
        <a href="{{ route('jenis-simpanan.index') }}" class="kbsm-btn kbsm-btn--outline-slate">Reset</a>
      </div>
    </form>
  </section>

  <section class="kbsm-business-panel">
    <div class="kbsm-business-panel__header kbsm-business-panel__header--action">
      <div>
        <h2 class="kbsm-business-panel__title">Master Jenis Simpanan</h2>
        <p class="kbsm-business-panel__copy">Satu kategori hanya boleh memiliki satu master aktif.</p>
      </div>

      @if(count($missingActiveCategories) > 0)
        <a href="{{ route('jenis-simpanan.create') }}" class="kbsm-business-add-button">+ TAMBAH JENIS SIMPANAN</a>
      @endif
    </div>

    <div class="kbsm-business-table-wrap">
      <table class="kbsm-business-table">
        <thead>
          <tr>
            <th>Kode</th>
            <th>Nama</th>
            <th>Kategori</th>
            <th class="kbsm-business-table__right">Nominal</th>
            <th>Frekuensi</th>
            <th>Berlaku Mulai</th>
            <th>Status</th>
            <th>Audit</th>
            <th>Aksi</th>
          </tr>
        </thead>
        <tbody>
          @forelse($jenisSimpanan as $item)
            <tr>
              <td>
                <span class="kbsm-business-code">{{ $item->kode ?: '-' }}</span>
              </td>
              <td>
                <span class="kbsm-business-strong">{{ $item->nama_jenis }}</span>
                <div class="kbsm-business-muted">{{ $item->akun ? $item->akun->kode_akun . ' - ' . $item->akun->nama_akun : 'COA belum dipetakan' }}</div>
              </td>
              <td>{{ $item->kategori_label }}</td>
              <td class="kbsm-business-table__right">
                <span class="kbsm-business-amount">
                  {{ $item->nominal_default !== null ? 'Rp ' . number_format((float) $item->nominal_default, 0, ',', '.') : '-' }}
                </span>
              </td>
              <td>{{ $item->frekuensi_label }}</td>
              <td>{{ $item->berlaku_mulai?->format('d/m/Y') ?? '-' }}</td>
              <td>
                <span class="{{ $statusClass($item->aktif) }}">{{ $item->aktif ? 'Aktif' : 'Nonaktif' }}</span>
              </td>
              <td>
                <div class="kbsm-business-muted">
                  Dibuat: {{ $item->created_at?->format('d/m/Y H:i') ?? '-' }}
                </div>
                <div class="kbsm-business-muted">
                  Update: {{ $item->updated_at?->format('d/m/Y H:i') ?? '-' }}
                </div>
                @if($item->latestRiwayat)
                  <div class="kbsm-business-muted">
                    Oleh: {{ $item->latestRiwayat->changedBy->name ?? 'System' }}
                  </div>
                @endif
              </td>
              <td>
                <div class="kbsm-business-inline-actions">
                  <a href="{{ route('jenis-simpanan.edit', $item) }}" class="kbsm-btn kbsm-btn--navy kbsm-btn--sm">Edit</a>
                  @unless($item->is_terpakai)
                    <form method="POST" action="{{ route('jenis-simpanan.destroy', $item) }}" onsubmit="return confirm('Hapus master yang belum dipakai ini?')">
                      @csrf
                      @method('DELETE')
                      <button class="kbsm-btn kbsm-btn--outline-red kbsm-btn--sm">Hapus</button>
                    </form>
                  @endunless
                </div>
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="9" class="kbsm-business-empty">Belum ada Master Jenis Simpanan sesuai filter.</td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>

    <div class="kbsm-business-pagination">
      {{ $jenisSimpanan->links() }}
    </div>
  </section>
</div>
@endsection
