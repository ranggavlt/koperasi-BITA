<?php

namespace Tests\Feature;

use App\Models\Akun;
use App\Models\Anggota;
use App\Models\DompetKoperasi;
use App\Models\JenisSimpanan;
use App\Models\JurnalUmum;
use App\Models\Karyawan;
use App\Models\MutasiKas;
use App\Models\ReversalTransaksi;
use App\Models\SaldoSimpananSukarela;
use App\Models\Simpanan;
use App\Models\User;
use App\Services\MasterDataKoperasiService;
use App\Services\SimpananSukarelaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Tests\TestCase;

class SimpananSukarelaSp3Test extends TestCase
{
    use RefreshDatabase;

    public function test_setoran_dan_penarikan_sukarela_memperbarui_saldo_mutasi_jurnal_dan_snapshot(): void
    {
        $finance = $this->finance();
        $anggota = $this->anggota();
        $jenis = $this->jenisSukarela();
        $kas = $this->dompet('Kas SP3', DompetKoperasi::JENIS_KAS, 'kas', 1000000);
        $bank = $this->dompet('Bank SP3', DompetKoperasi::JENIS_BANK, 'bank', 1000000);
        $service = app(SimpananSukarelaService::class);

        $setoran = $service->setoran([
            'idempotency_key' => 'sp3-setoran-1',
            'anggota_id' => $anggota->id,
            'jenis_simpanan_id' => $jenis->id,
            'dompet_id' => $kas->id,
            'jenis_transaksi' => Simpanan::JENIS_SETORAN,
            'metode_pembayaran' => Simpanan::METODE_TUNAI,
            'jumlah' => 100000,
            'tanggal' => '2026-07-10',
            'nomor_referensi' => 'SET-001',
        ], $finance->id);

        $penarikan = $service->penarikan([
            'idempotency_key' => 'sp3-penarikan-1',
            'anggota_id' => $anggota->id,
            'jenis_simpanan_id' => $jenis->id,
            'dompet_id' => $bank->id,
            'jenis_transaksi' => Simpanan::JENIS_PENARIKAN,
            'metode_pembayaran' => Simpanan::METODE_TRANSFER_BANK,
            'jumlah' => 40000,
            'tanggal' => '2026-07-11',
            'nomor_referensi' => 'WD-001',
        ], $finance->id);

        $this->assertMatchesRegularExpression('/^SSK-202607-\d{6}$/', $setoran->kode_transaksi);
        $this->assertMatchesRegularExpression('/^SSK-202607-\d{6}$/', $penarikan->kode_transaksi);
        $this->assertSame('0.00', $setoran->saldo_sebelum_snapshot);
        $this->assertSame('100000.00', $setoran->saldo_sesudah_snapshot);
        $this->assertSame('100000.00', $penarikan->saldo_sebelum_snapshot);
        $this->assertSame('60000.00', $penarikan->saldo_sesudah_snapshot);

        $this->assertDatabaseHas('saldo_simpanan_sukarela', [
            'anggota_id' => $anggota->id,
            'jenis_simpanan_id' => $jenis->id,
            'saldo' => '60000.00',
        ]);
        $this->assertDatabaseHas('mutasi_kas', [
            'referensi_tipe' => Simpanan::class,
            'referensi_id' => $setoran->id,
            'dompet_id' => $kas->id,
            'tipe' => 'masuk',
            'jumlah' => '100000.00',
        ]);
        $this->assertDatabaseHas('mutasi_kas', [
            'referensi_tipe' => Simpanan::class,
            'referensi_id' => $penarikan->id,
            'dompet_id' => $bank->id,
            'tipe' => 'keluar',
            'jumlah' => '40000.00',
        ]);

        $this->assertJurnalLine($setoran, $kas->akun_id, 'debit', '100000.00');
        $this->assertJurnalLine($setoran, $jenis->akun_id, 'kredit', '100000.00');
        $this->assertJurnalLine($penarikan, $jenis->akun_id, 'debit', '40000.00');
        $this->assertJurnalLine($penarikan, $bank->akun_id, 'kredit', '40000.00');
        $this->assertSame('1100000.00', $kas->fresh()->saldo);
        $this->assertSame('960000.00', $bank->fresh()->saldo);
    }

    public function test_penarikan_sampai_nol_boleh_tetapi_overdraw_ditolak_dan_rollback(): void
    {
        $finance = $this->finance();
        $anggota = $this->anggota();
        $jenis = $this->jenisSukarela();
        $kas = $this->dompet('Kas Zero SP3', DompetKoperasi::JENIS_KAS, 'kas', 500000);
        $service = app(SimpananSukarelaService::class);

        $service->setoran($this->payload($anggota, $jenis, $kas, 75000, 'sp3-zero-setor'), $finance->id);
        $service->penarikan($this->payload($anggota, $jenis, $kas, 75000, 'sp3-zero-tarik', [
            'jenis_transaksi' => Simpanan::JENIS_PENARIKAN,
        ]), $finance->id);

        $this->assertDatabaseHas('saldo_simpanan_sukarela', [
            'anggota_id' => $anggota->id,
            'saldo' => '0.00',
        ]);

        $before = $this->counts();
        $this->expectValidation(fn () => $service->penarikan($this->payload($anggota, $jenis, $kas, 1, 'sp3-overdraw', [
            'jenis_transaksi' => Simpanan::JENIS_PENARIKAN,
        ]), $finance->id));
        $this->assertSame($before, $this->counts());
    }

    public function test_status_anggota_karyawan_metode_dompet_dan_manual_pokok_wajib_dijaga(): void
    {
        $finance = $this->finance();
        $anggota = $this->anggota();
        $jenis = $this->jenisSukarela();
        $pokok = JenisSimpanan::query()->where('kode', JenisSimpanan::KODE_SIMPANAN_POKOK)->firstOrFail();
        $kas = $this->dompet('Kas Guard SP3', DompetKoperasi::JENIS_KAS, 'kas', 500000);
        $bank = $this->dompet('Bank Guard SP3', DompetKoperasi::JENIS_BANK, 'bank', 500000);
        $service = app(SimpananSukarelaService::class);

        $this->expectValidation(fn () => $service->setoran($this->payload($anggota, $jenis, $bank, 50000, 'sp3-mismatch', [
            'metode_pembayaran' => Simpanan::METODE_TUNAI,
        ]), $finance->id));

        $this->expectValidation(fn () => $service->setoran($this->payload($anggota, $pokok, $kas, 50000, 'sp3-pokok'), $finance->id));

        $anggota->update(['status' => Anggota::STATUS_NONAKTIF, 'tanggal_nonaktif' => '2026-07-12']);
        $this->expectValidation(fn () => $service->setoran($this->payload($anggota->fresh('karyawan'), $jenis, $kas, 50000, 'sp3-nonaktif'), $finance->id));

        $aktif = $this->anggota();
        $aktif->karyawan->update(['status_kerja' => Karyawan::STATUS_BERHENTI, 'tanggal_berhenti' => '2026-07-12']);
        $this->expectValidation(fn () => $service->setoran($this->payload($aktif->fresh('karyawan'), $jenis, $kas, 50000, 'sp3-berhenti'), $finance->id));
    }

    public function test_idempotency_retry_tidak_menggandakan_saldo_mutasi_atau_jurnal(): void
    {
        $finance = $this->finance();
        $anggota = $this->anggota();
        $jenis = $this->jenisSukarela();
        $kas = $this->dompet('Kas Retry SP3', DompetKoperasi::JENIS_KAS, 'kas', 0);
        $service = app(SimpananSukarelaService::class);
        $payload = $this->payload($anggota, $jenis, $kas, 80000, 'sp3-retry');

        $first = $service->setoran($payload, $finance->id);
        $second = $service->setoran($payload, $finance->id);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, Simpanan::query()->where('idempotency_key', 'sp3-retry')->count());
        $this->assertSame(1, MutasiKas::query()->where('idempotency_key', 'simpanan:manual:mutasi:sp3-retry')->count());
        $this->assertSame(1, JurnalUmum::query()->where('idempotency_key', 'simpanan:manual:jurnal:sp3-retry')->count());
        $this->assertDatabaseHas('saldo_simpanan_sukarela', ['anggota_id' => $anggota->id, 'saldo' => '80000.00']);
    }

    public function test_koreksi_setoran_dan_penarikan_membuat_reversal_penuh_dan_tidak_bisa_ganda(): void
    {
        $finance = $this->finance();
        $anggota = $this->anggota();
        $jenis = $this->jenisSukarela();
        $kas = $this->dompet('Kas Koreksi SP3', DompetKoperasi::JENIS_KAS, 'kas', 1000000);
        $service = app(SimpananSukarelaService::class);

        $setoran = $service->setoran($this->payload($anggota, $jenis, $kas, 100000, 'sp3-koreksi-setor'), $finance->id);
        $reversal = $service->koreksi($setoran, 'Koreksi penuh setoran salah input.', $finance->id);

        $this->assertSame(Simpanan::STATUS_REVERSED, $setoran->fresh()->status);
        $this->assertDatabaseHas('saldo_simpanan_sukarela', ['anggota_id' => $anggota->id, 'saldo' => '0.00']);
        $this->assertDatabaseHas('reversal_transaksi', [
            'id' => $reversal->id,
            'source_type' => Simpanan::class,
            'source_id' => $setoran->id,
            'jenis_reversal' => ReversalTransaksi::JENIS_SIMPANAN_SUKARELA_CORRECTION,
        ]);
        $this->assertDatabaseHas('mutasi_kas', [
            'referensi_tipe' => ReversalTransaksi::class,
            'referensi_id' => $reversal->id,
            'tipe' => 'keluar',
            'jumlah' => '100000.00',
        ]);
        $this->assertReversalJurnal($reversal, $jenis->akun_id, 'debit', '100000.00');
        $this->assertReversalJurnal($reversal, $kas->akun_id, 'kredit', '100000.00');
        $this->expectValidation(fn () => $service->koreksi($setoran->fresh(), 'Koreksi ganda ditolak.', $finance->id));

        $setoranBaru = $service->setoran($this->payload($anggota, $jenis, $kas, 100000, 'sp3-koreksi-tarik-setor'), $finance->id);
        $penarikan = $service->penarikan($this->payload($anggota, $jenis, $kas, 40000, 'sp3-koreksi-tarik', [
            'jenis_transaksi' => Simpanan::JENIS_PENARIKAN,
        ]), $finance->id);
        $reversalPenarikan = $service->koreksi($penarikan, 'Koreksi penuh penarikan salah input.', $finance->id);

        $this->assertSame(Simpanan::STATUS_SETTLED, $setoranBaru->fresh()->status);
        $this->assertDatabaseHas('saldo_simpanan_sukarela', ['anggota_id' => $anggota->id, 'saldo' => '100000.00']);
        $this->assertDatabaseHas('mutasi_kas', [
            'referensi_tipe' => ReversalTransaksi::class,
            'referensi_id' => $reversalPenarikan->id,
            'tipe' => 'masuk',
            'jumlah' => '40000.00',
        ]);

        $this->expectException(RuntimeException::class);
        $penarikan->delete();
    }

    public function test_ui_authorization_get_read_only_dan_route_edit_delete_tidak_tersedia(): void
    {
        $finance = $this->finance();
        $kasir = User::factory()->create(['role' => 'kasir', 'is_active' => true, 'must_change_password' => false]);
        $anggota = $this->anggota();
        $jenis = $this->jenisSukarela();
        $kas = $this->dompet('Kas UI SP3', DompetKoperasi::JENIS_KAS, 'kas', 0);
        app(SimpananSukarelaService::class)->setoran($this->payload($anggota, $jenis, $kas, 50000, 'sp3-ui'), $finance->id);

        $before = $this->counts();
        $this->actingAs($finance)->get(route('simpanan.index'))->assertOk()->assertSee('Transaksi Simpanan');
        $this->actingAs($finance)->get(route('simpanan.create'))->assertOk()->assertSee('Transaksi Simpanan Sukarela');
        $this->actingAs($finance)->get(route('simpanan.saldo-sukarela', $anggota))->assertOk()->assertJsonPath('saldo', 50000);
        $this->assertSame($before, $this->counts());

        $this->actingAs($kasir)->get(route('simpanan.index'))->assertForbidden();
        $this->actingAs($kasir)->post(route('simpanan.store'), [])->assertForbidden();
        auth()->logout();
        $this->get(route('simpanan.index'))->assertRedirect(route('login'));

        $this->assertFalse(Route::has('simpanan.edit'));
        $this->assertFalse(Route::has('simpanan.update'));
        $this->assertFalse(Route::has('simpanan.destroy'));
    }

    public function test_preflight_sukarela_read_only_dan_mendeteksi_mismatch_saldo(): void
    {
        $finance = $this->finance();
        $anggota = $this->anggota();
        $jenis = $this->jenisSukarela();
        $kas = $this->dompet('Kas Preflight SP3', DompetKoperasi::JENIS_KAS, 'kas', 0);
        app(SimpananSukarelaService::class)->setoran($this->payload($anggota, $jenis, $kas, 50000, 'sp3-preflight'), $finance->id);

        $before = $this->counts();
        $this->artisan('koperasi:preflight-simpanan-sukarela')->assertExitCode(0);
        $this->assertSame($before, $this->counts());

        SaldoSimpananSukarela::query()
            ->where('anggota_id', $anggota->id)
            ->update(['saldo' => '1.00']);

        $dirtyCounts = $this->counts();
        $this->artisan('koperasi:preflight-simpanan-sukarela')->assertExitCode(1);
        $this->assertSame($dirtyCounts, $this->counts());
    }

    public function test_unique_saldo_per_anggota_siklus_jenis_dilindungi_database(): void
    {
        $finance = $this->finance();
        $anggota = $this->anggota();
        $jenis = $this->jenisSukarela();
        $kas = $this->dompet('Kas Unique SP3', DompetKoperasi::JENIS_KAS, 'kas', 0);
        app(SimpananSukarelaService::class)->setoran($this->payload($anggota, $jenis, $kas, 50000, 'sp3-unique'), $finance->id);
        $saldo = SaldoSimpananSukarela::query()->where('anggota_id', $anggota->id)->firstOrFail();

        $this->expectException(\Illuminate\Database\QueryException::class);
        SaldoSimpananSukarela::query()->create([
            'anggota_id' => $saldo->anggota_id,
            'siklus_keanggotaan_id' => $saldo->siklus_keanggotaan_id,
            'jenis_simpanan_id' => $saldo->jenis_simpanan_id,
            'saldo' => '0.00',
        ]);
    }

    public function test_kode_transaksi_sukarela_unique_dilindungi_database(): void
    {
        $finance = $this->finance();
        $anggota = $this->anggota();
        $jenis = $this->jenisSukarela();
        $kas = $this->dompet('Kas Unique Code SP3', DompetKoperasi::JENIS_KAS, 'kas', 0);
        $simpanan = app(SimpananSukarelaService::class)->setoran($this->payload($anggota, $jenis, $kas, 50000, 'sp3-unique-code'), $finance->id);
        $saldo = SaldoSimpananSukarela::query()->where('anggota_id', $anggota->id)->firstOrFail();

        $this->expectException(\Illuminate\Database\QueryException::class);
        Simpanan::query()->create([
            'idempotency_key' => 'sp3-duplicate-code',
            'kode_transaksi' => $simpanan->kode_transaksi,
            'anggota_id' => $anggota->id,
            'karyawan_id' => $anggota->karyawan_id,
            'siklus_keanggotaan_id' => $saldo->siklus_keanggotaan_id,
            'jenis_simpanan_id' => $jenis->id,
            'jumlah' => '1.00',
            'tanggal' => '2026-07-12',
        ]);
    }

    private function payload(Anggota $anggota, JenisSimpanan $jenis, DompetKoperasi $dompet, int $jumlah, string $key, array $overrides = []): array
    {
        return $overrides + [
            'idempotency_key' => $key,
            'anggota_id' => $anggota->id,
            'jenis_simpanan_id' => $jenis->id,
            'dompet_id' => $dompet->id,
            'jenis_transaksi' => Simpanan::JENIS_SETORAN,
            'metode_pembayaran' => $dompet->jenis_dompet === DompetKoperasi::JENIS_BANK
                ? Simpanan::METODE_TRANSFER_BANK
                : Simpanan::METODE_TUNAI,
            'jumlah' => $jumlah,
            'tanggal' => '2026-07-10',
            'keterangan' => 'Test SP-3',
        ];
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

    private function counts(): array
    {
        return [
            'simpanan' => Simpanan::query()->count(),
            'saldo' => SaldoSimpananSukarela::query()->count(),
            'mutasi' => MutasiKas::query()->count(),
            'jurnal' => JurnalUmum::query()->count(),
            'reversal' => ReversalTransaksi::query()->count(),
            'dompet_saldo' => DompetKoperasi::query()->sum('saldo'),
        ];
    }

    private function assertJurnalLine(Simpanan $simpanan, int $akunId, string $side, string $nominal): void
    {
        $jurnal = JurnalUmum::query()
            ->where('referensi_tipe', Simpanan::class)
            ->where('referensi_id', $simpanan->id)
            ->with('details')
            ->firstOrFail();

        $this->assertTrue($jurnal->details->contains(function ($detail) use ($akunId, $side, $nominal): bool {
            return (int) $detail->akun_id === $akunId && $detail->{$side} === $nominal;
        }));
    }

    private function assertReversalJurnal(ReversalTransaksi $reversal, int $akunId, string $side, string $nominal): void
    {
        $jurnal = JurnalUmum::query()
            ->where('referensi_tipe', ReversalTransaksi::class)
            ->where('referensi_id', $reversal->id)
            ->with('details')
            ->firstOrFail();

        $this->assertTrue($jurnal->details->contains(function ($detail) use ($akunId, $side, $nominal): bool {
            return (int) $detail->akun_id === $akunId && $detail->{$side} === $nominal;
        }));
    }

    private function anggota(): Anggota
    {
        $karyawan = Karyawan::factory()->create(['status_kerja' => Karyawan::STATUS_AKTIF]);

        return app(MasterDataKoperasiService::class)->createAnggota([
            'karyawan_id' => $karyawan->id,
            'tanggal_bergabung' => '2026-07-01',
            'alamat' => 'Jl. Test SP-3',
            'plafon_pinjaman' => 1000000,
        ])->fresh(['karyawan', 'siklusAktif']);
    }

    private function finance(): User
    {
        return User::factory()->create([
            'role' => 'admin',
            'is_active' => true,
            'must_change_password' => false,
        ]);
    }

    private function jenisSukarela(): JenisSimpanan
    {
        return JenisSimpanan::query()
            ->where('kode', JenisSimpanan::KODE_SIMPANAN_SUKARELA)
            ->where('aktif', true)
            ->firstOrFail();
    }

    private function dompet(string $nama, string $jenisDompet, string $akunKey, int $saldo): DompetKoperasi
    {
        return DompetKoperasi::query()->create([
            'akun_id' => $this->akun($akunKey)->id,
            'nama_dompet' => $nama,
            'jenis_dompet' => $jenisDompet,
            'saldo' => $saldo,
        ])->fresh('akun');
    }

    private function akun(string $key): Akun
    {
        return Akun::query()
            ->where('kode_akun', config("account_map.accounts.{$key}.kode_akun"))
            ->firstOrFail();
    }
}
