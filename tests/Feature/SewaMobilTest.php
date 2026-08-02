<?php

namespace Tests\Feature;

use App\Models\Akun;
use App\Models\Anggota;
use App\Models\AsetKoperasi;
use App\Models\DompetKoperasi;
use App\Models\Karyawan;
use App\Models\MutasiKas;
use App\Models\PemakaianPotongGaji;
use App\Models\PembayaranSewaMobil;
use App\Models\PembayaranVendorSewa;
use App\Models\PengurusKoperasi;
use App\Models\SewaMobil;
use App\Models\User;
use App\Services\B2BRentalService;
use App\Services\SewaMobilService;
use Database\Seeders\AkunSeeder;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class SewaMobilTest extends TestCase
{
    use RefreshDatabase;

    public function test_finance_dapat_membuat_draft_vendor_based_dan_draft_tidak_posting(): void
    {
        $finance = $this->user('admin');
        $karyawan = Karyawan::factory()->create(['nama' => 'Karyawan Sewa Mobil Aktif']);

        $this->actingAs($finance)
            ->post(route('sewa-mobil.finance.store'), $this->payload($karyawan, [
                'tanggal_mulai' => '2026-08-10',
                'tanggal_selesai' => '2026-08-12',
            ]))
            ->assertRedirect(route('sewa-mobil.finance.index'));

        $sewa = SewaMobil::query()->firstOrFail();

        $this->assertNull($sewa->aset_koperasi_id);
        $this->assertSame(SewaMobil::STATUS_DRAFT, $sewa->status);
        $this->assertSame(3, $sewa->jumlah_hari);
        $this->assertSame(1200000, $sewa->total_harga_vendor);
        $this->assertSame(225000, $sewa->total_markup);
        $this->assertSame(1425000, $sewa->total_tagihan_perusahaan);
        $this->assertSame(1425000, $sewa->total_sewa);
        $this->assertSame('B1234KBS', $sewa->plat_nomor_normalized);
        $this->assertSame(0, MutasiKas::query()->count());
        $this->assertSame(0, DB::table('jurnal_umum')->count());
    }

    public function test_authorization_route_master_mobil_dan_route_hard_delete(): void
    {
        $finance = $this->user('admin');
        $kasir = $this->user('kasir');
        $employee = $this->employeeUser('employee-sewa-mobil@kbsm.test');
        $active = Karyawan::factory()->create(['nama' => 'Aktif Untuk Sewa Mobil']);
        Karyawan::factory()->create([
            'nama' => 'Berhenti Tidak Muncul',
            'status_kerja' => Karyawan::STATUS_BERHENTI,
            'tanggal_berhenti' => '2026-07-01',
        ]);

        $this->assertFalse(Route::has('aset-mobil.index'));
        $this->assertFalse(Route::has('sewa-mobil.finance.destroy'));
        $this->assertFalse(Route::has('sewa-mobil.karyawan.index'));

        $this->get(route('sewa-mobil.finance.index'))->assertRedirect(route('login'));
        $this->actingAs($kasir)->get(route('sewa-mobil.finance.index'))->assertForbidden();
        $this->actingAs($kasir)->get(route('sewa-mobil.finance.create'))->assertForbidden();
        $this->actingAs($employee)->get(route('sewa-mobil.finance.index'))->assertForbidden();
        $this->actingAs($employee)->get(route('sewa-mobil.finance.create'))->assertForbidden();
        $this->actingAs($employee)->get('/pengajuan-sewa-mobil')->assertNotFound();

        $this->actingAs($finance)
            ->get(route('sewa-mobil.finance.index'))
            ->assertOk()
            ->assertSee('Filter Sewa Mobil')
            ->assertSee('Daftar Sewa Mobil')
            ->assertSee('+ TAMBAH SEWA MOBIL')
            ->assertDontSee('name="aset_koperasi_id"', false);

        $draft = app(SewaMobilService::class)->createDraft($this->payload($active), $finance->id);

        $this->actingAs($finance)
            ->get(route('sewa-mobil.finance.create'))
            ->assertOk()
            ->assertSee('Tambah Sewa Mobil')
            ->assertSee('Aktif Untuk Sewa Mobil')
            ->assertDontSee('Berhenti Tidak Muncul')
            ->assertDontSee('name="aset_koperasi_id"', false);

        $this->actingAs($finance)
            ->get(route('sewa-mobil.finance.edit', $draft))
            ->assertOk()
            ->assertSee('Edit Draft Sewa Mobil')
            ->assertSee('data-sewa-mobil-form', false)
            ->assertDontSee('name="aset_koperasi_id"', false);
    }

    public function test_filter_index_memakai_karyawan_status_vendor_plat_overlap_tanggal_dan_pagination_query(): void
    {
        $finance = $this->user('admin');
        $service = app(SewaMobilService::class);
        $karyawanA = Karyawan::factory()->create(['nama' => 'Pemohon Filter A']);
        $karyawanB = Karyawan::factory()->create(['nama' => 'Pemohon Filter B']);

        $service->createDraft($this->payload($karyawanA, [
            'nama_kegiatan' => 'Vendor Overlap Masuk',
            'tanggal_mulai' => '2026-08-01',
            'tanggal_selesai' => '2026-08-03',
            'vendor_nama' => 'CV Armada Hijau',
            'plat_nomor_snapshot' => 'B 2222 KBS',
        ]), $finance->id);
        $service->createDraft($this->payload($karyawanB, [
            'nama_kegiatan' => 'Vendor Di Luar Range',
            'tanggal_mulai' => '2026-08-20',
            'tanggal_selesai' => '2026-08-22',
            'vendor_nama' => 'CV Armada Biru',
            'plat_nomor_snapshot' => 'B 3333 KBS',
        ]), $finance->id);

        $this->actingAs($finance)
            ->get(route('sewa-mobil.finance.index', [
                'tanggal_dari' => '2026-08-02',
                'tanggal_sampai' => '2026-08-05',
                'vendor' => 'Hijau',
                'plat_nomor' => 'B 2222 KBS',
            ]))
            ->assertOk()
            ->assertSee('Vendor Overlap Masuk')
            ->assertDontSee('Vendor Di Luar Range');

        $this->actingAs($finance)
            ->get(route('sewa-mobil.finance.index', [
                'karyawan_id' => $karyawanB->id,
            ]))
            ->assertOk()
            ->assertSee('Vendor Di Luar Range')
            ->assertDontSee('Vendor Overlap Masuk');

        $this->actingAs($finance)
            ->get(route('sewa-mobil.finance.index', [
                'tanggal_dari' => '2026-08-10',
                'tanggal_sampai' => '2026-08-01',
            ]))
            ->assertSessionHasErrors('tanggal_sampai');

        for ($i = 1; $i <= 11; $i++) {
            $service->createDraft($this->payload($karyawanA, [
                'nama_kegiatan' => 'Mobil Pagination '.$i,
                'lokasi_kegiatan' => 'Lokasi '.$i,
                'tanggal_mulai' => '2026-09-01',
                'tanggal_selesai' => '2026-09-02',
                'plat_nomor_snapshot' => 'B 99'.str_pad((string) $i, 2, '0', STR_PAD_LEFT).' KBS',
            ]), $finance->id);
        }

        $this->actingAs($finance)
            ->get(route('sewa-mobil.finance.index', [
                'karyawan_id' => $karyawanA->id,
                'tanggal_dari' => '2026-09-01',
                'tanggal_sampai' => '2026-09-30',
            ]))
            ->assertOk()
            ->assertSee('karyawan_id='.$karyawanA->id, false)
            ->assertSee('tanggal_dari=2026-09-01', false)
            ->assertSee('tanggal_sampai=2026-09-30', false);
    }

    public function test_kalkulasi_tanggal_dan_total_selalu_server_side(): void
    {
        $service = app(SewaMobilService::class);
        $finance = $this->user('admin');
        $karyawan = Karyawan::factory()->create();

        $sameDay = $service->createDraft($this->payload($karyawan, [
            'tanggal_mulai' => '2026-08-10',
            'tanggal_selesai' => '2026-08-10',
            'jumlah_hari' => 999,
            'total_harga_vendor' => 1200000,
            'total_markup' => 225000,
            'total_tagihan_perusahaan' => 1,
            'total_sewa' => 1,
        ]), $finance->id);

        $this->assertSame(1, $sameDay->jumlah_hari);
        $this->assertSame(1425000, $sameDay->total_tagihan_perusahaan);

        $threeDays = $service->createDraft($this->payload($karyawan, [
            'tanggal_mulai' => '2026-08-10',
            'tanggal_selesai' => '2026-08-12',
            'total_harga_vendor' => 1200000,
            'total_markup' => 225000,
        ]), $finance->id);

        $this->assertSame(3, $threeDays->jumlah_hari);
        $this->assertSame(1200000, $threeDays->total_harga_vendor);
        $this->assertSame(225000, $threeDays->total_markup);
        $this->assertSame(1425000, $threeDays->total_tagihan_perusahaan);
        $this->assertSame(1425000, $threeDays->total_sewa);

        $inactive = Karyawan::factory()->create([
            'status_kerja' => Karyawan::STATUS_BERHENTI,
            'tanggal_berhenti' => '2026-07-01',
        ]);
        $this->expectValidation(fn () => $service->createDraft($this->payload($inactive), $finance->id));
    }

    public function test_approval_membutuhkan_pengurus_aktif_snapshot_lengkap_dan_menolak_overlap_plat(): void
    {
        $service = app(SewaMobilService::class);
        $finance = $this->user('admin');
        $pengurus = $this->pengurus();
        $karyawan = Karyawan::factory()->create();

        $tanpaPlat = $service->submit($service->createDraft($this->payload($karyawan, [
            'plat_nomor_snapshot' => null,
        ]), $finance->id), $finance->id);

        $this->expectValidation(fn () => $service->approve($tanpaPlat, ['pengurus_penyetuju_id' => $pengurus->id], $finance->id));

        $inactivePengurus = PengurusKoperasi::query()->create([
            'anggota_id' => Anggota::factory()->create(['status' => Anggota::STATUS_AKTIF])->id,
            'jabatan' => 'Sekretaris',
            'status' => PengurusKoperasi::STATUS_NONAKTIF,
        ]);

        $candidate = $service->submit($service->createDraft($this->payload($karyawan, [
            'plat_nomor_snapshot' => 'B 4444 KBS',
        ]), $finance->id), $finance->id);
        $this->expectValidation(fn () => $service->approve($candidate, ['pengurus_penyetuju_id' => $inactivePengurus->id], $finance->id));

        $first = $service->submit($service->createDraft($this->payload($karyawan, [
            'tanggal_mulai' => '2026-08-10',
            'tanggal_selesai' => '2026-08-12',
            'plat_nomor_snapshot' => 'B 5555 KBS',
        ]), $finance->id), $finance->id);
        $service->approve($first, ['pengurus_penyetuju_id' => $pengurus->id], $finance->id);

        $overlap = $service->submit($service->createDraft($this->payload(Karyawan::factory()->create(), [
            'tanggal_mulai' => '2026-08-12',
            'tanggal_selesai' => '2026-08-14',
            'plat_nomor_snapshot' => 'B-5555-KBS',
        ]), $finance->id), $finance->id);

        $this->expectValidation(fn () => $service->approve($overlap, ['pengurus_penyetuju_id' => $pengurus->id], $finance->id));
    }

    public function test_vendor_dibayar_dari_kas_operasional_sebelum_invoice_perusahaan(): void
    {
        $service = app(SewaMobilService::class);
        $finance = $this->user('admin');
        $sewa = $this->approvedSewa($service, $finance);
        $kasVendor = $this->dompet(DompetKoperasi::JENIS_KAS, 2000000);
        $this->expectValidation(fn () => $service->pay($sewa, [], $finance->id));

        $payment = app(B2BRentalService::class)->payVendor($sewa, [
            'dompet_id' => $kasVendor->id,
            'tanggal_bayar' => '2026-08-09',
            'idempotency_key' => 'test-sewa-mobil-vendor-first',
        ], $finance->id);

        $this->assertSame(SewaMobil::PEMBAYARAN_BELUM_BAYAR, $sewa->fresh()->status_pembayaran);
        $this->assertSame('800000.00', $kasVendor->fresh()->saldo);
        $this->assertSame(1, MutasiKas::query()->where('referensi_tipe', PembayaranVendorSewa::class)->where('referensi_id', $payment->id)->where('tipe', 'keluar')->count());
        $this->assertSame(1, $payment->jurnal()->count());
        $this->assertSame(0, PemakaianPotongGaji::query()->whereIn('source_type', [SewaMobil::class, PembayaranVendorSewa::class])->count());
        $this->assertSewaMobilJournalsBalanced();
    }

    public function test_start_complete_tidak_mengubah_aset_dan_completion_hanya_mengakui_margin(): void
    {
        $service = app(SewaMobilService::class);
        $finance = $this->user('admin');
        $asset = AsetKoperasi::query()->create([
            'kode_aset' => 'MBL-LEGACY-UNIT',
            'jenis_aset' => AsetKoperasi::JENIS_MOBIL,
            'merek' => 'Legacy',
            'model' => 'Asset',
            'status' => AsetKoperasi::STATUS_TERSEDIA,
            'created_by' => $finance->id,
        ]);
        $kas = $this->dompet(DompetKoperasi::JENIS_KAS, 2000000);
        $sewa = $this->paidSewa($service, $finance, $kas);

        $running = $service->start($sewa, $finance->id);
        $this->assertSame(SewaMobil::STATUS_BERJALAN, $running->status);
        $this->assertSame(AsetKoperasi::STATUS_TERSEDIA, $asset->fresh()->status);

        $completed = $service->complete($running, $finance->id);
        $completedAgain = $service->complete($completed, $finance->id);

        $this->assertSame(SewaMobil::STATUS_SELESAI, $completedAgain->status);
        $this->assertSame(AsetKoperasi::STATUS_TERSEDIA, $asset->fresh()->status);
        $this->assertSame(1, DB::table('jurnal_umum')->where('idempotency_key', 'like', 'b2b:margin:jurnal:%')->count());
        $this->assertDatabaseHas('jurnal_umum_detail', [
            'akun_kode' => '206',
            'debit' => '225000.00',
        ]);
        $this->assertDatabaseHas('jurnal_umum_detail', [
            'akun_kode' => '404',
            'kredit' => '225000.00',
        ]);
        $this->assertDatabaseMissing('jurnal_umum_detail', [
            'akun_kode' => '404',
            'kredit' => '1425000.00',
        ]);
        $this->assertSewaMobilJournalsBalanced();
    }

    public function test_pembayaran_vendor_final_menolak_cancel_otomatis_sebelum_dan_setelah_berjalan(): void
    {
        $service = app(SewaMobilService::class);
        $finance = $this->user('admin');
        $kas = $this->dompet(DompetKoperasi::JENIS_KAS, 2000000);
        $sewa = $this->paidSewa($service, $finance, $kas);

        $this->expectValidation(fn () => $service->cancelByFinance($sewa, 'Batal sebelum kegiatan', $finance->id));
        $this->assertSame('800000.00', $kas->fresh()->saldo);
        $this->assertSame(0, DB::table('reversal_transaksi')->where('jenis_reversal', 'sewa_mobil_refund')->count());
        $this->assertSewaMobilJournalsBalanced();

        $running = $this->paidSewa($service, $finance, $this->dompet(DompetKoperasi::JENIS_KAS, 2000000));
        $running = $service->start($running, $finance->id);
        $this->expectValidation(fn () => $service->cancelByFinance($running, 'Tidak boleh', $finance->id));
    }

    public function test_snapshot_histori_tidak_berubah_saat_referensi_lain_berubah(): void
    {
        $service = app(SewaMobilService::class);
        $finance = $this->user('admin');
        $karyawan = Karyawan::factory()->create(['nama' => 'Nama Awal']);

        $sewa = $service->createDraft($this->payload($karyawan, [
            'vendor_nama' => 'CV Snapshot Awal',
            'merek_kendaraan' => 'Toyota',
            'model_kendaraan' => 'HiAce',
        ]), $finance->id);

        $karyawan->update(['nama' => 'Nama Berubah']);
        AsetKoperasi::query()->create([
            'kode_aset' => 'MBL-SNAPSHOT',
            'jenis_aset' => AsetKoperasi::JENIS_MOBIL,
            'merek' => 'Merek Master Berubah',
            'model' => 'Model Master Berubah',
            'status' => AsetKoperasi::STATUS_TERSEDIA,
            'created_by' => $finance->id,
        ]);

        $fresh = $sewa->fresh();
        $this->assertSame('CV Snapshot Awal', $fresh->vendor_nama);
        $this->assertSame('Toyota', $fresh->merek_kendaraan);
        $this->assertSame('HiAce', $fresh->model_kendaraan);
        $this->assertNull($fresh->aset_koperasi_id);
    }

    public function test_preflight_sewa_mobil_mendeteksi_data_vendor_based_tidak_valid(): void
    {
        $this->artisan('koperasi:preflight-sewa-mobil')->assertExitCode(0);

        DB::table('sewa_mobil')->insert([
            'kode_sewa' => 'SWM-BROKEN-1',
            'aset_koperasi_id' => null,
            'model_sumber' => 'vendor',
            'karyawan_id' => Karyawan::factory()->create()->id,
            'nama_perusahaan_snapshot' => 'Bita Enarcon Engineering',
            'nama_kegiatan' => 'Broken',
            'lokasi_kegiatan' => 'Broken',
            'vendor_nama' => '',
            'vendor_kontak' => '',
            'vendor_alamat' => '',
            'jenis_kendaraan' => '',
            'merek_kendaraan' => '',
            'model_kendaraan' => '',
            'plat_nomor_snapshot' => null,
            'plat_nomor_normalized' => null,
            'tahun_kendaraan' => null,
            'warna_kendaraan' => '',
            'tanggal_mulai' => '2026-08-01',
            'tanggal_selesai' => '2026-08-03',
            'jumlah_hari' => 1,
            'tarif_harian_snapshot' => 0,
            'total_sewa' => 300000,
            'total_harga_vendor' => 1200000,
            'total_markup' => 225000,
            'total_tagihan_perusahaan' => 1425000,
            'status' => SewaMobil::STATUS_DISETUJUI,
            'status_pembayaran' => SewaMobil::PEMBAYARAN_BELUM_BAYAR,
            'idempotency_key' => 'broken-sewa-mobil',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->artisan('koperasi:preflight-sewa-mobil')->assertExitCode(1);
    }

    public function test_seeder_menghasilkan_contoh_sewa_mobil_vendor_based_valid(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertSame(0, DB::table('aset_mobil')->count());
        $this->assertDatabaseHas('sewa_mobil', ['status' => SewaMobil::STATUS_DISETUJUI]);
        $this->assertSame(1, PembayaranVendorSewa::query()->where('sewa_type', SewaMobil::class)->count());
        $this->assertSame(0, SewaMobil::query()->whereNotNull('aset_koperasi_id')->count());
        $this->assertSame(0, PemakaianPotongGaji::query()->whereIn('source_type', [SewaMobil::class, PembayaranSewaMobil::class])->count());
        $this->assertSame(0, DB::table('jurnal_umum as j')
            ->join('jurnal_umum_detail as d', 'd.jurnal_umum_id', '=', 'j.id')
            ->where('j.idempotency_key', 'like', 'sewa-mobil:%')
            ->groupBy('j.id')
            ->havingRaw('ABS(SUM(d.debit) - SUM(d.kredit)) > 0.01')
            ->get()
            ->count());
        $this->artisan('koperasi:preflight-sewa-mobil')->assertExitCode(0);
    }

    private function approvedSewa(SewaMobilService $service, User $finance): SewaMobil
    {
        $karyawan = Karyawan::factory()->create();
        $pengurus = $this->pengurus();
        $sewa = $service->submit($service->createDraft($this->payload($karyawan, [
            'plat_nomor_snapshot' => fake()->unique()->numerify('B #### KBS'),
        ]), $finance->id), $finance->id);

        return $service->approve($sewa, [
            'pengurus_penyetuju_id' => $pengurus->id,
        ], $finance->id);
    }

    private function paidSewa(SewaMobilService $service, User $finance, DompetKoperasi $dompet): SewaMobil
    {
        $sewa = $this->approvedSewa($service, $finance);

        app(B2BRentalService::class)->payVendor($sewa, [
            'dompet_id' => $dompet->id,
            'tanggal_bayar' => '2026-08-09',
        ], $finance->id);

        app(B2BRentalService::class)->createInvoice($sewa->perusahaan, [
            'sewa_mobil_ids' => [$sewa->id],
            'tanggal_invoice' => '2026-08-10',
            'jatuh_tempo' => '2026-08-24',
        ], $finance->id);

        return $sewa->fresh(['pembayaranVendor', 'invoiceDetail.invoice']);
    }

    private function payload(Karyawan $karyawan, array $overrides = []): array
    {
        return array_merge([
            'karyawan_id' => $karyawan->id,
            'nama_kegiatan' => 'Kunjungan Proyek',
            'lokasi_kegiatan' => 'Karawang',
            'tanggal_mulai' => '2026-08-10',
            'tanggal_selesai' => '2026-08-12',
            'vendor_nama' => 'CV Rental Mobil Nusantara',
            'vendor_kontak' => '0812-1234-5678',
            'vendor_alamat' => 'Jl. Vendor No. 1, Jakarta',
            'jenis_kendaraan' => 'MPV',
            'merek_kendaraan' => 'Toyota',
            'model_kendaraan' => 'Innova Reborn',
            'plat_nomor_snapshot' => 'B 1234 KBS',
            'tahun_kendaraan' => 2022,
            'warna_kendaraan' => 'Hitam',
            'keterangan_kendaraan' => 'Mobil vendor dengan sopir',
            'total_harga_vendor' => 1200000,
            'total_markup' => 225000,
            'keterangan' => 'Unit test sewa mobil vendor-based',
        ], $overrides);
    }

    private function paymentPayload(
        SewaMobil $sewa,
        DompetKoperasi $dompetPenerimaan,
        DompetKoperasi $dompetVendor,
        string $metodePenerimaan,
        string $metodeVendor
    ): array {
        return [
            'metode_penerimaan' => $metodePenerimaan,
            'dompet_penerimaan_id' => $dompetPenerimaan->id,
            'jumlah_diterima' => $sewa->total_tagihan_perusahaan,
            'metode_pembayaran_vendor' => $metodeVendor,
            'dompet_vendor_id' => $dompetVendor->id,
            'jumlah_bayar_vendor' => $sewa->total_harga_vendor,
            'paid_at' => '2026-08-09 10:00:00',
        ];
    }

    private function employeeUser(string $email): User
    {
        $karyawan = Karyawan::factory()->create(['email' => $email]);

        return User::factory()->create([
            'name' => $karyawan->nama,
            'email' => 'user-'.$email,
            'role' => 'karyawan',
            'karyawan_id' => $karyawan->id,
            'is_active' => true,
            'must_change_password' => false,
        ]);
    }

    private function pengurus(): PengurusKoperasi
    {
        $existing = PengurusKoperasi::query()->aktif()->with('anggota.karyawan')->first();
        if ($existing) {
            return $existing;
        }

        $karyawan = Karyawan::factory()->create();
        $anggota = Anggota::factory()->create(['karyawan_id' => $karyawan->id, 'status' => Anggota::STATUS_AKTIF]);

        return PengurusKoperasi::query()->create([
            'anggota_id' => $anggota->id,
            'jabatan' => 'Ketua Pengurus',
            'status' => PengurusKoperasi::STATUS_AKTIF,
        ]);
    }

    private function dompet(string $jenis, int $saldo = 0): DompetKoperasi
    {
        $this->seed(AkunSeeder::class);
        $accountKey = $jenis === DompetKoperasi::JENIS_BANK ? 'bank' : 'kas';
        $akun = Akun::query()->where('kode_akun', config("account_map.accounts.{$accountKey}.kode_akun"))->firstOrFail();

        return DompetKoperasi::query()->create([
            'akun_id' => $akun->id,
            'nama_dompet' => $jenis === DompetKoperasi::JENIS_BANK ? 'Bank Test '.fake()->unique()->numberBetween(1, 9999) : 'Kas Test '.fake()->unique()->numberBetween(1, 9999),
            'jenis_dompet' => $jenis,
            'saldo' => $saldo,
            'is_default_penerimaan_payroll' => false,
            'is_kas_operasional' => $jenis === DompetKoperasi::JENIS_KAS,
        ]);
    }

    private function user(string $role): User
    {
        return User::factory()->create([
            'role' => $role,
            'is_active' => true,
            'must_change_password' => false,
        ]);
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

    private function assertSewaMobilJournalsBalanced(): void
    {
        $unbalanced = DB::table('jurnal_umum as j')
            ->join('jurnal_umum_detail as d', 'd.jurnal_umum_id', '=', 'j.id')
            ->where('j.idempotency_key', 'like', 'sewa-mobil:%')
            ->groupBy('j.id')
            ->havingRaw('ABS(SUM(d.debit) - SUM(d.kredit)) > 0.01')
            ->get()
            ->count();

        $this->assertSame(0, $unbalanced);
    }
}
