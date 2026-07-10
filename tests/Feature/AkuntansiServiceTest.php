<?php

namespace Tests\Feature;

use App\Models\Akun;
use App\Models\JenisSimpanan;
use App\Models\JurnalUmum;
use App\Models\Karyawan;
use App\Models\Simpanan;
use App\Services\AkuntansiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class AkuntansiServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_setoran_simpanan_pokok_memakai_akun_master_dan_balance(): void
    {
        $karyawan = Karyawan::query()->create([
            'nama' => 'Anggota Uji',
            'is_anggota' => true,
        ]);

        $jenis = JenisSimpanan::query()->create([
            'akun_id' => Akun::query()->where('kode_akun', '301')->value('id'),
            'nama_jenis' => 'Simpanan Pokok',
            'wajib' => true,
            'nominal_default' => 100000,
        ]);

        $simpanan = Simpanan::query()->create([
            'karyawan_id' => $karyawan->id,
            'jenis_simpanan_id' => $jenis->id,
            'jumlah' => 100000,
            'tanggal' => '2026-07-10',
        ]);

        app(AkuntansiService::class)->recordSimpanan($simpanan);

        $jurnal = JurnalUmum::query()->with('details.akun')->firstOrFail();
        $kas = $jurnal->details->firstWhere('akun_kode', '101');
        $simpananPokok = $jurnal->details->firstWhere('akun_kode', '301');

        $this->assertNotNull($kas);
        $this->assertNotNull($simpananPokok);
        $this->assertSame('100000.00', $kas->debit);
        $this->assertSame('100000.00', $simpananPokok->kredit);
        $this->assertSame($kas->akun_id, $kas->akun->id);
        $this->assertSame($simpananPokok->akun_id, $simpananPokok->akun->id);
        $this->assertEquals(
            (float) $jurnal->details->sum('debit'),
            (float) $jurnal->details->sum('kredit')
        );
    }

    public function test_jenis_simpanan_tanpa_mapping_ditolak_agar_tidak_salah_akun(): void
    {
        $karyawan = Karyawan::query()->create([
            'nama' => 'Anggota Uji',
            'is_anggota' => true,
        ]);

        $jenis = JenisSimpanan::query()->create([
            'akun_id' => null,
            'nama_jenis' => 'Simpanan Eksperimental',
            'wajib' => false,
        ]);

        $simpanan = Simpanan::query()->create([
            'karyawan_id' => $karyawan->id,
            'jenis_simpanan_id' => $jenis->id,
            'jumlah' => 50000,
            'tanggal' => '2026-07-10',
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('belum memiliki pemetaan ke master COA');

        app(AkuntansiService::class)->recordSimpanan($simpanan);
    }

    public function test_akun_sistem_nonaktif_tidak_boleh_dipakai_posting(): void
    {
        Akun::query()->where('kode_akun', '101')->update(['is_aktif' => false]);

        $karyawan = Karyawan::query()->create([
            'nama' => 'Anggota Uji',
            'is_anggota' => true,
        ]);

        $jenis = JenisSimpanan::query()->create([
            'akun_id' => Akun::query()->where('kode_akun', '302')->value('id'),
            'nama_jenis' => 'Simpanan Wajib',
            'wajib' => true,
        ]);

        $simpanan = Simpanan::query()->create([
            'karyawan_id' => $karyawan->id,
            'jenis_simpanan_id' => $jenis->id,
            'jumlah' => 50000,
            'tanggal' => '2026-07-10',
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('sedang tidak aktif');

        app(AkuntansiService::class)->recordSimpanan($simpanan);
    }
}
