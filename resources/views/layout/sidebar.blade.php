@php
  // helper buat class menu aktif
  $is = fn(string $name) => request()->routeIs($name);
  $isAny = fn(array $names) => collect($names)->contains(fn($n) => request()->routeIs($n));
  $brandName = config('navigation.brand.name', 'Koperasi BITA');
  $brandSubtitle = config('navigation.brand.subtitle', 'POS, Simpan Pinjam, dan Laporan');
  $brandLogo = config('navigation.brand.logo', 'assets/img/logo-koperasi.png');

  $linkClass = fn(bool $active) =>
    $active
      ? 'kbsm-sidebar-link kbsm-sidebar-link--active py-2.7 text-sm ease-nav-brand my-0 mx-4 flex items-center whitespace-nowrap rounded-lg px-4 font-semibold transition-colors'
      : 'kbsm-sidebar-link py-2.7 text-sm ease-nav-brand my-0 mx-4 flex items-center whitespace-nowrap rounded-lg px-4 transition-colors';

  $iconWrap = fn(bool $active) =>
    $active
      ? 'kbsm-sidebar-icon kbsm-sidebar-icon--active shadow-soft-2xl mr-2 flex h-8 w-8 items-center justify-center rounded-lg bg-center stroke-0 text-center xl:p-2.5'
      : 'kbsm-sidebar-icon shadow-soft-2xl mr-2 flex h-8 w-8 items-center justify-center rounded-lg bg-center stroke-0 text-center xl:p-2.5';

  $iconColor = fn(bool $active) => $active ? 'text-white' : 'text-inherit';
  $role = auth()->user()->role ?? '';
@endphp

<aside
  class="kbsm-sidebar max-w-62.5 ease-nav-brand z-990 fixed inset-y-0 my-4 ml-4 block w-full -translate-x-full flex-wrap items-center justify-between overflow-y-auto rounded-2xl border-0 bg-white p-0 antialiased shadow-none transition-transform duration-200 xl:left-0 xl:translate-x-0 xl:bg-transparent">

  {{-- FIX: Mengubah tinggi menjadi auto agar menyesuaikan teks yang turun baris --}}
  <div class="h-auto pb-4 pt-2">
    <i class="absolute top-0 right-0 hidden p-4 opacity-50 cursor-pointer fas fa-times text-slate-400 xl:hidden"
       sidenav-close></i>

    {{-- FIX: Menggunakan flex layout dan membuang whitespace-nowrap dari container utama --}}
    <a class="flex items-center px-6 py-4 m-0 text-sm text-slate-700 w-full"
       href="{{ route('pages.dashboard') }}">
      
      {{-- Bagian Logo --}}
      <span
        class="inline-flex items-center justify-center shrink-0 rounded-xl bg-white shadow-soft-xl"
        style="width: 2.75rem; height: 2.75rem; padding: 0.35rem;">
        <img
          src="{{ asset($brandLogo) }}"
          alt="{{ $brandName }}"
          style="width: 100%; height: 100%; object-fit: contain;" />
      </span>
      
      {{-- Bagian Teks --}}
      <span class="flex flex-col min-w-0" style="margin-left: 1.20rem;">
        <span class="block font-semibold transition-all duration-200 ease-nav-brand truncate">
          {{ $brandName }}
        </span>
        {{-- FIX: Menambahkan whitespace-normal agar teks bisa turun baris (wrap) dengan rapi --}}
        <span class="block text-xs leading-normal text-slate-500 whitespace-normal break-words mt-0.5">
          {{ $brandSubtitle }}
        </span>
      </span>
    </a>
  </div>

  <hr class="h-px mt-0 bg-transparent bg-gradient-to-r from-transparent via-black/40 to-transparent" />

  <div class="items-center block w-auto max-h-screen h-sidenav grow basis-full">
    <ul class="kbsm-sidebar-nav flex flex-col pl-0 mb-0">

      {{-- DASHBOARD --}}
      @php $active = $is('pages.dashboard') || request()->path() === '/'; @endphp
      <li class="mt-0.5 w-full">
        <a class="{{ $linkClass($active) }}" href="{{ route('pages.dashboard') }}">
          <div class="{{ $iconWrap($active) }}">
            <svg
              class="fill-current {{ $iconColor($active) }}"
              width="12px" height="12px" viewBox="0 0 24 24"
              xmlns="http://www.w3.org/2000/svg">
              <path fill="currentColor"
                d="M3 13h8V3H3v10Zm0 8h8v-6H3v6Zm10 0h8V11h-8v10Zm0-18v6h8V3h-8Z"/>
            </svg>
          </div>

          <span class="ml-1 duration-300 opacity-100 pointer-events-none ease-soft">Dashboard</span>
        </a>
      </li>

      {{-- PROFILE --}}
      @php $active = $is('pages.profile'); @endphp
      <li class="mt-0.5 w-full">
        <a class="{{ $linkClass($active) }}" href="{{ route('pages.profile') }}">
          <div class="{{ $iconWrap($active) }}">
            <svg width="12px" height="12px" viewBox="0 0 24 24"
                 class="fill-current {{ $iconColor($active) }}" xmlns="http://www.w3.org/2000/svg">
              <path fill="currentColor"
                d="M12 12a4 4 0 1 0-4-4 4 4 0 0 0 4 4Zm0 2c-4.42 0-8 2-8 4.5V21h16v-2.5C20 16 16.42 14 12 14Z"/>
            </svg>
          </div>

          <span class="ml-1 duration-300 opacity-100 pointer-events-none ease-soft">Profile</span>
        </a>
      </li>

      {{-- LOGOUT --}}
      <li class="mt-0.5 w-full">
        <form method="POST" action="{{ route('logout') }}">
          @csrf
          <button type="submit" class="{{ $linkClass(false) }} w-full text-left">
            <div class="{{ $iconWrap(false) }}">
              <svg width="12px" height="12px" viewBox="0 0 24 24"
                   class="fill-current {{ $iconColor(false) }}" xmlns="http://www.w3.org/2000/svg">
                <path fill="currentColor"
                  d="M10 17v-2h4v-6h-4V7l-5 5 5 5Zm9 4H12v-2h7V5h-7V3h7a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2Z"/>
              </svg>
            </div>

            <span class="ml-1 duration-300 opacity-100 pointer-events-none ease-soft">Logout</span>
          </button>
        </form>
      </li>

      {{-- POS KOPERASI --}}
      @if($role === 'kasir')
      <li class="w-full mt-4">
        <h6 class="pl-6 ml-2 text-xs font-bold leading-tight uppercase opacity-60">
          POS Koperasi
        </h6>
      </li>

      {{-- PENJUALAN / KASIR --}}
      @php $active = $is('penjualan.index'); @endphp
      <li class="mt-0.5 w-full">
        <a class="{{ $linkClass($active) }}" href="{{ route('penjualan.index') }}">
          <div class="{{ $iconWrap($active) }}">
            <svg width="12px" height="12px" viewBox="0 0 24 24"
                 class="fill-current {{ $iconColor($active) }}" xmlns="http://www.w3.org/2000/svg">
              <path fill="currentColor"
                d="M7 4V2h2v2h6V2h2v2h2a2 2 0 0 1 2 2v3H3V6a2 2 0 0 1 2-2h2Zm14 7v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-7h18Zm-5 2H8v2h8v-2Z"/>
            </svg>
          </div>
          <span class="ml-1 duration-300 opacity-100 pointer-events-none ease-soft">Penjualan / Kasir</span>
        </a>
      </li>

      {{-- KATEGORI PRODUK --}}
      @php $active = $is('kategori-produk.*'); @endphp
      <li class="mt-0.5 w-full">
        <a class="{{ $linkClass($active) }}" href="{{ route('kategori-produk.index') }}">
          <div class="{{ $iconWrap($active) }}">
            <svg width="12px" height="12px" viewBox="0 0 24 24"
                 class="fill-current {{ $iconColor($active) }}" xmlns="http://www.w3.org/2000/svg">
              <path fill="currentColor"
                d="M20.59 13.41 11 3.83V2h-2v2.66l9.59 9.58a2 2 0 0 1 0 2.83l-4.34 4.34a2 2 0 0 1-2.83 0L2 12.99V3h10l9.59 9.59a2 2 0 0 1 0 2.82ZM7 7a2 2 0 1 0 0 4 2 2 0 0 0 0-4Z"/>
            </svg>
          </div>
          <span class="ml-1 duration-300 opacity-100 pointer-events-none ease-soft">Kategori Produk</span>
        </a>
      </li>

      {{-- PRODUK --}}
      @php $active = $is('produk.*'); @endphp
      <li class="mt-0.5 w-full">
        <a class="{{ $linkClass($active) }}" href="{{ route('produk.index') }}">
          <div class="{{ $iconWrap($active) }}">
            <svg width="12px" height="12px" viewBox="0 0 24 24"
                 class="fill-current {{ $iconColor($active) }}" xmlns="http://www.w3.org/2000/svg">
              <path fill="currentColor"
                d="M12 2 3 6.5v11L12 22l9-4.5v-11L12 2Zm7 6.2-7 3.5-7-3.5L12 4.8l7 3.4ZM5 10l6 3v7.1L5 17.1V10Zm14 7.1-6 3V13l6-3v7.1Z"/>
            </svg>
          </div>
          <span class="ml-1 duration-300 opacity-100 pointer-events-none ease-soft">Produk</span>
        </a>
      </li>
       {{-- RESELLER: aktif untuk reseller.* --}}
      @php $active = $is('reseller.*'); @endphp
      <li class="mt-0.5 w-full">
        <a class="{{ $linkClass($active) }}" href="{{ route('reseller.index') }}">
          <div class="{{ $iconWrap($active) }}">
            <svg width="12px" height="12px" viewBox="0 0 24 24"
                 class="fill-current {{ $iconColor($active) }}" xmlns="http://www.w3.org/2000/svg">
              <path fill="currentColor"
                d="M12 12a4 4 0 1 0-4-4 4 4 0 0 0 4 4Zm0 2c-4.42 0-8 2-8 4.5V21h16v-2.5C20 16 16.42 14 12 14Z"/>
            </svg>
          </div>
          <span class="ml-1 duration-300 opacity-100 pointer-events-none ease-soft">Reseller</span>
        </a>
      </li>

      {{-- PEMBAYARAN KONSINYASI --}}
      @php $active = $is('pembayaran-konsinyasi.*'); @endphp
      <li class="mt-0.5 w-full">
        <a class="{{ $linkClass($active) }}" href="{{ route('pembayaran-konsinyasi.index') }}">
          <div class="{{ $iconWrap($active) }}">
            <svg width="12px" height="12px" viewBox="0 0 24 24"
                 class="fill-current {{ $iconColor($active) }}" xmlns="http://www.w3.org/2000/svg">
              <path fill="currentColor"
                d="M3 6h18v12H3V6Zm2 2v8h14V8H5Zm2 1h4v2H7V9Zm8 0h2v2h-2V9Zm-8 4h6v2H7v-2Z"/>
              <path fill="currentColor"
                d="M13 3h8v2h-8V3Zm4 14 4 4h-3v3h-2v-3h-3l4-4Z"/>
            </svg>
          </div>
          <span class="ml-1 duration-300 opacity-100 pointer-events-none ease-soft">Pembayaran Konsinyasi</span>
        </a>
      </li>
      @endif

      {{-- ====== MASTER DATA ====== --}}
      @if($role === 'keuangan')
      <li class="w-full mt-4">
        <h6 class="pl-6 ml-2 text-xs font-bold leading-tight uppercase opacity-60">
          MASTER DATA
        </h6>
      </li>

      {{-- KARYAWAN --}}
      @php $active = $is('karyawan.*'); @endphp
      <li class="mt-0.5 w-full">
        <a class="{{ $linkClass($active) }}" href="{{ route('karyawan.index') }}">
          <div class="{{ $iconWrap($active) }}">
            <svg width="12px" height="12px" viewBox="0 0 24 24"
                 class="fill-current {{ $iconColor($active) }}" xmlns="http://www.w3.org/2000/svg">
              <path fill="currentColor"
                d="M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5c-1.66 0-3 1.34-3 3s1.34 3 3 3zm-8 0c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5C6.34 5 5 6.34 5 8s1.34 3 3 3zm0 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5c0-2.33-4.67-3.5-7-3.5zm8 0c-.29 0-.62.02-.97.05 1.16.84 1.97 1.97 1.97 3.45V19h6v-2.5c0-2.33-4.67-3.5-7-3.5z"/>
            </svg>
          </div>
          <span class="ml-1 duration-300 opacity-100 pointer-events-none ease-soft">Karyawan</span>
        </a>
      </li>

      {{-- ANGGOTA --}}
      @php $active = $is('anggota.*'); @endphp
      <li class="mt-0.5 w-full">
        <a class="{{ $linkClass($active) }}" href="{{ route('anggota.index') }}">
          <div class="{{ $iconWrap($active) }}">
            <svg width="12px" height="12px" viewBox="0 0 24 24"
                 class="fill-current {{ $iconColor($active) }}" xmlns="http://www.w3.org/2000/svg">
              <path fill="currentColor" d="M4 4h16a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2Zm2 3v10h12V7H6Zm2 2h4v2H8V9Zm0 4h8v2H8v-2Z"/>
            </svg>
          </div>
          <span class="ml-1 duration-300 opacity-100 pointer-events-none ease-soft">Anggota</span>
        </a>
      </li>

      {{-- PENGURUS --}}
      @php $active = $is('pengurus-koperasi.*'); @endphp
      <li class="mt-0.5 w-full">
        <a class="{{ $linkClass($active) }}" href="{{ route('pengurus-koperasi.index') }}">
          <div class="{{ $iconWrap($active) }}">
            <svg width="12px" height="12px" viewBox="0 0 24 24"
                 class="fill-current {{ $iconColor($active) }}" xmlns="http://www.w3.org/2000/svg">
              <path fill="currentColor"
                d="M12 3a4 4 0 1 1 0 8 4 4 0 0 1 0-8Zm-7 15c0-2.76 3.13-5 7-5s7 2.24 7 5v1H5v-1Zm15-8V8h2v2h-2Zm-1 1h4v2h-4v-2Z"/>
            </svg>
          </div>
          <span class="ml-1 duration-300 opacity-100 pointer-events-none ease-soft">
            Pengurus
          </span>
        </a>
      </li>

      {{-- JENIS SIMPANAN --}}
      @php $active = $is('jenis-simpanan.*'); @endphp
      <li class="mt-0.5 w-full">
        <a class="{{ $linkClass($active) }}" href="{{ route('jenis-simpanan.index') }}">
          <div class="{{ $iconWrap($active) }}">
            <svg width="12px" height="12px" viewBox="0 0 24 24"
                 class="fill-current {{ $iconColor($active) }}" xmlns="http://www.w3.org/2000/svg">
              <path fill="currentColor"
                d="M19 8h-1.18A3 3 0 0 0 15 6h-1V4h-2v2H9.5a5.5 5.5 0 0 0-5.45 4.74L3 11v2h1v2a3 3 0 0 0 3 3h1v2h2v-2h4v2h2v-2h.5A4.5 4.5 0 0 0 21 13.5V12a4 4 0 0 0-2-4ZM7 9a1 1 0 1 1 0 2 1 1 0 0 1 0-2Zm9.5 7h-9A1.5 1.5 0 0 1 6 14.5V13h2.26a3 3 0 0 0 5.48 0H16a1 1 0 0 0 0-2h-3v1a1 1 0 1 1-2 0v-1H5.15A3.5 3.5 0 0 1 8.5 8H15a1 1 0 0 1 1 1v1h2v-.73A2 2 0 0 1 19 11v2.5a2.5 2.5 0 0 1-2.5 2.5Z"/>
            </svg>
          </div>
          <span class="ml-1 duration-300 opacity-100 pointer-events-none ease-soft">
            Jenis Simpanan
          </span>
        </a>
      </li>

      {{-- JENIS PINJAMAN --}}
      @php $active = $is('jenis-pinjaman.*'); @endphp
      <li class="mt-0.5 w-full">
        <a class="{{ $linkClass($active) }}" href="{{ route('jenis-pinjaman.index') }}">
          <div class="{{ $iconWrap($active) }}">
            <svg width="12px" height="12px" viewBox="0 0 24 24"
                 class="fill-current {{ $iconColor($active) }}" xmlns="http://www.w3.org/2000/svg">
              <path fill="currentColor"
                d="M2 6h20v2H2V6Zm2 4h16v10H4V10Zm4 2v2h8v-2H8Zm0 4v2h5v-2H8Z"/>
            </svg>
          </div>
          <span class="ml-1 duration-300 opacity-100 pointer-events-none ease-soft">
            Jenis Pinjaman
          </span>
        </a>
      </li>

      {{-- CHART OF ACCOUNTS --}}
      @php $active = $is('akun.*'); @endphp
      <li class="mt-0.5 w-full">
        <a class="{{ $linkClass($active) }}" href="{{ route('akun.index') }}">
          <div class="{{ $iconWrap($active) }}">
            <svg width="12px" height="12px" viewBox="0 0 24 24"
                 class="fill-current {{ $iconColor($active) }}" xmlns="http://www.w3.org/2000/svg">
              <path fill="currentColor" d="M4 3h16a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2Zm2 4v2h4V7H6Zm6 0v2h6V7h-6Zm-6 4v2h4v-2H6Zm6 0v2h6v-2h-6Zm-6 4v2h4v-2H6Zm6 0v2h6v-2h-6Z"/>
            </svg>
          </div>
          <span class="ml-1 duration-300 opacity-100 pointer-events-none ease-soft">COA</span>
        </a>
      </li>

      {{-- MOBIL KOPERASI --}}
      @php $active = $is('aset-mobil.*'); @endphp
      <li class="mt-0.5 w-full">
        <a class="{{ $linkClass($active) }}" href="{{ route('aset-mobil.index') }}">
          <div class="{{ $iconWrap($active) }}">
            <svg width="12px" height="12px" viewBox="0 0 24 24"
                 class="fill-current {{ $iconColor($active) }}" xmlns="http://www.w3.org/2000/svg">
              <path fill="currentColor" d="M5 11 6.5 6.5A2 2 0 0 1 8.4 5h7.2a2 2 0 0 1 1.9 1.5L19 11h1a1 1 0 0 1 1 1v5h-2a2 2 0 1 1-4 0H9a2 2 0 1 1-4 0H3v-5a1 1 0 0 1 1-1h1Zm2.6-4-1.1 4h11L16.4 7H7.6ZM7 18a1 1 0 1 0 0-2 1 1 0 0 0 0 2Zm10 0a1 1 0 1 0 0-2 1 1 0 0 0 0 2Z"/>
            </svg>
          </div>
          <span class="ml-1 duration-300 opacity-100 pointer-events-none ease-soft">Mobil Koperasi</span>
        </a>
      </li>

      @if(config('features.master_printer_enabled', false))
        {{-- PRINTER KOPERASI --}}
        @php $active = $is('aset-printer.*'); @endphp
        <li class="mt-0.5 w-full">
          <a class="{{ $linkClass($active) }}" href="{{ route('aset-printer.index') }}">
            <div class="{{ $iconWrap($active) }}">
              <svg width="12px" height="12px" viewBox="0 0 24 24"
                   class="fill-current {{ $iconColor($active) }}" xmlns="http://www.w3.org/2000/svg">
                <path fill="currentColor" d="M6 3h12v5H6V3Zm-2 7h16a2 2 0 0 1 2 2v5h-4v4H6v-4H2v-5a2 2 0 0 1 2-2Zm4 6v3h8v-5H8v2Zm10-3h2v-2h-2v2Z"/>
              </svg>
            </div>
            <span class="ml-1 duration-300 opacity-100 pointer-events-none ease-soft">Printer Koperasi</span>
          </a>
        </li>
      @endif

      <li class="w-full mt-4">
        <h6 class="pl-6 ml-2 text-xs font-bold leading-tight uppercase opacity-60">
          KAS & BANK
        </h6>
      </li>

      {{-- DOMPET KOPERASI --}}
      @php $active = $is('dompet-koperasi.*'); @endphp
      <li class="mt-0.5 w-full">
        <a class="{{ $linkClass($active) }}" href="{{ route('dompet-koperasi.index') }}">
          <div class="{{ $iconWrap($active) }}">
            <svg width="12px" height="12px" viewBox="0 0 24 24"
                 class="fill-current {{ $iconColor($active) }}" xmlns="http://www.w3.org/2000/svg">
              <path fill="currentColor"
                d="M3 6h18v12H3V6Zm2 2v8h14V8H5Zm2 2h6v2H7v-2Z"/>
            </svg>
          </div>
          <span class="ml-1 duration-300 opacity-100 pointer-events-none ease-soft">
            Dompet Koperasi
          </span>
        </a>
      </li>

      {{-- MUTASI KAS & BANK --}}
      @php $active = $is('mutasi-kas.*'); @endphp
      <li class="mt-0.5 w-full">
        <a class="{{ $linkClass($active) }}" href="{{ route('mutasi-kas.index') }}">
          <div class="{{ $iconWrap($active) }}">
            <svg width="12px" height="12px" viewBox="0 0 24 24"
                 class="fill-current {{ $iconColor($active) }}" xmlns="http://www.w3.org/2000/svg">
              <path fill="currentColor"
                d="M7 7h11V4l5 4-5 4V9H7a3 3 0 0 0 0 6h2v2H7A5 5 0 0 1 7 7Zm10 0h-2V5h-2v4h4V7Zm0 10H6v3l-5-4 5-4v3h11a3 3 0 1 0 0-6h-2V7h2a5 5 0 1 1 0 10Z"/>
            </svg>
          </div>
          <span class="ml-1 duration-300 opacity-100 pointer-events-none ease-soft">
            Mutasi Kas & Bank
          </span>
        </a>
      </li>

      <li class="w-full mt-4">
        <h6 class="pl-6 ml-2 text-xs font-bold leading-tight uppercase opacity-60">
          SIMPAN PINJAM
        </h6>
      </li>

      {{-- TRANSAKSI SIMPANAN --}}
      @php $active = $is('simpanan.*'); @endphp
      <li class="mt-0.5 w-full">
        <a class="{{ $linkClass($active) }}" href="{{ route('simpanan.index') }}">
          <div class="{{ $iconWrap($active) }}">
            <svg width="12px" height="12px" viewBox="0 0 24 24"
                 class="fill-current {{ $iconColor($active) }}" xmlns="http://www.w3.org/2000/svg">
              <path fill="currentColor"
                d="M12 3C8.13 3 5 5.24 5 8c0 1.54.98 2.91 2.5 3.83V15c0 2.76 3.13 5 7 5s7-2.24 7-5v-3.17C23.02 10.91 24 9.54 24 8c0-2.76-3.13-5-7-5h-5Zm0 2h5c2.76 0 5 1.34 5 3s-2.24 3-5 3h-5C9.24 11 7 9.66 7 8s2.24-3 5-3Zm-2.5 8.23c.8.18 1.64.27 2.5.27h5c.86 0 1.7-.09 2.5-.27V15c0 1.66-2.24 3-5 3h-5c-2.76 0-5-1.34-5-3v-1.77Z"/>
            </svg>
          </div>
          <span class="ml-1 duration-300 opacity-100 pointer-events-none ease-soft">
            Transaksi Simpanan
          </span>
        </a>
      </li>

      {{-- PINJAMAN --}}
      @php $active = $is('pinjaman.*'); @endphp
      <li class="mt-0.5 w-full">
        <a class="{{ $linkClass($active) }}" href="{{ route('pinjaman.index') }}">
          <div class="{{ $iconWrap($active) }}">
            <svg width="12px" height="12px" viewBox="0 0 24 24"
                 class="fill-current {{ $iconColor($active) }}" xmlns="http://www.w3.org/2000/svg">
              <path fill="currentColor"
                d="M3 7h18v10H3V7Zm2 2v6h14V9H5Zm3 1h5a3 3 0 1 1 0 6H8v-2h5a1 1 0 1 0 0-2H8v-2Z"/>
            </svg>
          </div>
          <span class="ml-1 duration-300 opacity-100 pointer-events-none ease-soft">
            Pinjaman
          </span>
        </a>
      </li>

      {{-- CICILAN PINJAMAN --}}
      @php $active = $is('cicilan-pinjaman.*'); @endphp
      <li class="mt-0.5 w-full">
        <a class="{{ $linkClass($active) }}" href="{{ route('cicilan-pinjaman.index') }}">
          <div class="{{ $iconWrap($active) }}">
            <svg width="12px" height="12px" viewBox="0 0 24 24"
                 class="fill-current {{ $iconColor($active) }}" xmlns="http://www.w3.org/2000/svg">
              <path fill="currentColor"
                d="M4 5h16a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2Zm0 2v10h16V7H4Zm2 2h5v2H6V9Zm0 4h8v2H6v-2Z"/>
            </svg>
          </div>
          <span class="ml-1 duration-300 opacity-100 pointer-events-none ease-soft">
            Cicilan Pinjaman
          </span>
        </a>
      </li>

      {{-- PERIODE POTONG GAJI --}}
      @php $active = $is('periode-potong-gaji.*'); @endphp
      <li class="mt-0.5 w-full">
        <a class="{{ $linkClass($active) }}" href="{{ route('periode-potong-gaji.index') }}">
          <div class="{{ $iconWrap($active) }}">
            <svg width="12px" height="12px" viewBox="0 0 24 24"
                 class="fill-current {{ $iconColor($active) }}" xmlns="http://www.w3.org/2000/svg">
              <path fill="currentColor"
                d="M7 2h2v2h6V2h2v2h3a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h3V2Zm13 8H4v10h16V10ZM6 12h4v4H6v-4Zm6 0h6v2h-6v-2Zm0 3h5v2h-5v-2Z"/>
            </svg>
          </div>
          <span class="ml-1 duration-300 opacity-100 pointer-events-none ease-soft">
            Periode Potong Gaji
          </span>
        </a>
      </li>

      @if(config('features.shu_enabled', false))
        {{-- ====== MENU SHU ====== --}}
        <li class="w-full mt-4">
          <h6 class="pl-6 ml-2 text-xs font-bold leading-tight uppercase opacity-60">
            SHU Koperasi
          </h6>
        </li>

        @php $active = $is('shu-koperasi.*'); @endphp
        <li class="mt-0.5 w-full">
          <a class="{{ $linkClass($active) }}" href="{{ route('shu-koperasi.index') }}">
            <div class="{{ $iconWrap($active) }}">
              <svg width="12px" height="12px" viewBox="0 0 24 24"
                   class="fill-current {{ $iconColor($active) }}" xmlns="http://www.w3.org/2000/svg">
                <path fill="currentColor"
                  d="M5 3h14a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2Zm1 4v10h12V7H6Zm2 2h3v6H8V9Zm5 2h3v4h-3v-4Z"/>
              </svg>
            </div>
            <span class="ml-1 duration-300 opacity-100 pointer-events-none ease-soft">
              Transaksi SHU
            </span>
          </a>
        </li>
      @endif

      <li class="w-full mt-4">
        <h6 class="pl-6 ml-2 text-xs font-bold leading-tight uppercase opacity-60">
          USAHA KOPERASI
        </h6>
      </li>

      {{-- SEWA MOBIL --}}
      @php $active = $is('sewa-mobil.finance.*'); @endphp
      <li class="mt-0.5 w-full">
        <a class="{{ $linkClass($active) }}" href="{{ route('sewa-mobil.finance.index') }}">
          <div class="{{ $iconWrap($active) }}">
            <svg width="12px" height="12px" viewBox="0 0 24 24"
                 class="fill-current {{ $iconColor($active) }}" xmlns="http://www.w3.org/2000/svg">
              <path fill="currentColor" d="M5 11 6.5 6.5A2 2 0 0 1 8.4 5h7.2a2 2 0 0 1 1.9 1.5L19 11h1a1 1 0 0 1 1 1v5h-2a2 2 0 1 1-4 0H9a2 2 0 1 1-4 0H3v-5a1 1 0 0 1 1-1h1Zm2.6-4-1.1 4h11L16.4 7H7.6ZM7 18a1 1 0 1 0 0-2 1 1 0 0 0 0 2Zm10 0a1 1 0 1 0 0-2 1 1 0 0 0 0 2ZM10 13h4v2h-4v-2Z"/>
            </svg>
          </div>
          <span class="ml-1 duration-300 opacity-100 pointer-events-none ease-soft">Sewa Mobil</span>
        </a>
      </li>

      {{-- SEWA PRINTER --}}
      @php $active = $is('sewa-printer.*'); @endphp
      <li class="mt-0.5 w-full">
        <a class="{{ $linkClass($active) }}" href="{{ route('sewa-printer.index') }}">
          <div class="{{ $iconWrap($active) }}">
            <svg width="12px" height="12px" viewBox="0 0 24 24"
                 class="fill-current {{ $iconColor($active) }}" xmlns="http://www.w3.org/2000/svg">
              <path fill="currentColor" d="M6 3h12v5H6V3Zm-2 7h16a2 2 0 0 1 2 2v5h-4v4H6v-4H2v-5a2 2 0 0 1 2-2Zm4 6v3h8v-5H8v2Zm10-3h2v-2h-2v2Zm-8 2h4v1h-4v-1Z"/>
            </svg>
          </div>
          <span class="ml-1 duration-300 opacity-100 pointer-events-none ease-soft">Sewa Printer</span>
        </a>
      </li>

      <li class="w-full mt-4">
        <h6 class="pl-6 ml-2 text-xs font-bold leading-tight uppercase opacity-60">
          OPERASIONAL
        </h6>
      </li>

      {{-- BEBAN OPERASIONAL --}}
      @php $active = $is('beban-operasional.*'); @endphp
      <li class="mt-0.5 w-full">
        <a class="{{ $linkClass($active) }}" href="{{ route('beban-operasional.index') }}">
          <div class="{{ $iconWrap($active) }}">
            <svg width="12px" height="12px" viewBox="0 0 24 24"
                 class="fill-current {{ $iconColor($active) }}" xmlns="http://www.w3.org/2000/svg">
              <path fill="currentColor" d="M4 3h16a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2Zm2 4v2h12V7H6Zm0 4v2h8v-2H6Zm0 4v2h10v-2H6Zm12-4h-2v2h2v-2Zm0 4h-2v2h2v-2Z"/>
            </svg>
          </div>
          <span class="ml-1 duration-300 opacity-100 pointer-events-none ease-soft">Beban Operasional</span>
        </a>
      </li>

      @endif

      {{-- ====== MENU LAPORAN ====== --}}
      <li class="w-full mt-4">
        <h6 class="pl-6 ml-2 text-xs font-bold leading-tight uppercase opacity-60">
          Laporan Operasional
        </h6>
      </li>

      {{-- LAPORAN KONSINYASI --}}
      @php $active = $is('konsinyasi.report'); @endphp
      <li class="mt-0.5 w-full">
        <a class="{{ $linkClass($active) }}" href="{{ route('konsinyasi.report') }}">
          <div class="{{ $iconWrap($active) }}">
            <svg width="12px" height="12px" viewBox="0 0 24 24"
                 class="fill-current {{ $iconColor($active) }}" xmlns="http://www.w3.org/2000/svg">
              <path fill="currentColor"
                d="M3 3h2v18H3V3Zm4 10h2v8H7v-8Zm4-6h2v14h-2V7Zm4 4h2v10h-2V11Zm4-8h2v18h-2V3Z"/>
            </svg>
          </div>
          <span class="ml-1 duration-300 opacity-100 pointer-events-none ease-soft">
            Laporan Konsinyasi
          </span>
        </a>
      </li>

      {{-- LAPORAN AKUNTANSI (Keuangan) --}}
      @if($role === 'keuangan')
      <li class="w-full mt-4">
        <h6 class="pl-6 ml-2 text-xs font-bold leading-tight uppercase opacity-60">
          LAPORAN AKUNTANSI
        </h6>
      </li>

      {{-- JURNAL UMUM PERIODIK --}}
      @php $active = $is('akuntansi.jurnal-umum'); @endphp
      <li class="mt-0.5 w-full">
        <a class="{{ $linkClass($active) }}" href="{{ route('akuntansi.jurnal-umum') }}">
          <div class="{{ $iconWrap($active) }}">
            <svg width="12px" height="12px" viewBox="0 0 24 24"
                 class="fill-current {{ $iconColor($active) }}" xmlns="http://www.w3.org/2000/svg">
              <path fill="currentColor"
                d="M6 2h9l3 3v17a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2Zm8 1.5V6h2.5L14 3.5ZM7 9h10v2H7V9Zm0 4h10v2H7v-2Zm0 4h7v2H7v-2Z"/>
            </svg>
          </div>
          <span class="ml-1 duration-300 opacity-100 pointer-events-none ease-soft">
            Jurnal Umum Periodik
          </span>
        </a>
      </li>

      {{-- BUKU BESAR --}}
      @php $active = $is('akuntansi.buku-besar'); @endphp
      <li class="mt-0.5 w-full">
        <a class="{{ $linkClass($active) }}" href="{{ route('akuntansi.buku-besar') }}">
          <div class="{{ $iconWrap($active) }}">
            <svg width="12px" height="12px" viewBox="0 0 24 24"
                 class="fill-current {{ $iconColor($active) }}" xmlns="http://www.w3.org/2000/svg">
              <path fill="currentColor"
                d="M4 4h16v2H4V4Zm0 4h16v14H4V8Zm2 2v10h12V10H6Zm2 2h8v2H8v-2Zm0 4h6v2H8v-2Z"/>
            </svg>
          </div>
          <span class="ml-1 duration-300 opacity-100 pointer-events-none ease-soft">
            Buku Besar
          </span>
        </a>
      </li>

      {{-- LAPORAN POTONG GAJI --}}
      @php $active = $is('laporan.potong-gaji'); @endphp
      <li class="mt-0.5 w-full">
        <a class="{{ $linkClass($active) }}" href="{{ route('laporan.potong-gaji') }}">
          <div class="{{ $iconWrap($active) }}">
            <svg width="12px" height="12px" viewBox="0 0 24 24"
                 class="fill-current {{ $iconColor($active) }}" xmlns="http://www.w3.org/2000/svg">
              <path fill="currentColor"
                d="M4 4h16v2H4V4Zm0 4h16v12H4V8Zm3 3v2h4v-2H7Zm0 4v2h7v-2H7Zm9-4h2v6h-2v-6Z"/>
            </svg>
          </div>
          <span class="ml-1 duration-300 opacity-100 pointer-events-none ease-soft">
            Laporan Potong Gaji
          </span>
        </a>
      </li>

      @php $active = $is('rekonsiliasi-potong-gaji.*'); @endphp
      <li class="mt-0.5 w-full">
        <a class="{{ $linkClass($active) }}" href="{{ route('rekonsiliasi-potong-gaji.index') }}">
          <div class="{{ $iconWrap($active) }}">
            <svg width="12px" height="12px" viewBox="0 0 24 24"
                 class="fill-current {{ $iconColor($active) }}" xmlns="http://www.w3.org/2000/svg">
              <path fill="currentColor" d="M4 5h10v2H4V5Zm0 4h16v2H4V9Zm0 4h10v2H4v-2Zm0 4h16v2H4v-2Zm14-13 4 4-1.4 1.4L18 6.8l-4.6 4.6L12 10l6-6Z"/>
            </svg>
          </div>
          <span class="ml-1 duration-300 opacity-100 pointer-events-none ease-soft">
            Rekonsiliasi Payroll
          </span>
        </a>
      </li>

      @php $active = $is('outstanding-cash.*'); @endphp
      <li class="mt-0.5 w-full">
        <a class="{{ $linkClass($active) }}" href="{{ route('outstanding-cash.index') }}">
          <div class="{{ $iconWrap($active) }}">
            <svg width="12px" height="12px" viewBox="0 0 24 24"
                 class="fill-current {{ $iconColor($active) }}" xmlns="http://www.w3.org/2000/svg">
              <path fill="currentColor" d="M12 2a10 10 0 1 1 0 20 10 10 0 0 1 0-20Zm1 5h-2v6h6v-2h-4V7Zm-7 9h12v2H6v-2Z"/>
            </svg>
          </div>
          <span class="ml-1 duration-300 opacity-100 pointer-events-none ease-soft">
            Outstanding Cash
          </span>
        </a>
      </li>

      @php $active = $is('penyelesaian-keanggotaan.*'); @endphp
      <li class="mt-0.5 w-full">
        <a class="{{ $linkClass($active) }}" href="{{ route('penyelesaian-keanggotaan.index') }}">
          <div class="{{ $iconWrap($active) }}">
            <svg width="12px" height="12px" viewBox="0 0 24 24"
                 class="fill-current {{ $iconColor($active) }}" xmlns="http://www.w3.org/2000/svg">
              <path fill="currentColor" d="M12 2a5 5 0 0 1 5 5v1h1a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2v-9a2 2 0 0 1 2-2h1V7a5 5 0 0 1 5-5Zm-3 6h6V7a3 3 0 0 0-6 0v1Zm-1 5h8v2H8v-2Zm0 4h5v2H8v-2Z"/>
            </svg>
          </div>
          <span class="ml-1 duration-300 opacity-100 pointer-events-none ease-soft">
            Penyelesaian Keanggotaan
          </span>
        </a>
      </li>

      @php $active = $is('reversal-transaksi.*'); @endphp
      <li class="mt-0.5 w-full">
        <a class="{{ $linkClass($active) }}" href="{{ route('reversal-transaksi.index') }}">
          <div class="{{ $iconWrap($active) }}">
            <svg width="12px" height="12px" viewBox="0 0 24 24"
                 class="fill-current {{ $iconColor($active) }}" xmlns="http://www.w3.org/2000/svg">
              <path fill="currentColor" d="M12 4V1L7 6l5 5V8a6 6 0 1 1-5.2 3H4.6A8 8 0 1 0 12 4Zm-1 6h2v5h-2v-5Zm0 6h2v2h-2v-2Z"/>
            </svg>
          </div>
          <span class="ml-1 duration-300 opacity-100 pointer-events-none ease-soft">
            Audit Reversal
          </span>
        </a>
      </li>
      @endif

      @if(false)
      {{-- TABLES --}}

      {{-- MENU TEMPLATE LAMA (kalau masih dipakai) --}}
      @php $active = $is('pages.tables'); @endphp
      <li class="mt-0.5 w-full">
        <a class="{{ $linkClass($active) }}" href="{{ route('pages.tables') }}">
          <div class="{{ $iconWrap($active) }}">
            {{-- svg tables kamu --}}
            <svg width="12px" height="12px" viewBox="0 0 42 42" xmlns="http://www.w3.org/2000/svg">
              <g stroke="none" stroke-width="1" fill="none" fill-rule="evenodd">
                <g transform="translate(-1869.000000, -293.000000)" fill="#FFFFFF" fill-rule="nonzero">
                  <g transform="translate(1716.000000, 291.000000)">
                    <g transform="translate(153.000000, 2.000000)">
                      <path class="fill-slate-800 opacity-60"
                        d="M12.25,17.5 L8.75,17.5 L8.75,1.75 C8.75,0.78225 9.53225,0 10.5,0 L31.5,0 C32.46775,0 33.25,0.78225 33.25,1.75 L33.25,12.25 L29.75,12.25 L29.75,3.5 L12.25,3.5 L12.25,17.5 Z"></path>
                      <path class="fill-slate-800"
                        d="M40.25,14 L24.5,14 C23.53225,14 22.75,14.78225 22.75,15.75 L22.75,38.5 L19.25,38.5 L19.25,22.75 C19.25,21.78225 18.46775,21 17.5,21 L1.75,21 C0.78225,21 0,21.78225 0,22.75 L0,40.25 C0,41.21775 0.78225,42 1.75,42 L40.25,42 C41.21775,42 42,41.21775 42,40.25 L42,15.75 C42,14.78225 41.21775,14 40.25,14 Z"></path>
                    </g>
                  </g>
                </g>
              </g>
            </svg>
          </div>
          <span class="ml-1 duration-300 opacity-100 pointer-events-none ease-soft">Tables</span>
        </a>
      </li>
      @endif

      {{-- lanjutkan menu lama kamu (billing, virtual, rtl, profile, signin, signup) seperti biasa --}}
    </ul>
  </div>

  <div class="mx-4">
    <p class="invisible hidden text-gray-800 text-red-500 text-red-600 after:bg-gradient-to-tl after:from-gray-900 after:to-slate-800 after:from-blue-600 after:to-cyan-400 after:from-red-500 after:to-yellow-400 after:from-green-600 after:to-lime-400 after:from-red-600 after:to-rose-400 after:from-slate-600 after:to-slate-300 text-lime-500 text-cyan-500 text-slate-400"></p>

    <div
      class="after:opacity-65 after:bg-gradient-to-tl after:from-slate-600 after:to-slate-300 relative flex min-w-0 flex-col items-center break-words rounded-2xl border-0 border-solid border-blue-900 bg-white bg-clip-border shadow-none after:absolute after:top-0 after:bottom-0 after:left-0 after:z-10 after:block after:h-full after:w-full after:rounded-2xl after:content-['']"
      sidenav-card>
      <div class="mb-7.5 absolute h-full w-full rounded-2xl bg-cover bg-center"
           style="background-image: url('{{ asset('assets/img/curved-images/white-curved.jpeg') }}');"></div>
    </div>
  </div>


</aside>

<script>
(function () {
  var SCROLL_KEY = 'kbsm_sidebar_scroll';
  var sidebar = document.querySelector('aside.kbsm-sidebar');
  if (!sidebar) return;

  /* 1. Restore saved scroll position from sessionStorage */
  try {
    var saved = sessionStorage.getItem(SCROLL_KEY);
    if (saved !== null) {
      sidebar.scrollTop = parseInt(saved, 10) || 0;
    } else {
      /* No saved position: scroll active link into center view */
      var active = sidebar.querySelector('.kbsm-sidebar-link--active');
      if (active) {
        sidebar.scrollTop = Math.max(0,
          active.offsetTop - sidebar.clientHeight / 2 + active.offsetHeight / 2
        );
      }
    }
  } catch (e) { /* sessionStorage may be unavailable in private mode */ }

  /* 2. Save scroll position when user clicks any navigable link */
  sidebar.addEventListener('click', function (e) {
    var link = e.target.closest('a[href]');
    if (link && !link.getAttribute('href').startsWith('#')) {
      try { sessionStorage.setItem(SCROLL_KEY, sidebar.scrollTop); } catch (e) {}
    }
  });

  /* 3. Clear saved position on logout so next user starts fresh */
  var logoutForm = sidebar.querySelector('form[action*="logout"]');
  if (logoutForm) {
    logoutForm.addEventListener('submit', function () {
      try { sessionStorage.removeItem(SCROLL_KEY); } catch (e) {}
    });
  }
})();
</script>
