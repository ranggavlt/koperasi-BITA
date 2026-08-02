<?php

namespace Tests\Feature;

use App\Models\Akun;
use App\Models\Anggota;
use App\Models\DompetKoperasi;
use App\Models\Karyawan;
use App\Models\KonfigurasiManasukaRutin;
use App\Models\PemakaianPotongGaji;
use App\Models\SaldoSimpananSukarela;
use App\Models\SiklusKeanggotaan;
use App\Models\Simpanan;
use App\Models\User;
use App\Services\ManasukaRutinService;
use App\Services\PotongGajiBulananService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class ManasukaRutinPg2Test extends TestCase
{
    use RefreshDatabase;

    public function test_konfigurasi_immutable_dan_perubahan_baru_berlaku_periode_berikutnya(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-15 09:00:00', 'Asia/Jakarta'));

        try {
            $finance = $this->finance();
            [$anggota, $siklus] = $this->anggotaDenganSiklus();
            $service = app(ManasukaRutinService::class);

            $aktif = $service->schedule(
                $anggota,
                KonfigurasiManasukaRutin::STATUS_AKTIF,
                50000,
                $finance->id,
                'Permintaan rutin Anggota.',
                'pg2-config-active-1'
            );
            $dijeda = $service->schedule(
                $anggota,
                KonfigurasiManasukaRutin::STATUS_DIJEDA,
                50000,
                $finance->id,
                'Dijeda mulai periode Agustus.',
                'pg2-config-pause-1',
                '2026-08-01'
            );

            $this->assertSame($aktif->id, $service->effectiveFor($anggota, $siklus->id, '2026-07')->id);
            $this->assertSame($dijeda->id, $service->effectiveFor($anggota, $siklus->id, '2026-08')->id);
            $this->assertSame('50000.00', $dijeda->nominal_snapshot);

            $this->expectException(RuntimeException::class);
            $aktif->update(['nominal_snapshot' => 75000]);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_reservasi_full_only_idempotent_dan_prioritas_setelah_wajib(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-15 09:00:00', 'Asia/Jakarta'));

        try {
            $finance = $this->finance();
            [$anggota] = $this->anggotaDenganSiklus();
            $this->aktifkanRutin($anggota, $finance, 50000, 'full-only-a');
            $payroll = app(PotongGajiBulananService::class);

            $limitKurang = $payroll->activateLimit(
                $payroll->createLimit($anggota, '2026-07', 149999, $finance->id, 'Limit kurang satu rupiah untuk Manasuka'),
                $finance->id
            );

            $this->assertSame(0, $limitKurang->pemakaian()
                ->where('kategori', PemakaianPotongGaji::KATEGORI_SIMPANAN_MANASUKA)
                ->count());

            [$anggotaCukup] = $this->anggotaDenganSiklus();
            $this->aktifkanRutin($anggotaCukup, $finance, 50000, 'full-only-b');
            $limitCukup = $payroll->activateLimit(
                $payroll->createLimit($anggotaCukup, '2026-07', 150000, $finance->id, 'Limit tepat untuk Wajib dan Manasuka'),
                $finance->id
            );
            $payroll->activateLimit($limitCukup->fresh(), $finance->id);

            $ledger = $limitCukup->pemakaian()
                ->where('kategori', PemakaianPotongGaji::KATEGORI_SIMPANAN_MANASUKA)
                ->firstOrFail();
            $simpanan = Simpanan::query()->findOrFail($ledger->source_id);

            $this->assertSame(PemakaianPotongGaji::STATUS_RESERVED, $ledger->status);
            $this->assertSame('50000.00', $ledger->nominal);
            $this->assertMatchesRegularExpression('/^SMN-202607-\d{6}$/', $simpanan->kode_transaksi);
            $this->assertNotNull($simpanan->konfigurasi_manasuka_rutin_id);
            $this->assertSame(1, $limitCukup->pemakaian()
                ->where('kategori', PemakaianPotongGaji::KATEGORI_SIMPANAN_MANASUKA)
                ->count());
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_confirm_payroll_membentuk_saldo_mutasi_dan_jurnal_balance_secara_atomik(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-15 09:00:00', 'Asia/Jakarta'));

        try {
            $finance = $this->finance();
            [$anggota] = $this->anggotaDenganSiklus();
            $this->aktifkanRutin($anggota, $finance, 50000, 'settlement');
            $bank = $this->bankPayroll();
            $payroll = app(PotongGajiBulananService::class);
            $limit = $payroll->activateLimit(
                $payroll->createLimit($anggota, '2026-07', 150000, $finance->id, 'Settlement Wajib dan Manasuka'),
                $finance->id
            );
            $ledger = $limit->pemakaian()
                ->where('kategori', PemakaianPotongGaji::KATEGORI_SIMPANAN_MANASUKA)
                ->firstOrFail();
            $simpanan = Simpanan::query()->findOrFail($ledger->source_id);

            $payroll->confirmLimit($payroll->closeLimit($limit, $finance->id), $finance->id);
            $simpanan->refresh();

            $saldo = SaldoSimpananSukarela::query()
                ->where('anggota_id', $anggota->id)
                ->where('siklus_keanggotaan_id', $simpanan->siklus_keanggotaan_id)
                ->where('jenis_simpanan_id', $simpanan->jenis_simpanan_id)
                ->firstOrFail();
            $jurnal = $simpanan->jurnal()->with('details')->firstOrFail();

            $this->assertSame(Simpanan::STATUS_SETTLED, $simpanan->status);
            $this->assertSame('50000.00', $saldo->saldo);
            $this->assertSame('0.00', $simpanan->saldo_sebelum_snapshot);
            $this->assertSame('50000.00', $simpanan->saldo_sesudah_snapshot);
            $this->assertDatabaseHas('mutasi_kas', [
                'referensi_tipe' => Simpanan::class,
                'referensi_id' => $simpanan->id,
                'dompet_id' => $bank->id,
                'tipe' => 'masuk',
                'jumlah' => '50000.00',
            ]);
            $this->assertEqualsWithDelta(
                $jurnal->details->sum(fn ($detail) => (float) $detail->debit),
                $jurnal->details->sum(fn ($detail) => (float) $detail->kredit),
                0.01
            );
            $this->assertSame('150000.00', $bank->fresh()->saldo);
            $this->artisan('koperasi:preflight-manasuka-rutin')->assertExitCode(0);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_auto_pause_mempertahankan_nominal_dan_release_membatalkan_transaksi_pending(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-15 09:00:00', 'Asia/Jakarta'));

        try {
            $finance = $this->finance();
            [$anggota] = $this->anggotaDenganSiklus();
            $this->aktifkanRutin($anggota, $finance, 50000, 'auto-pause');
            $payroll = app(PotongGajiBulananService::class);
            $limit = $payroll->activateLimit(
                $payroll->createLimit($anggota, '2026-07', 150000, $finance->id, 'Limit sebelum Anggota nonaktif'),
                $finance->id
            );
            $ledger = $limit->pemakaian()
                ->where('kategori', PemakaianPotongGaji::KATEGORI_SIMPANAN_MANASUKA)
                ->firstOrFail();

            $pause = app(ManasukaRutinService::class)->pauseForInactive(
                $anggota,
                $finance->id,
                'Dijeda otomatis karena Anggota nonaktif.'
            );
            app(ManasukaRutinService::class)->releaseReservationsForLimit(
                $limit,
                $finance->id,
                'Anggota nonaktif sebelum konfirmasi.'
            );

            $this->assertSame(KonfigurasiManasukaRutin::STATUS_DIJEDA, $pause->status);
            $this->assertSame('50000.00', $pause->nominal_snapshot);
            $this->assertSame(PemakaianPotongGaji::STATUS_RELEASED, $ledger->fresh()->status);
            $this->assertSame(Simpanan::STATUS_CANCELLED, Simpanan::query()->findOrFail($ledger->source_id)->status);
        } finally {
            Carbon::setTestNow();
        }
    }

    private function finance(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    private function anggotaDenganSiklus(): array
    {
        $karyawan = Karyawan::factory()->create(['status_kerja' => Karyawan::STATUS_AKTIF]);
        $anggota = Anggota::factory()->create([
            'karyawan_id' => $karyawan->id,
            'tanggal_bergabung' => '2026-06-01',
            'status' => Anggota::STATUS_AKTIF,
        ]);
        $siklus = SiklusKeanggotaan::query()->create([
            'anggota_id' => $anggota->id,
            'siklus_ke' => 1,
            'tanggal_mulai' => '2026-06-01',
            'status' => SiklusKeanggotaan::STATUS_ACTIVE,
        ]);

        return [$anggota, $siklus];
    }

    private function aktifkanRutin(Anggota $anggota, User $finance, int $nominal, string $scope): void
    {
        app(ManasukaRutinService::class)->schedule(
            $anggota,
            KonfigurasiManasukaRutin::STATUS_AKTIF,
            $nominal,
            $finance->id,
            'Aktivasi Manasuka rutin untuk test.',
            'pg2-config-'.$scope.'-'.$anggota->id
        );
    }

    private function bankPayroll(): DompetKoperasi
    {
        return DompetKoperasi::query()->create([
            'akun_id' => Akun::query()->where('kode_akun', config('account_map.accounts.bank.kode_akun'))->value('id'),
            'nama_dompet' => 'Bank Payroll PG-2',
            'jenis_dompet' => DompetKoperasi::JENIS_BANK,
            'is_default_penerimaan_payroll' => true,
            'saldo' => 0,
        ]);
    }
}
