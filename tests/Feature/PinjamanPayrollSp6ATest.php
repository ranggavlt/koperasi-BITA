<?php

namespace Tests\Feature;

use App\Models\Akun;
use App\Models\Anggota;
use App\Models\CicilanPinjaman;
use App\Models\DompetKoperasi;
use App\Models\JadwalCicilanPinjaman;
use App\Models\JenisSimpanan;
use App\Models\JurnalUmum;
use App\Models\KategoriProduk;
use App\Models\Karyawan;
use App\Models\LimitPotongGajiAnggota;
use App\Models\MutasiKas;
use App\Models\Pembayaran;
use App\Models\PemakaianPotongGaji;
use App\Models\Penjualan;
use App\Models\PenyelesaianKeanggotaan;
use App\Models\Pinjaman;
use App\Models\Produk;
use App\Models\SiklusKeanggotaan;
use App\Models\Simpanan;
use App\Models\User;
use App\Services\KeanggotaanLifecycleService;
use App\Services\MasterDataKoperasiService;
use App\Services\PinjamanKoperasiService;
use App\Services\PosCheckoutService;
use App\Services\PotongGajiBulananService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PinjamanPayrollSp6ATest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_tunggakan_dipilih_oldest_first_dan_limit_kurang_rollback_tanpa_partial(): void
    {
        $this->deferSimpananWajib();
        $finance = $this->finance();
        $service = app(PotongGajiBulananService::class);
        $pinjaman = $this->pinjaman($this->anggota(), 900000, 3, '2026-05-10');

        $limitKurang = $service->createLimit($pinjaman->anggota, '2026-07', 500000, $finance->id, 'Limit kurang dari dua cicilan due');

        $this->expectValidation(fn () => $service->activateLimit($limitKurang, $finance->id));
        $this->assertSame(0, PemakaianPotongGaji::query()->where('kategori', PemakaianPotongGaji::KATEGORI_CICILAN)->count());
        $this->assertSame(
            [JadwalCicilanPinjaman::STATUS_SCHEDULED, JadwalCicilanPinjaman::STATUS_SCHEDULED, JadwalCicilanPinjaman::STATUS_SCHEDULED],
            $pinjaman->jadwalCicilan()->pluck('status')->all()
        );

        $limit = $service->updateLimit($limitKurang->fresh(), 600000, $finance->id, 'Limit pas untuk dua cicilan due');
        $service->activateLimit($limit, $finance->id);

        $ledgers = PemakaianPotongGaji::query()
            ->where('kategori', PemakaianPotongGaji::KATEGORI_CICILAN)
            ->orderBy('id')
            ->get();

        $this->assertCount(2, $ledgers);
        $this->assertSame(
            $pinjaman->jadwalCicilan()->orderBy('periode')->limit(2)->pluck('id')->all(),
            $ledgers->pluck('source_id')->all()
        );
        $this->assertSame(['300000.00', '300000.00'], $ledgers->pluck('nominal')->all());
    }

    public function test_offset_sebagian_membuat_payroll_menagih_nominal_sisa_dan_tidak_overcharge_pokok(): void
    {
        $this->deferSimpananWajib();
        Carbon::setTestNow(Carbon::parse('2026-07-20 10:00:00', 'Asia/Jakarta'));
        $finance = $this->finance();
        $bank = $this->bankDefaultPayroll();
        $service = app(PotongGajiBulananService::class);
        $pinjaman = $this->pinjaman($this->anggota(), 500000, 1, '2026-06-10');
        $jadwal = $pinjaman->jadwalCicilan()->firstOrFail();

        $jadwal->update([
            'nominal_offset' => '200000.00',
            'nominal_sisa' => '300000.00',
        ]);
        $pinjaman->update(['sisa_pinjaman' => '300000.00']);

        $limit = $service->activateLimit(
            $service->createLimit($pinjaman->anggota, '2026-07', 300000, $finance->id, 'Limit sisa cicilan'),
            $finance->id
        );

        $ledger = PemakaianPotongGaji::query()->where('kategori', PemakaianPotongGaji::KATEGORI_CICILAN)->firstOrFail();
        $this->assertSame('300000.00', $ledger->nominal);

        $service->confirmLimit($service->closeLimit($limit, $finance->id), $finance->id);

        $payment = CicilanPinjaman::query()->firstOrFail();
        $this->assertSame('300000.00', $payment->jumlah_cicilan);
        $this->assertSame('0.00', $jadwal->fresh()->nominal_sisa);
        $this->assertSame(Pinjaman::STATUS_LUNAS, $pinjaman->fresh()->status);
        $this->assertSame('300000.00', $bank->fresh()->saldo);
    }

    public function test_pos_payroll_ditolak_saat_cicilan_due_unreserved_tetapi_pos_tunai_tetap_boleh(): void
    {
        $this->deferSimpananWajib();
        $finance = $this->finance();
        $anggota = $this->anggota();
        $this->pinjaman($anggota, 300000, 1, '2026-06-10');
        $limit = app(PotongGajiBulananService::class)->createLimit($anggota, '2026-07', 500000, $finance->id, 'Limit dibuat manual active untuk simulasi konflik');
        $limit->update([
            'status' => LimitPotongGajiAnggota::STATUS_ACTIVE,
            'activated_by' => $finance->id,
            'activated_at' => now(),
        ]);

        $produk = $this->produk(50000);

        $this->expectValidation(fn () => app(PosCheckoutService::class)->checkout([
            'tanggal_transaksi' => '2026-07-15',
            'tipe_pelanggan' => Penjualan::TIPE_ANGGOTA,
            'anggota_id' => $anggota->id,
            'metode_pembayaran' => Pembayaran::METODE_POTONG_GAJI,
            'items' => [['produk_id' => $produk->id, 'jumlah' => 1]],
        ], $finance->id));

        $cashSale = app(PosCheckoutService::class)->checkout([
            'tanggal_transaksi' => '2026-07-15',
            'tipe_pelanggan' => Penjualan::TIPE_ANGGOTA,
            'anggota_id' => $anggota->id,
            'metode_pembayaran' => Pembayaran::METODE_TUNAI,
            'dompet_id' => $this->kasDompet()->id,
            'items' => [['produk_id' => $produk->id, 'jumlah' => 1]],
        ], $finance->id);

        $this->assertSame(Pembayaran::METODE_TUNAI, $cashSale->pembayaran->metode_pembayaran);
    }

    public function test_pencairan_setelah_limit_active_mencoba_reservasi_dan_rollback_bila_limit_kurang(): void
    {
        $this->deferSimpananWajib();
        $finance = $this->finance();
        $loanService = app(PinjamanKoperasiService::class);
        $payrollService = app(PotongGajiBulananService::class);

        $anggota = $this->anggota();
        $limit = $payrollService->activateLimit(
            $payrollService->createLimit($anggota, '2026-07', 300000, $finance->id, 'Limit sebelum pencairan'),
            $finance->id
        );
        $draft = $loanService->createDraft($this->loanPayload($anggota, 300000, 1, '2026-06-10'), $finance->id);
        $approved = $loanService->approve($loanService->submit($draft, $finance->id), $finance->id);
        $active = $loanService->disburse($approved, [
            'dompet_id' => $this->kasDompet(1000000)->id,
            'tanggal_pencairan' => '2026-06-10',
        ], $finance->id);

        $this->assertSame(Pinjaman::STATUS_AKTIF, $active->status);
        $this->assertSame(1, PemakaianPotongGaji::query()->where('limit_potong_gaji_anggota_id', $limit->id)->where('kategori', PemakaianPotongGaji::KATEGORI_CICILAN)->count());

        $anggotaKurang = $this->anggota();
        $payrollService->activateLimit(
            $payrollService->createLimit($anggotaKurang, '2026-07', 200000, $finance->id, 'Limit kurang sebelum pencairan'),
            $finance->id
        );
        $draftKurang = $loanService->createDraft($this->loanPayload($anggotaKurang, 300000, 1, '2026-06-10'), $finance->id);
        $approvedKurang = $loanService->approve($loanService->submit($draftKurang, $finance->id), $finance->id);

        $this->expectValidation(fn () => $loanService->disburse($approvedKurang, [
            'dompet_id' => $this->kasDompet(1000000)->id,
            'tanggal_pencairan' => '2026-06-10',
        ], $finance->id));

        $this->assertSame(Pinjaman::STATUS_DISETUJUI, $approvedKurang->fresh()->status);
        $this->assertSame(0, $approvedKurang->jadwalCicilan()->count());
        $this->assertSame(0, MutasiKas::query()->where('referensi_tipe', Pinjaman::class)->where('referensi_id', $approvedKurang->id)->count());
        $this->assertSame(0, JurnalUmum::query()->where('referensi_tipe', Pinjaman::class)->where('referensi_id', $approvedKurang->id)->count());
    }

    public function test_pembayaran_tunai_ditolak_saat_reserved_dan_pelunasan_penuh_memakai_total_nominal_sisa(): void
    {
        $this->deferSimpananWajib();
        Carbon::setTestNow(Carbon::parse('2026-07-20 10:00:00', 'Asia/Jakarta'));
        $finance = $this->finance();
        $service = app(PotongGajiBulananService::class);
        $pinjaman = $this->pinjaman($this->anggota(), 600000, 2, '2026-06-10');
        $service->activateLimit(
            $service->createLimit($pinjaman->anggota, '2026-07', 300000, $finance->id, 'Limit reserve cicilan'),
            $finance->id
        );
        Simpanan::query()
            ->where('anggota_id', $pinjaman->anggota_id)
            ->where('kode_jenis_snapshot', JenisSimpanan::KODE_SIMPANAN_POKOK)
            ->update(['status' => Simpanan::STATUS_REVERSED]);

        $this->stopKaryawan($pinjaman->anggota->karyawan);
        $kas = $this->kasDompet();
        $firstPayment = $service->payScheduledCash($pinjaman->fresh(), $kas, $finance->id);

        $this->assertSame('300000.00', $firstPayment->jumlah_cicilan);

        $future = $pinjaman->jadwalCicilan()->where('status', JadwalCicilanPinjaman::STATUS_SCHEDULED)->firstOrFail();
        $future->update([
            'nominal_offset' => '200000.00',
            'nominal_sisa' => '100000.00',
        ]);
        $pinjaman->update(['sisa_pinjaman' => '100000.00']);

        $payments = $service->payFullCash($pinjaman->fresh(), $kas->fresh(), $finance->id);

        $this->assertCount(1, $payments);
        $this->assertSame('100000.00', $payments->first()->jumlah_cicilan);
        $this->assertSame(Pinjaman::STATUS_LUNAS, $pinjaman->fresh()->status);
        $this->assertSame('400000.00', $kas->fresh()->saldo);
    }

    public function test_pembayaran_tunai_ditolak_jika_ledger_reserved_masih_aktif(): void
    {
        $this->deferSimpananWajib();
        Carbon::setTestNow(Carbon::parse('2026-07-20 10:00:00', 'Asia/Jakarta'));
        $finance = $this->finance();
        $service = app(PotongGajiBulananService::class);
        $pinjaman = $this->pinjaman($this->anggota(), 300000, 1, '2026-06-10');
        $service->activateLimit(
            $service->createLimit($pinjaman->anggota, '2026-07', 300000, $finance->id, 'Limit reserved'),
            $finance->id
        );

        $pinjaman->anggota->update(['status' => Anggota::STATUS_NONAKTIF, 'tanggal_nonaktif' => '2026-07-01']);
        $pinjaman->anggota->karyawan->update(['status_kerja' => Karyawan::STATUS_BERHENTI, 'tanggal_berhenti' => '2026-07-01']);

        $this->expectValidation(fn () => $service->payScheduledCash($pinjaman->fresh(), $this->kasDompet(), $finance->id));
        $this->assertSame(0, CicilanPinjaman::query()->count());
    }

    public function test_settlement_menolak_jadwal_already_paid_dan_double_confirm_tidak_menggandakan_posting(): void
    {
        $this->deferSimpananWajib();
        $finance = $this->finance();
        $this->bankDefaultPayroll();
        $service = app(PotongGajiBulananService::class);
        $pinjaman = $this->pinjaman($this->anggota(), 300000, 1, '2026-06-10');
        $limit = $service->activateLimit(
            $service->createLimit($pinjaman->anggota, '2026-07', 300000, $finance->id, 'Limit payroll'),
            $finance->id
        );

        $jadwal = $pinjaman->jadwalCicilan()->firstOrFail();
        $jadwal->update([
            'status' => JadwalCicilanPinjaman::STATUS_PAID,
            'metode_penyelesaian' => JadwalCicilanPinjaman::METODE_TUNAI,
            'nominal_sisa' => '0.00',
            'paid_at' => now(),
        ]);

        $this->expectValidation(fn () => $service->confirmLimit($service->closeLimit($limit, $finance->id), $finance->id));
        $this->assertSame(PemakaianPotongGaji::STATUS_RESERVED, PemakaianPotongGaji::query()->firstOrFail()->status);
        $this->assertSame(0, CicilanPinjaman::query()->count());
    }

    public function test_pinjaman_siklus_lama_tidak_masuk_payroll_siklus_baru_dan_daftar_ulang_ditolak_jika_belum_lunas(): void
    {
        $this->deferSimpananWajib();
        $finance = $this->finance();
        $anggota = $this->anggota();
        $oldCycle = $anggota->siklusAktif()->firstOrFail();
        $pinjaman = $this->pinjaman($anggota, 600000, 2, '2026-06-10');

        $oldCycle->update(['status' => SiklusKeanggotaan::STATUS_CLOSED, 'tanggal_selesai' => '2026-07-01']);
        $anggota->update(['status' => Anggota::STATUS_NONAKTIF, 'tanggal_nonaktif' => '2026-07-01']);
        $anggota->karyawan->update(['status_kerja' => Karyawan::STATUS_AKTIF, 'tanggal_berhenti' => null]);

        $settlement = PenyelesaianKeanggotaan::query()->create([
            'kode_penyelesaian' => 'PKA-SP6A-' . uniqid(),
            'anggota_id' => $anggota->id,
            'siklus_keanggotaan_id' => $oldCycle->id,
            'tanggal_keluar' => '2026-07-01',
            'simpanan_pokok_snapshot' => '0.00',
            'kredit_refund_snapshot' => '0.00',
            'total_hak_anggota' => '0.00',
            'total_kewajiban_awal' => '0.00',
            'total_offset' => '0.00',
            'total_refund' => '0.00',
            'sisa_kewajiban' => '0.00',
            'status' => PenyelesaianKeanggotaan::STATUS_COMPLETED,
            'alasan' => 'Fixture SP-6A',
            'created_by' => $finance->id,
            'completed_by' => $finance->id,
            'completed_at' => now(),
            'idempotency_key' => 'settlement:sp6a:' . $anggota->id,
        ]);

        $this->expectValidation(fn () => app(KeanggotaanLifecycleService::class)->reRegisterMember(
            $settlement,
            '2026-07-10',
            'Coba daftar ulang saat pinjaman lama belum lunas.',
            $finance->id
        ));

        $newCycle = SiklusKeanggotaan::query()->create([
            'anggota_id' => $anggota->id,
            'siklus_ke' => 2,
            'tanggal_mulai' => '2026-07-10',
            'status' => SiklusKeanggotaan::STATUS_ACTIVE,
            'created_by' => $finance->id,
        ]);
        $anggota->update(['status' => Anggota::STATUS_AKTIF, 'tanggal_nonaktif' => null]);

        $limit = app(PotongGajiBulananService::class)->activateLimit(
            app(PotongGajiBulananService::class)->createLimit($anggota, '2026-07', 600000, $finance->id, 'Limit siklus baru'),
            $finance->id
        );

        $this->assertSame($oldCycle->id, $pinjaman->fresh()->siklus_keanggotaan_id);
        $this->assertSame($newCycle->id, $anggota->fresh()->siklusAktif()->firstOrFail()->id);
        $this->assertSame(0, PemakaianPotongGaji::query()->where('limit_potong_gaji_anggota_id', $limit->id)->where('kategori', PemakaianPotongGaji::KATEGORI_CICILAN)->count());
    }

    public function test_preflight_sp6a_bersih_dan_mendeteksi_due_cicilan_tanpa_ledger(): void
    {
        $this->deferSimpananWajib();
        $finance = $this->finance();
        $service = app(PotongGajiBulananService::class);
        $anggota = $this->anggotaDenganPokokPending();
        $pinjaman = $this->pinjaman($anggota, 300000, 1, '2026-06-10');
        $limit = $service->activateLimit(
            $service->createLimit($pinjaman->anggota, '2026-07', 400000, $finance->id, 'Limit valid'),
            $finance->id
        );

        $this->artisan('koperasi:preflight-pinjaman')->assertExitCode(0);

        PemakaianPotongGaji::query()
            ->where('limit_potong_gaji_anggota_id', $limit->id)
            ->where('kategori', PemakaianPotongGaji::KATEGORI_CICILAN)
            ->delete();
        $pinjaman->jadwalCicilan()->firstOrFail()->update([
            'status' => JadwalCicilanPinjaman::STATUS_SCHEDULED,
            'metode_penyelesaian' => null,
        ]);

        $this->artisan('koperasi:preflight-potong-gaji')->assertExitCode(1);
    }

    private function finance(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    private function anggota(int $plafon = 5000000): Anggota
    {
        $karyawan = Karyawan::factory()->create();

        $anggota = app(MasterDataKoperasiService::class)->createAnggota([
            'karyawan_id' => $karyawan->id,
            'tanggal_bergabung' => '2026-01-01',
            'alamat' => 'Jl. SP-6A',
            'plafon_pinjaman' => $plafon,
        ]);

        Simpanan::query()
            ->where('anggota_id', $anggota->id)
            ->where('kode_jenis_snapshot', JenisSimpanan::KODE_SIMPANAN_WAJIB)
            ->update(['status' => Simpanan::STATUS_SETTLED, 'settled_at' => now()]);

        return $anggota->fresh(['karyawan', 'siklusAktif']);
    }

    private function anggotaDenganPokokPending(int $plafon = 5000000): Anggota
    {
        $karyawan = Karyawan::factory()->create();

        return app(MasterDataKoperasiService::class)->createAnggota([
            'karyawan_id' => $karyawan->id,
            'tanggal_bergabung' => '2026-01-01',
            'alamat' => 'Jl. SP-6A Preflight',
            'plafon_pinjaman' => $plafon,
        ])->fresh(['karyawan', 'siklusAktif']);
    }

    private function pinjaman(Anggota $anggota, int $jumlah, int $tenor, string $tanggal): Pinjaman
    {
        return app(PinjamanKoperasiService::class)->create(
            $this->loanPayload($anggota, $jumlah, $tenor, $tanggal) + ['dompet_id' => $this->kasDompet(10000000)->id],
            $this->finance()->id
        );
    }

    private function loanPayload(Anggota $anggota, int $jumlah, int $tenor, string $tanggal): array
    {
        return [
            'anggota_id' => $anggota->id,
            'jumlah_pinjaman' => $jumlah,
            'tenor_bulan' => $tenor,
            'tanggal_pengajuan' => $tanggal,
            'tanggal_pinjaman' => $tanggal,
            'keterangan' => 'Pinjaman fixture SP-6A',
        ];
    }

    private function kasDompet(int $saldo = 0): DompetKoperasi
    {
        return DompetKoperasi::query()->create([
            'akun_id' => Akun::query()->where('kode_akun', '101')->value('id'),
            'nama_dompet' => 'Kas SP6A ' . uniqid(),
            'jenis_dompet' => DompetKoperasi::JENIS_KAS,
            'saldo' => $saldo,
        ]);
    }

    private function bankDefaultPayroll(): DompetKoperasi
    {
        return DompetKoperasi::query()->create([
            'akun_id' => Akun::query()->where('kode_akun', '102')->value('id'),
            'nama_dompet' => 'Bank Payroll SP6A ' . uniqid(),
            'jenis_dompet' => DompetKoperasi::JENIS_BANK,
            'is_default_penerimaan_payroll' => true,
            'saldo' => 0,
        ]);
    }

    private function produk(int $harga = 50000): Produk
    {
        $kategori = KategoriProduk::query()->create(['nama_kategori' => 'Kategori SP6A ' . uniqid()]);

        return Produk::query()->create([
            'nama_produk' => 'Produk SP6A ' . uniqid(),
            'kategori_id' => $kategori->id,
            'harga_beli' => $harga,
            'harga_jual' => $harga,
            'stok' => 10,
            'konsinyasi' => false,
            'harga_setor' => 0,
        ]);
    }

    private function deferSimpananWajib(): void
    {
        JenisSimpanan::query()
            ->where('kode', JenisSimpanan::KODE_SIMPANAN_WAJIB)
            ->update([
                'aktif' => true,
                'berlaku_mulai' => '2027-01-01',
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
