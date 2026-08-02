<?php

namespace Tests\Feature;

use App\Models\Anggota;
use App\Models\Akun;
use App\Models\DompetKoperasi;
use App\Models\JadwalSimpananWajib;
use App\Models\JenisSimpanan;
use App\Models\Karyawan;
use App\Models\PemakaianPotongGaji;
use App\Models\PenyelesaianKeanggotaanDetail;
use App\Models\Simpanan;
use App\Models\User;
use App\Services\KeanggotaanLifecycleService;
use App\Services\MasterDataKoperasiService;
use App\Services\PotongGajiBulananService;
use App\Services\SimpananWajibService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class SimpananWajibSp7Test extends TestCase
{
    use RefreshDatabase;

    public function test_master_final_hanya_wajib_dan_manasuka_aktif(): void
    {
        $this->assertSame(0, JenisSimpanan::query()
            ->where('kategori', JenisSimpanan::KATEGORI_POKOK)
            ->where('aktif', true)
            ->count());
        $this->assertSame(1, JenisSimpanan::query()
            ->where('kategori', JenisSimpanan::KATEGORI_WAJIB)
            ->where('kode', JenisSimpanan::KODE_SIMPANAN_WAJIB)
            ->where('aktif', true)
            ->count());
        $this->assertSame(1, JenisSimpanan::query()
            ->where('kategori', JenisSimpanan::KATEGORI_MANASUKA)
            ->where('kode', JenisSimpanan::KODE_SIMPANAN_MANASUKA)
            ->where('aktif', true)
            ->count());

        $wajib = JenisSimpanan::query()
            ->where('kode', JenisSimpanan::KODE_SIMPANAN_WAJIB)
            ->where('aktif', true)
            ->firstOrFail();

        $this->assertSame('10000.00', $wajib->nominal_default);
        $this->assertNull($wajib->interval_bulan);
    }

    public function test_pendaftaran_anggota_membuat_wajib_default_potong_gaji_tanpa_mutasi(): void
    {
        $finance = $this->finance();
        $this->actingAs($finance);

        $anggota = $this->registerAnggota();
        $simpanan = $this->wajibFor($anggota);

        $this->assertSame(JenisSimpanan::KODE_SIMPANAN_WAJIB, $simpanan->kode_jenis_snapshot);
        $this->assertSame(Simpanan::METODE_POTONG_GAJI, $simpanan->metode_pembayaran);
        $this->assertSame(Simpanan::STATUS_PENDING_PAYROLL, $simpanan->status);
        $this->assertSame('10000.00', $simpanan->nominal_snapshot);
        $this->assertSame('simpanan-wajib:siklus:' . $anggota->siklusAktif->id, $simpanan->idempotency_key);
        $this->assertDatabaseMissing('mutasi_kas', [
            'referensi_tipe' => Simpanan::class,
            'referensi_id' => $simpanan->id,
        ]);
        $this->assertDatabaseHas('jurnal_umum', [
            'idempotency_key' => 'simpanan-wajib:pengakuan:jurnal:' . $simpanan->id,
            'referensi_tipe' => Simpanan::class,
            'referensi_id' => $simpanan->id,
        ]);
    }

    public function test_wajib_idempotent_satu_kali_per_siklus(): void
    {
        $finance = $this->finance();
        $this->actingAs($finance);
        $anggota = $this->registerAnggota();
        $cycle = $anggota->siklusAktif;
        $existing = $this->wajibFor($anggota);

        $returned = app(SimpananWajibService::class)->createForCycle($anggota, $cycle, Simpanan::METODE_POTONG_GAJI, null, $finance->id);

        $this->assertSame($existing->id, $returned->id);
        $this->assertSame(1, Simpanan::query()
            ->where('siklus_keanggotaan_id', $cycle->id)
            ->where('kode_jenis_snapshot', JenisSimpanan::KODE_SIMPANAN_WAJIB)
            ->whereNotIn('status', [Simpanan::STATUS_REVERSED, Simpanan::STATUS_REVERSED_DUE_TO_EXIT])
            ->count());
    }

    public function test_wajib_tunai_dan_transfer_langsung_paid_mutasi_jurnal_dan_tidak_membuat_ledger(): void
    {
        $finance = $this->finance();
        $this->actingAs($finance);
        $kas = $this->dompet('Kas SP-7', DompetKoperasi::JENIS_KAS, 'kas', 250000);
        $bank = $this->dompet('Bank SP-7', DompetKoperasi::JENIS_BANK, 'bank', 500000);

        $anggotaTunai = $this->registerAnggota([
            'simpanan_wajib_metode_pembayaran' => Simpanan::METODE_TUNAI,
            'simpanan_wajib_dompet_id' => $kas->id,
        ]);
        $anggotaTransfer = $this->registerAnggota([
            'simpanan_wajib_metode_pembayaran' => Simpanan::METODE_TRANSFER_BANK,
            'simpanan_wajib_dompet_id' => $bank->id,
        ]);

        foreach ([[$anggotaTunai, $kas], [$anggotaTransfer, $bank]] as [$anggota, $dompet]) {
            $simpanan = $this->wajibFor($anggota);
            $this->assertSame(Simpanan::STATUS_SETTLED, $simpanan->status);
            $this->assertNull($simpanan->pemakaian_potong_gaji_id);
            $this->assertDatabaseHas('mutasi_kas', [
                'idempotency_key' => 'simpanan-wajib:direct:mutasi:' . $simpanan->id,
                'dompet_id' => $dompet->id,
                'tipe' => 'masuk',
                'jumlah' => '10000.00',
            ]);
            $this->assertDatabaseHas('jurnal_umum', [
                'idempotency_key' => 'simpanan-wajib:direct:jurnal:' . $simpanan->id,
                'referensi_tipe' => Simpanan::class,
                'referensi_id' => $simpanan->id,
            ]);
        }

        $this->assertSame('260000.00', $kas->fresh()->saldo);
        $this->assertSame('510000.00', $bank->fresh()->saldo);
        $this->assertSame(0, PemakaianPotongGaji::query()
            ->where('kategori', PemakaianPotongGaji::KATEGORI_SIMPANAN_WAJIB)
            ->count());
    }

    public function test_validasi_dompet_tunai_dan_transfer_tidak_boleh_silang(): void
    {
        $finance = $this->finance();
        $this->actingAs($finance);
        $bank = $this->dompet('Bank Salah', DompetKoperasi::JENIS_BANK, 'bank', 100000);

        $this->expectException(ValidationException::class);
        $this->registerAnggota([
            'simpanan_wajib_metode_pembayaran' => Simpanan::METODE_TUNAI,
            'simpanan_wajib_dompet_id' => $bank->id,
        ]);
    }

    public function test_periode_dan_aktivasi_limit_tidak_membuat_jadwal_wajib_legacy(): void
    {
        $finance = $this->finance();
        $this->actingAs($finance);
        $anggota = $this->registerAnggota();
        $service = app(PotongGajiBulananService::class);

        $service->createPeriodeDraft('2026-07-15', $finance->id);
        $limit = $service->createLimit($anggota, '2026-07', 1000000, $finance->id, 'Limit SP-7');
        $service->activateLimit($limit, $finance->id);

        $this->assertSame(0, JadwalSimpananWajib::query()->count());
        $this->assertDatabaseHas('pemakaian_potong_gaji', [
            'kategori' => PemakaianPotongGaji::KATEGORI_SIMPANAN_WAJIB,
            'source_type' => Simpanan::class,
            'source_id' => $this->wajibFor($anggota)->id,
            'status' => PemakaianPotongGaji::STATUS_RESERVED,
        ]);
    }

    public function test_settlement_menghitung_wajib_paid_dan_membatalkan_wajib_pending(): void
    {
        $finance = $this->finance();
        $this->actingAs($finance);
        $kas = $this->dompet('Kas Settlement SP-7', DompetKoperasi::JENIS_KAS, 'kas', 300000);
        $anggotaPaid = $this->registerAnggota([
            'simpanan_wajib_metode_pembayaran' => Simpanan::METODE_TUNAI,
            'simpanan_wajib_dompet_id' => $kas->id,
        ]);
        $anggotaPending = $this->registerAnggota();
        $service = app(KeanggotaanLifecycleService::class);

        $paidCycle = $anggotaPaid->siklusAktif;
        $pendingCycle = $anggotaPending->siklusAktif;
        $service->closeActiveCycleForExit($anggotaPaid, '2026-08-01', $finance->id, 'Test paid keluar.');
        $paidSettlement = $service->createPenyelesaianForExit($anggotaPaid, $paidCycle, '2026-08-01', 'Test paid keluar.', $finance->id);
        $service->closeActiveCycleForExit($anggotaPending, '2026-08-01', $finance->id, 'Test pending keluar.');
        $pendingSettlement = $service->createPenyelesaianForExit($anggotaPending, $pendingCycle, '2026-08-01', 'Test pending keluar.', $finance->id);

        $this->assertDatabaseHas('penyelesaian_keanggotaan_detail', [
            'penyelesaian_keanggotaan_id' => $paidSettlement->id,
            'tipe_detail' => PenyelesaianKeanggotaanDetail::TIPE_HAK,
            'kategori_sumber' => PenyelesaianKeanggotaanDetail::KATEGORI_SIMPANAN_WAJIB,
            'nominal_hak_awal' => '10000.00',
        ]);
        $this->assertDatabaseMissing('penyelesaian_keanggotaan_detail', [
            'penyelesaian_keanggotaan_id' => $pendingSettlement->id,
            'tipe_detail' => PenyelesaianKeanggotaanDetail::TIPE_HAK,
            'kategori_sumber' => PenyelesaianKeanggotaanDetail::KATEGORI_SIMPANAN_WAJIB,
        ]);
        $this->assertDatabaseHas('penyelesaian_keanggotaan_detail', [
            'penyelesaian_keanggotaan_id' => $pendingSettlement->id,
            'tipe_detail' => PenyelesaianKeanggotaanDetail::TIPE_PEMBATALAN_WAJIB,
            'kategori_sumber' => PenyelesaianKeanggotaanDetail::KATEGORI_PEMBATALAN_WAJIB,
            'nominal_dibatalkan' => '10000.00',
        ]);
    }

    public function test_preflight_simpanan_wajib_bersih_untuk_fixture_minimal(): void
    {
        $this->assertSame(0, Artisan::call('koperasi:preflight-simpanan-wajib'));
    }

    private function registerAnggota(array $overrides = []): Anggota
    {
        $karyawan = Karyawan::factory()->create(['status_kerja' => Karyawan::STATUS_AKTIF]);

        return app(MasterDataKoperasiService::class)->createAnggota($overrides + [
            'karyawan_id' => $karyawan->id,
            'tanggal_bergabung' => '2026-07-01',
            'alamat' => 'Jl. SP-7 Test',
            'plafon_pinjaman' => 1000000,
        ])->fresh(['karyawan', 'siklusAktif']);
    }

    private function wajibFor(Anggota $anggota): Simpanan
    {
        return Simpanan::query()
            ->where('anggota_id', $anggota->id)
            ->where('kode_jenis_snapshot', JenisSimpanan::KODE_SIMPANAN_WAJIB)
            ->firstOrFail();
    }

    private function finance(): User
    {
        return User::factory()->create([
            'role' => 'admin',
            'is_active' => true,
            'must_change_password' => false,
        ]);
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
