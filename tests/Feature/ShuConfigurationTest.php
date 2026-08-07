<?php

namespace Tests\Feature;

use App\Models\ShuConfig;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class ShuConfigurationTest extends TestCase
{
    use RefreshDatabase;

    public function test_configuration_is_feature_gated_admin_only_and_uses_fixed_final_percentages(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true, 'must_change_password' => false]);
        $cashier = User::factory()->create(['role' => 'kasir', 'is_active' => true, 'must_change_password' => false]);

        config(['features.shu_enabled' => false]);
        $this->actingAs($admin)->get(route('shu-config.index'))->assertNotFound();

        config(['features.shu_enabled' => true]);
        auth()->logout();
        $this->get(route('shu-config.index'))->assertRedirect(route('login'));
        $this->actingAs($cashier)->get(route('shu-config.index'))->assertForbidden();
        $this->actingAs($admin)->get(route('shu-config.index'))
            ->assertOk()
            ->assertSee('Pengaturan SHU')
            ->assertSee('Dana Pendidikan hanya histori');

        $this->actingAs($admin)->post(route('shu-config.store'), $this->payload([
            'persen_dana_cadangan' => 31,
        ]))->assertSessionHasErrors('persen_dana_cadangan');
        $this->assertDatabaseCount('shu_config', 0);

        $this->actingAs($admin)->post(route('shu-config.store'), $this->payload())->assertRedirect();
        $config = ShuConfig::query()->sole();
        $this->assertSame('30.00', $config->persen_dana_cadangan);
        $this->assertSame('40.00', $config->persen_shu_anggota);
        $this->assertSame('10.00', $config->persen_dana_sosial);
        $this->assertSame('0.00', $config->persen_dana_pendidikan);
        $this->assertSame(1, $config->versi);
        $this->assertSame($admin->id, $config->created_by);
    }

    public function test_configuration_versions_are_immutable_and_member_split_must_total_one_hundred(): void
    {
        config(['features.shu_enabled' => true]);
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true, 'must_change_password' => false]);

        $this->actingAs($admin)->post(route('shu-config.store'), $this->payload([
            'persen_jasa_modal' => 40,
            'persen_jasa_usaha' => 50,
        ]))->assertSessionHasErrors('persen_jasa_modal');

        $this->actingAs($admin)->post(route('shu-config.store'), $this->payload())->assertRedirect();
        $config = ShuConfig::query()->sole();
        $this->expectException(RuntimeException::class);
        $config->update(['persen_jasa_modal' => 45]);
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'berlaku_mulai' => '2025-07-01',
            'dasar_keputusan' => 'Keputusan RAT Tahun Buku 2025/2026',
            'persen_dana_cadangan' => 30,
            'persen_shu_anggota' => 40,
            'persen_pengurus' => 10,
            'persen_pengawas' => 5,
            'persen_pembina' => 5,
            'persen_dana_sosial' => 10,
            'persen_jasa_modal' => 40,
            'persen_jasa_usaha' => 60,
        ], $overrides);
    }
}
