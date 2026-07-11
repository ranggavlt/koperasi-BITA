@extends('layout.main')

@section('content')
@php
  $categoryMeta = [
    'aset' => ['icon' => 'fas fa-wallet'],
    'kewajiban' => ['icon' => 'fas fa-file-invoice-dollar'],
    'ekuitas' => ['icon' => 'fas fa-landmark'],
    'pendapatan' => ['icon' => 'fas fa-chart-line'],
    'beban' => ['icon' => 'fas fa-receipt'],
  ];
@endphp

<div class="coa-page">
  @if (session('success'))
    <div class="coa-alert coa-alert--success" role="status">
      <i class="fas fa-check-circle" aria-hidden="true"></i>
      <span>{{ session('success') }}</span>
    </div>
  @endif

  @if ($errors->any())
    <div class="coa-alert coa-alert--danger" role="alert">
      <i class="fas fa-exclamation-circle" aria-hidden="true"></i>
      <ul class="mb-0 list-disc pl-5">
        @foreach ($errors->all() as $error)
          <li>{{ $error }}</li>
        @endforeach
      </ul>
    </div>
  @endif

  <header class="coa-page-header">
    <div>
      <p class="coa-eyebrow">Keuangan / Master Akun</p>
      <h1 class="coa-page-title">Chart of Accounts</h1>
      <p class="coa-page-subtitle">Kelola master akun resmi yang digunakan jurnal dan laporan keuangan koperasi.</p>
    </div>

    <button
      type="button"
      class="coa-btn coa-btn--primary"
      id="btn-toggle-akun"
      aria-controls="akun-form"
      aria-expanded="{{ $errors->any() ? 'true' : 'false' }}"
      onclick="toggleAkunForm()">
      <i class="fas fa-plus" aria-hidden="true"></i>
      <span data-toggle-label>{{ $errors->any() ? 'Tutup Form' : 'Tambah Akun' }}</span>
    </button>
  </header>

  <section id="akun-form" class="coa-form-panel {{ $errors->any() ? 'block' : 'hidden' }}" aria-labelledby="coa-form-title">
    <div class="coa-section-heading">
      <span class="coa-section-heading__icon" aria-hidden="true">
        <i class="fas fa-folder-plus"></i>
      </span>
      <div>
        <h2 class="coa-section-title" id="coa-form-title">Tambah Akun Baru</h2>
        <p class="coa-section-copy">Saldo normal akan ditentukan otomatis sesuai kategori akun.</p>
      </div>
    </div>

    <form method="POST" action="{{ route('akun.store') }}">
      @csrf
      <div class="coa-form-grid">
        <div class="coa-field coa-field--code">
          <label class="coa-label" for="kode_akun">Kode Akun</label>
          <input
            class="coa-input"
            id="kode_akun"
            type="text"
            name="kode_akun"
            inputmode="numeric"
            value="{{ old('kode_akun') }}"
            placeholder="Contoh: 107" />
        </div>

        <div class="coa-field coa-field--name">
          <label class="coa-label" for="nama_akun">Nama Akun</label>
          <input
            class="coa-input"
            id="nama_akun"
            type="text"
            name="nama_akun"
            value="{{ old('nama_akun') }}"
            placeholder="Masukkan nama akun" />
        </div>

        <div class="coa-field coa-field--category">
          <label class="coa-label" for="kategori-akun">Kategori</label>
          <select class="coa-input" id="kategori-akun" name="kategori">
            <option value="">Pilih kategori</option>
            @foreach($categories as $key => $label)
              <option value="{{ $key }}" {{ old('kategori') === $key ? 'selected' : '' }}>{{ $label }}</option>
            @endforeach
          </select>
        </div>

        <div class="coa-field coa-field--submit">
          <button type="submit" class="coa-btn coa-btn--primary">
            <i class="fas fa-save" aria-hidden="true"></i>
            Simpan Akun
          </button>
        </div>

        <div class="coa-field coa-field--description">
          <label class="coa-label" for="keterangan-akun">Keterangan</label>
          <textarea
            class="coa-input"
            id="keterangan-akun"
            name="keterangan"
            rows="2"
            placeholder="Jelaskan fungsi akun agar mudah ditelusuri">{{ old('keterangan') }}</textarea>
        </div>
      </div>
    </form>
  </section>

  <aside class="coa-protection" aria-label="Perlindungan Chart of Accounts">
    <span class="coa-protection__icon" aria-hidden="true">
      <i class="fas fa-shield-alt"></i>
    </span>
    <div>
      <strong>COA dilindungi sebagai sumber pencatatan keuangan</strong>
      <p>Akun dapat ditambahkan, tetapi tidak dapat diedit atau dihapus agar identitas akun pada jurnal historis tetap konsisten.</p>
    </div>
  </aside>

  <section class="coa-summary-section" aria-labelledby="coa-summary-title">
    <div class="coa-summary-heading">
      <h2 id="coa-summary-title">Ringkasan Kategori</h2>
      <p>Jumlah akun aktif berdasarkan kelompok pencatatan.</p>
    </div>

    <div class="coa-summary-grid">
      @foreach($categories as $key => $label)
        <article class="coa-summary-card coa-summary-card--{{ $key }}">
          <span class="coa-summary-icon" aria-hidden="true">
            <i class="{{ $categoryMeta[$key]['icon'] ?? 'fas fa-layer-group' }}"></i>
          </span>
          <div class="coa-summary-content">
            <p class="coa-summary-label">{{ $label }}</p>
            <p class="coa-summary-value">{{ $ringkasan[$key] ?? 0 }}</p>
            <span class="coa-summary-caption">akun terdaftar</span>
          </div>
        </article>
      @endforeach
    </div>
  </section>

  <section class="coa-data-panel" aria-labelledby="coa-list-title">
    <div class="coa-data-header">
      <div class="coa-section-heading">
        <span class="coa-section-heading__icon" aria-hidden="true">
          <i class="fas fa-book"></i>
        </span>
        <div>
          <h2 class="coa-section-title" id="coa-list-title">Daftar Akun</h2>
          <p class="coa-section-copy">Telusuri akun berdasarkan kode, nama, atau kategori.</p>
        </div>
      </div>
      <span class="coa-result-count">{{ $akun->total() }} akun</span>
    </div>

    <div class="coa-filter-bar">
      <form method="GET" action="{{ route('akun.index') }}" class="coa-filter-grid">
        <div class="coa-field">
          <label class="coa-label" for="coa-search">Cari Akun</label>
          <input
            class="coa-input"
            id="coa-search"
            type="search"
            name="search"
            value="{{ $search }}"
            placeholder="Kode atau nama akun" />
        </div>

        <div class="coa-field">
          <label class="coa-label" for="coa-category-filter">Kategori</label>
          <select class="coa-input" id="coa-category-filter" name="kategori">
            <option value="">Semua kategori</option>
            @foreach($categories as $key => $label)
              <option value="{{ $key }}" {{ $kategori === $key ? 'selected' : '' }}>{{ $label }}</option>
            @endforeach
          </select>
        </div>

        <div class="coa-filter-actions">
          <button type="submit" class="coa-btn coa-btn--navy">
            <i class="fas fa-filter" aria-hidden="true"></i>
            Filter
          </button>
          <a href="{{ route('akun.index') }}" class="coa-btn coa-btn--secondary">
            <i class="fas fa-undo" aria-hidden="true"></i>
            Reset
          </a>
        </div>
      </form>
    </div>

    @if($akun->isEmpty())
      <div class="coa-empty-state">
        <span class="coa-empty-state__icon" aria-hidden="true">
          <i class="fas fa-search"></i>
        </span>
        <h3>Belum ada akun yang sesuai</h3>
        <p>Coba ubah kata pencarian atau kategori untuk menampilkan akun yang tersedia.</p>
        @if($search !== '' || $kategori !== '')
          <a href="{{ route('akun.index') }}" class="coa-btn coa-btn--secondary">Reset Filter</a>
        @endif
      </div>
    @else
      <div class="coa-table-wrap">
        <table class="coa-table">
          <thead>
            <tr>
              <th scope="col">Kode</th>
              <th scope="col">Nama Akun</th>
              <th scope="col" class="coa-cell--center">Kategori</th>
              <th scope="col" class="coa-cell--center">Saldo Normal</th>
              <th scope="col" class="coa-cell--center">Sumber</th>
              <th scope="col">Keterangan</th>
            </tr>
          </thead>
          <tbody>
            @foreach($akun as $item)
              <tr>
                <td><span class="coa-code">{{ $item->kode_akun }}</span></td>
                <td><span class="coa-account-name">{{ $item->nama_akun }}</span></td>
                <td class="coa-cell--center">
                  <span class="coa-badge coa-badge--{{ $item->kategori }}">{{ $item->kategori_label }}</span>
                </td>
                <td class="coa-cell--center">
                  <span class="coa-badge coa-badge--{{ $item->posisi_saldo }}">{{ $item->posisi_saldo }}</span>
                </td>
                <td class="coa-cell--center">
                  @if($item->is_sistem)
                    <span class="coa-badge coa-badge--sistem">Sistem</span>
                  @else
                    <span class="coa-badge coa-badge--tambahan">Tambahan</span>
                  @endif
                </td>
                <td><span class="coa-description">{{ $item->keterangan ?: '-' }}</span></td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    @endif

    <footer class="coa-pagination-wrap">
      <div class="coa-pagination">
        <p class="coa-pagination__info">
          Menampilkan {{ $akun->firstItem() ?? 0 }}–{{ $akun->lastItem() ?? 0 }} dari {{ $akun->total() }} akun
        </p>

        @if($akun->hasPages())
          @php
            $paginationStart = max(1, $akun->currentPage() - 2);
            $paginationEnd = min($akun->lastPage(), $akun->currentPage() + 2);
          @endphp
          <nav class="coa-pagination__nav" aria-label="Pagination Chart of Accounts">
            @if($akun->onFirstPage())
              <span class="coa-page-link is-disabled" aria-disabled="true" aria-label="Halaman sebelumnya">
                <i class="fas fa-chevron-left" aria-hidden="true"></i>
              </span>
            @else
              <a class="coa-page-link" href="{{ $akun->previousPageUrl() }}" rel="prev" aria-label="Halaman sebelumnya">
                <i class="fas fa-chevron-left" aria-hidden="true"></i>
              </a>
            @endif

            <span class="coa-pagination__pages">
              @foreach(range($paginationStart, $paginationEnd) as $page)
                @if($page === $akun->currentPage())
                  <span class="coa-page-link is-active" aria-current="page">{{ $page }}</span>
                @else
                  <a class="coa-page-link" href="{{ $akun->url($page) }}">{{ $page }}</a>
                @endif
              @endforeach
            </span>

            @if($akun->hasMorePages())
              <a class="coa-page-link" href="{{ $akun->nextPageUrl() }}" rel="next" aria-label="Halaman berikutnya">
                <i class="fas fa-chevron-right" aria-hidden="true"></i>
              </a>
            @else
              <span class="coa-page-link is-disabled" aria-disabled="true" aria-label="Halaman berikutnya">
                <i class="fas fa-chevron-right" aria-hidden="true"></i>
              </span>
            @endif
          </nav>
        @endif
      </div>
    </footer>
  </section>
</div>

<script>
  function toggleAkunForm() {
    const form = document.getElementById('akun-form');
    const button = document.getElementById('btn-toggle-akun');
    const label = button.querySelector('[data-toggle-label]');
    const willOpen = form.classList.contains('hidden');

    form.classList.toggle('hidden');
    button.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
    label.textContent = willOpen ? 'Tutup Form' : 'Tambah Akun';
  }
</script>
@endsection
