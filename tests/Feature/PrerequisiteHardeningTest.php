<?php

namespace Tests\Feature;

use App\Models\Karyawan;
use App\Models\Simpanan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PrerequisiteHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_testing_runtime_is_permanently_isolated_to_sqlite_memory(): void
    {
        $this->assertTrue(app()->environment('testing'));
        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        $this->assertSame('bootstrap/cache/config.testing.php', getenv('APP_CONFIG_CACHE'));
        $this->assertFalse(app()->configurationIsCached());
    }

    public function test_user_karyawan_relation_is_available_again(): void
    {
        $karyawan = Karyawan::factory()->create();
        $user = User::factory()->create([
            'role' => 'karyawan',
            'karyawan_id' => $karyawan->id,
        ]);

        $this->assertTrue($user->karyawan()->is($karyawan));
        $this->assertTrue($user->fresh()->karyawan->is($karyawan));
    }

    public function test_simpanan_manasuka_canonical_method_without_legacy_alias(): void
    {
        $simpanan = new Simpanan([
            'kode_jenis_snapshot' => \App\Models\JenisSimpanan::KODE_SIMPANAN_MANASUKA,
        ]);

        $this->assertTrue($simpanan->isSimpananManasuka());
        $this->assertFalse(method_exists($simpanan, 'isSimpananSukarela'));
    }
}
