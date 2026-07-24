<!DOCTYPE html>
<html lang="en">
  @include('layout.header')

  <body class="kbsm-app m-0 font-sans text-base antialiased font-normal leading-default bg-gray-50 text-slate-500" style="overflow: hidden;">

    @include('layout.sidebar')

    <style>
      @media (min-width: 1200px) {
        /* Override default template margin to give more space for POS */
        main.xl\:ml-68\.5 { margin-left: 230px !important; }
      }
    </style>
    <main class="ease-soft-in-out xl:ml-68.5 relative rounded-xl transition-all duration-200 flex flex-col" style="height: 100vh; overflow: hidden;">
      <div class="flex-none" style="z-index: 110;">
        @include('layout.navbar')
      </div>
      <div class="flex-1 overflow-y-auto pb-6">
        @yield('content')
      </div>
    </main>

    <script src="{{ asset('assets/js/plugins/chartjs.min.js') }}" defer></script>
    <script src="https://buttons.github.io/buttons.js" defer></script>
    <script src="{{ asset('assets/js/soft-ui-dashboard-tailwind.js') }}?v=1.0.5" defer></script>

    @stack('scripts')
    @livewireScripts
  </body>
</html>
