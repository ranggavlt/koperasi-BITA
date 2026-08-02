<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

class NavigationMenu
{
    public static function brand(): array
    {
        return config('navigation.brand', []);
    }

    public static function dashboardModule(): array
    {
        $module = collect(config('navigation.modules', []))
            ->firstWhere('key', 'dashboard');

        if (! is_array($module)) {
            $module = [
                'key' => 'dashboard',
                'label' => 'Dashboard',
                'route' => 'pages.dashboard',
                'route_patterns' => ['pages.dashboard'],
                'paths' => ['/', 'pages/dashboard'],
                'roles' => ['admin', 'kasir', 'karyawan'],
                'group' => null,
                'order' => 0,
                'icon' => 'dashboard',
                'sidebar' => true,
                'search' => true,
            ];
        }

        return self::normalizeModule($module);
    }

    public static function visibleModules(string $surface = 'sidebar', ?string $role = null): Collection
    {
        $role ??= auth()->user()->role ?? null;

        return collect(config('navigation.modules', []))
            ->map(fn (array $module): array => self::normalizeModule($module))
            ->filter(fn (array $module): bool => self::isVisible($module, $surface, $role))
            ->sortBy([
                ['group_order', 'asc'],
                ['order', 'asc'],
                ['label', 'asc'],
            ])
            ->values();
    }

    public static function sidebarGroups(?string $role = null): Collection
    {
        $groups = collect(config('navigation.groups', []))
            ->mapWithKeys(fn (array $group): array => [$group['key'] => $group]);

        return self::visibleModules('sidebar', $role)
            ->reject(fn (array $module): bool => ($module['key'] ?? null) === 'dashboard')
            ->filter(fn (array $module): bool => filled($module['group'] ?? null))
            ->groupBy('group')
            ->map(function (Collection $modules, string $groupKey) use ($groups): array {
                $group = $groups->get($groupKey, []);
                $active = $modules->contains(fn (array $module): bool => (bool) ($module['active'] ?? false));

                return [
                    'key' => $groupKey,
                    'label' => $group['label'] ?? Str::headline($groupKey),
                    'icon' => $group['icon'] ?? 'folder',
                    'order' => $group['order'] ?? 999,
                    'active' => $active,
                    'modules' => $modules->values(),
                ];
            })
            ->sortBy('order')
            ->values();
    }

    public static function searchModules(?string $role = null): Collection
    {
        return self::visibleModules('search', $role)
            ->map(function (array $module): array {
                return [
                    'badge' => $module['badge'],
                    'description' => $module['description'],
                    'isQuickLink' => self::quickLinkRoutes()->contains($module['route']),
                    'keywords' => array_values($module['keywords'] ?? []),
                    'label' => $module['label'],
                    'route' => $module['route'],
                    'section' => $module['section'],
                    'url' => $module['url'],
                ];
            })
            ->values();
    }

    public static function quickLinks(?string $role = null): Collection
    {
        $routes = self::quickLinkRoutes();

        return self::visibleModules('search', $role)
            ->filter(fn (array $module): bool => $routes->contains($module['route']))
            ->values();
    }

    public static function currentModule(?string $role = null): ?array
    {
        $currentRouteName = request()->route()?->getName();
        $currentPath = request()->path();
        $currentPath = $currentPath === '/' ? '/' : trim($currentPath, '/');

        $modules = self::visibleModules('search', $role);

        $current = $modules->first(function (array $module) use ($currentRouteName, $currentPath): bool {
            return self::moduleMatches($module, $currentRouteName, $currentPath);
        });

        if (! $current && $currentRouteName === 'pages.profile') {
            return [
                'section' => 'Akun',
                'label' => 'Profile',
                'route' => 'pages.profile',
                'badge' => 'PR',
                'url' => Route::has('pages.profile') ? route('pages.profile') : '#',
            ];
        }

        return $current ?: self::dashboardModule();
    }

    public static function sidebarStateKeys(): array
    {
        $user = auth()->user();
        $userId = $user?->getAuthIdentifier() ?: 'guest';
        $sessionId = session()->getId() ?: 'no-session';
        $date = CarbonImmutable::now(config('app.timezone', 'Asia/Jakarta'))->toDateString();

        return [
            'groups' => "kbsm_sidebar_groups:{$userId}:{$sessionId}:{$date}",
            'scroll' => "kbsm_sidebar_scroll:{$userId}:{$sessionId}:{$date}",
            'prefixes' => ['kbsm_sidebar_groups:', 'kbsm_sidebar_scroll:'],
        ];
    }

    public static function visibleRouteNames(string $surface = 'sidebar', ?string $role = null): Collection
    {
        return self::visibleModules($surface, $role)
            ->pluck('route')
            ->filter()
            ->values();
    }

    private static function isVisible(array $module, string $surface, ?string $role): bool
    {
        if (! ($module['enabled'] ?? true)) {
            return false;
        }

        if (! ($module[$surface] ?? true)) {
            return false;
        }

        $feature = $module['feature'] ?? null;
        if ($feature && ! config("features.{$feature}", false)) {
            return false;
        }

        $roles = $module['roles'] ?? [];
        if (is_array($roles) && $roles !== [] && (! $role || ! in_array($role, $roles, true))) {
            return false;
        }

        return Route::has($module['route']);
    }

    private static function normalizeModule(array $module): array
    {
        $route = (string) ($module['route'] ?? '');
        $groupKey = $module['group'] ?? null;
        $group = $groupKey ? self::group($groupKey) : [];
        $patterns = array_values($module['route_patterns'] ?? $module['patterns'] ?? [$route]);
        $active = self::moduleMatches($module + ['route_patterns' => $patterns], request()->route()?->getName(), request()->path());
        $label = (string) ($module['label'] ?? Str::headline($module['key'] ?? $route));
        $words = preg_split('/\s+/', $label) ?: [];

        return array_merge([
            'key' => $module['key'] ?? Str::slug($route ?: $label, '_'),
            'label' => $label,
            'section' => $group['label'] ?? ($module['section'] ?? 'Navigasi'),
            'group' => $groupKey,
            'group_order' => $group['order'] ?? 999,
            'order' => $module['order'] ?? 999,
            'route' => $route,
            'route_patterns' => $patterns,
            'paths' => array_values($module['paths'] ?? []),
            'roles' => array_values($module['roles'] ?? []),
            'feature' => $module['feature'] ?? null,
            'icon' => $module['icon'] ?? 'circle',
            'description' => $module['description'] ?? '',
            'keywords' => array_values($module['keywords'] ?? []),
            'sidebar' => $module['sidebar'] ?? true,
            'search' => $module['search'] ?? true,
            'enabled' => $module['enabled'] ?? true,
        ], $module, [
            'section' => $group['label'] ?? ($module['section'] ?? 'Navigasi'),
            'group_order' => $group['order'] ?? 999,
            'route_patterns' => $patterns,
            'active' => $active,
            'url' => Route::has($route) ? route($route) : null,
            'badge' => collect($words)
                ->filter()
                ->take(2)
                ->map(fn (string $word): string => strtoupper(substr($word, 0, 1)))
                ->implode(''),
        ]);
    }

    private static function moduleMatches(array $module, ?string $currentRouteName, string $currentPath): bool
    {
        $path = $currentPath === '/' ? '/' : trim($currentPath, '/');
        $routeMatches = $currentRouteName
            && collect($module['route_patterns'] ?? $module['patterns'] ?? [])
                ->contains(fn (string $pattern): bool => request()->routeIs($pattern));

        $pathMatches = collect($module['paths'] ?? [])
            ->map(fn (string $candidate): string => $candidate === '/' ? '/' : trim($candidate, '/'))
            ->contains($path);

        return $routeMatches || $pathMatches;
    }

    private static function group(string $key): array
    {
        return collect(config('navigation.groups', []))->firstWhere('key', $key) ?? [];
    }

    private static function quickLinkRoutes(): Collection
    {
        return collect(config('navigation.quick_links', []));
    }
}
