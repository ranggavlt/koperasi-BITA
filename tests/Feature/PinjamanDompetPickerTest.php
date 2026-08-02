<?php

namespace Tests\Feature;

use App\Models\DompetKoperasi;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PinjamanDompetPickerTest extends TestCase
{
    use RefreshDatabase;

    public function test_form_pinjaman_memuat_pemilih_dompet_yang_dapat_diklik(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $dompet = DompetKoperasi::query()->create([
            'nama_dompet' => 'Kas Pencairan Test',
            'jenis_dompet' => DompetKoperasi::JENIS_KAS,
            'saldo' => 500000,
        ]);

        $this->actingAs($admin)
            ->get(route('pinjaman.create'))
            ->assertOk()
            ->assertSee('data-dompet-picker', false)
            ->assertSee('data-dompet-trigger', false)
            ->assertSee('data-dompet-option', false)
            ->assertSee($dompet->nama_dompet);
    }
}
