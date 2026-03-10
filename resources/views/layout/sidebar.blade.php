@php
  // helper buat class menu aktif
  $is = fn(string $name) => request()->routeIs($name);
  $isAny = fn(array $names) => collect($names)->contains(fn($n) => request()->routeIs($n));

  $linkClass = fn(bool $active) =>
    $active
      ? 'py-2.7 shadow-soft-xl text-sm ease-nav-brand my-0 mx-4 flex items-center whitespace-nowrap rounded-lg bg-white px-4 font-semibold text-slate-700 transition-colors'
      : 'py-2.7 text-sm ease-nav-brand my-0 mx-4 flex items-center whitespace-nowrap px-4 transition-colors';

  $iconWrap = fn(bool $active) =>
    $active
      ? 'bg-gradient-to-tl from-purple-700 to-pink-500 shadow-soft-2xl mr-2 flex h-8 w-8 items-center justify-center rounded-lg bg-white bg-center stroke-0 text-center xl:p-2.5'
      : 'shadow-soft-2xl mr-2 flex h-8 w-8 items-center justify-center rounded-lg bg-white bg-center stroke-0 text-center xl:p-2.5';

  $iconColor = fn(bool $active) => $active ? 'text-white' : 'text-slate-700';
@endphp

<aside
  class="max-w-62.5 ease-nav-brand z-990 fixed inset-y-0 my-4 ml-4 block w-full -translate-x-full flex-wrap items-center justify-between overflow-y-auto rounded-2xl border-0 bg-white p-0 antialiased shadow-none transition-transform duration-200 xl:left-0 xl:translate-x-0 xl:bg-transparent">

  <div class="h-19.5">
    <i class="absolute top-0 right-0 hidden p-4 opacity-50 cursor-pointer fas fa-times text-slate-400 xl:hidden"
       sidenav-close></i>

    <a class="block px-8 py-6 m-0 text-sm whitespace-nowrap text-slate-700"
       href="{{ route('pages.dashboard') }}">
      <img
        src="{{ asset('assets/img/logo-ct.png') }}"
        class="inline h-full max-w-full transition-all duration-200 ease-nav-brand max-h-8"
        alt="main_logo" />
      <span class="ml-1 font-semibold transition-all duration-200 ease-nav-brand">
        Soft UI Dashboard
      </span>
    </a>
  </div>

  <hr class="h-px mt-0 bg-transparent bg-gradient-to-r from-transparent via-black/40 to-transparent" />

  <div class="items-center block w-auto max-h-screen h-sidenav grow basis-full">
    <ul class="flex flex-col pl-0 mb-0">

      {{-- DASHBOARD --}}
      @php $active = $is('pages.dashboard'); @endphp
      <li class="mt-0.5 w-full">
        <a class="{{ $linkClass($active) }}" href="{{ route('pages.dashboard') }}">
          <div class="{{ $active
              ? 'bg-gradient-to-tl from-purple-700 to-pink-500 shadow-soft-2xl mr-2 flex h-8 w-8 items-center justify-center rounded-lg bg-center stroke-0 text-center xl:p-2.5'
              : 'shadow-soft-2xl mr-2 flex h-8 w-8 items-center justify-center rounded-lg bg-white bg-center stroke-0 text-center xl:p-2.5'
            }}">
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

      {{-- KARYAWAN (Ditambahkan di sini) --}}
      @php $active = $is('karyawan.*'); @endphp
      <li class="mt-0.5 w-full">
        <a class="{{ $linkClass($active) }}" href="{{ route('karyawan.index') }}">
          <div class="{{ $iconWrap($active) }}">
            {{-- Ikon Users --}}
            <svg width="12px" height="12px" viewBox="0 0 24 24"
                 class="fill-current {{ $iconColor($active) }}" xmlns="http://www.w3.org/2000/svg">
              <path fill="currentColor"
                d="M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5c-1.66 0-3 1.34-3 3s1.34 3 3 3zm-8 0c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5C6.34 5 5 6.34 5 8s1.34 3 3 3zm0 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5c0-2.33-4.67-3.5-7-3.5zm8 0c-.29 0-.62.02-.97.05 1.16.84 1.97 1.97 1.97 3.45V19h6v-2.5c0-2.33-4.67-3.5-7-3.5z"/>
            </svg>
          </div>
          <span class="ml-1 duration-300 opacity-100 pointer-events-none ease-soft">Karyawan</span>
        </a>
      </li>

      {{-- POS KOPERASI --}}
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
          <span class="ml-1 duration-300 opacity-100 pointer-events-none ease-soft">Laporan Konsinyasi</span>
        </a>
      </li>

      {{-- ====== MENU SIMPAN PINJAM ====== --}}
      <li class="w-full mt-4">
        <h6 class="pl-6 ml-2 text-xs font-bold leading-tight uppercase opacity-60">
          Simpan Pinjam
        </h6>
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

      {{-- KARYWAN --}}
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
          <span class="ml-1 duration-300 opacity-100 pointer-events-none ease-soft">
            Anggota
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

      {{-- MUTASI KAS --}}
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
            Mutasi Kas
          </span>
        </a>
      </li>
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

      {{-- lanjutkan menu lama kamu (billing, virtual, rtl, profile, signin, signup) seperti biasa --}}
    </ul>
  </div>

  <div class="mx-4">
    <p class="invisible hidden text-gray-800 text-red-500 text-red-600 after:bg-gradient-to-tl after:from-gray-900 after:to-slate-800 after:from-blue-600 after:to-cyan-400 after:from-red-500 after:to-yellow-400 after:from-green-600 after:to-lime-400 after:from-red-600 after:to-rose-400 after:from-slate-600 after:to-slate-300 text-lime-500 text-cyan-500 text-slate-400 text-fuchsia-500"></p>

    <div
      class="after:opacity-65 after:bg-gradient-to-tl after:from-slate-600 after:to-slate-300 relative flex min-w-0 flex-col items-center break-words rounded-2xl border-0 border-solid border-blue-900 bg-white bg-clip-border shadow-none after:absolute after:top-0 after:bottom-0 after:left-0 after:z-10 after:block after:h-full after:w-full after:rounded-2xl after:content-['']"
      sidenav-card>
      <div class="mb-7.5 absolute h-full w-full rounded-2xl bg-cover bg-center"
           style="background-image: url('{{ asset('assets/img/curved-images/white-curved.jpeg') }}');"></div>
    </div>
  </div>

</aside>
