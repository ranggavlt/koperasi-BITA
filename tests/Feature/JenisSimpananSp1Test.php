<?php

namespace Tests\Feature;

use App\Models\Akun;
use App\Models\Anggota;
use App\Models\DompetKoperasi;
use App\Models\JenisSimpanan;
use App\Models\JurnalUmum;
use App\Models\Karyawan;
use App\Models\MutasiKas;
use App\Models\Simpanan;
use App\Models\User;
use App\Services\JenisSimpananService;
use App\Services\MasterDataKoperasiService;
use App\Services\SimpananManualService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class JenisSimpananSp1Test extends TestCase
{
    use RefreshDatabase;

    public function test_validasi_kategori_interval_dan_nominal_master_jenis_simpanan(): void
    {
        $service = app(JenisSimpananService::class);
        $wajib = $this->jenis(JenisSimpanan::KATEGORI_WAJIB);
        $manasuka = $this->jenis(JenisSimpanan::KATEGORI_MANASUKA);

        $this->assertSame(0, JenisSimpanan::query()
            ->where('kategori', JenisSimpanan::KATEGORI_POKOK)
            ->where('aktif', true)
            ->count());

        $this->expectValidation(fn () => $service->update($wajib, $this->payload($wajib, [
            'interval_bulan' => 1,
            'alasan_perubahan' => 'Uji interval wajib.',
        ]), $this->finance()->id));

        $this->expectValidation(fn () => $service->update($wajib, $this->payload($wajib, [
            'nominal_default' => 0,
            'alasan_perubahan' => 'Uji nominal wajib.',
        ]), $this->finance()->id));

        $this->expectValidation(fn () => $service->update($manasuka, $this->payload($manasuka, [
            'interval_bulan' => 1,
            'alasan_perubahan' => 'Uji interval manasuka.',
        ]), $this->finance()->id));
    }

    public function test_satu_master_aktif_per_kategori_dilindungi_service_dan_database(): void
    {
        $service = app(JenisSimpananService::class);
        $wajib = $this->jenis(JenisSimpanan::KATEGORI_WAJIB);

        $this->expectValidation(fn () => $service->create($this->payload($wajib, [
            'nama_jenis' => 'Simpanan Wajib Duplikat',
        ]), $this->finance()->id));

        $this->expectException(QueryException::class);
        JenisSimpanan::query()->create([
            'akun_id' => $this->akun('simpanan_wajib')->id,
            'kode' => 'SIMPANAN_WAJIB_DUP',
            'kategori' => JenisSimpanan::KATEGORI_WAJIB,
            'nama_jenis' => 'Simpanan Wajib Race',
            'wajib' => true,
            'aktif' => true,
            'nominal_default' => 10000,
            'berlaku_mulai' => '2026-01-01',
        ]);
    }

    public function test_perubahan_master_mencatat_riwayat_changed_by_dan_alasan_wajib(): void
    {
        $service = app(JenisSimpananService::class);
        $finance = $this->finance();
        $manasuka = $this->jenis(JenisSimpanan::KATEGORI_MANASUKA);

        $this->expectValidation(fn () => $service->update($manasuka, $this->payload($manasuka, [
            'nominal_default' => 25000,
            'alasan_perubahan' => null,
        ]), $finance->id));

        $service->update($manasuka, $this->payload($manasuka, [
            'nominal_default' => 25000,
            'alasan_perubahan' => 'Penyesuaian nominal Manasuka dummy SP-7.',
        ]), $finance->id);

        $this->assertDatabaseHas('riwayat_jenis_simpanan', [
            'jenis_simpanan_id' => $manasuka->id,
            'changed_by' => $finance->id,
            'alasan' => 'Penyesuaian nominal Manasuka dummy SP-7.',
        ]);
        $this->assertSame('25000.00', $manasuka->fresh()->nominal_default);
    }

    public function test_master_terpakai_tidak_menyediakan_route_hard_delete(): void
    {
        $anggota = $this->anggota();
        $jenis = $this->jenis(JenisSimpanan::KATEGORI_MANASUKA);

        Simpanan::query()->create([
            'anggota_id' => $anggota->id,
            'karyawan_id' => $anggota->karyawan_id,
            'jenis_simpanan_id' => $jenis->id,
            'jumlah' => 50000,
            'tanggal' => '2026-07-10',
        ]);

        $this->assertFalse(Route::has('jenis-simpanan.destroy'));
        $this->assertDatabaseHas('jenis_simpanan', ['id' => $jenis->id]);
    }

    public function test_perubahan_master_manasuka_tidak_mengubah_snapshot_transaksi_lama(): void
    {
        $finance = $this->finance();
        $anggota = $this->anggota();
        $manasuka = $this->jenis(JenisSimpanan::KATEGORI_MANASUKA);
        $kas = $this->dompet('Kas Snapshot Manasuka', 'kas', 'kas');
        $simpanan = app(SimpananManualService::class)->create([
            'idempotency_key' => 'snapshot-manasuka',
            'anggota_id' => $anggota->id,
            'jenis_simpanan_id' => $manasuka->id,
            'dompet_id' => $kas->id,
            'jumlah' => 50000,
            'tanggal' => '2026-07-10',
            'keterangan' => 'Snapshot lama',
        ], $finance->id);

        app(JenisSimpananService::class)->update($manasuka, $this->payload($manasuka, [
            'nominal_default' => 25000,
            'alasan_perubahan' => 'Uji snapshot Manasuka lama.',
        ]), $finance->id);

        $this->assertSame('50000.00', $simpanan->fresh()->nominal_snapshot);
        $this->assertSame('25000.00', $manasuka->fresh()->nominal_default);
    }

    public function test_simpanan_wajib_otomatis_tetap_satu_kali_dan_rollback_jika_master_invalid(): void
    {
        $anggota = $this->anggota();

        $this->assertSame(1, Simpanan::query()
            ->where('anggota_id', $anggota->id)
            ->where('kode_jenis_snapshot', JenisSimpanan::KODE_SIMPANAN_WAJIB)
            ->count());

        $this->jenis(JenisSimpanan::KATEGORI_WAJIB)->update(['nominal_default' => 0]);
        $karyawan = Karyawan::factory()->create();

        try {
            app(MasterDataKoperasiService::class)->createAnggota([
                'karyawan_id' => $karyawan->id,
                'tanggal_bergabung' => '2026-07-01',
                'alamat' => 'Jl. Rollback SP-1',
                'plafon_pinjaman' => 1000000,
            ]);
            $this->fail('Pendaftaran Anggota wajib gagal bila Master Simpanan Wajib tidak valid.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('nominal_default', $exception->errors());
        }

        $this->assertDatabaseMissing('anggota', ['karyawan_id' => $karyawan->id]);
    }

    public function test_posting_simpanan_manual_mendebit_coa_kas_dan_bank_yang_dipilih(): void
    {
        $finance = $this->finance();
        $anggotaKas = $this->anggota();
        $anggotaBank = $this->anggota();
        $jenis = $this->jenis(JenisSimpanan::KATEGORI_MANASUKA);
        $kas = $this->dompet('Kas Unit Test', 'kas', 'kas');
        $bank = $this->dompet('Bank Unit Test', 'bank', 'bank');
        $service = app(SimpananManualService::class);

        $simpananKas = $service->create([
            'idempotency_key' => 'test-simpanan-kas',
            'anggota_id' => $anggotaKas->id,
            'jenis_simpanan_id' => $jenis->id,
            'dompet_id' => $kas->id,
            'jumlah' => 70000,
            'tanggal' => '2026-07-10',
            'keterangan' => 'Kas',
        ], $finance->id);
        $simpananBank = $service->create([
            'idempotency_key' => 'test-simpanan-bank',
            'anggota_id' => $anggotaBank->id,
            'jenis_simpanan_id' => $jenis->id,
            'dompet_id' => $bank->id,
            'jumlah' => 90000,
            'tanggal' => '2026-07-10',
            'keterangan' => 'Bank',
        ], $finance->id);

        $this->assertJurnalDebitAkun($simpananKas, $kas->akun_id, '70000.00');
        $this->assertJurnalDebitAkun($simpananBank, $bank->akun_id, '90000.00');
        $this->assertDatabaseHas('mutasi_kas', [
            'referensi_tipe' => Simpanan::class,
            'referensi_id' => $simpananKas->id,
            'dompet_id' => $kas->id,
            'jumlah' => '70000.00',
        ]);
        $this->assertDatabaseHas('mutasi_kas', [
            'referensi_tipe' => Simpanan::class,
            'referensi_id' => $simpananBank->id,
            'dompet_id' => $bank->id,
            'jumlah' => '90000.00',
        ]);
    }

    public function test_retry_simpanan_manual_tidak_menggandakan_transaksi_mutasi_jurnal_atau_saldo(): void
    {
        $finance = $this->finance();
        $anggota = $this->anggota();
        $jenis = $this->jenis(JenisSimpanan::KATEGORI_MANASUKA);
        $kas = $this->dompet('Kas Retry', 'kas', 'kas');
        $service = app(SimpananManualService::class);
        $payload = [
            'idempotency_key' => 'test-simpanan-retry',
            'anggota_id' => $anggota->id,
            'jenis_simpanan_id' => $jenis->id,
            'dompet_id' => $kas->id,
            'jumlah' => 80000,
            'tanggal' => '2026-07-10',
            'keterangan' => 'Retry',
        ];

        $first = $service->create($payload, $finance->id);
        $second = $service->create($payload, $finance->id);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, Simpanan::query()->where('idempotency_key', 'test-simpanan-retry')->count());
        $this->assertSame(1, MutasiKas::query()->where('idempotency_key', 'simpanan:manual:mutasi:test-simpanan-retry')->count());
        $this->assertSame(1, JurnalUmum::query()->where('idempotency_key', 'simpanan:manual:jurnal:test-simpanan-retry')->count());
        $this->assertSame('80000.00', $kas->fresh()->saldo);
    }

    public function test_authorization_master_jenis_simpanan_dan_get_read_only(): void
    {
        $finance = $this->finance();
        $kasir = User::factory()->create(['role' => 'kasir', 'is_active' => true, 'must_change_password' => false]);
        $jenis = $this->jenis(JenisSimpanan::KATEGORI_WAJIB);
        $before = [
            'simpanan' => Simpanan::query()->count(),
            'mutasi' => MutasiKas::query()->count(),
            'jurnal' => JurnalUmum::query()->count(),
            'saldo' => DompetKoperasi::query()->sum('saldo'),
        ];

        $this->actingAs($finance)->get(route('jenis-simpanan.index'))->assertOk();
        $this->actingAs($finance)->get(route('jenis-simpanan.create'))->assertOk();
        $this->actingAs($finance)->get(route('jenis-simpanan.edit', $jenis))->assertOk();

        $this->actingAs($kasir)->get(route('jenis-simpanan.index'))->assertForbidden();
        auth()->logout();
        $this->get(route('jenis-simpanan.index'))->assertRedirect(route('login'));

        $after = [
            'simpanan' => Simpanan::query()->count(),
            'mutasi' => MutasiKas::query()->count(),
            'jurnal' => JurnalUmum::query()->count(),
            'saldo' => DompetKoperasi::query()->sum('saldo'),
        ];

        $this->assertSame($before, $after);
    }

    private function expectValidation(callable $callback): void
    {
        try {
            $callback();
            $this->fail('ValidationException semestinya dilempar.');
        } catch (ValidationException) {
            $this->assertTrue(true);
        }
    }

    private function payload(JenisSimpanan $jenis, array $overrides = []): array
    {
        return $overrides + [
            'akun_id' => $jenis->akun_id,
            'kategori' => $jenis->kategori,
            'nama_jenis' => $jenis->nama_jenis,
            'aktif' => $jenis->aktif ? 1 : 0,
            'interval_bulan' => $jenis->interval_bulan,
            'berlaku_mulai' => $jenis->berlaku_mulai?->toDateString() ?? '2026-01-01',
            'nominal_default' => (int) $jenis->nominal_default,
            'keterangan' => $jenis->keterangan,
            'alasan_perubahan' => 'Uji perubahan SP-1.',
        ];
    }

    private function jenis(string $kategori): JenisSimpanan
    {
        return JenisSimpanan::query()
            ->where('kategori', $kategori)
            ->where('aktif', true)
            ->firstOrFail();
    }

    private function akun(string $key): Akun
    {
        return Akun::query()
            ->where('kode_akun', config("account_map.accounts.{$key}.kode_akun"))
            ->firstOrFail();
    }

    private function dompet(string $nama, string $jenisDompet, string $akunKey): DompetKoperasi
    {
        return DompetKoperasi::query()->create([
            'akun_id' => $this->akun($akunKey)->id,
            'nama_dompet' => $nama,
            'jenis_dompet' => $jenisDompet,
            'saldo' => 0,
        ])->fresh('akun');
    }

    private function anggota(): Anggota
    {
        $karyawan = Karyawan::factory()->create();

        return app(MasterDataKoperasiService::class)->createAnggota([
            'karyawan_id' => $karyawan->id,
            'tanggal_bergabung' => '2026-07-01',
            'alamat' => 'Jl. Test SP-1',
            'plafon_pinjaman' => 1000000,
        ]);
    }

    private function finance(): User
    {
        return User::factory()->create([
            'role' => 'admin',
            'is_active' => true,
            'must_change_password' => false,
        ]);
    }

    private function assertJurnalDebitAkun(Simpanan $simpanan, int $akunId, string $nominal): void
    {
        $jurnal = JurnalUmum::query()
            ->where('referensi_tipe', Simpanan::class)
            ->where('referensi_id', $simpanan->id)
            ->with('details')
            ->firstOrFail();

        $this->assertTrue($jurnal->details->contains(function ($detail) use ($akunId, $nominal): bool {
            return (int) $detail->akun_id === $akunId && $detail->debit === $nominal;
        }));
    }
}
