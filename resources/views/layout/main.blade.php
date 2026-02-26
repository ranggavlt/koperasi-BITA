<!DOCTYPE html>
<html lang="en">
  @include('layout.header')

  <body class="m-0 font-sans text-base antialiased font-normal leading-default bg-gray-50 text-slate-500">

    <!-- sidenav -->
    @include('layout.sidebar')
    <!-- end sidenav -->

    <main class="ease-soft-in-out xl:ml-68.5 relative h-full max-h-screen rounded-xl transition-all duration-200">
      <!-- Navbar -->
      @include('layout.navbar')
      <!-- end Navbar -->

      @yield('content')
    </main>

    <!-- plugin for charts -->
    <script src="{{ asset('assets/js/plugins/chartjs.min.js') }}" defer></script>
    <!-- plugin for scrollbar -->
    <script src="{{ asset('assets/js/plugins/perfect-scrollbar.min.js') }}" defer></script>
    <!-- github button -->
    <script src="https://buttons.github.io/buttons.js" defer></script>
    <!-- main script file -->
    <script src="{{ asset('assets/js/soft-ui-dashboard-tailwind.js') }}?v=1.0.5" defer></script>
  </body>
</html>