@php
  $role = auth()->user()->role ?? null;
  $modules = collect(config('navigation.modules', []))
    ->filter(function (array $module) use ($role) {
      $feature = $module['feature'] ?? null;
      if ($feature && ! config("features.{$feature}", false)) {
        return false;
      }

      $allowed = $module['roles'] ?? null;
      if (! is_array($allowed) || $allowed === []) {
        return true;
      }
      return $role && in_array($role, $allowed, true);
    });
  $currentRouteName = request()->route()?->getName();
  $currentPath = request()->path();
  $currentPath = $currentPath === '/' ? '/' : trim($currentPath, '/');

  $currentModule = $modules->first(function (array $module) use ($currentPath, $currentRouteName) {
    $routeMatches = $currentRouteName
      && collect($module['patterns'] ?? [])->contains(fn(string $pattern) => request()->routeIs($pattern));

    $pathMatches = collect($module['paths'] ?? [])
      ->map(fn(string $path) => $path === '/' ? '/' : trim($path, '/'))
      ->contains($currentPath);

    return $routeMatches || $pathMatches;
  });

  $brandName = config('navigation.brand.name', 'Koperasi BITA');
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

  <!-- Font Awesome -->
  <script src="https://kit.fontawesome.com/42d5adcbca.js" crossorigin="anonymous"></script>

  <!-- Nucleo Icons -->
  <link href="{{ asset('assets/css/nucleo-icons.css') }}" rel="stylesheet" />
  <link href="{{ asset('assets/css/nucleo-svg.css') }}" rel="stylesheet" />

  <!-- Popper -->
  <script src="https://unpkg.com/@popperjs/core@2"></script>

  <!-- Main Styling -->
  <link href="{{ asset('assets/css/soft-ui-dashboard-tailwind.css') }}?v=1.0.5" rel="stylesheet" />
  <link href="{{ asset('assets/css/kbsm-theme.css') }}?v=1.0.0" rel="stylesheet" />
  @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
