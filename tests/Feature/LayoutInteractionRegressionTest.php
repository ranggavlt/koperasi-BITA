<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LayoutInteractionRegressionTest extends TestCase
{
    use RefreshDatabase;

    public function test_long_forms_are_not_clipped_outside_the_clickable_main_area(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        foreach (['sewa-mobil.finance.create', 'sewa-hardware.create'] as $route) {
            $this->actingAs($admin)
                ->get(route($route))
                ->assertOk()
                ->assertSee('kbsm-layout-main ease-soft-in-out relative min-h-screen', false)
                ->assertDontSee('h-full max-h-screen', false)
                ->assertSee('type="submit" class="kbsm-btn kbsm-btn--navy"', false);
        }

        $theme = file_get_contents(public_path('assets/css/kbsm-theme.css'));
        $loader = file_get_contents(public_path('assets/js/soft-ui-dashboard-tailwind.js'));

        $this->assertStringNotContainsString('overflow-x: clip', $theme);
        $this->assertStringContainsString('var to_build = "/";', $loader);
        $this->assertStringContainsString('document.getElementById("chart-bars")', $loader);
        $this->assertStringContainsString('document.getElementById("chart-line")', $loader);
    }
}
