<?php

namespace Tests\Feature;

use App\Models\Anggota;
use App\Models\JenisSimpanan;
use App\Models\Karyawan;
use App\Models\KategoriProduk;
use App\Models\LimitPotongGajiAnggota;
use App\Models\OverrideLimitPotongGajiAnggota;
use App\Models\Pembayaran;
use App\Models\PemakaianPotongGaji;
use App\Models\Penjualan;
use App\Models\Perusahaan;
use App\Models\Produk;
use App\Models\Simpanan;
use App\Models\User;
use App\Services\MasterDataKoperasiService;
use App\Services\PosCheckoutService;
use App\Services\PotongGajiBulananService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PotongGajiPg1Test extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_bulk_generate_membuat_limit_umum_1500000_untuk_semua_anggota_eligible_dan_idempotent(): void
    {
        $finance = $this->finance();
        $service = app(PotongGajiBulananService::class);
        $this->createPolicy('2026-07-01', 1500000, $finance);

        $bee = $this->company('BEE', 'Bita Enarcon Engineering');
        $anggotaA = $this->anggota($bee, 'A');
        $anggotaB = $this->anggota($bee, 'B');
        $this->anggota(null, 'Tanpa Perusahaan');

        $summary = $service->bulkGenerateLimitsForPeriod('2026-07-01', $finance->id);

        $this->assertSame(2, $summary['created']);
        $this->assertSame(0, $summary['existing']);
        $this->assertSame(1, $summary['failed']);
        $this->assertCount(1, $summary['warnings']);
        $this->assertDatabaseCount('limit_potong_gaji_anggota', 2);

        foreach ([$anggotaA, $anggotaB] as $anggota) {
            $limit = app(PotongGajiBulananService::class)->findLimitFor($anggota, '2026-07-01');
            $this->assertNotNull($limit);
            $this->assertSame('1500000.00', $limit->limit_nominal);
            $this->assertSame(LimitPotongGajiAnggota::SUMBER_LIMIT_UMUM, $limit->sumber_limit);
            $this->assertSame('BEE', $limit->perusahaan_kode_snapshot);
            $this->assertTrue((bool) $limit->kredit_waserba_enabled_snapshot);
        }

        $second = $service->bulkGenerateLimitsForPeriod('2026-07-01', $finance->id);
        $this->assertSame(0, $second['created']);
        $this->assertSame(2, $second['existing']);
        $this->assertDatabaseCount('limit_potong_gaji_anggota', 2);
    }

    public function test_limit_khusus_menggantikan_umum_dan_tetap_berlaku_periode_berikutnya(): void
    {
        $finance = $this->finance();
        $service = app(PotongGajiBulananService::class);
        $this->createPolicy('2026-07-01', 1500000, $finance);
        $anggota = $this->anggota($this->company('BEE', 'Bita Enarcon Engineering'), 'Budi');

        $service->setMemberOverride($anggota, 800000, '2026-07-01', $finance->id, 'Limit khusus Budi.');
        $service->bulkGenerateLimitsForPeriod('2026-07-01', $finance->id);
        $service->bulkGenerateLimitsForPeriod('2026-08-01', $finance->id);

        $july = $service->findLimitFor($anggota, '2026-07-01');
        $august = $service->findLimitFor($anggota, '2026-08-01');

        $this->assertSame('800000.00', $july->limit_nominal);
        $this->assertSame('800000.00', $august->limit_nominal);
        $this->assertSame(LimitPotongGajiAnggota::SUMBER_OVERRIDE_ANGGOTA, $july->sumber_limit);
        $this->assertSame(LimitPotongGajiAnggota::SUMBER_OVERRIDE_ANGGOTA, $august->sumber_limit);
    }

    public function test_reset_ke_limit_umum_tidak_mengubah_periode_yang_sudah_dipakai_dan_berlaku_berikutnya(): void
    {
        $finance = $this->finance();
        $service = app(PotongGajiBulananService::class);
        $this->createPolicy('2026-07-01', 1500000, $finance);
        $anggota = $this->anggota($this->company('BEE', 'Bita Enarcon Engineering'), 'Reset');

        $service->setMemberOverride($anggota, 800000, '2026-07-01', $finance->id, 'Limit khusus sementara.');
        $service->bulkGenerateLimitsForPeriod('2026-07-01', $finance->id);
        $july = $service->activateLimit($service->findLimitFor($anggota, '2026-07-01'), $finance->id);

        $service->createLedgerEntry($july, [
            'kategori' => PemakaianPotongGaji::KATEGORI_POS,
            'source_type' => Penjualan::class,
            'source_id' => 999,
            'jenis' => PemakaianPotongGaji::JENIS_PEMAKAIAN,
            'nominal' => 100000,
            'idempotency_key' => 'pg1-reset-used-ledger',
            'created_by' => $finance->id,
            'updated_by' => $finance->id,
        ]);

        $service->resetMemberOverrideToGlobal($anggota, $finance->id, 'Kembalikan ke limit umum.');
        $service->bulkGenerateLimitsForPeriod('2026-08-01', $finance->id);

        $this->assertSame('800000.00', $july->fresh()->limit_nominal);
        $this->assertSame('1500000.00', $service->findLimitFor($anggota, '2026-08-01')->limit_nominal);
        $this->assertSame(OverrideLimitPotongGajiAnggota::STATUS_INACTIVE, $anggota->overrideLimitPotongGaji()->firstOrFail()->status);
    }

    public function test_perubahan_limit_umum_dan_perusahaan_berlaku_mulai_periode_berikutnya(): void
    {
        $finance = $this->finance();
        $service = app(PotongGajiBulananService::class);
        $bee = $this->company('BEE', 'Bita Enarcon Engineering');
        $bbs = $this->company('BBS', 'Bita Bina Semesta');
        $anggota = $this->anggota($bee, 'Pindah');

        $this->createPolicy('2026-07-01', 1500000, $finance);
        $service->bulkGenerateLimitsForPeriod('2026-07-01', $finance->id);

        $service->updateGlobalPolicy(2000000, '2026-08-01', $finance->id, 'Naik periode berikutnya.');
        $anggota->karyawan->update(['perusahaan_id' => $bbs->id]);
        $service->bulkGenerateLimitsForPeriod('2026-08-01', $finance->id);

        $july = $service->findLimitFor($anggota, '2026-07-01');
        $august = $service->findLimitFor($anggota, '2026-08-01');

        $this->assertSame('1500000.00', $july->limit_nominal);
        $this->assertSame('BEE', $july->perusahaan_kode_snapshot);
        $this->assertSame('2000000.00', $august->limit_nominal);
        $this->assertSame('BBS', $august->perusahaan_kode_snapshot);
    }

    public function test_kredit_waserba_nonaktif_menolak_pos_payroll_tetapi_tidak_memblokir_wajib(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-10 09:00:00', 'Asia/Jakarta'));
        $finance = $this->finance();
        $service = app(PotongGajiBulananService::class);
        $this->createPolicy('2026-07-01', 1500000, $finance);
        $anggota = $this->anggota($this->company('BEE', 'Bita Enarcon Engineering'), 'Waserba Off');
        $produk = $this->produk();

        $service->setWaserbaCredit($anggota, false, $finance->id, 'Kredit belanja ditahan sementara.');
        $summary = $service->bulkGenerateLimitsForPeriod('2026-07-01', $finance->id);
        $this->assertSame(1, $summary['created']);

        $limit = $service->activateLimit($service->findLimitFor($anggota, '2026-07-01'), $finance->id);
        $this->assertGreaterThan(0, PemakaianPotongGaji::query()
            ->where('limit_potong_gaji_anggota_id', $limit->id)
            ->where('kategori', PemakaianPotongGaji::KATEGORI_SIMPANAN_WAJIB)
            ->count());

        $this->expectValidation(function () use ($anggota, $produk, $finance): void {
            app(PosCheckoutService::class)->checkout([
                'tipe_pelanggan' => Penjualan::TIPE_ANGGOTA,
                'anggota_id' => $anggota->id,
                'metode_pembayaran' => Pembayaran::METODE_POTONG_GAJI,
                'tanggal_transaksi' => '2026-07-10 09:00:00',
                'diskon' => 0,
                'items' => [
                    ['produk_id' => $produk->id, 'jumlah' => 1],
                ],
            ], $finance->id);
        });
    }

    public function test_preflight_mendeteksi_manasuka_masuk_payroll(): void
    {
        $finance = $this->finance();
        $this->createPolicy('2026-07-01', 1500000, $finance);
        $anggota = $this->anggota($this->company('BEE', 'Bita Enarcon Engineering'), 'Manasuka');
        $jenis = JenisSimpanan::query()->where('kode', JenisSimpanan::KODE_SIMPANAN_MANASUKA)->firstOrFail();

        Simpanan::query()->create([
            'anggota_id' => $anggota->id,
            'karyawan_id' => $anggota->karyawan_id,
            'jenis_simpanan_id' => $jenis->id,
            'kode_jenis_snapshot' => $jenis->kode,
            'nama_jenis_snapshot' => $jenis->nama_jenis,
            'jumlah' => '50000.00',
            'tanggal' => '2026-07-10',
            'jenis_transaksi' => Simpanan::JENIS_SETORAN,
            'metode_pembayaran' => Simpanan::METODE_POTONG_GAJI,
            'status' => Simpanan::STATUS_PENDING_PAYROLL,
            'keterangan' => 'Data invalid untuk preflight PG-1.',
        ]);

        $this->artisan('koperasi:preflight-potong-gaji')
            ->expectsOutputToContain('manasuka_masuk_payroll')
            ->assertFailed();
    }

    private function finance(): User
    {
        return User::factory()->create([
            'role' => 'admin',
            'is_active' => true,
            'must_change_password' => false,
        ]);
    }

    private function createPolicy(string $period, int $nominal, User $finance): void
    {
        app(PotongGajiBulananService::class)->updateGlobalPolicy($nominal, $period, $finance->id, 'Policy PG-1 test.');
    }

    private function company(string $code, string $name): Perusahaan
    {
        return Perusahaan::query()->updateOrCreate(['kode' => $code], ['nama' => $name]);
    }

    private function anggota(?Perusahaan $perusahaan, string $suffix): Anggota
    {
        $karyawan = Karyawan::factory()->create([
            'nama' => 'Anggota PG1 ' . $suffix,
            'email' => strtolower(str_replace(' ', '.', $suffix)) . '.' . uniqid() . '@pg1.test',
            'perusahaan_id' => $perusahaan?->id,
            'status_kerja' => Karyawan::STATUS_AKTIF,
        ]);

        return app(MasterDataKoperasiService::class)->createAnggota([
            'karyawan_id' => $karyawan->id,
            'tanggal_bergabung' => '2026-01-01',
            'alamat' => 'Jl. PG-1',
            'plafon_pinjaman' => 5000000,
        ])->fresh(['karyawan.perusahaan', 'overrideLimitPotongGaji']);
    }

    private function produk(): Produk
    {
        $kategori = KategoriProduk::query()->create([
            'nama_kategori' => 'Kategori PG1 ' . uniqid(),
        ]);

        return Produk::query()->create([
            'nama_produk' => 'Produk PG1',
            'kategori_id' => $kategori->id,
            'harga_beli' => 50000,
            'harga_jual' => 50000,
            'stok' => 10,
            'konsinyasi' => false,
            'harga_setor' => 0,
        ]);
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
}
