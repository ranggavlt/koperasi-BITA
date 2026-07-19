<?php

namespace Tests\Feature;

use App\Models\Akun;
use App\Models\Anggota;
use App\Models\CicilanPinjaman;
use App\Models\DompetKoperasi;
use App\Models\JadwalCicilanPinjaman;
use App\Models\JurnalUmum;
use App\Models\Karyawan;
use App\Models\LimitPotongGajiAnggota;
use App\Models\MutasiKas;
use App\Models\PemakaianPotongGaji;
use App\Models\Pinjaman;
use App\Models\Simpanan;
use App\Models\User;
use App\Services\AkuntansiService;
use App\Services\MasterDataKoperasiService;
use App\Services\PinjamanKoperasiService;
use App\Services\PotongGajiBulananService;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class PotongGajiTahap2CTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_aktivasi_limit_mereservasi_cicilan_dan_idempotent_tanpa_posting(): void
    {
        $service = app(PotongGajiBulananService::class);
        $user = $this->user();
        $pinjaman = $this->pinjamanDenganJadwalJuli(jumlah: 900000, tenor: 3);
        $limit = $service->createLimit($pinjaman->anggota, '2026-07', 500000, $user->id, 'Limit Juli');

        $limit = $service->activateLimit($limit, $user->id);
        $limit = $service->activateLimit($limit, $user->id);

        $jadwal = $pinjaman->jadwalCicilan()->whereDate('periode', '2026-07-01')->firstOrFail();
        $ledger = PemakaianPotongGaji::query()->firstOrFail();

        $this->assertSame(LimitPotongGajiAnggota::STATUS_ACTIVE, $limit->status);
        $this->assertSame(JadwalCicilanPinjaman::STATUS_RESERVED, $jadwal->fresh()->status);
        $this->assertSame(JadwalCicilanPinjaman::METODE_POTONG_GAJI, $jadwal->fresh()->metode_penyelesaian);
        $this->assertSame('300000.00', $ledger->nominal);
        $this->assertSame(20000000, $limit->fresh()->sisaLimitCents());
        $this->assertSame(1, PemakaianPotongGaji::query()->count());
        $this->assertSame(0, MutasiKas::query()->where('referensi_tipe', CicilanPinjaman::class)->count());
        $this->assertSame(0, JurnalUmum::query()->where('referensi_tipe', CicilanPinjaman::class)->count());
    }

    public function test_limit_kurang_dari_cicilan_ditolak_tanpa_data_parsial(): void
    {
        $service = app(PotongGajiBulananService::class);
        $user = $this->user();
        $pinjaman = $this->pinjamanDenganJadwalJuli(jumlah: 900000, tenor: 3);
        $limit = $service->createLimit($pinjaman->anggota, '2026-07', 250000, $user->id, 'Limit kurang');

        $this->expectException(\Illuminate\Validation\ValidationException::class);

        try {
            $service->activateLimit($limit, $user->id);
        } finally {
            $this->assertSame(0, PemakaianPotongGaji::query()->count());
            $this->assertSame(JadwalCicilanPinjaman::STATUS_SCHEDULED, $pinjaman->jadwalCicilan()->first()->fresh()->status);
        }
    }

    public function test_konfirmasi_payroll_memakai_bank_default_membayar_cicilan_dan_menjurnal(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-20 10:00:00', 'Asia/Jakarta'));
        $service = app(PotongGajiBulananService::class);
        $user = $this->user();
        $bank = $this->bankDefaultPayroll();
        $pinjaman = $this->pinjamanDenganJadwalJuli(jumlah: 300000, tenor: 1);
        $limit = $service->activateLimit(
            $service->createLimit($pinjaman->anggota, '2026-07', 300000, $user->id, 'Limit Juli'),
            $user->id
        );

        $service->confirmLimit($service->closeLimit($limit, $user->id), $user->id);

        $payment = CicilanPinjaman::query()->firstOrFail();
        $jadwal = $pinjaman->jadwalCicilan()->firstOrFail();
        $jurnal = $payment->jurnal()->with('details')->firstOrFail();

        $this->assertSame(CicilanPinjaman::METODE_POTONG_GAJI, $payment->metode_pembayaran);
        $this->assertSame($bank->id, $payment->dompet_id);
        $this->assertSame(JadwalCicilanPinjaman::STATUS_PAID, $jadwal->fresh()->status);
        $this->assertSame('0.00', $pinjaman->fresh()->sisa_pinjaman);
        $this->assertSame(Pinjaman::STATUS_LUNAS, $pinjaman->fresh()->status);
        $this->assertSame('300000.00', $bank->fresh()->saldo);
        $this->assertSame(PemakaianPotongGaji::STATUS_SETTLED, PemakaianPotongGaji::query()->firstOrFail()->status);
        $this->assertNotNull($jurnal->details->firstWhere('akun_kode', '102'));
        $this->assertNotNull($jurnal->details->firstWhere('akun_kode', '105'));
    }

    public function test_konfirmasi_tanpa_bank_default_ditolak_dan_rollback_jika_posting_gagal(): void
    {
        $service = app(PotongGajiBulananService::class);
        $user = $this->user();
        $pinjaman = $this->pinjamanDenganJadwalJuli(jumlah: 300000, tenor: 1);
        $limit = $service->closeLimit(
            $service->activateLimit($service->createLimit($pinjaman->anggota, '2026-07', 300000, $user->id, 'Limit Juli'), $user->id),
            $user->id
        );

        $this->expectException(\Illuminate\Validation\ValidationException::class);

        try {
            $service->confirmLimit($limit, $user->id);
        } finally {
            $this->assertSame(0, CicilanPinjaman::query()->count());
            $this->assertSame(PemakaianPotongGaji::STATUS_RESERVED, PemakaianPotongGaji::query()->firstOrFail()->status);
        }
    }

    public function test_kegagalan_jurnal_payroll_rollback_seluruh_perubahan_konfirmasi(): void
    {
        $user = $this->user();
        $bank = $this->bankDefaultPayroll();
        $pinjaman = $this->pinjamanDenganJadwalJuli(jumlah: 300000, tenor: 1);
        $service = app(PotongGajiBulananService::class);
        $limit = $service->closeLimit(
            $service->activateLimit($service->createLimit($pinjaman->anggota, '2026-07', 300000, $user->id, 'Limit Juli'), $user->id),
            $user->id
        );

        $mock = Mockery::mock(AkuntansiService::class);
        $mock->shouldReceive('recordPembayaranCicilan')->once()->andThrow(new RuntimeException('jurnal gagal'));
        $this->app->instance(AkuntansiService::class, $mock);

        try {
            app(PotongGajiBulananService::class)->confirmLimit($limit, $user->id);
            $this->fail('Konfirmasi harus rollback saat jurnal gagal.');
        } catch (RuntimeException $exception) {
            $this->assertSame('jurnal gagal', $exception->getMessage());
        }

        $this->assertSame('0.00', $bank->fresh()->saldo);
        $this->assertSame(0, CicilanPinjaman::query()->count());
        $this->assertSame(PemakaianPotongGaji::STATUS_RESERVED, PemakaianPotongGaji::query()->firstOrFail()->status);
        $this->assertSame(JadwalCicilanPinjaman::STATUS_RESERVED, $pinjaman->jadwalCicilan()->firstOrFail()->fresh()->status);
        $this->assertSame('300000.00', $pinjaman->fresh()->sisa_pinjaman);
    }

    public function test_karyawan_berhenti_melepaskan_reservasi_belum_confirmed_tetapi_tidak_membatalkan_confirmed(): void
    {
        $service = app(PotongGajiBulananService::class);
        $user = $this->user();
        $pinjaman = $this->pinjamanDenganJadwalJuli(jumlah: 300000, tenor: 1);
        $limit = $service->activateLimit(
            $service->createLimit($pinjaman->anggota, '2026-07', 300000, $user->id, 'Limit Juli'),
            $user->id
        );

        app(MasterDataKoperasiService::class)->updateKaryawan($pinjaman->anggota->karyawan, [
            'nama' => $pinjaman->anggota->karyawan->nama,
            'email' => $pinjaman->anggota->karyawan->email,
            'telepon' => $pinjaman->anggota->karyawan->telepon,
            'jabatan' => $pinjaman->anggota->karyawan->jabatan,
            'status_kerja' => Karyawan::STATUS_BERHENTI,
            'tanggal_berhenti' => '2026-07-15',
        ]);

        $this->assertSame(LimitPotongGajiAnggota::STATUS_CANCELLED, $limit->fresh()->status);
        $this->assertSame(PemakaianPotongGaji::STATUS_RELEASED, PemakaianPotongGaji::query()->firstOrFail()->status);
        $this->assertSame(JadwalCicilanPinjaman::STATUS_SCHEDULED, $pinjaman->jadwalCicilan()->firstOrFail()->fresh()->status);

        $bank = $this->bankDefaultPayroll();
        $pinjamanConfirmed = $this->pinjamanDenganJadwalJuli(jumlah: 300000, tenor: 1);
        $limitConfirmed = $service->activateLimit(
            $service->createLimit($pinjamanConfirmed->anggota, '2026-07', 300000, $user->id, 'Limit Juli confirmed'),
            $user->id
        );
        $service->confirmLimit($service->closeLimit($limitConfirmed, $user->id), $user->id);
        app(MasterDataKoperasiService::class)->updateKaryawan($pinjamanConfirmed->anggota->karyawan, [
            'nama' => $pinjamanConfirmed->anggota->karyawan->nama,
            'email' => $pinjamanConfirmed->anggota->karyawan->email,
            'telepon' => $pinjamanConfirmed->anggota->karyawan->telepon,
            'jabatan' => $pinjamanConfirmed->anggota->karyawan->jabatan,
            'status_kerja' => Karyawan::STATUS_BERHENTI,
            'tanggal_berhenti' => '2026-07-31',
        ]);

        $this->assertSame(LimitPotongGajiAnggota::STATUS_CONFIRMED, $limitConfirmed->fresh()->status);
        $this->assertSame('300000.00', $bank->fresh()->saldo);
    }

    public function test_mantan_karyawan_bisa_bayar_tunai_terjadwal_dan_lunasi_penuh_tetapi_anggota_aktif_ditolak(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-20 10:00:00', 'Asia/Jakarta'));
        $service = app(PotongGajiBulananService::class);
        $user = $this->user();
        $kas = $this->kasDompet();

        $pinjamanAktif = $this->pinjamanDenganJadwalJuli(jumlah: 600000, tenor: 2);
        try {
            $service->payScheduledCash($pinjamanAktif, $kas, $user->id);
            $this->fail('Anggota aktif harus ditolak dari pembayaran tunai rutin.');
        } catch (\Illuminate\Validation\ValidationException $exception) {
            $this->assertSame(0, CicilanPinjaman::query()->count());
        }

        $pinjaman = $this->pinjamanDenganJadwalJuli(jumlah: 600000, tenor: 2);
        $this->stopKaryawan($pinjaman->anggota->karyawan);
        $payment = $service->payScheduledCash($pinjaman, $kas, $user->id);

        $this->assertSame(CicilanPinjaman::METODE_TUNAI, $payment->metode_pembayaran);
        $this->assertSame('300000.00', $pinjaman->fresh()->sisa_pinjaman);
        $this->assertSame('300000.00', $kas->fresh()->saldo);

        $payments = $service->payFullCash($pinjaman->fresh(), $kas->fresh(), $user->id);

        $this->assertCount(1, $payments);
        $this->assertSame(Pinjaman::STATUS_LUNAS, $pinjaman->fresh()->status);
        $this->assertSame('600000.00', $kas->fresh()->saldo);
    }

    public function test_pelunasan_penuh_payroll_menolak_limit_kurang_dan_tidak_menggandakan_reservasi(): void
    {
        $service = app(PotongGajiBulananService::class);
        $user = $this->user();
        $this->bankDefaultPayroll();

        $pinjamanKurang = $this->pinjamanDenganJadwalJuli(jumlah: 900000, tenor: 3);
        $limitKurang = $service->activateLimit(
            $service->createLimit($pinjamanKurang->anggota, '2026-07', 500000, $user->id, 'Limit kurang payoff'),
            $user->id
        );

        try {
            $service->reserveFullPayoffPayroll($limitKurang, $user->id);
            $this->fail('Pelunasan penuh payroll harus ditolak jika sisa limit kurang.');
        } catch (\Illuminate\Validation\ValidationException $exception) {
            $this->assertSame(1, PemakaianPotongGaji::query()->count());
        }

        $pinjaman = $this->pinjamanDenganJadwalJuli(jumlah: 900000, tenor: 3);
        $limit = $service->activateLimit(
            $service->createLimit($pinjaman->anggota, '2026-07', 900000, $user->id, 'Limit payoff'),
            $user->id
        );
        $service->reserveFullPayoffPayroll($limit, $user->id);
        $service->reserveFullPayoffPayroll($limit, $user->id);

        $this->assertSame(4, PemakaianPotongGaji::query()->count()); // 1 dari skenario kurang + 3 dari pelunasan valid
        $service->confirmLimit($service->closeLimit($limit, $user->id), $user->id);
        $this->assertSame(Pinjaman::STATUS_LUNAS, $pinjaman->fresh()->status);
    }

    public function test_default_bank_unique_dan_preflight_mendeteksi_inkonsistensi_utama(): void
    {
        $this->bankDefaultPayroll();

        $this->expectException(QueryException::class);

        try {
            $this->bankDefaultPayroll('Bank Payroll Kedua');
        } catch (QueryException $exception) {
            $pinjaman = $this->pinjamanDenganJadwalJuli(jumlah: 300000, tenor: 1);
            $pinjaman->jadwalCicilan()->firstOrFail()->update(['status' => JadwalCicilanPinjaman::STATUS_PAID]);

            $this->artisan('koperasi:preflight-potong-gaji')->assertExitCode(1);

            throw $exception;
        }
    }

    private function user(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    private function anggota(): Anggota
    {
        $karyawan = Karyawan::factory()->create();

        $anggota = app(MasterDataKoperasiService::class)->createAnggota([
            'karyawan_id' => $karyawan->id,
            'tanggal_bergabung' => '2026-01-01',
            'alamat' => 'Jl. Tahap 2C',
            'plafon_pinjaman' => 5000000,
        ]);

        $anggota->simpanan()
            ->where('kode_jenis_snapshot', \App\Models\JenisSimpanan::KODE_SIMPANAN_POKOK)
            ->update([
                'status' => Simpanan::STATUS_SETTLED,
                'settled_at' => now(),
            ]);

        return $anggota->fresh('karyawan');
    }

    private function pinjamanDenganJadwalJuli(int $jumlah, int $tenor): Pinjaman
    {
        return app(PinjamanKoperasiService::class)->create([
            'anggota_id' => $this->anggota()->id,
            'dompet_id' => $this->kasDompet(10000000)->id,
            'jumlah_pinjaman' => $jumlah,
            'tenor_bulan' => $tenor,
            'tanggal_pinjaman' => '2026-06-10',
            'keterangan' => 'Pinjaman test 2C',
        ], $this->user()->id);
    }

    private function kasDompet(int $saldo = 0): DompetKoperasi
    {
        return DompetKoperasi::query()->create([
            'akun_id' => Akun::query()->where('kode_akun', '101')->value('id'),
            'nama_dompet' => 'Kas Test ' . uniqid(),
            'jenis_dompet' => DompetKoperasi::JENIS_KAS,
            'saldo' => $saldo,
        ]);
    }

    private function bankDefaultPayroll(string $nama = 'Bank Payroll Test'): DompetKoperasi
    {
        return DompetKoperasi::query()->create([
            'akun_id' => Akun::query()->where('kode_akun', '102')->value('id'),
            'nama_dompet' => $nama . ' ' . uniqid(),
            'jenis_dompet' => DompetKoperasi::JENIS_BANK,
            'is_default_penerimaan_payroll' => true,
            'saldo' => 0,
        ]);
    }

    private function stopKaryawan(Karyawan $karyawan): void
    {
        app(MasterDataKoperasiService::class)->updateKaryawan($karyawan, [
            'nama' => $karyawan->nama,
            'email' => $karyawan->email,
            'telepon' => $karyawan->telepon,
            'jabatan' => $karyawan->jabatan,
            'status_kerja' => Karyawan::STATUS_BERHENTI,
            'tanggal_berhenti' => '2026-07-01',
        ]);
    }
}
