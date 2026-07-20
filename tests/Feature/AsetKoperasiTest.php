<?php

namespace Tests\Feature;

use App\Models\AsetKoperasi;
use App\Models\AsetMobil;
use App\Models\AsetPrinter;
use App\Models\JurnalUmum;
use App\Models\Karyawan;
use App\Models\MutasiKas;
use App\Models\PemakaianPotongGaji;
use App\Models\User;
use App\Services\AsetKoperasiService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class AsetKoperasiTest extends TestCase
{
    use RefreshDatabase;

    public function test_generator_kode_per_jenis_tidak_reset_dan_tidak_reuse_setelah_delete(): void
    {
        $service = app(AsetKoperasiService::class);
        $keuangan = $this->user('admin');

        $mobilA = $service->createMobil($this->mobilPayload(['plat_nomor' => 'B 1001 KBS']), $keuangan->id);
        $printerA = $service->createPrinter($this->printerPayload(['nomor_seri' => 'PRN-1001']), $keuangan->id);
        $mobilB = $service->createMobil($this->mobilPayload(['plat_nomor' => 'B 1002 KBS']), $keuangan->id);

        $this->assertSame('MBL-0001', $mobilA->kode_aset);
        $this->assertSame('PRT-0001', $printerA->kode_aset);
        $this->assertSame('MBL-0002', $mobilB->kode_aset);

        $service->delete($mobilA, true, $keuangan->id);

        $mobilC = $service->createMobil($this->mobilPayload(['plat_nomor' => 'B 1003 KBS']), $keuangan->id);

        $this->assertSame('MBL-0003', $mobilC->kode_aset);
        $this->assertDatabaseHas('nomor_urut_aset', ['jenis_aset' => AsetKoperasi::JENIS_MOBIL, 'last_number' => 3]);
        $this->assertDatabaseHas('nomor_urut_aset', ['jenis_aset' => AsetKoperasi::JENIS_PRINTER, 'last_number' => 1]);
    }

    public function test_rollback_duplicate_detail_tidak_meninggalkan_kode_setengah(): void
    {
        $service = app(AsetKoperasiService::class);
        $keuangan = $this->user('admin');

        $service->createMobil($this->mobilPayload(['plat_nomor' => 'B 2001 KBS']), $keuangan->id);

        try {
            $service->createMobil($this->mobilPayload(['plat_nomor' => 'B 2001 KBS']), $keuangan->id);
            $this->fail('Duplikasi plat nomor seharusnya ditolak.');
        } catch (ValidationException) {
            $this->assertSame(1, AsetKoperasi::query()->count());
        }

        $mobil = $service->createMobil($this->mobilPayload(['plat_nomor' => 'B 2002 KBS']), $keuangan->id);

        $this->assertSame('MBL-0002', $mobil->kode_aset);
        $this->assertSame(2, AsetKoperasi::query()->count());
        $this->assertDatabaseHas('nomor_urut_aset', ['jenis_aset' => AsetKoperasi::JENIS_MOBIL, 'last_number' => 2]);
    }

    public function test_relasi_detail_dan_tidak_membuat_posting_keuangan(): void
    {
        $service = app(AsetKoperasiService::class);
        $keuangan = $this->user('admin');

        $mobil = $service->createMobil($this->mobilPayload(['plat_nomor' => 'B 3001 KBS']), $keuangan->id);
        $printer = $service->createPrinter($this->printerPayload(['nomor_seri' => 'PRN-3001']), $keuangan->id);

        $this->assertTrue($mobil->fresh('mobil')->isMobil());
        $this->assertNotNull($mobil->fresh('mobil')->mobil);
        $this->assertNull($mobil->fresh('printer')->printer);
        $this->assertTrue($printer->fresh('printer')->isPrinter());
        $this->assertNotNull($printer->fresh('printer')->printer);
        $this->assertNull($printer->fresh('mobil')->mobil);
        $this->assertSame(0, MutasiKas::query()->count());
        $this->assertSame(0, JurnalUmum::query()->count());
        $this->assertSame(0, PemakaianPotongGaji::query()->count());
    }

    public function test_lifecycle_status_nonaktif_idempotent_dan_aktifkan_membersihkan_audit(): void
    {
        $service = app(AsetKoperasiService::class);
        $keuangan = $this->user('admin');
        $mobil = $service->createMobil($this->mobilPayload(['plat_nomor' => 'B 4001 KBS']), $keuangan->id);

        $this->assertSame(AsetKoperasi::STATUS_TERSEDIA, $mobil->status);

        $nonaktif = $service->nonaktifkan($mobil, $keuangan->id);
        $timestamp = $nonaktif->nonaktif_at->toDateTimeString();

        $this->assertSame(AsetKoperasi::STATUS_NONAKTIF, $nonaktif->status);
        $this->assertSame($keuangan->id, $nonaktif->nonaktif_by);

        $nonaktifLagi = $service->nonaktifkan($nonaktif, $keuangan->id);
        $this->assertSame($timestamp, $nonaktifLagi->nonaktif_at->toDateTimeString());

        $aktif = $service->aktifkan($nonaktifLagi, $keuangan->id);
        $this->assertSame(AsetKoperasi::STATUS_TERSEDIA, $aktif->status);
        $this->assertNull($aktif->nonaktif_at);
        $this->assertNull($aktif->nonaktif_by);

        $this->expectException(ValidationException::class);
        $service->updateStatus($aktif, 'rusak_total', $keuangan->id);
    }

    public function test_delete_guard_menolak_status_berisiko_dan_dependency_future_sewa(): void
    {
        $service = app(AsetKoperasiService::class);
        $keuangan = $this->user('admin');
        $mobil = $service->createMobil($this->mobilPayload(['plat_nomor' => 'B 5001 KBS']), $keuangan->id);

        $service->updateStatus($mobil, AsetKoperasi::STATUS_PERAWATAN, $keuangan->id);
        $this->expectValidation(fn () => $service->delete($mobil->fresh(), true, $keuangan->id));

        $mobil = $service->aktifkan($mobil->fresh(), $keuangan->id);
        $karyawan = Karyawan::factory()->create();
        $pemohon = User::factory()->create([
            'role' => 'karyawan',
            'karyawan_id' => $karyawan->id,
        ]);

        DB::table('sewa_mobil')->insert([
            'kode_sewa' => 'SWM-TEST-0001',
            'aset_koperasi_id' => $mobil->id,
            'karyawan_id' => $karyawan->id,
            'pemohon_user_id' => $pemohon->id,
            'recorded_by' => $keuangan->id,
            'nama_perusahaan_snapshot' => 'Bita Enarcon Engineering',
            'nama_kegiatan' => 'Unit Test Sewa',
            'lokasi_kegiatan' => 'Kantor Proyek',
            'tanggal_mulai' => now()->addDay()->toDateString(),
            'tanggal_selesai' => now()->addDays(2)->toDateString(),
            'jumlah_hari' => 2,
            'tarif_harian_snapshot' => 300000,
            'total_sewa' => 600000,
            'status' => 'draft',
            'status_pembayaran' => 'belum_bayar',
            'idempotency_key' => 'aset-test-sewa-1',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->expectValidation(fn () => $service->delete($mobil->fresh(), true, $keuangan->id));
        $this->assertDatabaseHas('aset_koperasi', ['id' => $mobil->id]);
    }

    public function test_route_authorization_keuangan_kasir_dan_guest(): void
    {
        $kasir = $this->user('kasir');
        $keuangan = $this->user('admin');

        $this->get(route('aset-mobil.index'))->assertRedirect(route('login'));
        $this->actingAs($kasir)->get(route('aset-mobil.index'))->assertForbidden();
        $this->actingAs($kasir)->post(route('aset-mobil.store'), $this->mobilPayload())->assertForbidden();

        $this->actingAs($keuangan)
            ->get(route('aset-mobil.index'))
            ->assertOk()
            ->assertSee('Mobil Koperasi');

        $this->actingAs($keuangan)
            ->post(route('aset-mobil.store'), $this->mobilPayload(['plat_nomor' => 'B 6001 KBS']))
            ->assertRedirect(route('aset-mobil.index'));

        $this->actingAs($keuangan)->get('/aset-printer')->assertNotFound();
    }

    public function test_preflight_aset_mendeteksi_konflik_dan_tetap_read_only(): void
    {
        $service = app(AsetKoperasiService::class);
        $keuangan = $this->user('admin');
        $service->createMobil($this->mobilPayload(['plat_nomor' => 'B 7001 KBS']), $keuangan->id);
        $service->createPrinter($this->printerPayload(['nomor_seri' => 'PRN-7001']), $keuangan->id);

        $before = AsetKoperasi::query()->count();
        $this->artisan('koperasi:preflight-aset')->assertExitCode(0);
        $this->assertSame($before, AsetKoperasi::query()->count());

        DB::table('aset_koperasi')->where('kode_aset', 'MBL-0001')->update(['status' => 'rusak_total']);

        $this->artisan('koperasi:preflight-aset')->assertExitCode(1);
        $this->assertSame($before, AsetKoperasi::query()->count());
    }

    public function test_seeder_membuat_dummy_aset_valid_tanpa_transaksi_keuangan(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertSame(4, AsetKoperasi::query()->mobil()->count());
        $this->assertSame(0, AsetKoperasi::query()->printer()->count());
        $this->assertSame(4, AsetMobil::query()->count());
        $this->assertSame(0, AsetPrinter::query()->count());
        $this->assertDatabaseHas('aset_koperasi', ['kode_aset' => 'MBL-0001', 'status' => AsetKoperasi::STATUS_DIGUNAKAN_DISEWA]);
        $this->assertDatabaseHas('nomor_urut_aset', ['jenis_aset' => AsetKoperasi::JENIS_MOBIL, 'last_number' => 4]);
        $this->assertDatabaseMissing('nomor_urut_aset', ['jenis_aset' => AsetKoperasi::JENIS_PRINTER]);
    }

    private function user(string $role): User
    {
        return User::factory()->create(['role' => $role]);
    }

    private function mobilPayload(array $overrides = []): array
    {
        return array_merge([
            'plat_nomor' => 'B 9999 KBS',
            'merek' => 'Toyota',
            'model' => 'Avanza',
            'tahun' => 2022,
            'warna' => 'Hitam',
            'tarif_sewa_harian' => 300000,
            'keterangan' => 'Unit test mobil koperasi',
        ], $overrides);
    }

    private function printerPayload(array $overrides = []): array
    {
        return array_merge([
            'nomor_seri' => 'PRN-9999',
            'merek' => 'Epson',
            'model' => 'L3210',
            'lokasi' => 'Kantor Koperasi',
            'keterangan' => 'Unit test printer koperasi',
        ], $overrides);
    }

    private function expectValidation(callable $callback): void
    {
        try {
            $callback();
            $this->fail('ValidationException tidak dilempar.');
        } catch (ValidationException $exception) {
            $this->assertNotEmpty($exception->errors());
        }
    }
}
