<?php

namespace Tests\Feature;

use App\Services\AkuntansiService;
use App\Services\MutasiKasService;
use Database\Seeders\AkunSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CoreAccountingIntegrityTest extends TestCase
{
    use RefreshDatabase;

    public function test_account_map_dan_seeder_memisahkan_utang_vendor_mobil_dari_dana_sosial(): void
    {
        $this->seed(AkunSeeder::class);

        $conflictingCodes = collect(config('account_map.accounts'))
            ->groupBy('kode_akun')
            ->filter(fn ($accounts): bool => $accounts
                ->map(fn (array $account): string => implode('|', [
                    $account['nama_akun'],
                    $account['kategori'],
                    $account['posisi_saldo'],
                ]))
                ->unique()
                ->count() > 1);
        $this->assertCount(0, $conflictingCodes);

        $this->assertDatabaseHas('akun', [
            'kode_akun' => '209',
            'nama_akun' => 'Utang Vendor Sewa Mobil',
            'kategori' => 'kewajiban',
            'posisi_saldo' => 'kredit',
        ]);
        $this->assertDatabaseHas('akun', [
            'kode_akun' => '210',
            'nama_akun' => 'Dana Sosial Tersedia',
            'kategori' => 'kewajiban',
            'posisi_saldo' => 'kredit',
        ]);
        $this->assertSame(1, DB::table('akun')->where('kode_akun', '209')->count());
        $this->assertSame(1, DB::table('akun')->where('kode_akun', '210')->count());

        $this->artisan('koperasi:preflight-accounting-integrity')->assertExitCode(0);
    }

    public function test_service_inti_tidak_menyediakan_api_penghapus_jurnal_dan_mutasi_asli(): void
    {
        $this->assertFalse(method_exists(AkuntansiService::class, 'reverseByReference'));
        $this->assertFalse(method_exists(MutasiKasService::class, 'reverseByReference'));
        $this->assertFalse(method_exists(MutasiKasService::class, 'deleteAndReverse'));
    }
}
