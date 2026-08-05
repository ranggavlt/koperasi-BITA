<?php

namespace Tests\Feature;

use App\Models\ShuConfig;
use App\Models\ShuKoperasi;
use App\Models\PeriodeAkuntansi;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class ShuConfigurationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_prepare_versioned_configuration_while_shu_operation_is_disabled(): void
    {
        config(['features.shu_enabled' => false]);
        $admin = User::factory()->create([
            'role' => 'admin',
            'is_active' => true,
            'must_change_password' => false,
        ]);
        $kasir = User::factory()->create([
            'role' => 'kasir',
            'is_active' => true,
            'must_change_password' => false,
        ]);

        $this->get(route('shu-config.index'))->assertRedirect(route('login'));
        $this->actingAs($kasir)->get(route('shu-config.index'))->assertForbidden();
        $this->actingAs($admin)->get(route('shu-config.index'))
            ->assertOk()
            ->assertSee('Pengaturan Persentase SHU')
            ->assertSee('Operasional SHU masih nonaktif');
        $this->actingAs($admin)->get(route('shu-koperasi.index'))->assertNotFound();

        $this->actingAs($admin)
            ->post(route('shu-config.update'), $this->payload(['persen_dana_sosial' => 4]))
            ->assertSessionHasErrors('persen_dana_cadangan');
        $this->assertDatabaseCount('shu_configs', 0);

        $this->actingAs($admin)
            ->post(route('shu-config.update'), $this->payload())
            ->assertRedirect();

        $config = ShuConfig::query()->sole();
        $this->assertSame(ShuConfig::STATUS_APPROVED, $config->status_persetujuan);
        $this->assertSame($admin->id, $config->approved_by);
        $this->assertNotNull($config->approved_at);

        $this->expectException(RuntimeException::class);
        $config->update(['persen_pengurus' => 11]);
    }

    public function test_new_shu_period_uses_effective_config_snapshot_and_ignores_percentage_payload(): void
    {
        config(['features.shu_enabled' => true]);
        $admin = User::factory()->create([
            'role' => 'admin',
            'is_active' => true,
            'must_change_password' => false,
        ]);

        $this->actingAs($admin)->post(route('shu-config.update'), $this->payload())->assertRedirect();
        $config = ShuConfig::query()->sole();

        $accountingPeriod = PeriodeAkuntansi::query()->create([
            'kode' => 'FY-2026',
            'nama' => 'Tahun Buku 2026',
            'tanggal_mulai' => '2026-01-01',
            'tanggal_selesai' => '2026-12-31',
            'status' => PeriodeAkuntansi::STATUS_CLOSED,
            'total_pendapatan' => 1000000,
            'total_beban' => 400000,
            'laba_bersih' => 600000,
            'jumlah_jurnal' => 2,
            'checksum' => hash('sha256', 'test'),
            'closing_snapshot' => ['source' => 'posted_general_ledger'],
            'created_by' => $admin->id,
            'closed_by' => $admin->id,
            'closed_at' => now(),
            'idempotency_key' => 'period-config-test',
        ]);

        $this->actingAs($admin)->post(route('shu-koperasi.store'), [
            'judul' => 'SHU Tahun Buku 2026',
            'periode_akuntansi_id' => $accountingPeriod->id,
            'keterangan' => 'Periode dengan snapshot konfigurasi',
            'persen_dana_cadangan' => 100,
            'persen_shu_anggota' => 0,
        ])->assertRedirect();

        $period = ShuKoperasi::query()->sole();
        $this->assertSame('40.00', $period->persen_dana_cadangan);
        $this->assertSame('40.00', $period->persen_shu_anggota);
        $this->assertSame('10.00', $period->persen_pengurus);
        $this->assertSame($config->id, $period->config_snapshot['shu_config_id']);
        $this->assertSame('Keputusan RAT tahun buku 2026', $period->config_snapshot['dasar_persetujuan']);
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'persen_dana_cadangan' => 40,
            'persen_anggota' => 40,
            'persen_pengawas' => 0,
            'persen_pembina' => 0,
            'persen_pengurus' => 10,
            'persen_dana_sosial' => 5,
            'persen_dana_pendidikan' => 5,
            'persen_jasa_modal' => 50,
            'persen_jasa_usaha' => 50,
            'berlaku_mulai' => '2026-01-01',
            'dasar_persetujuan' => 'Keputusan RAT tahun buku 2026',
        ], $overrides);
    }
}
