<?php

namespace Tests\Feature;

use App\Models\ShuKoperasi;
use App\Models\ShuPenerima;
use App\Models\StrukturKoperasi;
use App\Services\SocialFundService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class AnnualShuAndSocialFundTest extends TestCase
{
    use RefreshDatabase;

    public function test_final_rights_balance_each_pool_and_effective_structure_is_snapshotted(): void
    {
        $this->seed();
        $shu = ShuKoperasi::query()->with('recipients')->sole();

        $this->assertNotSame($shu->created_by, $shu->approved_by);
        $this->assertNotSame($shu->calculated_by, $shu->approved_by);
        $this->assertNotSame($shu->submitted_by, $shu->approved_by);
        $this->assertSame(3, StrukturKoperasi::query()->where('kelompok', 'pengurus')->count());
        $this->assertSame(2, StrukturKoperasi::query()->where('kelompok', 'pengawas')->count());
        $this->assertSame(1, StrukturKoperasi::query()->where('kelompok', 'pembina')->count());

        foreach ([
            'anggota' => (int) $shu->nominal_shu_anggota,
            'pengurus' => (int) $shu->nominal_pengurus,
            'pengawas' => (int) $shu->nominal_pengawas,
            'pembina' => (int) $shu->nominal_pembina,
        ] as $group => $pool) {
            $actual = (int) $shu->recipients->where('jenis_penerima', $group)->where('diikutkan', true)->sum('hak_final');
            $this->assertSame($pool, $actual, "Hak Final kelompok {$group} harus sama dengan pool.");
        }

        $members = $shu->recipients->where('jenis_penerima', 'anggota')->where('diikutkan', true);
        $this->assertGreaterThanOrEqual(6, $members->count());
        $this->assertTrue($members->contains(fn (ShuPenerima $row) => (int) $row->basis_jasa_modal === 10000));
        $adjusted = $members->filter(fn (ShuPenerima $row) => (int) $row->hak_final !== (int) $row->hitungan_sistem);
        $this->assertTrue($adjusted->isNotEmpty());
        $this->assertTrue($adjusted->every(
            fn (ShuPenerima $row) => filled($row->alasan_hak_final) && filled($row->detail_alasan_hak_final)
        ));
    }

    public function test_non_shu_social_sources_and_removed_manual_shu_routes_are_rejected(): void
    {
        $this->seed();
        $admin = \App\Models\User::query()->where('role', 'admin')->firstOrFail();

        try {
            app(SocialFundService::class)->createDonation(['jumlah' => 100000], $admin->id);
            $this->fail('Donasi tidak boleh menjadi sumber Dana Sosial aktif.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('hanya berasal dari alokasi SHU', $exception->getMessage());
        }

        config(['features.shu_enabled' => true]);
        $shu = ShuKoperasi::query()->sole();
        $this->actingAs($admin)->post("/shu-koperasi/{$shu->id}/ganti-periode", ['periode_id' => $shu->periode_akuntansi_id])->assertNotFound();
        $this->actingAs($admin)->post("/shu-koperasi/{$shu->id}/bobot", [])->assertNotFound();
    }
}
