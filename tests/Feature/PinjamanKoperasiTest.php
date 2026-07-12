<?php

namespace Tests\Feature;

use App\Models\Akun;
use App\Models\Anggota;
use App\Models\DompetKoperasi;
use App\Models\JadwalCicilanPinjaman;
use App\Models\JurnalUmum;
use App\Models\Karyawan;
use App\Models\MutasiKas;
use App\Models\PemakaianPotongGaji;
use App\Models\Pinjaman;
use App\Models\User;
use App\Services\AkuntansiService;
use App\Services\MasterDataKoperasiService;
use App\Services\PinjamanKoperasiService;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class PinjamanKoperasiTest extends TestCase
{
    use RefreshDatabase;

    public function test_hanya_anggota_dan_karyawan_aktif_dapat_meminjam(): void
    {
        $service = app(PinjamanKoperasiService::class);
        $keuangan = $this->user('keuangan');
        $dompet = $this->dompet();
        $anggotaNonaktif = $this->anggota(1000000, Anggota::STATUS_NONAKTIF);

        $this->expectException(ValidationException::class);

        $service->create($this->payload($anggotaNonaktif, $dompet), $keuangan->id);
    }

    public function test_karyawan_berhenti_tidak_dapat_meminjam(): void
    {
        $service = app(PinjamanKoperasiService::class);
        $keuangan = $this->user('keuangan');
        $dompet = $this->dompet();
        $anggota = $this->anggota(1000000);
        $anggota->karyawan->update([
            'status_kerja' => Karyawan::STATUS_BERHENTI,
            'tanggal_berhenti' => '2026-07-01',
        ]);

        $this->expectException(ValidationException::class);

        $service->create($this->payload($anggota, $dompet), $keuangan->id);
    }

    public function test_satu_anggota_maksimal_satu_pinjaman_aktif_dan_database_melindungi_race(): void
    {
        $service = app(PinjamanKoperasiService::class);
        $keuangan = $this->user('keuangan');
        $dompet = $this->dompet(10000000);
        $anggota = $this->anggota(5000000);

        $service->create($this->payload($anggota, $dompet), $keuangan->id);

        $this->expectException(ValidationException::class);
        $service->create($this->payload($anggota, $dompet), $keuangan->id);
    }

    public function test_database_unique_menolak_dua_pinjaman_aktif_untuk_anggota_yang_sama(): void
    {
        $anggota = $this->anggota(5000000);

        Pinjaman::query()->create($this->rawPinjaman($anggota, ['kode_pinjaman' => 'PJM-202607-900001']));

        $this->expectException(QueryException::class);
        Pinjaman::query()->create($this->rawPinjaman($anggota, ['kode_pinjaman' => 'PJM-202607-900002']));
    }

    public function test_batas_nominal_plafon_bunga_dan_tenor(): void
    {
        $service = app(PinjamanKoperasiService::class);
        $keuangan = $this->user('keuangan');
        $dompet = $this->dompet(10000000);

        $this->expectValidation(fn () => $service->create($this->payload($this->anggota(6000000), $dompet, [
            'jumlah_pinjaman' => 5000001,
        ]), $keuangan->id));

        $this->expectValidation(fn () => $service->create($this->payload($this->anggota(1000000), $dompet, [
            'jumlah_pinjaman' => 1500000,
        ]), $keuangan->id));

        $this->expectValidation(fn () => $service->create($this->payload($this->anggota(5000000), $dompet, [
            'tenor_bulan' => 13,
        ]), $keuangan->id));

        $pinjaman = $service->create($this->payload($this->anggota(5000000), $dompet, [
            'jumlah_pinjaman' => 1000000,
            'tenor_bulan' => 12,
            'bunga_persen' => 99,
        ]), $keuangan->id);

        $this->assertSame('0.00', $pinjaman->bunga_persen);
    }

    public function test_snapshot_plafon_tidak_berubah_saat_plafon_anggota_diubah(): void
    {
        $service = app(PinjamanKoperasiService::class);
        $keuangan = $this->user('keuangan');
        $dompet = $this->dompet();
        $anggota = $this->anggota(3000000);

        $pinjaman = $service->create($this->payload($anggota, $dompet, [
            'jumlah_pinjaman' => 2500000,
        ]), $keuangan->id);

        $anggota->update(['plafon_pinjaman' => 1000000]);

        $this->assertSame('3000000.00', $pinjaman->fresh()->plafon_pinjaman_snapshot);
    }

    public function test_kode_pinjaman_bulanan_unique_dan_mengikuti_timezone_wib(): void
    {
        config(['app.timezone' => 'Asia/Jakarta']);
        $service = app(PinjamanKoperasiService::class);
        $keuangan = $this->user('keuangan');
        $dompet = $this->dompet(10000000);

        $pinjamanA = $service->create($this->payload($this->anggota(5000000), $dompet, [
            'tanggal_pinjaman' => Carbon::parse('2026-07-31 18:30:00', 'UTC'),
        ]), $keuangan->id);
        $pinjamanB = $service->create($this->payload($this->anggota(5000000), $dompet, [
            'tanggal_pinjaman' => '2026-08-15',
        ]), $keuangan->id);
        $pinjamanC = $service->create($this->payload($this->anggota(5000000), $dompet, [
            'tanggal_pinjaman' => '2026-09-01',
        ]), $keuangan->id);

        $this->assertSame('PJM-202608-000001', $pinjamanA->kode_pinjaman);
        $this->assertSame('PJM-202608-000002', $pinjamanB->kode_pinjaman);
        $this->assertSame('PJM-202609-000001', $pinjamanC->kode_pinjaman);
        $this->assertDatabaseHas('nomor_urut_transaksi', [
            'jenis' => 'pinjaman',
            'periode' => '202608',
            'last_number' => 2,
        ]);
    }

    public function test_jadwal_cicilan_dibuat_bulan_berikutnya_seimbang_dan_pembulatan_di_akhir(): void
    {
        $service = app(PinjamanKoperasiService::class);
        $pinjaman = $service->create($this->payload($this->anggota(5000000), $this->dompet(10000000), [
            'jumlah_pinjaman' => 5000000,
            'tenor_bulan' => 3,
            'tanggal_pinjaman' => '2026-07-10',
        ]), $this->user('keuangan')->id);

        $jadwal = $pinjaman->jadwalCicilan()->orderBy('angsuran_ke')->get();

        $this->assertCount(3, $jadwal);
        $this->assertSame('2026-08-01', $jadwal[0]->periode->toDateString());
        $this->assertSame('1666666.00', $jadwal[0]->nominal_pokok);
        $this->assertSame('1666666.00', $jadwal[1]->nominal_pokok);
        $this->assertSame('1666668.00', $jadwal[2]->nominal_pokok);
        $this->assertSame(5000000.0, (float) $jadwal->sum('nominal_pokok'));

        $this->expectException(QueryException::class);
        JadwalCicilanPinjaman::query()->create([
            'pinjaman_id' => $pinjaman->id,
            'angsuran_ke' => 1,
            'periode' => '2026-11-01',
            'nominal_pokok' => 1,
            'status' => JadwalCicilanPinjaman::STATUS_SCHEDULED,
        ]);
    }

    public function test_dompet_harus_punya_coa_dan_saldo_cukup(): void
    {
        $service = app(PinjamanKoperasiService::class);
        $keuangan = $this->user('keuangan');

        $this->expectValidation(fn () => $service->create($this->payload($this->anggota(5000000), $this->dompetTanpaAkun()), $keuangan->id));
        $this->expectValidation(fn () => $service->create($this->payload($this->anggota(5000000), $this->dompet(100000), [
            'jumlah_pinjaman' => 1000000,
        ]), $keuangan->id));
    }

    public function test_pencairan_mengurangi_saldo_sekali_dan_membuat_mutasi_jurnal_balance(): void
    {
        $service = app(PinjamanKoperasiService::class);
        $keuangan = $this->user('keuangan');
        $dompet = $this->dompet(3000000);

        $pinjaman = $service->create($this->payload($this->anggota(3000000), $dompet, [
            'jumlah_pinjaman' => 1250000,
        ]), $keuangan->id);

        $this->assertSame('1750000.00', $dompet->fresh()->saldo);
        $this->assertSame(1, MutasiKas::query()->where('referensi_tipe', Pinjaman::class)->where('referensi_id', $pinjaman->id)->count());
        $this->assertSame(1, JurnalUmum::query()->where('referensi_tipe', Pinjaman::class)->where('referensi_id', $pinjaman->id)->count());
        $this->assertSame(0, PemakaianPotongGaji::query()->count());

        $jurnal = $pinjaman->jurnal()->with('details')->firstOrFail();
        $this->assertSame($pinjaman->kode_pinjaman, $jurnal->nomor_bukti);
        $this->assertEquals((float) $jurnal->details->sum('debit'), (float) $jurnal->details->sum('kredit'));
        $this->assertNotNull($jurnal->details->firstWhere('akun_kode', '105'));
        $this->assertNotNull($jurnal->details->firstWhere('akun_kode', '101'));
    }

    public function test_kegagalan_posting_rollback_seluruh_transaksi(): void
    {
        $dompet = $this->dompet(3000000);
        $anggota = $this->anggota(3000000);
        $saldoAwal = $dompet->saldo;
        $jurnalAwal = JurnalUmum::query()->count();
        $mutasiAwal = MutasiKas::query()->count();

        $mock = Mockery::mock(AkuntansiService::class);
        $mock->shouldReceive('recordPencairanPinjaman')
            ->once()
            ->andThrow(new RuntimeException('posting gagal'));
        $this->app->instance(AkuntansiService::class, $mock);

        try {
            app(PinjamanKoperasiService::class)->create($this->payload($anggota, $dompet), $this->user('keuangan')->id);
            $this->fail('Pencairan harus rollback saat posting gagal.');
        } catch (RuntimeException $exception) {
            $this->assertSame('posting gagal', $exception->getMessage());
        }

        $this->assertSame($saldoAwal, $dompet->fresh()->saldo);
        $this->assertDatabaseCount('pinjaman', 0);
        $this->assertDatabaseCount('jadwal_cicilan_pinjaman', 0);
        $this->assertDatabaseCount('mutasi_kas', $mutasiAwal);
        $this->assertDatabaseCount('jurnal_umum', $jurnalAwal);
        $this->assertDatabaseCount('nomor_urut_transaksi', 0);
    }

    public function test_kasir_ditolak_dan_route_hapus_manual_dinonaktifkan(): void
    {
        $kasir = $this->user('kasir');

        $this->actingAs($kasir)
            ->post(route('pinjaman.store'), [])
            ->assertForbidden();

        $this->assertFalse(Route::has('pinjaman.destroy'));
        $this->assertFalse(Route::has('cicilan-pinjaman.store'));
        $this->assertFalse(Route::has('cicilan-pinjaman.destroy'));
    }

    public function test_pinjaman_jadwal_mutasi_dan_jurnal_pencairan_tidak_dapat_dihapus_permanen(): void
    {
        $pinjaman = app(PinjamanKoperasiService::class)->create(
            $this->payload($this->anggota(3000000), $this->dompet()),
            $this->user('keuangan')->id
        );

        $this->expectDeleteGuard(fn () => $pinjaman->delete());
        $this->expectDeleteGuard(fn () => $pinjaman->jadwalCicilan()->firstOrFail()->delete());
        $this->expectDeleteGuard(fn () => $pinjaman->mutasiKas()->firstOrFail()->delete());
        $this->expectDeleteGuard(fn () => $pinjaman->jurnal()->firstOrFail()->delete());
    }

    public function test_preflight_mendeteksi_jadwal_dan_pinjaman_tidak_valid(): void
    {
        $pinjaman = app(PinjamanKoperasiService::class)->create(
            $this->payload($this->anggota(3000000), $this->dompet(), [
                'tenor_bulan' => 3,
            ]),
            $this->user('keuangan')->id
        );

        DB::table('jadwal_cicilan_pinjaman')
            ->where('pinjaman_id', $pinjaman->id)
            ->where('angsuran_ke', 3)
            ->delete();

        DB::table('pinjaman')
            ->where('id', $pinjaman->id)
            ->update(['bunga_persen' => 1]);

        $this->artisan('koperasi:preflight-potong-gaji')
            ->assertExitCode(1);
    }

    private function user(string $role): User
    {
        return User::factory()->create(['role' => $role]);
    }

    private function anggota(int $plafon = 5000000, string $status = Anggota::STATUS_AKTIF): Anggota
    {
        $karyawan = Karyawan::factory()->create();
        $anggota = app(MasterDataKoperasiService::class)->createAnggota([
            'karyawan_id' => $karyawan->id,
            'tanggal_bergabung' => '2026-01-01',
            'alamat' => 'Jl. Pinjaman Test',
            'plafon_pinjaman' => $plafon,
        ]);

        if ($status !== Anggota::STATUS_AKTIF) {
            $anggota->update([
                'status' => $status,
                'tanggal_nonaktif' => '2026-07-01',
            ]);
        }

        return $anggota->fresh('karyawan');
    }

    private function dompet(int $saldo = 10000000): DompetKoperasi
    {
        return DompetKoperasi::query()->create([
            'akun_id' => Akun::query()->where('kode_akun', '101')->value('id'),
            'nama_dompet' => 'Kas Test ' . uniqid(),
            'saldo' => $saldo,
        ]);
    }

    private function dompetTanpaAkun(): DompetKoperasi
    {
        return DompetKoperasi::query()->create([
            'nama_dompet' => 'Dompet Tanpa COA',
            'saldo' => 10000000,
        ]);
    }

    private function payload(Anggota $anggota, DompetKoperasi $dompet, array $overrides = []): array
    {
        return $overrides + [
            'anggota_id' => $anggota->id,
            'dompet_id' => $dompet->id,
            'jumlah_pinjaman' => 1000000,
            'tenor_bulan' => 10,
            'tanggal_pinjaman' => '2026-07-10',
            'keterangan' => 'Pinjaman test',
        ];
    }

    private function rawPinjaman(Anggota $anggota, array $overrides = []): array
    {
        return $overrides + [
            'anggota_id' => $anggota->id,
            'karyawan_id' => $anggota->karyawan_id,
            'jumlah_pinjaman' => 1000000,
            'plafon_pinjaman_snapshot' => 5000000,
            'bunga_persen' => 0,
            'tenor_bulan' => 10,
            'sisa_pinjaman' => 1000000,
            'status' => Pinjaman::STATUS_AKTIF,
            'tanggal_pinjaman' => '2026-07-10',
        ];
    }

    private function expectValidation(callable $callback): void
    {
        try {
            $callback();
            $this->fail('ValidationException expected.');
        } catch (ValidationException) {
            $this->assertTrue(true);
        }
    }

    private function expectDeleteGuard(callable $callback): void
    {
        try {
            $callback();
            $this->fail('Delete guard expected.');
        } catch (RuntimeException) {
            $this->assertTrue(true);
        }
    }
}
