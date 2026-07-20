<?php

namespace Tests\Feature;

use App\Models\Anggota;
use App\Models\Akun;
use App\Models\CicilanPinjaman;
use App\Models\DompetKoperasi;
use App\Models\JadwalSimpananWajib;
use App\Models\JurnalUmum;
use App\Models\Karyawan;
use App\Models\MutasiKas;
use App\Models\PemakaianPotongGaji;
use App\Models\PenyelesaianKeanggotaan;
use App\Models\PenyelesaianKeanggotaanDetail;
use App\Models\Pinjaman;
use App\Models\ReversalTransaksi;
use App\Models\SaldoSimpananSukarela;
use App\Models\SiklusKeanggotaan;
use App\Models\Simpanan;
use App\Models\User;
use App\Services\KeanggotaanLifecycleService;
use App\Services\MasterDataKoperasiService;
use App\Services\PinjamanKoperasiService;
use App\Services\PotongGajiBulananService;
use App\Services\SimpananSukarelaService;
use App\Services\SimpananWajibService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class KeanggotaanSettlementSp4Test extends TestCase
{
    use RefreshDatabase;

    public function test_deactivation_idempotent_membekukan_sukarela_dan_membatalkan_wajib_belum_dibayar(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);
        $anggota = $this->anggotaAktif('2026-01-05');
        $this->payrollBank();
        $kas = $this->kasDompet(1000000);
        $this->confirmPayroll($anggota, '2026-01-01', 200000, $admin);
        app(SimpananWajibService::class)->generateUntil('2026-04-01', $anggota, $admin->id);
        $activeLimit = app(PotongGajiBulananService::class)->activateLimit(
            app(PotongGajiBulananService::class)->createLimit($anggota, '2026-04-01', 100000, $admin->id, 'Reserve Wajib sebelum exit'),
            $admin->id
        );

        $this->assertSame(1, PemakaianPotongGaji::query()
            ->where('limit_potong_gaji_anggota_id', $activeLimit->id)
            ->where('kategori', PemakaianPotongGaji::KATEGORI_SIMPANAN_WAJIB)
            ->where('status', PemakaianPotongGaji::STATUS_RESERVED)
            ->count());

        app(SimpananSukarelaService::class)->setoran([
            'anggota_id' => $anggota->id,
            'dompet_id' => $kas->id,
            'jumlah' => 150000,
            'metode_pembayaran' => Simpanan::METODE_TUNAI,
            'tanggal' => '2026-03-15',
        ], $admin->id);

        $this->stopKaryawan($anggota, '2026-04-30');
        $this->stopKaryawan($anggota->fresh(), '2026-04-30');

        $this->assertSame(1, PenyelesaianKeanggotaan::query()->where('anggota_id', $anggota->id)->count());
        $penyelesaian = PenyelesaianKeanggotaan::query()->where('anggota_id', $anggota->id)->firstOrFail();
        $this->assertSame(PenyelesaianKeanggotaan::STATUS_PENDING_REVIEW, $penyelesaian->status);
        $this->assertSame(1, SaldoSimpananSukarela::query()->where('anggota_id', $anggota->id)->where('penyelesaian_keanggotaan_id', $penyelesaian->id)->whereNotNull('frozen_at')->count());

        $this->assertSame(1, JadwalSimpananWajib::query()->where('anggota_id', $anggota->id)->where('status', JadwalSimpananWajib::STATUS_SETTLED)->count());
        $this->assertSame(1, JadwalSimpananWajib::query()->where('anggota_id', $anggota->id)->where('status', JadwalSimpananWajib::STATUS_CANCELLED_EXIT)->count());
        $this->assertSame(1, PemakaianPotongGaji::query()->where('kategori', PemakaianPotongGaji::KATEGORI_SIMPANAN_WAJIB)->where('status', PemakaianPotongGaji::STATUS_RELEASED)->count());
        $this->assertSame(1, ReversalTransaksi::query()->where('jenis_reversal', ReversalTransaksi::JENIS_SIMPANAN_WAJIB_EXIT_CANCEL)->count());
        $this->assertSame(1, JurnalUmum::query()->where('referensi_tipe', ReversalTransaksi::class)->where('idempotency_key', 'like', 'reversal:jurnal:%')->count());
        $this->assertSame(0, MutasiKas::query()->where('referensi_tipe', ReversalTransaksi::class)->count());

        $this->expectException(ValidationException::class);
        app(SimpananSukarelaService::class)->setoran([
            'anggota_id' => $anggota->id,
            'dompet_id' => $kas->id,
            'jumlah' => 10000,
            'metode_pembayaran' => Simpanan::METODE_TUNAI,
            'tanggal' => '2026-05-01',
        ], $admin->id);
    }

    public function test_batalkan_penonaktifan_memulihkan_siklus_lama_sukarela_dan_wajib_tanpa_mutasi_kas(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);
        $anggota = $this->anggotaAktif('2026-01-05');
        $karyawanUser = User::factory()->create([
            'role' => 'karyawan',
            'karyawan_id' => $anggota->karyawan_id,
            'is_active' => true,
            'must_change_password' => false,
            'password_changed_at' => now(),
        ]);
        $this->payrollBank();
        $kas = $this->kasDompet(1000000);
        $this->confirmPayroll($anggota, '2026-01-01', 200000, $admin);
        app(SimpananWajibService::class)->generateUntil('2026-04-01', $anggota, $admin->id);
        app(SimpananSukarelaService::class)->setoran([
            'anggota_id' => $anggota->id,
            'dompet_id' => $kas->id,
            'jumlah' => 175000,
            'metode_pembayaran' => Simpanan::METODE_TUNAI,
            'tanggal' => '2026-03-15',
        ], $admin->id);

        $oldCycle = $anggota->siklusAktif()->firstOrFail();
        $pokokBefore = Simpanan::query()
            ->where('anggota_id', $anggota->id)
            ->where('kode_jenis_snapshot', \App\Models\JenisSimpanan::KODE_SIMPANAN_POKOK)
            ->count();

        $this->stopKaryawan($anggota, '2026-04-30');
        $penyelesaian = PenyelesaianKeanggotaan::query()->where('anggota_id', $anggota->id)->firstOrFail();
        $this->assertSame(1, JadwalSimpananWajib::query()->where('anggota_id', $anggota->id)->where('status', JadwalSimpananWajib::STATUS_CANCELLED_EXIT)->count());

        $restored = app(KeanggotaanLifecycleService::class)->cancelDeactivation(
            $penyelesaian,
            'Salah input nonaktif pada data dummy.',
            $admin->id
        );

        $this->assertSame(PenyelesaianKeanggotaan::STATUS_DEACTIVATION_CANCELLED, $restored->status);
        $this->assertSame(Anggota::STATUS_AKTIF, $anggota->fresh()->status);
        $this->assertSame(Karyawan::STATUS_AKTIF, $anggota->karyawan->fresh()->status_kerja);
        $this->assertTrue((bool) $karyawanUser->fresh()->is_active);
        $this->assertSame($oldCycle->id, $anggota->fresh()->siklusAktif()->firstOrFail()->id);
        $this->assertSame('175000.00', SaldoSimpananSukarela::query()->where('anggota_id', $anggota->id)->where('siklus_keanggotaan_id', $oldCycle->id)->firstOrFail()->saldo);
        $this->assertSame(0, SaldoSimpananSukarela::query()->where('anggota_id', $anggota->id)->where('siklus_keanggotaan_id', $oldCycle->id)->whereNotNull('frozen_at')->count());
        $this->assertSame(0, JadwalSimpananWajib::query()->where('anggota_id', $anggota->id)->where('status', JadwalSimpananWajib::STATUS_CANCELLED_EXIT)->count());
        $this->assertSame(1, JadwalSimpananWajib::query()->where('anggota_id', $anggota->id)->whereNotNull('recovery_jurnal_id')->count());
        $this->assertSame(0, MutasiKas::query()->where('referensi_tipe', JadwalSimpananWajib::class)->count());
        $this->assertSame($pokokBefore, Simpanan::query()->where('anggota_id', $anggota->id)->where('kode_jenis_snapshot', \App\Models\JenisSimpanan::KODE_SIMPANAN_POKOK)->count());

        $counts = [
            'cycles' => SiklusKeanggotaan::query()->where('anggota_id', $anggota->id)->count(),
            'pokok' => Simpanan::query()->where('anggota_id', $anggota->id)->where('kode_jenis_snapshot', \App\Models\JenisSimpanan::KODE_SIMPANAN_POKOK)->count(),
            'recovery_journal' => JurnalUmum::query()->where('idempotency_key', 'like', 'keanggotaan:wajib-recovery:jurnal:%')->count(),
        ];

        app(KeanggotaanLifecycleService::class)->cancelDeactivation(
            $restored,
            'Retry double submit tidak boleh duplikasi.',
            $admin->id
        );

        $this->assertSame($counts['cycles'], SiklusKeanggotaan::query()->where('anggota_id', $anggota->id)->count());
        $this->assertSame($counts['pokok'], Simpanan::query()->where('anggota_id', $anggota->id)->where('kode_jenis_snapshot', \App\Models\JenisSimpanan::KODE_SIMPANAN_POKOK)->count());
        $this->assertSame($counts['recovery_journal'], JurnalUmum::query()->where('idempotency_key', 'like', 'keanggotaan:wajib-recovery:jurnal:%')->count());
    }

    public function test_offset_mengurangi_pinjaman_tanpa_cicilan_palsu_dan_menyisakan_kewajiban_pending(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);
        $anggota = $this->anggotaAktif('2026-01-05', 1000000);
        $this->payrollBank();
        $kas = $this->kasDompet(1500000);

        $this->confirmPayroll($anggota, '2026-01-01', 200000, $admin);
        app(SimpananSukarelaService::class)->setoran([
            'anggota_id' => $anggota->id,
            'dompet_id' => $kas->id,
            'jumlah' => 150000,
            'metode_pembayaran' => Simpanan::METODE_TUNAI,
            'tanggal' => '2026-02-15',
        ], $admin->id);

        $pinjaman = app(PinjamanKoperasiService::class)->create([
            'anggota_id' => $anggota->id,
            'dompet_id' => $kas->id,
            'jumlah_pinjaman' => 600000,
            'tenor_bulan' => 6,
            'tanggal_pinjaman' => '2026-03-01',
            'keterangan' => 'Pinjaman test SP-4.',
        ], $admin->id);

        $this->stopKaryawan($anggota, '2026-04-30');
        $penyelesaian = PenyelesaianKeanggotaan::query()->where('anggota_id', $anggota->id)->firstOrFail();
        $processed = app(KeanggotaanLifecycleService::class)->processOffset($penyelesaian, $admin->id);

        $this->assertSame(PenyelesaianKeanggotaan::STATUS_WAITING_SETTLEMENT, $processed->status);
        $this->assertSame('350000.00', $processed->total_offset);
        $this->assertSame('250000.00', $processed->sisa_kewajiban);
        $this->assertSame('250000.00', $pinjaman->fresh()->sisa_pinjaman);
        $this->assertSame(Pinjaman::STATUS_AKTIF, $pinjaman->fresh()->status);
        $this->assertSame(0, CicilanPinjaman::query()->where('pinjaman_id', $pinjaman->id)->count());
        $this->assertSame(1, JurnalUmum::query()->where('referensi_tipe', PenyelesaianKeanggotaan::class)->where('idempotency_key', 'keanggotaan:offset:jurnal:' . $penyelesaian->id)->count());

        $retry = app(KeanggotaanLifecycleService::class)->processOffset($processed, $admin->id);
        $this->assertSame('250000.00', $pinjaman->fresh()->sisa_pinjaman);
        $this->assertSame($processed->total_offset, $retry->total_offset);

        $this->expectValidation(fn () => app(KeanggotaanLifecycleService::class)->cancelDeactivation(
            $processed,
            'Tidak boleh dibatalkan setelah offset.',
            $admin->id
        ));

        $this->expectException(ValidationException::class);
        app(KeanggotaanLifecycleService::class)->complete($processed, $admin->id);
    }

    public function test_refund_kas_bank_idempotent_dan_reaktivasi_membuat_saldo_sukarela_baru_nol(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);
        $anggota = $this->anggotaAktif('2026-01-05');
        $this->payrollBank();
        $kas = $this->kasDompet(1000000);
        $bank = $this->bankDompet(1000000);

        $this->confirmPayroll($anggota, '2026-01-01', 200000, $admin);
        app(SimpananSukarelaService::class)->setoran([
            'anggota_id' => $anggota->id,
            'dompet_id' => $bank->id,
            'jumlah' => 125000,
            'metode_pembayaran' => Simpanan::METODE_TRANSFER_BANK,
            'tanggal' => '2026-02-15',
        ], $admin->id);

        $this->stopKaryawan($anggota, '2026-04-30');
        $penyelesaian = PenyelesaianKeanggotaan::query()->where('anggota_id', $anggota->id)->firstOrFail();
        $ready = app(KeanggotaanLifecycleService::class)->processOffset($penyelesaian, $admin->id);
        $this->assertSame(PenyelesaianKeanggotaan::STATUS_READY_TO_COMPLETE, $ready->status);
        $this->assertSame('325000.00', $ready->total_refund);

        $this->expectValidation(fn () => app(KeanggotaanLifecycleService::class)->processRefund(
            $ready,
            $bank,
            $admin->id,
            PenyelesaianKeanggotaan::METODE_TUNAI
        ));

        $refunded = app(KeanggotaanLifecycleService::class)->processRefund(
            $ready,
            $kas,
            $admin->id,
            PenyelesaianKeanggotaan::METODE_TUNAI
        );
        app(KeanggotaanLifecycleService::class)->processRefund(
            $refunded,
            $kas,
            $admin->id,
            PenyelesaianKeanggotaan::METODE_TUNAI
        );

        $this->assertSame('675000.00', $kas->fresh()->saldo);
        $this->assertSame(1, MutasiKas::query()->where('referensi_tipe', PenyelesaianKeanggotaan::class)->where('tipe', 'keluar')->count());
        $this->assertSame(1, JurnalUmum::query()->where('referensi_tipe', PenyelesaianKeanggotaan::class)->where('idempotency_key', 'keanggotaan:refund:jurnal:' . $ready->id)->count());
        $this->assertSame('0.00', SaldoSimpananSukarela::query()->where('anggota_id', $anggota->id)->where('siklus_keanggotaan_id', $ready->siklus_keanggotaan_id)->firstOrFail()->saldo);

        $this->expectValidation(fn () => app(MasterDataKoperasiService::class)->updateKaryawan($anggota->karyawan->fresh(), $this->karyawanData($anggota->karyawan->fresh(), Karyawan::STATUS_AKTIF)));

        $completed = app(KeanggotaanLifecycleService::class)->complete($refunded, $admin->id);
        $this->assertSame(PenyelesaianKeanggotaan::STATUS_COMPLETED, $completed->status);

        $this->expectValidation(fn () => app(KeanggotaanLifecycleService::class)->cancelDeactivation(
            $completed,
            'Tidak boleh dibatalkan setelah completed.',
            $admin->id
        ));

        $this->expectValidation(fn () => app(KeanggotaanLifecycleService::class)->reRegisterMember(
            $completed,
            '2026-05-10',
            'Karyawan belum aktif.',
            $admin->id
        ));

        app(MasterDataKoperasiService::class)->updateKaryawan($anggota->karyawan->fresh(), $this->karyawanData($anggota->karyawan->fresh(), Karyawan::STATUS_AKTIF));
        app(KeanggotaanLifecycleService::class)->reRegisterMember(
            $completed,
            '2026-05-10',
            'Daftar kembali setelah settlement completed.',
            $admin->id
        );

        $this->assertSame(2, SiklusKeanggotaan::query()->where('anggota_id', $anggota->id)->count());
        $newCycle = $anggota->fresh()->siklusAktif()->firstOrFail();
        $this->assertSame($newCycle->id, $completed->fresh()->re_registered_cycle_id);
        $this->assertSame('0.00', SaldoSimpananSukarela::query()
            ->where('anggota_id', $anggota->id)
            ->where('siklus_keanggotaan_id', $newCycle->id)
            ->firstOrFail()
            ->saldo);
        $this->assertSame(1, Simpanan::query()
            ->where('anggota_id', $anggota->id)
            ->where('siklus_keanggotaan_id', $newCycle->id)
            ->where('kode_jenis_snapshot', \App\Models\JenisSimpanan::KODE_SIMPANAN_POKOK)
            ->whereNotIn('status', [Simpanan::STATUS_REVERSED, Simpanan::STATUS_REVERSED_DUE_TO_EXIT])
            ->count());
        $this->assertSame(0, JadwalSimpananWajib::query()
            ->where('anggota_id', $anggota->id)
            ->where('siklus_keanggotaan_id', '!=', $newCycle->id)
            ->where('status', JadwalSimpananWajib::STATUS_OUTSTANDING)
            ->count());
    }

    public function test_ui_authorization_dan_get_read_only(): void
    {
        $admin = $this->admin();
        $kasir = User::factory()->create(['role' => 'kasir']);
        $karyawanUser = User::factory()->create(['role' => 'karyawan']);
        $this->actingAs($admin);
        $anggota = $this->anggotaAktif('2026-01-05');
        $this->payrollBank();
        $this->confirmPayroll($anggota, '2026-01-01', 200000, $admin);
        $this->stopKaryawan($anggota, '2026-04-30');
        $penyelesaian = PenyelesaianKeanggotaan::query()->where('anggota_id', $anggota->id)->firstOrFail();

        $before = [
            'settlement' => PenyelesaianKeanggotaan::query()->count(),
            'detail' => PenyelesaianKeanggotaanDetail::query()->count(),
            'mutasi' => MutasiKas::query()->count(),
        ];

        $this->actingAs($admin)->get(route('penyelesaian-keanggotaan.index'))->assertOk()->assertSee('Penyelesaian Keanggotaan');
        $this->actingAs($admin)->get(route('penyelesaian-keanggotaan.show', $penyelesaian))->assertOk()->assertSee('Hak Anggota');
        $this->actingAs($kasir)->get(route('penyelesaian-keanggotaan.index'))->assertForbidden();
        $this->actingAs($karyawanUser)->post(route('penyelesaian-keanggotaan.process-offset', $penyelesaian))->assertForbidden();
        $this->actingAs($kasir)->post(route('penyelesaian-keanggotaan.cancel-deactivation', $penyelesaian), ['alasan' => 'Tidak boleh kasir'])->assertForbidden();
        $this->actingAs($karyawanUser)->post(route('penyelesaian-keanggotaan.re-register', $penyelesaian), [
            'tanggal_bergabung' => '2026-05-01',
            'alasan' => 'Tidak boleh karyawan',
            'konfirmasi_siklus_baru' => '1',
        ])->assertForbidden();
        $this->post(route('logout'));
        $this->get(route('penyelesaian-keanggotaan.index'))->assertRedirect(route('login'));

        $this->assertSame($before['settlement'], PenyelesaianKeanggotaan::query()->count());
        $this->assertSame($before['detail'], PenyelesaianKeanggotaanDetail::query()->count());
        $this->assertSame($before['mutasi'], MutasiKas::query()->count());
    }

    public function test_preflight_keanggotaan_mendeteksi_konflik_dan_read_only(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);
        $anggota = $this->anggotaAktif('2026-01-05');
        app(SimpananWajibService::class)->generateUntil('2026-04-01', $anggota, $admin->id);

        $cycle = $anggota->siklusAktif()->firstOrFail();
        $cycle->update(['status' => SiklusKeanggotaan::STATUS_CLOSED, 'tanggal_selesai' => '2026-04-30']);

        $before = [
            'jadwal' => JadwalSimpananWajib::query()->count(),
            'settlement' => PenyelesaianKeanggotaan::query()->count(),
            'detail' => PenyelesaianKeanggotaanDetail::query()->count(),
        ];

        $this->artisan('koperasi:preflight-keanggotaan')
            ->assertExitCode(1);

        $this->assertSame($before['jadwal'], JadwalSimpananWajib::query()->count());
        $this->assertSame($before['settlement'], PenyelesaianKeanggotaan::query()->count());
        $this->assertSame($before['detail'], PenyelesaianKeanggotaanDetail::query()->count());
    }

    private function anggotaAktif(string $tanggalBergabung, int $plafon = 5000000): Anggota
    {
        $karyawan = Karyawan::factory()->create([
            'status_kerja' => Karyawan::STATUS_AKTIF,
        ]);

        return app(MasterDataKoperasiService::class)->createAnggota([
            'karyawan_id' => $karyawan->id,
            'tanggal_bergabung' => $tanggalBergabung,
            'alamat' => 'Jl. SP4 Test No. 1',
            'plafon_pinjaman' => $plafon,
        ])->fresh(['karyawan', 'siklusAktif']);
    }

    private function confirmPayroll(Anggota $anggota, string $periode, int $nominal, User $admin): void
    {
        $service = app(PotongGajiBulananService::class);
        $limit = $service->findLimitFor($anggota, $periode)
            ?: $service->createLimit($anggota, $periode, $nominal, $admin->id, 'Limit test SP-4');

        if ($limit->status === \App\Models\LimitPotongGajiAnggota::STATUS_DRAFT) {
            $limit = $service->activateLimit($limit, $admin->id);
        }

        if ($limit->status === \App\Models\LimitPotongGajiAnggota::STATUS_ACTIVE) {
            $limit = $service->closeLimit($limit, $admin->id);
        }

        if ($limit->status === \App\Models\LimitPotongGajiAnggota::STATUS_CLOSED_PENDING_CONFIRMATION) {
            $service->confirmLimit($limit, $admin->id);
        }
    }

    private function stopKaryawan(Anggota $anggota, string $tanggalBerhenti): void
    {
        $karyawan = $anggota->karyawan->fresh();
        app(MasterDataKoperasiService::class)->updateKaryawan(
            $karyawan,
            $this->karyawanData($karyawan, Karyawan::STATUS_BERHENTI, $tanggalBerhenti)
        );
    }

    private function karyawanData(Karyawan $karyawan, string $status, ?string $tanggalBerhenti = null): array
    {
        return [
            'nama' => $karyawan->nama,
            'email' => $karyawan->email,
            'telepon' => $karyawan->telepon,
            'jabatan' => $karyawan->jabatan,
            'status_kerja' => $status,
            'tanggal_berhenti' => $tanggalBerhenti,
        ];
    }

    private function kasDompet(int $saldo): DompetKoperasi
    {
        return DompetKoperasi::query()->create([
            'akun_id' => Akun::query()->where('kode_akun', '101')->value('id'),
            'nama_dompet' => 'Kas SP4 ' . uniqid(),
            'jenis_dompet' => DompetKoperasi::JENIS_KAS,
            'saldo' => $saldo,
        ]);
    }

    private function bankDompet(int $saldo): DompetKoperasi
    {
        return DompetKoperasi::query()->create([
            'akun_id' => Akun::query()->where('kode_akun', '102')->value('id'),
            'nama_dompet' => 'Bank SP4 ' . uniqid(),
            'jenis_dompet' => DompetKoperasi::JENIS_BANK,
            'saldo' => $saldo,
        ]);
    }

    private function payrollBank(): DompetKoperasi
    {
        return DompetKoperasi::query()->create([
            'akun_id' => Akun::query()->where('kode_akun', '102')->value('id'),
            'nama_dompet' => 'Bank Payroll SP4 ' . uniqid(),
            'jenis_dompet' => DompetKoperasi::JENIS_BANK,
            'is_default_penerimaan_payroll' => true,
            'saldo' => 0,
        ]);
    }

    private function admin(): User
    {
        return User::factory()->create([
            'role' => 'admin',
            'is_active' => true,
            'must_change_password' => false,
            'password_changed_at' => now(),
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
