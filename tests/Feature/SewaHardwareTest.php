<?php

namespace Tests\Feature;

use App\Models\Akun;
use App\Models\DompetKoperasi;
use App\Models\Karyawan;
use App\Models\MutasiKas;
use App\Models\PembayaranSewaHardware;
use App\Models\PemakaianPotongGaji;
use App\Models\SewaHardware;
use App\Models\User;
use App\Services\SewaHardwareService;
use Database\Seeders\AkunSeeder;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class SewaHardwareTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'features.master_printer_enabled' => false,
            'features.jasa_print_enabled' => false,
        ]);
    }

    public function test_vendor_snapshot_detail_dinamis_margin_half_up_total_dan_kode(): void
    {
        $service = app(SewaHardwareService::class);
        $finance = $this->user('admin');
        $pic = Karyawan::factory()->create();

        $sewa = $service->createDraft($this->payload($pic, [
            [
                'jenis_hardware' => 'printer',
                'nama_model_hardware' => 'Epson EcoTank L3210',
                'spesifikasi_kebutuhan' => 'Dua unit printer warna',
                'kuantitas' => 2,
                'harga_vendor_per_unit' => 1000000,
            ],
            [
                'jenis_hardware' => 'laptop',
                'nama_model_hardware' => 'Lenovo Mini',
                'spesifikasi_kebutuhan' => 'Uji pembulatan margin',
                'kuantitas' => 1,
                'harga_vendor_per_unit' => 333,
            ],
        ], [
            'vendor_nama' => 'Vendor Snapshot Test',
            'vendor_kontak' => '0812-3333-4444',
            'vendor_alamat' => 'Jl. Vendor Snapshot',
        ]), $finance->id);

        $this->assertMatchesRegularExpression('/^SWH-\d{6}-\d{6}$/', $sewa->kode_sewa);
        $this->assertSame(2000333, $sewa->total_harga_vendor);
        $this->assertSame(300050, $sewa->total_margin);
        $this->assertSame(2300383, $sewa->total_tagihan_perusahaan);
        $this->assertDatabaseHas('sewa_hardware_detail', [
            'sewa_hardware_id' => $sewa->id,
            'jenis_hardware' => 'laptop',
            'nama_model_hardware' => 'Lenovo Mini',
            'kuantitas' => 1,
            'harga_vendor_per_unit' => 333,
            'margin_per_unit' => 50,
            'harga_tagihan_per_unit' => 383,
            'subtotal_tagihan' => 383,
        ]);

        $confirmed = $service->confirm($sewa, $finance->id);

        $this->assertSame(SewaHardware::STATUS_DIKONFIRMASI, $confirmed->status);
        $this->assertSame('Vendor Snapshot Test', $confirmed->vendor_nama);
        $this->assertSame($pic->id, $confirmed->karyawan_id);
    }

    public function test_finance_only_master_printer_hidden_route_lama_404_command_lama_hilang_dan_form_awal_satu_row(): void
    {
        $finance = $this->user('admin');
        $kasir = $this->user('kasir');
        $karyawanUser = User::factory()->create([
            'role' => 'karyawan',
            'is_active' => true,
            'must_change_password' => false,
        ]);
        Karyawan::factory()->create(['nama' => 'Pemohon Hardware Aktif']);

        $this->get(route('sewa-hardware.index'))->assertRedirect(route('login'));
        $this->actingAs($kasir)->get(route('sewa-hardware.index'))->assertForbidden();
        $this->actingAs($kasir)->get(route('sewa-hardware.create'))->assertForbidden();
        $this->actingAs($karyawanUser)->get(route('sewa-hardware.index'))->assertForbidden();
        $this->actingAs($karyawanUser)->get(route('sewa-hardware.create'))->assertForbidden();

        $indexResponse = $this->actingAs($finance)->get(route('sewa-hardware.index'));
        $indexResponse->assertOk()
            ->assertSee('Filter Sewa Hardware')
            ->assertSee('Daftar Sewa Hardware')
            ->assertSee('+ Buat Sewa Hardware')
            ->assertSee('href="' . route('sewa-hardware.create') . '"', false)
            ->assertDontSee('data-sewa-hardware-form', false);

        $response = $this->actingAs($finance)->get(route('sewa-hardware.create'));
        $response->assertOk()
            ->assertSee('Tambah Sewa Hardware')
            ->assertSee('Kembali ke Daftar Sewa Hardware')
            ->assertSee('Pemohon Hardware Aktif')
            ->assertSee('Vendor')
            ->assertSee('Tambah Hardware')
            ->assertSee('data-sewa-hardware-form', false);

        $content = $response->getContent();
        $this->assertSame(1, substr_count($content, 'name="details[0][jenis_hardware]"'));
        $this->assertSame(1, substr_count($content, 'name="details[0][nama_model_hardware]"'));
        $this->assertStringNotContainsString('name="details[1][nama_model_hardware]"', $content);

        $draft = app(SewaHardwareService::class)->createDraft($this->payload(Karyawan::factory()->create(['nama' => 'Pemohon Edit Hardware'])), $finance->id);
        $this->actingAs($finance)
            ->get(route('sewa-hardware.edit', $draft))
            ->assertOk()
            ->assertSee('Edit Draft Sewa Hardware')
            ->assertSee('Pemohon Edit Hardware')
            ->assertSee('data-sewa-hardware-form', false);

        $this->actingAs($finance)->get('/sewa-printer')->assertNotFound();
        $this->actingAs($finance)->get('/aset-printer')->assertNotFound();
        $this->assertArrayNotHasKey('koperasi:preflight-sewa-printer', Artisan::all());
        $this->actingAs($finance)
            ->get(route('pages.dashboard'))
            ->assertOk()
            ->assertDontSee('Printer Koperasi')
            ->assertDontSee('Jasa Print');
    }

    public function test_filter_sewa_hardware_memakai_status_karyawan_overlap_tanggal_dan_pagination_query(): void
    {
        $finance = $this->user('admin');
        $service = app(SewaHardwareService::class);
        $karyawanA = Karyawan::factory()->create(['nama' => 'Pemohon Hardware Filter A']);
        $karyawanB = Karyawan::factory()->create(['nama' => 'Pemohon Hardware Filter B']);

        $draft = $service->createDraft($this->payload($karyawanA, null, [
            'kebutuhan' => 'Hardware Overlap Masuk',
            'mulai_tanggal' => '2026-08-01',
            'selesai_tanggal' => '2026-08-03',
        ]), $finance->id);
        $confirmed = $service->confirm($service->createDraft($this->payload($karyawanB, null, [
            'kebutuhan' => 'Hardware Di Luar Status',
            'mulai_tanggal' => '2026-08-20',
            'selesai_tanggal' => '2026-08-22',
        ]), $finance->id), $finance->id);

        $this->actingAs($finance)
            ->get(route('sewa-hardware.index', [
                'status' => SewaHardware::STATUS_DRAFT,
                'tanggal_dari' => '2026-08-02',
                'tanggal_sampai' => '2026-08-05',
            ]))
            ->assertOk()
            ->assertSee($draft->kode_sewa)
            ->assertSee('Hardware Overlap Masuk')
            ->assertDontSee($confirmed->kode_sewa)
            ->assertDontSee('Hardware Di Luar Status');

        $this->actingAs($finance)
            ->get(route('sewa-hardware.index', [
                'status' => SewaHardware::STATUS_DIKONFIRMASI,
                'karyawan_id' => $karyawanB->id,
            ]))
            ->assertOk()
            ->assertSee($confirmed->kode_sewa)
            ->assertDontSee($draft->kode_sewa);

        $this->actingAs($finance)
            ->get(route('sewa-hardware.index', [
                'tanggal_dari' => '2026-08-10',
                'tanggal_sampai' => '2026-08-01',
            ]))
            ->assertSessionHasErrors('tanggal_sampai');

        for ($i = 1; $i <= 11; $i++) {
            $service->createDraft($this->payload($karyawanA, null, [
                'kebutuhan' => 'Hardware Pagination ' . $i,
                'mulai_tanggal' => '2026-09-01',
                'selesai_tanggal' => '2026-09-02',
            ]), $finance->id);
        }

        $this->actingAs($finance)
            ->get(route('sewa-hardware.index', [
                'status' => SewaHardware::STATUS_DRAFT,
                'karyawan_id' => $karyawanA->id,
                'tanggal_dari' => '2026-09-01',
                'tanggal_sampai' => '2026-09-30',
            ]))
            ->assertOk()
            ->assertSee('status=' . SewaHardware::STATUS_DRAFT, false)
            ->assertSee('karyawan_id=' . $karyawanA->id, false)
            ->assertSee('tanggal_dari=2026-09-01', false)
            ->assertSee('tanggal_sampai=2026-09-30', false);
    }

    public function test_validasi_tidak_membutuhkan_aset_printer_dan_menolak_karyawan_nonaktif(): void
    {
        $service = app(SewaHardwareService::class);
        $finance = $this->user('admin');
        $active = Karyawan::factory()->create();
        $inactive = Karyawan::factory()->create([
            'status_kerja' => Karyawan::STATUS_BERHENTI,
            'tanggal_berhenti' => '2026-07-01',
        ]);

        $this->expectValidation(fn () => $service->createDraft($this->payload($active, []), $finance->id));
        $this->expectValidation(fn () => $service->createDraft($this->payload($inactive), $finance->id));
        $this->expectValidation(fn () => $service->createDraft($this->payload($active, [[
            'jenis_hardware' => 'printer',
            'nama_model_hardware' => 'HP',
            'kuantitas' => 1,
            'harga_vendor_per_unit' => 100000,
        ]], [
            'selesai_tanggal' => '2026-07-31',
        ]), $finance->id));

        $sewa = $service->createDraft($this->payload($active), $finance->id);

        $this->assertDatabaseHas('sewa_hardware', ['id' => $sewa->id, 'karyawan_id' => $active->id]);
        $this->assertDatabaseCount('aset_printer', 0);
    }

    public function test_pelunasan_dua_dompet_membuat_mutasi_masuk_keluar_jurnal_split_dan_tanpa_ledger_payroll(): void
    {
        $service = app(SewaHardwareService::class);
        $finance = $this->user('admin');
        $sewa = $this->confirmedSewa($service, $finance);
        $kas = $this->dompet(DompetKoperasi::JENIS_KAS, 2000000);
        $bank = $this->dompet(DompetKoperasi::JENIS_BANK, 2000000);

        $this->expectValidation(fn () => $service->pay($sewa, [
            'metode_penerimaan' => PembayaranSewaHardware::METODE_TUNAI,
            'dompet_penerimaan_id' => $bank->id,
            'metode_pembayaran_vendor' => PembayaranSewaHardware::METODE_TUNAI,
            'dompet_vendor_id' => $kas->id,
            'jumlah_diterima' => $sewa->total_tagihan_perusahaan,
            'jumlah_bayar_vendor' => $sewa->total_harga_vendor,
            'paid_at' => '2026-07-31 08:00',
        ], $finance->id));

        $this->expectValidation(fn () => $service->pay($sewa, [
            'metode_penerimaan' => PembayaranSewaHardware::METODE_TUNAI,
            'dompet_penerimaan_id' => $kas->id,
            'metode_pembayaran_vendor' => PembayaranSewaHardware::METODE_TUNAI,
            'dompet_vendor_id' => $kas->id,
            'jumlah_diterima' => $sewa->total_tagihan_perusahaan - 1,
            'jumlah_bayar_vendor' => $sewa->total_harga_vendor,
            'paid_at' => '2026-07-31 08:00',
        ], $finance->id));

        $paid = $service->pay($sewa, [
            'metode_penerimaan' => PembayaranSewaHardware::METODE_TUNAI,
            'dompet_penerimaan_id' => $kas->id,
            'metode_pembayaran_vendor' => PembayaranSewaHardware::METODE_TRANSFER_BANK,
            'dompet_vendor_id' => $bank->id,
            'jumlah_diterima' => $sewa->total_tagihan_perusahaan,
            'jumlah_bayar_vendor' => $sewa->total_harga_vendor,
            'paid_at' => '2026-07-31 08:00',
        ], $finance->id);

        $this->assertSame('3150000.00', $kas->fresh()->saldo);
        $this->assertSame('1000000.00', $bank->fresh()->saldo);
        $this->assertSame(SewaHardware::PEMBAYARAN_PAID, $paid->status_pembayaran);
        $this->assertSame(1, MutasiKas::query()->where('referensi_tipe', PembayaranSewaHardware::class)->where('tipe', 'masuk')->count());
        $this->assertSame(1, MutasiKas::query()->where('referensi_tipe', PembayaranSewaHardware::class)->where('tipe', 'keluar')->count());
        $this->assertDatabaseHas('jurnal_umum_detail', ['akun_kode' => '101', 'debit' => '1150000.00']);
        $this->assertDatabaseHas('jurnal_umum_detail', ['akun_kode' => '208', 'kredit' => '1000000.00']);
        $this->assertDatabaseHas('jurnal_umum_detail', ['akun_kode' => '207', 'kredit' => '150000.00']);
        $this->assertDatabaseHas('jurnal_umum_detail', ['akun_kode' => '208', 'debit' => '1000000.00']);
        $this->assertDatabaseHas('jurnal_umum_detail', ['akun_kode' => '102', 'kredit' => '1000000.00']);
        $this->assertDatabaseMissing('jurnal_umum_detail', ['akun_kode' => '405', 'kredit' => '1000000.00']);
        $this->assertSame(0, PemakaianPotongGaji::query()->count());

        $this->expectValidation(fn () => $service->pay($paid->fresh(), [
            'metode_penerimaan' => PembayaranSewaHardware::METODE_TUNAI,
            'dompet_penerimaan_id' => $kas->id,
            'metode_pembayaran_vendor' => PembayaranSewaHardware::METODE_TRANSFER_BANK,
            'dompet_vendor_id' => $bank->id,
            'jumlah_diterima' => $sewa->total_tagihan_perusahaan,
            'jumlah_bayar_vendor' => $sewa->total_harga_vendor,
        ], $finance->id));
        $this->assertSame(2, MutasiKas::query()->where('referensi_tipe', PembayaranSewaHardware::class)->count());
    }

    public function test_lifecycle_start_complete_hanya_margin_menjadi_pendapatan_dan_idempotent(): void
    {
        $service = app(SewaHardwareService::class);
        $finance = $this->user('admin');
        $sewa = $this->confirmedSewa($service, $finance);
        $kas = $this->dompet(DompetKoperasi::JENIS_KAS, 2000000);

        $this->expectValidation(fn () => $service->start($sewa, $finance->id));

        $paid = $service->pay($sewa, [
            'metode_penerimaan' => PembayaranSewaHardware::METODE_TUNAI,
            'dompet_penerimaan_id' => $kas->id,
            'metode_pembayaran_vendor' => PembayaranSewaHardware::METODE_TUNAI,
            'dompet_vendor_id' => $kas->id,
            'jumlah_diterima' => $sewa->total_tagihan_perusahaan,
            'jumlah_bayar_vendor' => $sewa->total_harga_vendor,
            'paid_at' => '2026-07-31 08:00',
        ], $finance->id);

        $running = $service->start($paid, $finance->id);
        $this->assertSame(SewaHardware::STATUS_BERJALAN, $running->status);

        $completed = $service->complete($running, $finance->id);
        $completedAgain = $service->complete($completed, $finance->id);

        $this->assertSame(SewaHardware::STATUS_SELESAI, $completedAgain->status);
        $this->assertSame(1, DB::table('jurnal_umum')->where('idempotency_key', 'like', 'sewa-hardware:pengakuan-pendapatan:jurnal:%')->count());
        $this->assertDatabaseHas('jurnal_umum_detail', ['akun_kode' => '207', 'debit' => '150000.00']);
        $this->assertDatabaseHas('jurnal_umum_detail', ['akun_kode' => '406', 'kredit' => '150000.00']);
        $this->assertDatabaseMissing('jurnal_umum_detail', ['akun_kode' => '405', 'kredit' => '1000000.00']);
    }

    public function test_cancel_sebelum_paid_boleh_dan_setelah_paid_ditolak_tanpa_refund_otomatis(): void
    {
        $service = app(SewaHardwareService::class);
        $finance = $this->user('admin');

        $draft = $service->createDraft($this->payload(Karyawan::factory()->create()), $finance->id);
        $cancelledDraft = $service->cancelByFinance($draft, 'Batal draft', $finance->id);
        $this->assertSame(SewaHardware::STATUS_DIBATALKAN, $cancelledDraft->status);
        $this->assertSame(0, MutasiKas::query()->where('referensi_tipe', PembayaranSewaHardware::class)->count());

        $confirmed = $service->confirm($service->createDraft($this->payload(Karyawan::factory()->create(), null, [
            'mulai_tanggal' => '2026-09-01',
            'selesai_tanggal' => '2026-09-02',
        ]), $finance->id), $finance->id);
        $cancelledConfirmed = $service->cancelByFinance($confirmed, 'Batal sebelum paid', $finance->id);
        $this->assertSame(SewaHardware::STATUS_DIBATALKAN, $cancelledConfirmed->status);
        $this->assertSame(SewaHardware::PEMBAYARAN_BELUM_BAYAR, $cancelledConfirmed->status_pembayaran);

        $paid = $this->paidSewa($service, $finance, $this->dompet(DompetKoperasi::JENIS_KAS, 2000000));
        $this->expectValidation(fn () => $service->cancelByFinance($paid, 'Refund otomatis tidak boleh', $finance->id));
        $this->assertSame(SewaHardware::STATUS_DIKONFIRMASI, $paid->fresh()->status);
        $this->assertSame(SewaHardware::PEMBAYARAN_PAID, $paid->fresh()->status_pembayaran);
    }

    public function test_refund_penuh_sebelum_berjalan_mencatat_arus_balik_jurnal_dan_idempotent(): void
    {
        $service = app(SewaHardwareService::class);
        $finance = $this->user('admin');
        $sewa = $this->confirmedSewa($service, $finance);
        $kas = $this->dompet(DompetKoperasi::JENIS_KAS, 2000000);
        $bank = $this->dompet(DompetKoperasi::JENIS_BANK, 2000000);

        $paid = $service->pay($sewa, [
            'metode_penerimaan' => PembayaranSewaHardware::METODE_TUNAI,
            'dompet_penerimaan_id' => $kas->id,
            'metode_pembayaran_vendor' => PembayaranSewaHardware::METODE_TRANSFER_BANK,
            'dompet_vendor_id' => $bank->id,
            'jumlah_diterima' => $sewa->total_tagihan_perusahaan,
            'jumlah_bayar_vendor' => $sewa->total_harga_vendor,
            'paid_at' => '2026-07-31 08:00',
        ], $finance->id);

        $refunded = $service->refundByFinance($paid, 'Vendor mengembalikan dana penuh sebelum hardware digunakan.', $finance->id);
        $again = $service->refundByFinance($refunded, 'Retry tidak boleh double posting.', $finance->id);

        $this->assertSame(SewaHardware::STATUS_REFUNDED, $again->status);
        $this->assertSame(SewaHardware::PEMBAYARAN_REFUNDED, $again->status_pembayaran);
        $this->assertSame(PembayaranSewaHardware::STATUS_REFUNDED, $again->pembayaran->status);
        $this->assertSame('2000000.00', $kas->fresh()->saldo);
        $this->assertSame('2000000.00', $bank->fresh()->saldo);
        $this->assertSame(4, MutasiKas::query()->where('referensi_tipe', PembayaranSewaHardware::class)->count());
        $this->assertSame(1, DB::table('jurnal_umum')->where('idempotency_key', 'like', 'sewa-hardware:refund-vendor:jurnal:%')->count());
        $this->assertSame(1, DB::table('jurnal_umum')->where('idempotency_key', 'like', 'sewa-hardware:refund-perusahaan:jurnal:%')->count());
        $this->assertDatabaseHas('jurnal_umum_detail', ['akun_kode' => '102', 'debit' => '1000000.00']);
        $this->assertDatabaseHas('jurnal_umum_detail', ['akun_kode' => '208', 'kredit' => '1000000.00']);
        $this->assertDatabaseHas('jurnal_umum_detail', ['akun_kode' => '208', 'debit' => '1000000.00']);
        $this->assertDatabaseHas('jurnal_umum_detail', ['akun_kode' => '207', 'debit' => '150000.00']);
        $this->assertDatabaseHas('jurnal_umum_detail', ['akun_kode' => '101', 'kredit' => '1150000.00']);

        $running = $service->start($this->paidSewa($service, $finance, $this->dompet(DompetKoperasi::JENIS_KAS, 3000000), '2026-09-01', '2026-09-03'), $finance->id);
        $this->expectValidation(fn () => $service->refundByFinance($running, 'Tidak boleh setelah berjalan.', $finance->id));
    }

    public function test_get_halaman_read_only_dan_preflight_mendeteksi_konflik_schema_final(): void
    {
        $finance = $this->user('admin');
        $service = app(SewaHardwareService::class);
        $sewa = $this->confirmedSewa($service, $finance);
        $countBefore = SewaHardware::query()->count();
        $mutasiBefore = MutasiKas::query()->count();

        $this->actingAs($finance)
            ->get(route('sewa-hardware.index'))
            ->assertOk();

        $this->assertSame($countBefore, SewaHardware::query()->count());
        $this->assertSame($mutasiBefore, MutasiKas::query()->count());
        $this->artisan('koperasi:preflight-sewa-hardware')->assertExitCode(0);

        DB::table('sewa_hardware_detail')->insert([
            'sewa_hardware_id' => $sewa->id,
            'jenis_hardware' => 'printer',
            'nama_model_hardware' => 'BROKEN',
            'spesifikasi_kebutuhan' => null,
            'kuantitas' => 1,
            'harga_vendor_per_unit' => 100000,
            'margin_persen_snapshot' => 15,
            'margin_per_unit' => 1,
            'harga_tagihan_per_unit' => 100001,
            'subtotal_harga_vendor' => 100000,
            'subtotal_margin' => 1,
            'subtotal_tagihan' => 100001,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->artisan('koperasi:preflight-sewa-hardware')->assertExitCode(1);
    }

    public function test_seeder_menghasilkan_contoh_sewa_hardware_vendor_based_valid(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertDatabaseCount('aset_printer', 0);
        $this->assertDatabaseHas('sewa_hardware', ['status' => SewaHardware::STATUS_DRAFT]);
        $this->assertDatabaseHas('sewa_hardware', [
            'status' => SewaHardware::STATUS_DIKONFIRMASI,
            'status_pembayaran' => SewaHardware::PEMBAYARAN_BELUM_BAYAR,
        ]);
        $this->assertDatabaseHas('sewa_hardware', [
            'status' => SewaHardware::STATUS_DIKONFIRMASI,
            'status_pembayaran' => SewaHardware::PEMBAYARAN_PAID,
        ]);
        $this->assertDatabaseHas('sewa_hardware', ['status' => SewaHardware::STATUS_BERJALAN]);
        $this->assertDatabaseHas('sewa_hardware', ['status' => SewaHardware::STATUS_SELESAI]);
        $this->assertDatabaseHas('sewa_hardware', [
            'status' => SewaHardware::STATUS_DIBATALKAN,
            'status_pembayaran' => SewaHardware::PEMBAYARAN_BELUM_BAYAR,
        ]);
        $this->assertDatabaseHas('sewa_hardware', [
            'status' => SewaHardware::STATUS_REFUNDED,
            'status_pembayaran' => SewaHardware::PEMBAYARAN_REFUNDED,
        ]);
        $this->assertTrue(DB::table('sewa_hardware_detail')->where('kuantitas', '>', 1)->exists());
        foreach (['printer', 'laptop', 'kamera', 'lainnya'] as $jenis) {
            $this->assertTrue(DB::table('sewa_hardware_detail')->where('jenis_hardware', $jenis)->exists(), "Jenis {$jenis} tidak tersedia di seeder.");
        }
        $this->assertSame(0, DB::table('jurnal_umum_detail')->where('akun_kode', '405')->where('kredit', '>', 0)->count());
        $this->assertSame(0, PemakaianPotongGaji::query()->whereIn('source_type', [SewaHardware::class, PembayaranSewaHardware::class])->count());
        $this->artisan('koperasi:preflight-sewa-hardware')->assertExitCode(0);
    }

    private function confirmedSewa(SewaHardwareService $service, User $finance, string $mulai = '2026-08-01', string $selesai = '2026-08-03'): SewaHardware
    {
        $draft = $service->createDraft($this->payload(Karyawan::factory()->create(), null, [
            'mulai_tanggal' => $mulai,
            'selesai_tanggal' => $selesai,
        ]), $finance->id);

        return $service->confirm($draft, $finance->id);
    }

    private function paidSewa(SewaHardwareService $service, User $finance, DompetKoperasi $kas, string $mulai = '2026-08-01', string $selesai = '2026-08-03'): SewaHardware
    {
        $sewa = $this->confirmedSewa($service, $finance, $mulai, $selesai);

        return $service->pay($sewa, [
            'metode_penerimaan' => PembayaranSewaHardware::METODE_TUNAI,
            'dompet_penerimaan_id' => $kas->id,
            'metode_pembayaran_vendor' => PembayaranSewaHardware::METODE_TUNAI,
            'dompet_vendor_id' => $kas->id,
            'jumlah_diterima' => $sewa->total_tagihan_perusahaan,
            'jumlah_bayar_vendor' => $sewa->total_harga_vendor,
            'paid_at' => '2026-07-31 08:00',
        ], $finance->id);
    }

    private function payload(Karyawan $pic, ?array $details = null, array $overrides = []): array
    {
        return array_merge([
            'karyawan_id' => $pic->id,
            'mulai_tanggal' => '2026-08-01',
            'selesai_tanggal' => '2026-08-03',
            'kebutuhan' => 'Unit test kebutuhan hardware vendor',
            'vendor_nama' => 'Vendor Hardware Test',
            'vendor_kontak' => '0812-0000-1234',
            'vendor_alamat' => 'Jl. Vendor Test No. 1',
            'details' => $details ?? [
                [
                    'jenis_hardware' => 'printer',
                    'nama_model_hardware' => 'Epson EcoTank L3210',
                    'spesifikasi_kebutuhan' => 'Printer warna A4',
                    'kuantitas' => 1,
                    'harga_vendor_per_unit' => 1000000,
                ],
            ],
            'keterangan' => 'Unit test sewa hardware',
        ], $overrides);
    }

    private function dompet(string $jenis, int $saldo = 0): DompetKoperasi
    {
        $this->seed(AkunSeeder::class);
        $accountKey = $jenis === DompetKoperasi::JENIS_BANK ? 'bank' : 'kas';
        $akun = Akun::query()->where('kode_akun', config("account_map.accounts.{$accountKey}.kode_akun"))->firstOrFail();

        return DompetKoperasi::query()->create([
            'akun_id' => $akun->id,
            'nama_dompet' => $jenis === DompetKoperasi::JENIS_BANK ? 'Bank Hardware Test' . fake()->unique()->numberBetween(1, 9999) : 'Kas Hardware Test' . fake()->unique()->numberBetween(1, 9999),
            'jenis_dompet' => $jenis,
            'saldo' => $saldo,
            'is_default_penerimaan_payroll' => false,
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
}
