<?php

namespace Tests\Feature;

use App\Models\JenisSimpanan;
use App\Models\Karyawan;
use App\Models\Penjualan;
use App\Models\ShuKoperasi;
use App\Models\Simpanan;
use App\Models\User;
use App\Services\MasterDataKoperasiService;
use App\Services\ShuKoperasiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ShuKoperasiTest extends TestCase
{
    use RefreshDatabase;

    public function test_shu_pages_can_be_rendered(): void
    {
        config(['features.shu_enabled' => true]);

        $this->actingAs(User::factory()->create(['role' => 'admin']));

        $shuKoperasi = ShuKoperasi::create([
            'judul' => 'SHU Tahun 2026',
            'tanggal_mulai' => '2026-01-01',
            'tanggal_selesai' => '2026-12-31',
            'persen_dana_cadangan' => 40,
            'persen_shu_anggota' => 40,
            'persen_pengurus' => 10,
            'persen_dana_sosial' => 5,
            'persen_dana_pendidikan' => 5,
            'persen_jasa_modal' => 50,
            'persen_jasa_usaha' => 50,
        ]);

        $this->get(route('shu-koperasi.index'))->assertOk();
        $this->get(route('shu-koperasi.show', $shuKoperasi))->assertOk();
    }

    public function test_shu_koperasi_is_distributed_to_members_based_on_savings_and_sales(): void
    {
        $anggotaA = Karyawan::create([
            'nama' => 'Andi',
            'email' => 'andi@example.test',
            'telepon' => '08111',
            'jabatan' => 'Anggota',
        ]);

        $anggotaB = Karyawan::create([
            'nama' => 'Budi',
            'email' => 'budi@example.test',
            'telepon' => '08222',
            'jabatan' => 'Anggota',
        ]);

        $this->daftarkanAnggota($anggotaA);
        $this->daftarkanAnggota($anggotaB);

        $jenisSimpanan = JenisSimpanan::create([
            'nama_jenis' => 'Simpanan Wajib',
            'wajib' => true,
            'nominal_default' => 50000,
            'keterangan' => 'Test',
        ]);

        Simpanan::create([
            'karyawan_id' => $anggotaA->id,
            'jenis_simpanan_id' => $jenisSimpanan->id,
            'jumlah' => 100000,
            'tanggal' => '2026-01-10',
            'keterangan' => 'Simpanan anggota A',
        ]);

        Simpanan::create([
            'karyawan_id' => $anggotaB->id,
            'jenis_simpanan_id' => $jenisSimpanan->id,
            'jumlah' => 300000,
            'tanggal' => '2026-01-15',
            'keterangan' => 'Simpanan anggota B',
        ]);

        $penjualanA = Penjualan::create([
            'kode_transaksi' => 'PJL-100',
            'karyawan_id' => $anggotaA->id,
            'total_harga' => 200000,
            'diskon' => 0,
            'grand_total' => 200000,
        ]);

        $penjualanB = Penjualan::create([
            'kode_transaksi' => 'PJL-101',
            'karyawan_id' => $anggotaB->id,
            'total_harga' => 300000,
            'diskon' => 0,
            'grand_total' => 300000,
        ]);

        DB::table('penjualan')->where('id', $penjualanA->id)->update([
            'created_at' => '2026-01-11 09:00:00',
            'updated_at' => '2026-01-11 09:00:00',
        ]);

        DB::table('penjualan')->where('id', $penjualanB->id)->update([
            'created_at' => '2026-01-12 09:00:00',
            'updated_at' => '2026-01-12 09:00:00',
        ]);

        $service = app(ShuKoperasiService::class);

        $shuKoperasi = $service->create([
            'judul' => 'SHU Tahun 2026',
            'tanggal_mulai' => '2026-01-01',
            'tanggal_selesai' => '2026-12-31',
            'persen_dana_cadangan' => 40,
            'persen_shu_anggota' => 40,
            'persen_pengurus' => 10,
            'persen_dana_sosial' => 5,
            'persen_dana_pendidikan' => 5,
            'persen_jasa_modal' => 50,
            'persen_jasa_usaha' => 50,
            'keterangan' => 'Test SHU',
        ]);

        $service->addTransaksi($shuKoperasi, [
            'jenis' => 'pendapatan',
            'tanggal' => '2026-12-20',
            'jumlah' => 37000000,
            'keterangan' => 'Total pendapatan',
        ]);

        $service->addTransaksi($shuKoperasi->fresh(), [
            'jenis' => 'biaya',
            'tanggal' => '2026-12-21',
            'jumlah' => 13000000,
            'keterangan' => 'Total biaya',
        ]);

        $shuKoperasi = ShuKoperasi::with('anggotaPembagian')->findOrFail($shuKoperasi->id);

        $this->assertSame('24000000.00', $shuKoperasi->shu_total);
        $this->assertSame('9600000.00', $shuKoperasi->nominal_dana_cadangan);
        $this->assertSame('9600000.00', $shuKoperasi->nominal_shu_anggota);
        $this->assertSame('2400000.00', $shuKoperasi->nominal_pengurus);
        $this->assertSame('1200000.00', $shuKoperasi->nominal_dana_sosial);
        $this->assertSame('1200000.00', $shuKoperasi->nominal_dana_pendidikan);
        $this->assertSame('4800000.00', $shuKoperasi->nominal_jasa_modal);
        $this->assertSame('4800000.00', $shuKoperasi->nominal_jasa_usaha);

        $pembagian = $shuKoperasi->anggotaPembagian->keyBy('karyawan_id');

        $this->assertSame('1200000.00', $pembagian[$anggotaA->id]->nominal_jasa_modal);
        $this->assertSame('1920000.00', $pembagian[$anggotaA->id]->nominal_jasa_usaha);
        $this->assertSame('3120000.00', $pembagian[$anggotaA->id]->nominal_shu);

        $this->assertSame('3600000.00', $pembagian[$anggotaB->id]->nominal_jasa_modal);
        $this->assertSame('2880000.00', $pembagian[$anggotaB->id]->nominal_jasa_usaha);
        $this->assertSame('6480000.00', $pembagian[$anggotaB->id]->nominal_shu);
    }

    private function daftarkanAnggota(Karyawan $karyawan): void
    {
        $anggota = app(MasterDataKoperasiService::class)->createAnggota([
            'karyawan_id' => $karyawan->id,
            'tanggal_bergabung' => '2026-01-01',
            'alamat' => 'Jl. Dummy SHU',
            'plafon_pinjaman' => 1000000,
        ]);

        $anggota->simpanan()
            ->where('kode_jenis_snapshot', JenisSimpanan::KODE_SIMPANAN_WAJIB)
            ->get()
            ->each(function (Simpanan $simpanan): void {
                $simpanan->forceFill([
                    'status' => Simpanan::STATUS_REVERSED,
                    'tanggal' => '2025-12-31',
                ])->save();
            });
    }
}
