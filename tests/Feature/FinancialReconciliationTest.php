<?php

namespace Tests\Feature;

use App\Models\DompetKoperasi;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinancialReconciliationTest extends TestCase
{
    use RefreshDatabase;

    public function test_rekonsiliasi_final_bersih_setelah_seed_dan_tetap_read_only(): void
    {
        $this->seed(DatabaseSeeder::class);
        $before = DompetKoperasi::query()->pluck('saldo', 'id')->all();

        $this->artisan('koperasi:preflight-financial-reconciliation')->assertExitCode(0);

        $this->assertSame($before, DompetKoperasi::query()->pluck('saldo', 'id')->all());
    }

    public function test_rekonsiliasi_mendeteksi_saldo_dompet_yang_diubah_tanpa_mutasi(): void
    {
        $this->seed(DatabaseSeeder::class);
        $wallet = DompetKoperasi::query()->firstOrFail();
        $wallet->increment('saldo', 1);
        $corruptedBalance = $wallet->fresh()->saldo;

        $this->artisan('koperasi:preflight-financial-reconciliation')->assertExitCode(1);

        $this->assertSame($corruptedBalance, $wallet->fresh()->saldo);
    }
}
