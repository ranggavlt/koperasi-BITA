@php
  use App\Support\NavigationMenu;

  $brandName = config('navigation.brand.name', 'Koperasi BITA');
  $currentModule = NavigationMenu::currentModule();
  $pageTitle = $currentModule['label'] ?? 'Dashboard';
@endphp

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />

  <link rel="apple-touch-icon" sizes="76x76" href="{{ asset('assets/img/apple-icon.png') }}" />
  <link rel="icon" type="image/png" href="{{ asset('assets/img/favicon.png') }}" />

  <title>{{ $pageTitle }} | {{ $brandName }}</title>

  <!-- Fonts -->
  <link href="https://fonts.googleapis.com/css?family=Open+Sans:300,400,600,700" rel="stylesheet" />

  <!-- Nucleo Icons -->
  <link href="{{ asset('assets/css/nucleo-icons.css') }}" rel="stylesheet" />
  <link href="{{ asset('assets/css/nucleo-svg.css') }}" rel="stylesheet" />

  <!-- Popper -->
  <script src="https://unpkg.com/@popperjs/core@2"></script>

  <!-- Main Styling -->
  <link href="{{ asset('assets/css/soft-ui-dashboard-tailwind.css') }}?v=1.0.5" rel="stylesheet" />
  <link href="{{ asset('assets/css/kbsm-theme.css') }}?v=20260804-1" rel="stylesheet" />
  @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
