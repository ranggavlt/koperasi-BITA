<!DOCTYPE html>
<html lang="en">
  @include('layout.header')

  <body class="kbsm-app m-0 font-sans text-base antialiased font-normal leading-default bg-gray-50 text-slate-500">

    @include('layout.sidebar')
    <div class="kbsm-sidebar-backdrop" data-kbsm-sidebar-backdrop hidden></div>

    <main class="kbsm-layout-main ease-soft-in-out relative h-full max-h-screen rounded-xl transition-all duration-200">
      @include('layout.navbar')
      @yield('content')
    </main>

    <script src="{{ asset('assets/js/plugins/chartjs.min.js') }}" defer></script>
    <script src="{{ asset('assets/js/plugins/perfect-scrollbar.min.js') }}" defer></script>
    <script src="https://buttons.github.io/buttons.js" defer></script>
    <script src="{{ asset('assets/js/soft-ui-dashboard-tailwind.js') }}?v=1.0.5" defer></script>

    @stack('scripts')
  </body>
</html>
