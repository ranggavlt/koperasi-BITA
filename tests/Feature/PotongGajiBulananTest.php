<?php

namespace Tests\Feature;

use App\Models\Anggota;
use App\Models\CicilanPinjaman;
use App\Models\JenisSimpanan;
use App\Models\Karyawan;
use App\Models\LimitPotongGajiAnggota;
use App\Models\PemakaianPotongGaji;
use App\Models\Pembayaran;
use App\Models\Penjualan;
use App\Models\Pinjaman;
use App\Models\Simpanan;
use App\Models\User;
use App\Services\PotongGajiBulananService;
use Carbon\Carbon;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PotongGajiBulananTest extends TestCase
{
    use RefreshDatabase;

    public function test_periode_unique_dan_dinormalisasi_ke_awal_bulan_wib(): void
    {
        config(['app.timezone' => 'Asia/Jakarta']);
        $user = $this->user();
        $service = app(PotongGajiBulananService::class);

        Carbon::setTestNow(Carbon::parse('2026-07-31 18:30:00', 'UTC'));

        try {
            $periode = $service->createPeriodeDraft(null, $user->id);
            $periodeSama = $service->createPeriodeDraft('2026-08-20', $user->id);
        } finally {
            Carbon::setTestNow();
        }

        $this->assertSame('2026-08-01', $periode->periode->toDateString());
        $this->assertSame($periode->id, $periodeSama->id);
        $this->assertDatabaseCount('periode_potong_gaji', 1);
    }

    public function test_limit_per_anggota_per_periode_unique_dan_limit_kosong_berbeda_dari_nol(): void
    {
        $user = $this->user();
        $anggota = $this->anggota();
        $service = app(PotongGajiBulananService::class);

        $this->assertNull($service->findLimitFor($anggota, '2026-07'));

        $limit = $service->createLimit($anggota, '2026-07', 0, $user->id, 'Set limit nol eksplisit');

        $this->assertNotNull($service->findLimitFor($anggota, '2026-07'));
        $this->assertSame('0.00', $limit->limit_nominal);

        $this->expectException(ValidationException::class);
        $service->createLimit($anggota, '2026-07', 100000, $user->id, 'Duplikat');
    }

    public function test_header_bulan_berikutnya_boleh_dibuat_dan_renewal_dibatasi_per_anggota(): void
    {
        $user = $this->user();
        $anggotaA = $this->anggota();
        $anggotaB = $this->anggota();
        $service = app(PotongGajiBulananService::class);

        $limitAJuly = $service->createLimit($anggotaA, '2026-07', 1000000, $user->id, 'Limit Juli A');
        $limitBJuly = $service->createLimit($anggotaB, '2026-07', 1000000, $user->id, 'Limit Juli B');
        $service->activateLimit($limitAJuly, $user->id);
        $service->activateLimit($limitBJuly, $user->id);
        $service->confirmLimit($service->closeLimit($limitAJuly, $user->id), $user->id);

        $augustHeader = $service->createPeriodeDraft('2026-08', $user->id);
        $limitAAugust = $service->createLimit($anggotaA, '2026-08', 1000000, $user->id, 'Limit Agustus A');
        $limitBAugust = $service->createLimit($anggotaB, '2026-08', 1000000, $user->id, 'Limit Agustus B');

        $this->assertSame('2026-08-01', $augustHeader->periode->toDateString());
        $this->assertSame(LimitPotongGajiAnggota::STATUS_ACTIVE, $service->activateLimit($limitAAugust, $user->id)->status);

        try {
            $service->activateLimit($limitBAugust, $user->id);
            $this->fail('Aktivasi harus ditolak karena limit periode sebelumnya milik Anggota B belum confirmed.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('belum confirmed', $exception->getMessage());
        }
    }

    public function test_anggota_atau_karyawan_nonaktif_ditolak_saat_aktivasi_limit(): void
    {
        $user = $this->user();
        $service = app(PotongGajiBulananService::class);
        $anggotaNonaktif = $this->anggota([], [
            'status' => Anggota::STATUS_NONAKTIF,
            'tanggal_nonaktif' => '2026-07-01',
        ]);
        $limitNonaktif = $service->createLimit($anggotaNonaktif, '2026-07', 1000000, $user->id, 'Limit nonaktif');

        $this->expectException(ValidationException::class);
        $service->activateLimit($limitNonaktif, $user->id);
    }

    public function test_karyawan_berhenti_ditolak_saat_aktivasi_limit(): void
    {
        $user = $this->user();
        $service = app(PotongGajiBulananService::class);
        $anggota = $this->anggota([
            'status_kerja' => Karyawan::STATUS_BERHENTI,
            'tanggal_berhenti' => '2026-07-01',
        ]);
        $limit = $service->createLimit($anggota, '2026-07', 1000000, $user->id, 'Limit karyawan berhenti');

        $this->expectException(ValidationException::class);
        $service->activateLimit($limit, $user->id);
    }

    public function test_perubahan_limit_mencatat_histori_dan_menolak_penurunan_di_bawah_pemakaian(): void
    {
        $user = $this->user();
        $anggota = $this->anggota();
        $service = app(PotongGajiBulananService::class);
        $limit = $service->createLimit($anggota, '2026-07', 200000, $user->id, 'Limit awal');

        $limit = $service->updateLimit($limit, 150000, $user->id, 'Penyesuaian draft');
        $this->assertDatabaseHas('riwayat_limit_potong_gaji', [
            'limit_potong_gaji_anggota_id' => $limit->id,
            'nominal_sebelum' => '200000.00',
            'nominal_sesudah' => '150000.00',
            'changed_by' => $user->id,
            'alasan' => 'Penyesuaian draft',
        ]);

        $limit = $service->activateLimit($limit, $user->id);
        $service->createLedgerEntry($limit, [
            'kategori' => PemakaianPotongGaji::KATEGORI_CICILAN,
            'source_type' => CicilanPinjaman::class,
            'source_id' => 1,
            'jenis' => PemakaianPotongGaji::JENIS_RESERVASI,
            'nominal' => 60000,
            'idempotency_key' => 'reserve-cicilan-1',
            'created_by' => $user->id,
        ]);
        $service->createLedgerEntry($limit, [
            'kategori' => PemakaianPotongGaji::KATEGORI_POS,
            'source_type' => Penjualan::class,
            'source_id' => 1,
            'jenis' => PemakaianPotongGaji::JENIS_PEMAKAIAN,
            'nominal' => 40000,
            'idempotency_key' => 'pos-1',
            'created_by' => $user->id,
        ]);

        try {
            $service->updateLimit($limit, 99999, $user->id, 'Turun terlalu rendah');
            $this->fail('Penurunan limit di bawah reserved + consumed harus ditolak.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('reservasi dan pemakaian', $exception->getMessage());
        }

        $service->updateLimit($limit, 100000, $user->id, 'Turun sampai batas minimum');

        $confirmedLimit = $service->activateLimit(
            $service->createLimit($this->anggota(), '2026-07', 100000, $user->id, 'Limit untuk confirmed'),
            $user->id
        );
        $confirmedLimit = $service->confirmLimit($service->closeLimit($confirmedLimit, $user->id), $user->id);

        $this->assertSame(LimitPotongGajiAnggota::STATUS_CONFIRMED, $confirmedLimit->status);

        $this->expectException(ValidationException::class);
        $service->updateLimit($confirmedLimit, 120000, $user->id, 'Edit setelah confirmed');
    }

    public function test_idempotency_ledger_unique_dan_reversal_tidak_menghapus_record_awal(): void
    {
        $user = $this->user();
        $anggota = $this->anggota();
        $service = app(PotongGajiBulananService::class);
        $limit = $service->activateLimit(
            $service->createLimit($anggota, '2026-07', 200000, $user->id, 'Limit awal'),
            $user->id
        );

        $entry = $service->createLedgerEntry($limit, [
            'kategori' => PemakaianPotongGaji::KATEGORI_POS,
            'source_type' => Penjualan::class,
            'source_id' => 10,
            'jenis' => PemakaianPotongGaji::JENIS_PEMAKAIAN,
            'nominal' => 50000,
            'idempotency_key' => 'pos-10',
            'created_by' => $user->id,
        ]);

        try {
            $service->createLedgerEntry($limit, [
                'kategori' => PemakaianPotongGaji::KATEGORI_POS,
                'source_type' => Penjualan::class,
                'source_id' => 11,
                'jenis' => PemakaianPotongGaji::JENIS_PEMAKAIAN,
                'nominal' => 25000,
                'idempotency_key' => 'pos-10',
                'created_by' => $user->id,
            ]);
            $this->fail('Idempotency key ledger harus unique.');
        } catch (QueryException) {
            $this->assertDatabaseCount('pemakaian_potong_gaji', 1);
        }

        $reversal = $service->reverseLedgerEntry($entry, $user->id, 'reverse-pos-10');

        $this->assertDatabaseCount('pemakaian_potong_gaji', 2);
        $this->assertSame(PemakaianPotongGaji::STATUS_REVERSED, $entry->fresh()->status);
        $this->assertSame($entry->id, $reversal->reversal_of_id);
    }

    public function test_preflight_mendeteksi_konflik_dan_tidak_menulis_database(): void
    {
        $karyawan = Karyawan::factory()->create();
        $jenis = JenisSimpanan::query()->create([
            'nama_jenis' => 'Simpanan Uji',
            'wajib' => false,
            'aktif' => true,
        ]);

        Penjualan::query()->create([
            'kode_transaksi' => 'PJL-PREFLIGHT-1',
            'karyawan_id' => $karyawan->id,
            'total_harga' => 100000,
            'diskon' => 0,
            'grand_total' => 100000,
        ]);
        $penjualanDobel = Penjualan::query()->create([
            'kode_transaksi' => 'PJL-PREFLIGHT-2',
            'karyawan_id' => $karyawan->id,
            'total_harga' => 50000,
            'diskon' => 0,
            'grand_total' => 50000,
        ]);
        Pembayaran::query()->create([
            'penjualan_id' => $penjualanDobel->id,
            'metode_pembayaran' => 'tunai',
            'jumlah_bayar' => 50000,
        ]);
        Simpanan::query()->create([
            'karyawan_id' => $karyawan->id,
            'jenis_simpanan_id' => $jenis->id,
            'jumlah' => 100000,
            'tanggal' => '2026-07-01',
        ]);
        Pinjaman::query()->create([
            'karyawan_id' => $karyawan->id,
            'jumlah_pinjaman' => 6000000,
            'bunga_persen' => 1,
            'tenor_bulan' => 13,
            'sisa_pinjaman' => 6000000,
            'status' => 'aktif',
            'tanggal_pinjaman' => '2026-07-01',
        ]);

        $before = [
            'penjualan' => Penjualan::query()->count(),
            'pembayaran' => Pembayaran::query()->count(),
            'simpanan' => Simpanan::query()->count(),
            'pinjaman' => Pinjaman::query()->count(),
        ];

        $this->artisan('koperasi:preflight-potong-gaji')
            ->assertExitCode(1);

        $this->assertSame($before['penjualan'], Penjualan::query()->count());
        $this->assertSame($before['pembayaran'], Pembayaran::query()->count());
        $this->assertSame($before['simpanan'], Simpanan::query()->count());
        $this->assertSame($before['pinjaman'], Pinjaman::query()->count());
    }

    public function test_seeder_tidak_menghasilkan_data_ambigu_atau_orphan(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->artisan('koperasi:preflight-potong-gaji')
            ->assertExitCode(0);

        $this->assertSame(0, Penjualan::query()->doesntHave('pembayaran')->count());
        $this->assertSame(0, Simpanan::query()->whereNull('anggota_id')->count());
        $this->assertSame(0, Pinjaman::query()->whereNull('anggota_id')->count());
        $this->assertSame(0, Pinjaman::query()->where('bunga_persen', '!=', 0)->count());
        $this->assertSame(0, Pinjaman::query()->where('tenor_bulan', '>', 12)->count());
        $this->assertSame(0, Pinjaman::query()->where('jumlah_pinjaman', '>', 5000000)->count());
        $this->assertSame(0, JenisSimpanan::query()
            ->where('kategori', JenisSimpanan::KATEGORI_POKOK)
            ->where('aktif', true)
            ->count());
        $this->assertSame(1, JenisSimpanan::query()
            ->where('kode', JenisSimpanan::KODE_SIMPANAN_WAJIB)
            ->where('aktif', true)
            ->whereNull('interval_bulan')
            ->where('nominal_default', '10000.00')
            ->count());
        $this->assertSame(1, JenisSimpanan::query()
            ->where('kode', JenisSimpanan::KODE_SIMPANAN_MANASUKA)
            ->where('aktif', true)
            ->count());
    }

    private function user(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    private function anggota(array $karyawanAttributes = [], array $anggotaAttributes = []): Anggota
    {
        $karyawan = Karyawan::factory()->create($karyawanAttributes);

        return Anggota::factory()->create($anggotaAttributes + [
            'karyawan_id' => $karyawan->id,
            'status' => $anggotaAttributes['status'] ?? Anggota::STATUS_AKTIF,
        ]);
    }
}
