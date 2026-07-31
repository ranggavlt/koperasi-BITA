<?php

namespace Tests;

use App\Models\Akun;
use App\Models\JenisSimpanan;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    public function createApplication()
    {
        $this->forceIsolatedTestingEnvironment();

        $app = parent::createApplication();

        $this->guardTestingDatabaseIsolation();

        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->ensureOfficialJenisSimpananFixtures();
    }

    protected function ensureOfficialJenisSimpananFixtures(): void
    {
        if (! app()->environment('testing')
            || ! Schema::hasTable('akun')
            || ! Schema::hasTable('jenis_simpanan')) {
            return;
        }

        $accounts = [
            JenisSimpanan::KATEGORI_POKOK => Akun::query()->where('kode_akun', config('account_map.accounts.simpanan_pokok.kode_akun'))->value('id'),
            JenisSimpanan::KATEGORI_WAJIB => Akun::query()->where('kode_akun', config('account_map.accounts.simpanan_wajib.kode_akun'))->value('id'),
            JenisSimpanan::KATEGORI_MANASUKA => Akun::query()->where('kode_akun', config('account_map.accounts.simpanan_manasuka.kode_akun'))->value('id'),
        ];

        if (in_array(null, $accounts, true)) {
            return;
        }

        $rows = [
            JenisSimpanan::KATEGORI_POKOK => [
                'kode' => JenisSimpanan::KODE_SIMPANAN_POKOK,
                'nama_jenis' => 'Simpanan Pokok',
                'nominal_default' => '100000.00',
                'interval_bulan' => null,
                'wajib' => true,
            ],
            JenisSimpanan::KATEGORI_WAJIB => [
                'kode' => JenisSimpanan::KODE_SIMPANAN_WAJIB,
                'nama_jenis' => 'Simpanan Wajib',
                'nominal_default' => '100000.00',
                'interval_bulan' => 3,
                'wajib' => true,
            ],
            JenisSimpanan::KATEGORI_MANASUKA => [
                'kode' => JenisSimpanan::KODE_SIMPANAN_MANASUKA,
                'nama_jenis' => 'Simpanan Manasuka',
                'nominal_default' => '0.00',
                'interval_bulan' => null,
                'wajib' => false,
            ],
        ];

        foreach ($rows as $kategori => $row) {
            JenisSimpanan::query()->updateOrCreate(
                ['kode' => $row['kode']],
                [
                    'akun_id' => $accounts[$kategori],
                    'kategori' => $kategori,
                    'nama_jenis' => $row['nama_jenis'],
                    'wajib' => $row['wajib'],
                    'aktif' => true,
                    'interval_bulan' => $row['interval_bulan'],
                    'berlaku_mulai' => '2026-01-01',
                    'nominal_default' => $row['nominal_default'],
                    'keterangan' => 'Fixture resmi untuk test otomatis.',
                ]
            );
        }
    }

    private function forceIsolatedTestingEnvironment(): void
    {
        $values = [
            'APP_ENV' => 'testing',
            'APP_CONFIG_CACHE' => 'bootstrap/cache/config.testing.php',
            'DB_CONNECTION' => 'sqlite',
            'DB_DATABASE' => ':memory:',
        ];

        foreach ($values as $key => $value) {
            putenv($key.'='.$value);
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }
    }

    private function guardTestingDatabaseIsolation(): void
    {
        $connection = config('database.default');
        $database = config("database.connections.{$connection}.database");
        $environment = app()->environment();

        if ($environment === 'testing' && $connection === 'sqlite' && $database === ':memory:') {
            return;
        }

        throw new RuntimeException(sprintf(
            'KBSM testing database guard blocked test boot. Expected APP_ENV=testing, DB_CONNECTION=sqlite, DB_DATABASE=:memory:. Actual APP_ENV=%s, DB_CONNECTION=%s, DB_DATABASE=%s, APP_CONFIG_CACHE=%s.',
            $environment ?: '(empty)',
            $connection ?: '(empty)',
            is_scalar($database) ? (string) $database : gettype($database),
            getenv('APP_CONFIG_CACHE') ?: '(empty)'
        ));
    }
}
