<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

class PreflightAccessCommand extends Command
{
    protected $signature = 'koperasi:preflight-access';

    protected $description = 'Audit read-only feature flag, route role, dan celah akses aplikasi KBSM.';

    public function handle(): int
    {
        $checks = [
            $this->check('route_sensitif_tanpa_auth', 'Route sensitif tanpa auth middleware', $this->sensitiveRoutesWithoutAuth()),
            $this->check('route_finance_tanpa_role', 'Route Finance tanpa role:admin', $this->financeRoutesWithoutRole()),
            $this->check('route_karyawan_tanpa_ownership', 'Route Karyawan tanpa proteksi ownership', $this->employeeRoutesWithoutOwnership()),
            $this->check('shu_route_aktif_disabled', 'Route SHU aktif saat feature disabled', $this->shuRoutesActiveWhileDisabled()),
            $this->check('master_printer_route_aktif_disabled', 'Route Master Printer aktif saat feature disabled', $this->featureRoutesActiveWhileDisabled('aset-printer.', 'master_printer_enabled')),
            $this->check('menu_disabled_visible', 'Menu/module disabled masih terlihat', $this->disabledFeatureVisibleInNavigation()),
            $this->check('route_hard_delete_final', 'Route hard delete/edit transaksi final masih tersedia', $this->hardDeleteFinalRoutes()),
            $this->check('user_role_invalid', 'User privileged tanpa role valid', $this->invalidUserRoles()),
            $this->check('user_karyawan_tanpa_karyawan', 'User role Karyawan tanpa karyawan_id', $this->employeeUsersWithoutEmployee()),
            $this->check('akun_aktif_karyawan_berhenti', 'Akun aktif milik Karyawan berhenti', $this->activeStoppedEmployeeAccounts()),
            $this->check('akun_karyawan_ganda', 'Duplikasi akun Karyawan', $this->duplicateEmployeeAccounts()),
            $this->check('feature_flag_invalid', 'Feature flag tidak konsisten', $this->invalidFeatureFlags()),
        ];

        $this->newLine();
        $this->info('Ringkasan preflight access KBSM (read-only)');
        $this->table(
            ['Kode', 'Pemeriksaan', 'Count', 'Severity'],
            array_map(fn (array $check) => [
                $check['code'],
                $check['label'],
                $check['count'],
                $check['critical'] ? 'critical' : 'info',
            ], $checks)
        );

        $criticalCount = collect($checks)
            ->filter(fn (array $check) => $check['critical'] && $check['count'] > 0)
            ->count();

        if ($criticalCount > 0) {
            $this->error('Preflight access menemukan konflik kritis. Command ini tidak menulis database.');

            return self::FAILURE;
        }

        $this->info('Preflight access bersih: route, feature flag, dan akun lulus pemeriksaan kritis.');

        return self::SUCCESS;
    }

    private function check(string $code, string $label, int $count, bool $critical = true): array
    {
        return compact('code', 'label', 'count', 'critical');
    }

    private function sensitiveRoutesWithoutAuth(): int
    {
        return $this->webRoutes()
            ->filter(fn (RoutingRoute $route): bool => $this->isSensitiveUri($route->uri()))
            ->filter(fn (RoutingRoute $route): bool => ! $this->hasMiddleware($route, 'auth'))
            ->count();
    }

    private function financeRoutesWithoutRole(): int
    {
        return $this->webRoutes()
            ->filter(fn (RoutingRoute $route): bool => $this->isFinanceUri($route->uri()))
            ->filter(fn (RoutingRoute $route): bool => ! $this->hasRoleMiddleware($route, 'admin'))
            ->count();
    }

    private function employeeRoutesWithoutOwnership(): int
    {
        return $this->webRoutes()
            ->filter(fn (RoutingRoute $route): bool => str_starts_with(trim($route->uri(), '/'), 'pengajuan-sewa-mobil'))
            ->count();
    }

    private function shuRoutesActiveWhileDisabled(): int
    {
        return $this->featureRoutesActiveWhileDisabled('shu-koperasi.', 'shu_enabled');
    }

    private function featureRoutesActiveWhileDisabled(string $routeNamePrefix, string $feature): int
    {
        if ((bool) config("features.{$feature}", false)) {
            return 0;
        }

        return $this->webRoutes()
            ->filter(fn (RoutingRoute $route): bool => str_starts_with((string) $route->getName(), $routeNamePrefix))
            ->filter(fn (RoutingRoute $route): bool => ! $this->hasMiddleware($route, "feature:{$feature}"))
            ->count();
    }

    private function disabledFeatureVisibleInNavigation(): int
    {
        $issues = 0;

        if (! (bool) config('features.shu_enabled', false)) {
            $shuModulesWithoutFlag = collect(config('navigation.modules', []))
                ->filter(fn (array $module): bool => str_contains(strtolower(implode(' ', [
                    $module['section'] ?? '',
                    $module['label'] ?? '',
                    implode(' ', $module['keywords'] ?? []),
                ])), 'shu'))
                ->filter(fn (array $module): bool => ($module['feature'] ?? null) !== 'shu_enabled')
                ->count();

            $issues += $shuModulesWithoutFlag;
            $issues += $this->bladeFeatureGuardMissing(resource_path('views/layout/sidebar.blade.php'), 'shu-koperasi.index', 'features.shu_enabled');
            $issues += $this->plainTextInFile(resource_path('views/pages/dashboard.blade.php'), 'SHU');
            $issues += $this->plainTextInFile(resource_path('views/layout/navbar.blade.php'), 'atau SHU');
        }

        if (! (bool) config('features.jasa_print_enabled', false)) {
            foreach ([
                config_path('navigation.php'),
                resource_path('views/layout/sidebar.blade.php'),
                resource_path('views/layout/navbar.blade.php'),
                resource_path('views/pages/dashboard.blade.php'),
            ] as $path) {
                $issues += $this->plainTextInFile($path, 'Jasa Print');
            }
        }

        if (! (bool) config('features.master_printer_enabled', false)) {
            $printerModulesWithoutFlag = collect(config('navigation.modules', []))
                ->filter(fn (array $module): bool => ($module['route'] ?? null) === 'aset-printer.index')
                ->filter(fn (array $module): bool => ($module['feature'] ?? null) !== 'master_printer_enabled')
                ->count();

            $issues += $printerModulesWithoutFlag;
            $issues += $this->bladeFeatureGuardMissing(resource_path('views/layout/sidebar.blade.php'), 'aset-printer.index', 'features.master_printer_enabled');
        }

        return $issues;
    }

    private function hardDeleteFinalRoutes(): int
    {
        $forbiddenNames = [
            'penjualan.edit',
            'penjualan.destroy',
            'simpanan.edit',
            'simpanan.destroy',
            'pinjaman.edit',
            'pinjaman.destroy',
            'cicilan-pinjaman.edit',
            'cicilan-pinjaman.destroy',
            'mutasi-kas.edit',
            'mutasi-kas.destroy',
            'akuntansi.jurnal-umum.edit',
            'akuntansi.jurnal-umum.destroy',
        ];

        return collect($forbiddenNames)->filter(fn (string $name): bool => Route::has($name))->count();
    }

    private function invalidUserRoles(): int
    {
        if (! $this->hasColumns('users', ['role'])) {
            return 0;
        }

        return DB::table('users')
            ->whereNotIn('role', ['admin', 'kasir', 'karyawan'])
            ->count();
    }

    private function employeeUsersWithoutEmployee(): int
    {
        if (! $this->hasColumns('users', ['role', 'karyawan_id'])) {
            return 0;
        }

        return DB::table('users')
            ->where('role', 'karyawan')
            ->whereNull('karyawan_id')
            ->count();
    }

    private function activeStoppedEmployeeAccounts(): int
    {
        if (
            ! $this->hasTables(['users', 'karyawan'])
            || ! $this->hasColumns('users', ['role', 'karyawan_id', 'is_active'])
            || ! $this->hasColumns('karyawan', ['status_kerja'])
        ) {
            return 0;
        }

        return DB::table('users as u')
            ->join('karyawan as k', 'k.id', '=', 'u.karyawan_id')
            ->where('u.role', 'karyawan')
            ->where('u.is_active', true)
            ->where('k.status_kerja', 'berhenti')
            ->count('u.id');
    }

    private function duplicateEmployeeAccounts(): int
    {
        if (! $this->hasColumns('users', ['role', 'karyawan_id'])) {
            return 0;
        }

        return DB::table('users')
            ->select('karyawan_id', DB::raw('COUNT(*) as total'))
            ->where('role', 'karyawan')
            ->whereNotNull('karyawan_id')
            ->groupBy('karyawan_id')
            ->having('total', '>', 1)
            ->get()
            ->count();
    }

    private function invalidFeatureFlags(): int
    {
        return collect(['shu_enabled', 'jasa_print_enabled', 'master_printer_enabled'])
            ->filter(fn (string $key): bool => ! is_bool(config("features.{$key}")))
            ->count();
    }

    private function webRoutes()
    {
        return collect(Route::getRoutes())->filter(fn (RoutingRoute $route): bool => in_array('web', $route->middleware(), true));
    }

    private function hasMiddleware(RoutingRoute $route, string $middleware): bool
    {
        return collect($route->gatherMiddleware())->contains($middleware);
    }

    private function hasRoleMiddleware(RoutingRoute $route, string $role): bool
    {
        return collect($route->gatherMiddleware())
            ->filter(fn (string $middleware): bool => str_starts_with($middleware, 'role:'))
            ->contains(fn (string $middleware): bool => in_array($role, explode(',', substr($middleware, 5)), true));
    }

    private function isSensitiveUri(string $uri): bool
    {
        $path = trim($uri, '/');

        if ($path === '') {
            return false;
        }

        foreach ($this->sensitivePrefixes() as $prefix) {
            if ($path === $prefix || str_starts_with($path, $prefix . '/')) {
                return true;
            }
        }

        return false;
    }

    private function isFinanceUri(string $uri): bool
    {
        $path = trim($uri, '/');

        foreach ($this->financePrefixes() as $prefix) {
            if ($path === $prefix || str_starts_with($path, $prefix . '/')) {
                return true;
            }
        }

        return false;
    }

    private function sensitivePrefixes(): array
    {
        return array_merge($this->financePrefixes(), [
            'pages',
            'produk',
            'kategori-produk',
            'penjualan',
            'reseller',
            'pembayaran-konsinyasi',
            'pengajuan-sewa-mobil',
            'laporan-konsinyasi',
        ]);
    }

    private function financePrefixes(): array
    {
        return [
            'jenis-simpanan',
            'jenis-pinjaman',
            'dompet-koperasi',
            'aset-mobil',
            'aset-printer',
            'pengurus-koperasi',
            'simpanan',
            'pinjaman',
            'cicilan-pinjaman',
            'periode-potong-gaji',
            'mutasi-kas',
            'karyawan',
            'anggota',
            'shu-koperasi',
            'laporan-potong-gaji',
            'rekonsiliasi-potong-gaji',
            'outstanding-cash',
            'reversal-transaksi',
            'penyelesaian-keanggotaan',
            'sewa-mobil',
            'sewa-printer',
            'beban-operasional',
            'akun',
            'akuntansi',
            'users',
        ];
    }

    private function bladeFeatureGuardMissing(string $path, string $needle, string $configKey): int
    {
        if (! is_file($path)) {
            return 0;
        }

        $contents = (string) file_get_contents($path);

        return str_contains($contents, $needle) && ! str_contains($contents, "config('{$configKey}'") ? 1 : 0;
    }

    private function plainTextInFile(string $path, string $needle): int
    {
        if (! is_file($path)) {
            return 0;
        }

        return str_contains((string) file_get_contents($path), $needle) ? 1 : 0;
    }

    private function hasTables(array $tables): bool
    {
        foreach ($tables as $table) {
            if (! Schema::hasTable($table)) {
                return false;
            }
        }

        return true;
    }

    private function hasColumns(string $table, array $columns): bool
    {
        if (! Schema::hasTable($table)) {
            return false;
        }

        foreach ($columns as $column) {
            if (! Schema::hasColumn($table, $column)) {
                return false;
            }
        }

        return true;
    }
}
