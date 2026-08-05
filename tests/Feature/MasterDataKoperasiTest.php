<?php

namespace Tests\Feature;

use App\Models\Anggota;
use App\Models\CicilanPinjaman;
use App\Models\JenisSimpanan;
use App\Models\Karyawan;
use App\Models\Penjualan;
use App\Models\PenyelesaianKeanggotaan;
use App\Models\Perusahaan;
use App\Models\Pinjaman;
use App\Models\PengurusKoperasi;
use App\Models\ShuAnggota;
use App\Models\ShuKoperasi;
use App\Models\Simpanan;
use App\Models\User;
use App\Services\KeanggotaanLifecycleService;
use App\Services\MasterDataKoperasiService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Tests\TestCase;

class MasterDataKoperasiTest extends TestCase
{
    use RefreshDatabase;

    public function test_satu_karyawan_maksimal_mempunyai_satu_anggota(): void
    {
        $karyawan = $this->karyawan();
        $this->anggota($karyawan);

        $this->expectException(ValidationException::class);
        $this->anggota($karyawan);
    }

    public function test_karyawan_berhenti_tidak_dapat_menjadi_anggota(): void
    {
        $karyawan = $this->karyawan([
            'status_kerja' => Karyawan::STATUS_BERHENTI,
            'tanggal_berhenti' => '2026-07-01',
        ]);

        $this->expectException(ValidationException::class);
        $this->anggota($karyawan);
    }

    public function test_anggota_baru_langsung_aktif_dengan_nomor_global_yang_valid_dan_unique(): void
    {
        $anggotaA = $this->anggota($this->karyawan());
        $anggotaB = $this->anggota($this->karyawan());

        $this->assertSame(Anggota::STATUS_AKTIF, $anggotaA->status);
        $this->assertMatchesRegularExpression('/^AGT-\d{6}$/', $anggotaA->nomor_anggota);
        $this->assertNotSame($anggotaA->nomor_anggota, $anggotaB->nomor_anggota);
        $this->assertTrue($anggotaA->karyawan->fresh()->is_anggota);
    }

    public function test_karyawan_berhenti_menonaktifkan_anggota_dan_pengurus_tanpa_menghapus_histori(): void
    {
        $service = app(MasterDataKoperasiService::class);
        $karyawan = $this->karyawan();
        $anggota = $this->anggota($karyawan);
        $pengurus = $service->createPengurus([
            'anggota_id' => $anggota->id,
            'jabatan' => 'Ketua Pengurus',
        ]);

        $jenis = JenisSimpanan::query()->create([
            'nama_jenis' => 'Simpanan Uji',
            'wajib' => false,
        ]);
        Simpanan::query()->create([
            'karyawan_id' => $karyawan->id,
            'jenis_simpanan_id' => $jenis->id,
            'jumlah' => 100000,
            'tanggal' => '2026-06-01',
        ]);
        $pinjaman = Pinjaman::query()->create([
            'karyawan_id' => $karyawan->id,
            'jumlah_pinjaman' => 1000000,
            'bunga_persen' => 0,
            'tenor_bulan' => 10,
            'sisa_pinjaman' => 900000,
            'status' => 'aktif',
            'tanggal_pinjaman' => '2026-05-01',
        ]);
        CicilanPinjaman::query()->create([
            'pinjaman_id' => $pinjaman->id,
            'jumlah_cicilan' => 100000,
            'periode' => '2026-06',
            'status' => 'sudah_bayar',
            'tanggal_bayar' => '2026-06-01',
        ]);
        Penjualan::query()->create([
            'kode_transaksi' => 'PJL-HIST-001',
            'karyawan_id' => $karyawan->id,
            'total_harga' => 50000,
            'diskon' => 0,
            'grand_total' => 50000,
        ]);
        $shu = ShuKoperasi::query()->create([
            'judul' => 'SHU Histori',
            'tanggal_mulai' => '2026-01-01',
            'tanggal_selesai' => '2026-12-31',
        ]);
        ShuAnggota::query()->create([
            'shu_koperasi_id' => $shu->id,
            'karyawan_id' => $karyawan->id,
        ]);

        $service->updateKaryawan($karyawan, [
            'nama' => $karyawan->nama,
            'email' => $karyawan->email,
            'telepon' => $karyawan->telepon,
            'jabatan' => $karyawan->jabatan,
            'status_kerja' => Karyawan::STATUS_BERHENTI,
            'tanggal_berhenti' => '2026-07-01',
        ]);

        $this->assertDatabaseHas('anggota', [
            'id' => $anggota->id,
            'status' => Anggota::STATUS_NONAKTIF,
            'tanggal_nonaktif' => '2026-07-01 00:00:00',
        ]);
        $this->assertDatabaseHas('pengurus_koperasi', [
            'id' => $pengurus->id,
            'status' => PengurusKoperasi::STATUS_NONAKTIF,
            'anggota_aktif_id' => null,
            'jabatan_aktif' => null,
        ]);
        $this->assertDatabaseCount('simpanan', 2);
        $this->assertDatabaseCount('pinjaman', 1);
        $this->assertDatabaseCount('cicilan_pinjaman', 1);
        $this->assertDatabaseCount('penjualan', 1);
        $this->assertDatabaseCount('shu_anggota', 1);
        $this->assertDatabaseCount('anggota', 1);
        $this->assertDatabaseCount('pengurus_koperasi', 1);
        $this->assertFalse($karyawan->fresh()->is_anggota);
    }

    public function test_lifecycle_nonaktif_aktif_dan_reaktivasi_karyawan_tidak_melompati_service(): void
    {
        $service = app(MasterDataKoperasiService::class);
        $karyawan = $this->karyawan();
        $anggota = $this->anggota($karyawan);
        $pengurus = $service->createPengurus([
            'anggota_id' => $anggota->id,
            'jabatan' => 'Sekretaris',
        ]);

        $service->deactivateAnggota($anggota);

        $this->assertSame(Anggota::STATUS_NONAKTIF, $anggota->fresh()->status);
        $this->assertSame(today()->toDateString(), $anggota->fresh()->tanggal_nonaktif?->toDateString());
        $this->assertSame(PengurusKoperasi::STATUS_NONAKTIF, $pengurus->fresh()->status);
        $this->assertFalse($karyawan->fresh()->is_anggota);

        $tanggalNonaktifAwal = $anggota->fresh()->tanggal_nonaktif?->toDateString();
        $service->deactivateAnggota($anggota);
        $this->assertSame($tanggalNonaktifAwal, $anggota->fresh()->tanggal_nonaktif?->toDateString());

        $service->updateKaryawan($karyawan, $this->karyawanLifecycleData(
            $karyawan,
            Karyawan::STATUS_BERHENTI,
            '2026-07-01'
        ));
        try {
            $service->updateKaryawan($karyawan, $this->karyawanLifecycleData(
                $karyawan,
                Karyawan::STATUS_AKTIF
            ));
            $this->fail('Karyawan tidak boleh direaktivasi sebelum penyelesaian keanggotaan completed.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('status_kerja', $exception->errors());
        }

        $penyelesaian = PenyelesaianKeanggotaan::query()->where('anggota_id', $anggota->id)->firstOrFail();
        $lifecycle = app(KeanggotaanLifecycleService::class);
        $lifecycle->processOffset($penyelesaian, null);
        $lifecycle->complete($penyelesaian->fresh(), null);

        $service->updateKaryawan($karyawan, $this->karyawanLifecycleData(
            $karyawan,
            Karyawan::STATUS_AKTIF
        ));

        $this->assertSame(Karyawan::STATUS_AKTIF, $karyawan->fresh()->status_kerja);
        $this->assertNull($karyawan->fresh()->tanggal_berhenti);
        $this->assertSame(Anggota::STATUS_NONAKTIF, $anggota->fresh()->status);
        $this->assertSame(PengurusKoperasi::STATUS_NONAKTIF, $pengurus->fresh()->status);
        $this->assertFalse($karyawan->fresh()->is_anggota);

        $service->activateAnggota($anggota);

        $this->assertSame(Anggota::STATUS_AKTIF, $anggota->fresh()->status);
        $this->assertNull($anggota->fresh()->tanggal_nonaktif);
        $this->assertTrue($karyawan->fresh()->is_anggota);
        $this->assertSame(PengurusKoperasi::STATUS_NONAKTIF, $pengurus->fresh()->status);
    }

    public function test_anggota_tidak_dapat_diaktifkan_selama_karyawan_berhenti(): void
    {
        $service = app(MasterDataKoperasiService::class);
        $karyawan = $this->karyawan();
        $anggota = $this->anggota($karyawan);
        $service->deactivateAnggota($anggota);
        $service->updateKaryawan($karyawan, $this->karyawanLifecycleData(
            $karyawan,
            Karyawan::STATUS_BERHENTI,
            '2026-07-01'
        ));

        try {
            $service->activateAnggota($anggota);
            $this->fail('Anggota dari Karyawan berhenti semestinya tidak dapat diaktifkan.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('status', $exception->errors());
        }

        $this->assertSame(Anggota::STATUS_NONAKTIF, $anggota->fresh()->status);
        $this->assertFalse($karyawan->fresh()->is_anggota);
    }

    public function test_pengurus_wajib_anggota_aktif_dan_karyawan_aktif(): void
    {
        $service = app(MasterDataKoperasiService::class);
        $anggota = $this->anggota($this->karyawan());
        $service->deactivateAnggota($anggota);

        $this->expectException(ValidationException::class);
        $service->createPengurus([
            'anggota_id' => $anggota->id,
            'jabatan' => 'Ketua Pengurus',
        ]);
    }

    public function test_satu_anggota_dan_satu_jabatan_hanya_boleh_mempunyai_satu_pengurus_aktif(): void
    {
        $service = app(MasterDataKoperasiService::class);
        $anggotaA = $this->anggota($this->karyawan());
        $anggotaB = $this->anggota($this->karyawan());

        $service->createPengurus([
            'anggota_id' => $anggotaA->id,
            'jabatan' => 'Ketua Pengurus',
        ]);

        try {
            $service->createPengurus([
                'anggota_id' => $anggotaA->id,
                'jabatan' => 'Sekretaris',
            ]);
            $this->fail('Anggota yang sama semestinya ditolak untuk jabatan aktif kedua.');
        } catch (ValidationException) {
            $this->assertTrue(true);
        }

        $this->expectException(ValidationException::class);
        $service->createPengurus([
            'anggota_id' => $anggotaB->id,
            'jabatan' => 'Ketua Pengurus',
        ]);
    }

    public function test_database_menolak_jabatan_pengurus_aktif_kedua_untuk_anggota_yang_sama(): void
    {
        $anggota = $this->anggota($this->karyawan());
        app(MasterDataKoperasiService::class)->createPengurus([
            'anggota_id' => $anggota->id,
            'jabatan' => 'Ketua Pengurus',
        ]);

        $this->expectException(QueryException::class);
        DB::table('pengurus_koperasi')->insert([
            'anggota_id' => $anggota->id,
            'jabatan' => 'Sekretaris',
            'status' => PengurusKoperasi::STATUS_AKTIF,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_konflik_jabatan_pengurus_menghasilkan_validation_error_ramah(): void
    {
        $keuangan = User::factory()->create(['role' => 'admin']);
        $anggotaA = $this->anggota($this->karyawan());
        $anggotaB = $this->anggota($this->karyawan());
        app(MasterDataKoperasiService::class)->createPengurus([
            'anggota_id' => $anggotaA->id,
            'jabatan' => 'Bendahara',
        ]);

        $this->actingAs($keuangan)
            ->from(route('pengurus-koperasi.index'))
            ->post(route('pengurus-koperasi.store'), [
                'anggota_id' => $anggotaB->id,
                'jabatan' => 'Bendahara',
            ])
            ->assertRedirect(route('pengurus-koperasi.index'))
            ->assertSessionHasErrors([
                'jabatan' => 'Jabatan ini sudah diisi oleh Pengurus aktif.',
            ]);

        $this->assertDatabaseCount('pengurus_koperasi', 1);
    }

    public function test_anggota_dengan_transaksi_tidak_dapat_dihapus_permanen(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $anggota = $this->anggota($this->karyawan());
        $jenis = JenisSimpanan::query()->create([
            'nama_jenis' => 'Simpanan Uji Hapus',
            'wajib' => false,
        ]);
        Simpanan::query()->create([
            'karyawan_id' => $anggota->karyawan_id,
            'jenis_simpanan_id' => $jenis->id,
            'jumlah' => 50000,
            'tanggal' => '2026-06-01',
        ]);

        $this->actingAs($user)
            ->delete(route('anggota.destroy', $anggota))
            ->assertSessionHasErrors('anggota');

        $this->assertDatabaseHas('anggota', ['id' => $anggota->id]);
        $this->assertDatabaseHas('simpanan', ['karyawan_id' => $anggota->karyawan_id]);
    }

    public function test_karyawan_dengan_penjualan_tidak_dapat_dihapus_dan_fk_restrict_melindungi_histori(): void
    {
        $keuangan = User::factory()->create(['role' => 'admin']);
        $karyawan = $this->karyawan();
        $penjualan = Penjualan::query()->create([
            'kode_transaksi' => 'PJL-GUARD-001',
            'karyawan_id' => $karyawan->id,
            'total_harga' => 75000,
            'diskon' => 0,
            'grand_total' => 75000,
        ]);

        $this->actingAs($keuangan)
            ->delete(route('karyawan.destroy', $karyawan))
            ->assertSessionHasErrors('karyawan');

        $this->assertDatabaseHas('karyawan', ['id' => $karyawan->id]);
        $this->assertDatabaseHas('penjualan', ['id' => $penjualan->id]);

        try {
            DB::table('karyawan')->where('id', $karyawan->id)->delete();
            $this->fail('Foreign key semestinya menolak penghapusan Karyawan dengan Penjualan.');
        } catch (QueryException) {
            $this->assertDatabaseHas('penjualan', ['id' => $penjualan->id]);
        }
    }

    public function test_anggota_dengan_histori_pengurus_tidak_dapat_dihapus(): void
    {
        $keuangan = User::factory()->create(['role' => 'admin']);
        $anggota = $this->anggota($this->karyawan());
        $pengurus = app(MasterDataKoperasiService::class)->createPengurus([
            'anggota_id' => $anggota->id,
            'jabatan' => 'Ketua Pengurus',
        ]);
        app(MasterDataKoperasiService::class)->deactivatePengurus($pengurus);

        $this->actingAs($keuangan)
            ->delete(route('anggota.destroy', $anggota))
            ->assertSessionHasErrors('anggota');

        $this->assertDatabaseHas('anggota', ['id' => $anggota->id]);
        $this->assertDatabaseHas('pengurus_koperasi', ['id' => $pengurus->id]);
    }

    public function test_refactor_migration_menolak_record_legacy_tanpa_mapping_dan_tidak_menghapusnya(): void
    {
        Schema::drop('pengurus_koperasi');
        Schema::create('pengurus_koperasi', function ($table): void {
            $table->id();
            $table->string('nama');
            $table->string('email')->nullable();
            $table->string('telepon')->nullable();
            $table->string('jabatan');
            $table->timestamps();
        });
        DB::table('pengurus_koperasi')->insert([
            'nama' => 'Legacy Dummy',
            'email' => 'legacy@example.test',
            'jabatan' => 'Ketua Pengurus',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $migration = require database_path('migrations/2026_07_11_010100_refactor_pengurus_koperasi_table.php');

        try {
            $migration->up();
            $this->fail('Migration semestinya berhenti ketika record legacy belum mempunyai mapping Anggota.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('belum mempunyai mapping anggota_id', $exception->getMessage());
            $this->assertStringContainsString('tidak akan ditebak dari nama atau email', $exception->getMessage());
        }

        $this->assertDatabaseHas('pengurus_koperasi', [
            'nama' => 'Legacy Dummy',
            'email' => 'legacy@example.test',
        ]);
        $this->assertFalse(Schema::hasColumn('pengurus_koperasi', 'anggota_id'));
    }

    public function test_refactor_migration_roundtrip_kosong_mempertahankan_generated_column(): void
    {
        $migration = require database_path('migrations/2026_07_11_010100_refactor_pengurus_koperasi_table.php');

        $migration->down();
        $this->assertTrue(Schema::hasColumn('pengurus_koperasi', 'nama'));
        $this->assertFalse(Schema::hasColumn('pengurus_koperasi', 'anggota_aktif_id'));

        $migration->up();
        $this->assertTrue(Schema::hasColumn('pengurus_koperasi', 'anggota_id'));
        $this->assertTrue(Schema::hasColumn('pengurus_koperasi', 'anggota_aktif_id'));
        $this->assertTrue(Schema::hasColumn('pengurus_koperasi', 'jabatan_aktif'));
    }

    public function test_keuangan_dapat_mengelola_master_data_dan_kasir_ditolak(): void
    {
        $keuangan = User::factory()->create(['role' => 'admin']);
        $kasir = User::factory()->create(['role' => 'kasir']);
        $perusahaan = Perusahaan::query()->create([
            'kode' => 'BEE',
            'nama' => 'Bita Enarcon Engineering',
        ]);

        $this->actingAs($keuangan)->get(route('karyawan.index'))->assertOk();
        $this->actingAs($keuangan)->get(route('anggota.index'))->assertOk();
        $this->actingAs($keuangan)->get(route('pengurus-koperasi.index'))->assertOk();

        $this->actingAs($keuangan)->post(route('karyawan.store'), [
            'nama' => 'Karyawan Kelola',
            'email' => 'karyawan.kelola@example.test',
            'telepon' => '081234567890',
            'jabatan' => 'Staf Uji',
            'perusahaan_id' => $perusahaan->id,
            'status_kerja' => 'aktif',
            'perusahaan_id' => $perusahaan->id,
        ])->assertRedirect(route('karyawan.index'));

        $karyawan = Karyawan::query()->where('email', 'karyawan.kelola@example.test')->firstOrFail();

        $this->actingAs($keuangan)->post(route('anggota.store'), [
            'karyawan_id' => $karyawan->id,
            'tanggal_bergabung' => '2026-01-01',
            'alamat' => 'Jl. Dummy Kelola No. 1',
            'plafon_pinjaman' => 2000000,
        ])->assertRedirect(route('anggota.index'));

        $anggota = $karyawan->anggota()->firstOrFail();

        $this->actingAs($keuangan)->post(route('pengurus-koperasi.store'), [
            'anggota_id' => $anggota->id,
            'jabatan' => 'Sekretaris',
        ])->assertRedirect(route('pengurus-koperasi.index'));

        $pengurus = $anggota->pengurusAktif()->firstOrFail();
        $this->actingAs($keuangan)->put(route('pengurus-koperasi.update', $pengurus), [
            'anggota_id' => $anggota->id,
            'jabatan' => 'Bendahara',
        ])->assertRedirect(route('pengurus-koperasi.index'));

        $this->assertSame('Bendahara', $pengurus->fresh()->jabatan);

        $this->actingAs($kasir)->get(route('karyawan.index'))->assertForbidden();
        $this->actingAs($kasir)->get(route('anggota.index'))->assertForbidden();
        $this->actingAs($kasir)->get(route('pengurus-koperasi.index'))->assertForbidden();
        $this->actingAs($kasir)->post(route('karyawan.store'), [
            'nama' => 'Ditolak',
            'email' => 'ditolak@example.test',
            'jabatan' => 'Kasir',
            'status_kerja' => 'aktif',
        ])->assertForbidden();
    }

    public function test_seeder_menghasilkan_relasi_valid_tanpa_orphan(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertDatabaseHas('karyawan', [
            'email' => 'maya.pratiwi@bita.test',
            'status_kerja' => Karyawan::STATUS_AKTIF,
            'is_anggota' => false,
        ]);
        $this->assertDatabaseHas('karyawan', [
            'email' => 'agus.setiawan@bita.test',
            'status_kerja' => Karyawan::STATUS_BERHENTI,
            'is_anggota' => false,
        ]);

        $this->assertSame(0, Anggota::query()->whereDoesntHave('karyawan')->count());
        $this->assertSame(0, PengurusKoperasi::query()->whereDoesntHave('anggota.karyawan')->count());
        $this->assertSame(0, PengurusKoperasi::query()
            ->aktif()
            ->whereHas('anggota', fn ($query) => $query->where('status', '!=', Anggota::STATUS_AKTIF))
            ->count());
        $this->assertSame(
            PengurusKoperasi::query()->aktif()->count(),
            PengurusKoperasi::query()->aktif()->distinct()->count('anggota_id')
        );
        $this->assertSame(
            PengurusKoperasi::query()->aktif()->count(),
            PengurusKoperasi::query()->aktif()->distinct()->count('jabatan')
        );

        $transactionKaryawanIds = collect()
            ->merge(Simpanan::query()->pluck('karyawan_id'))
            ->merge(\App\Models\Pinjaman::query()->pluck('karyawan_id'))
            ->unique();

        $this->assertSame(
            $transactionKaryawanIds->count(),
            Anggota::query()->whereIn('karyawan_id', $transactionKaryawanIds)->count()
        );
    }

    private function karyawan(array $overrides = []): Karyawan
    {
        return Karyawan::factory()->create($overrides);
    }

    private function anggota(Karyawan $karyawan): Anggota
    {
        return app(MasterDataKoperasiService::class)->createAnggota([
            'karyawan_id' => $karyawan->id,
            'tanggal_bergabung' => '2026-01-01',
            'alamat' => 'Jl. Dummy Pengujian No. 1',
            'plafon_pinjaman' => 2500000,
        ]);
    }

    private function karyawanLifecycleData(
        Karyawan $karyawan,
        string $status,
        ?string $tanggalBerhenti = null
    ): array {
        return [
            'nama' => $karyawan->nama,
            'email' => $karyawan->email,
            'telepon' => $karyawan->telepon,
            'jabatan' => $karyawan->jabatan,
            'status_kerja' => $status,
            'tanggal_berhenti' => $tanggalBerhenti,
        ];
    }
}
