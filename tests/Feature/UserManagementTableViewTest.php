<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserManagementTableViewTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_melihat_tabel_manajemen_user_yang_ringkas_dan_rapi(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $account = User::factory()->create([
            'name' => 'Kasir Tampilan',
            'email' => 'kasir.tampilan@kbsm.test',
            'role' => 'kasir',
        ]);

        $this->actingAs($admin)
            ->get(route('users.index'))
            ->assertOk()
            ->assertSee('user-management-table', false)
            ->assertSee('user-management-identity', false)
            ->assertSee('user-management-actions', false)
            ->assertSee('Pengguna')
            ->assertSee($account->email);
    }
}
