<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CicilanPinjamanLayoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_cicilan_filter_uses_its_dedicated_responsive_layout(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->get(route('cicilan-pinjaman.index'))
            ->assertOk()
            ->assertSee('kbsm-business-filter--cicilan', false)
            ->assertSee('kbsm-business-filter__actions--split', false);
    }
}
