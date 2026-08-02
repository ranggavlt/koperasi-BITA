<?php

namespace Tests\Feature;

use App\Models\Akun;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class AkunTest extends TestCase
{
    use RefreshDatabase;

    public function test_keuangan_dapat_melihat_coa_sistem(): void
    {
        $user = User::factory()->create(['role' => 'admin']);

        $this->actingAs($user)
            ->get(route('akun.index'))
            ->assertOk()
            ->assertSee('Chart of Accounts')
            ->assertSee('101')
            ->assertSee('Kas');
    }

    public function test_keuangan_dapat_menambah_akun_dengan_saldo_normal_otomatis(): void
    {
        $user = User::factory()->create(['role' => 'admin']);

        $this->actingAs($user)
            ->post(route('akun.store'), [
                'kode_akun' => '199',
                'nama_akun' => 'Aset Lainnya',
                'kategori' => 'aset',
                'keterangan' => 'Tagihan selain potong gaji.',
            ])
            ->assertRedirect(route('akun.index'));

        $this->assertDatabaseHas('akun', [
            'kode_akun' => '199',
            'nama_akun' => 'Aset Lainnya',
            'kategori' => 'aset',
            'posisi_saldo' => 'debit',
            'is_sistem' => false,
        ]);
    }

    public function test_coa_tidak_menyediakan_route_edit_dan_hapus(): void
    {
        $this->assertTrue(Route::has('akun.index'));
        $this->assertTrue(Route::has('akun.store'));
        $this->assertFalse(Route::has('akun.edit'));
        $this->assertFalse(Route::has('akun.update'));
        $this->assertFalse(Route::has('akun.destroy'));
    }

    public function test_kode_akun_harus_unik(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $existing = Akun::query()->where('kode_akun', '101')->firstOrFail();

        $this->actingAs($user)
            ->from(route('akun.index'))
            ->post(route('akun.store'), [
                'kode_akun' => $existing->kode_akun,
                'nama_akun' => 'Kas Duplikat',
                'kategori' => 'aset',
            ])
            ->assertRedirect(route('akun.index'))
            ->assertSessionHasErrors('kode_akun');

        $this->assertSame(1, Akun::query()->where('kode_akun', '101')->count());
    }
}
