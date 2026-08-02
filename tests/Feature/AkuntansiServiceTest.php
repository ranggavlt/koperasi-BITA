<?php

namespace Tests\Feature;

use App\Models\Akun;
use App\Models\DompetKoperasi;
use App\Models\JenisSimpanan;
use App\Models\JurnalUmum;
use App\Models\Karyawan;
use App\Models\Simpanan;
use App\Services\AkuntansiService;
use App\Services\MasterDataKoperasiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class AkuntansiServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_setoran_simpanan_wajib_memakai_akun_master_dan_balance(): void
    {
        $karyawan = Karyawan::query()->create([
            'nama' => 'Anggota Uji',
        ]);
        $this->daftarkanAnggota($karyawan);

        $jurnal = JurnalUmum::query()
            ->where('idempotency_key', 'like', 'simpanan-wajib:pengakuan:jurnal:%')
            ->with('details.akun')
            ->firstOrFail();
        $piutangPotongGaji = $jurnal->details->firstWhere('akun_kode', '103');
        $simpananWajib = $jurnal->details->firstWhere('akun_kode', '301');

        $this->assertNotNull($piutangPotongGaji);
        $this->assertNotNull($simpananWajib);
        $this->assertSame('10000.00', $piutangPotongGaji->debit);
        $this->assertSame('10000.00', $simpananWajib->kredit);
        $this->assertSame($piutangPotongGaji->akun_id, $piutangPotongGaji->akun->id);
        $this->assertSame($simpananWajib->akun_id, $simpananWajib->akun->id);
        $this->assertEquals(
            (float) $jurnal->details->sum('debit'),
            (float) $jurnal->details->sum('kredit')
        );
    }

    public function test_jenis_simpanan_tanpa_mapping_ditolak_agar_tidak_salah_akun(): void
    {
        $karyawan = Karyawan::query()->create([
            'nama' => 'Anggota Uji',
        ]);
        $this->daftarkanAnggota($karyawan);

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

    public function test_akun_dompet_nonaktif_tidak_boleh_dipakai_posting_simpanan(): void
    {
        Akun::query()->where('kode_akun', '101')->update(['is_aktif' => false]);

        $karyawan = Karyawan::query()->create([
            'nama' => 'Anggota Uji',
        ]);
        $this->daftarkanAnggota($karyawan);

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
        $dompet = DompetKoperasi::query()->create([
            'akun_id' => Akun::query()->where('kode_akun', '101')->value('id'),
            'nama_dompet' => 'Kas Nonaktif',
            'jenis_dompet' => DompetKoperasi::JENIS_KAS,
            'saldo' => 0,
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Akun Dompet Simpanan harus aktif');

        app(AkuntansiService::class)->recordSimpanan($simpanan, $dompet->akun);
    }

    private function daftarkanAnggota(Karyawan $karyawan): void
    {
        app(MasterDataKoperasiService::class)->createAnggota([
            'karyawan_id' => $karyawan->id,
            'tanggal_bergabung' => '2026-01-01',
            'alamat' => 'Jl. Dummy Akuntansi',
            'plafon_pinjaman' => 1000000,
        ]);
    }
}
