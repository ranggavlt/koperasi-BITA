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
              width="12px" height="12px" viewBox="0 0 45 40" version="1.1"
              xmlns="http://www.w3.org/2000/svg">
              <g stroke="none" stroke-width="1" fill="none" fill-rule="evenodd">
                <g transform="translate(-1716.000000, -439.000000)" fill="currentColor" fill-rule="nonzero">
                  <g transform="translate(1716.000000, 291.000000)">
                    <g transform="translate(0.000000, 148.000000)">
                      <path class="opacity-60"
                        d="M46.7199583,10.7414583 L40.8449583,0.949791667 C40.4909749,0.360605034 39.8540131,0 39.1666667,0 L7.83333333,0 C7.1459869,0 6.50902508,0.360605034 6.15504167,0.949791667 L0.280041667,10.7414583 C0.0969176761,11.0460037 -1.23209662e-05,11.3946378 -1.23209662e-05,11.75 C-0.00758042603,16.0663731 3.48367543,19.5725301 7.80004167,19.5833333 L7.81570833,19.5833333 C9.75003686,19.5882688 11.6168794,18.8726691 13.0522917,17.5760417 C16.0171492,20.2556967 20.5292675,20.2556967 23.494125,17.5760417 C26.4604562,20.2616016 30.9794188,20.2616016 33.94575,17.5760417 C36.2421905,19.6477597 39.5441143,20.1708521 42.3684437,18.9103691 C45.1927731,17.649886 47.0084685,14.8428276 47.0000295,11.75 C47.0000295,11.3946378 46.9030823,11.0460037 46.7199583,10.7414583 Z"></path>
                      <path
                        d="M39.198,22.4912623 C37.3776246,22.4928106 35.5817531,22.0149171 33.951625,21.0951667 L33.92225,21.1107282 C31.1430221,22.6838032 27.9255001,22.9318916 24.9844167,21.7998837 C24.4750389,21.605469 23.9777983,21.3722567 23.4960833,21.1018359 L23.4745417,21.1129513 C20.6961809,22.6871153 17.4786145,22.9344611 14.5386667,21.7998837 C14.029926,21.6054643 13.533337,21.3722507 13.0522917,21.1018359 C11.4250962,22.0190609 9.63246555,22.4947009 7.81570833,22.4912623 C7.16510551,22.4842162 6.51607673,22.4173045 5.875,22.2911849 L5.875,44.7220845 C5.875,45.9498589 6.7517757,46.9451667 7.83333333,46.9451667 L19.5833333,46.9451667 L19.5833333,33.6066734 L27.4166667,33.6066734 L27.4166667,46.9451667 L39.1666667,46.9451667 C40.2482243,46.9451667 41.125,45.9498589 41.125,44.7220845 L41.125,22.2822926 C40.4887822,22.4116582 39.8442868,22.4815492 39.198,22.4912623 Z"></path>
                    </g>
                  </g>
                </g>
              </g>
            </svg>
          </div>

          <span class="ml-1 duration-300 opacity-100 pointer-events-none ease-soft">Dashboard</span>
        </a>
      </li>

      {{-- POS KOPERASI --}}
      <li class="w-full mt-4">
        <h6 class="pl-6 ml-2 text-xs font-bold leading-tight uppercase opacity-60">
          POS Koperasi
        </h6>
      </li>

      {{-- KASIR --}}
      @php $active = $is('kasir.index'); @endphp
      <li class="mt-0.5 w-full">
        <a class="{{ $linkClass($active) }}" href="{{ route('kasir.index') }}">
          <div class="{{ $iconWrap($active) }}">
            <svg width="12px" height="12px" viewBox="0 0 24 24"
                 class="fill-current {{ $iconColor($active) }}" xmlns="http://www.w3.org/2000/svg">
              <path fill="currentColor"
                d="M7 18c-1.1 0-1.99.9-1.99 2S5.9 22 7 22s2-.9 2-2-.9-2-2-2Zm10 0c-1.1 0-1.99.9-1.99 2S15.9 22 17 22s2-.9 2-2-.9-2-2-2ZM7.16 14h9.45c.75 0 1.4-.41 1.74-1.03L21 7H6.21L5.27 5H2v2h2l3.6 7.59-1.35 2.44C5.52 18.37 6.48 20 8 20h12v-2H8l1.16-2Z"/>
            </svg>
          </div>
          <span class="ml-1 duration-300 opacity-100 pointer-events-none ease-soft">Kasir</span>
        </a>
      </li>

      {{-- KATEGORI PRODUK --}}
      @php $active = $is('kategori.index'); @endphp
      <li class="mt-0.5 w-full">
        <a class="{{ $linkClass($active) }}" href="{{ route('kategori.index') }}">
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

      {{-- PRODUK: aktif untuk semua produk.* (index/edit/update/destroy) --}}
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