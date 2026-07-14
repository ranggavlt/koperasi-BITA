<?php

namespace Tests\Feature;

use App\Models\Akun;
use App\Models\AsetKoperasi;
use App\Models\DompetKoperasi;
use App\Models\Karyawan;
use App\Models\MutasiKas;
use App\Models\PembayaranSewaPrinter;
use App\Models\PemakaianPotongGaji;
use App\Models\SewaPrinter;
use App\Models\User;
use App\Services\AsetKoperasiService;
use App\Services\SewaPrinterService;
use Database\Seeders\AkunSeeder;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class SewaPrinterTest extends TestCase
{
    use RefreshDatabase;

    public function test_multi_printer_margin_half_up_total_snapshot_dan_kode(): void
    {
        $service = app(SewaPrinterService::class);
        $finance = $this->user('keuangan');
        $pic = Karyawan::factory()->create();
        $printerA = $this->printer('PRN-A');
        $printerB = $this->printer('PRN-B');

        $sewa = $service->createDraft($this->payload($pic, [
            ['aset_koperasi_id' => $printerA->id, 'harga_dasar' => 1000000],
            ['aset_koperasi_id' => $printerB->id, 'harga_dasar' => 333],
        ]), $finance->id);

        $this->assertMatchesRegularExpression('/^SWP-\d{6}-\d{6}$/', $sewa->kode_sewa);
        $this->assertSame('1000333.00', $sewa->total_harga_dasar);
        $this->assertSame('150050.00', $sewa->total_margin);
        $this->assertSame('1150383.00', $sewa->grand_total);
        $this->assertSame('50.00', $sewa->details()->where('aset_koperasi_id', $printerB->id)->first()->margin_nominal);

        $confirmed = $service->confirm($sewa, $finance->id);
        $printerA->update(['model' => 'Model Setelah Kontrak']);

        $this->assertNotSame('Model Setelah Kontrak', $confirmed->details()->where('aset_koperasi_id', $printerA->id)->first()->model_snapshot);

        $this->expectValidation(fn () => $service->createDraft($this->payload($pic, [
            ['aset_koperasi_id' => $printerA->id, 'harga_dasar' => 100000],
            ['aset_koperasi_id' => $printerA->id, 'harga_dasar' => 100000],
        ], ['mulai_tanggal' => '2026-09-01', 'selesai_tanggal' => '2026-09-03']), $finance->id));
    }

    public function test_draft_confirm_validation_overlap_dan_confirmed_tidak_bisa_edit(): void
    {
        $service = app(SewaPrinterService::class);
        $finance = $this->user('keuangan');
        $pic = Karyawan::factory()->create();
        $printerA = $this->printer('PRN-C');
        $printerB = $this->printer('PRN-D');
        $mobil = $this->mobil();

        $this->expectValidation(fn () => $service->createDraft($this->payload($pic, [
            ['aset_koperasi_id' => $mobil->id, 'harga_dasar' => 100000],
        ]), $finance->id));

        $printerB->update(['status' => AsetKoperasi::STATUS_PERAWATAN]);
        $this->expectValidation(fn () => $service->createDraft($this->payload($pic, [
            ['aset_koperasi_id' => $printerB->id, 'harga_dasar' => 100000],
        ]), $finance->id));
        $printerB->update(['status' => AsetKoperasi::STATUS_TERSEDIA]);

        $first = $service->createDraft($this->payload($pic, [
            ['aset_koperasi_id' => $printerA->id, 'harga_dasar' => 500000],
        ]), $finance->id);
        $service->confirm($first, $finance->id);

        $overlap = $service->createDraft($this->payload($pic, [
            ['aset_koperasi_id' => $printerA->id, 'harga_dasar' => 400000],
            ['aset_koperasi_id' => $printerB->id, 'harga_dasar' => 300000],
        ], ['mulai_tanggal' => '2026-08-03', 'selesai_tanggal' => '2026-08-05']), $finance->id);

        $this->expectValidation(fn () => $service->confirm($overlap, $finance->id));
        $this->assertSame(SewaPrinter::STATUS_DRAFT, $overlap->fresh()->status);

        $this->expectValidation(fn () => $service->updateDraft($first->fresh(), $this->payload($pic, [
            ['aset_koperasi_id' => $printerB->id, 'harga_dasar' => 300000],
        ], ['mulai_tanggal' => '2026-09-01', 'selesai_tanggal' => '2026-09-02']), $finance->id));
    }

    public function test_pembayaran_full_dompet_mutasi_jurnal_dimuka_dan_tanpa_ledger_payroll(): void
    {
        $service = app(SewaPrinterService::class);
        $finance = $this->user('keuangan');
        $sewa = $this->confirmedSewa($service, $finance);
        $kas = $this->dompet(DompetKoperasi::JENIS_KAS, 1000000);
        $bank = $this->dompet(DompetKoperasi::JENIS_BANK, 1000000);

        $this->expectValidation(fn () => $service->pay($sewa, [
            'metode_pembayaran' => PembayaranSewaPrinter::METODE_TUNAI,
            'dompet_id' => $bank->id,
            'jumlah_bayar' => 1150000,
            'paid_at' => '2026-07-31 08:00',
        ], $finance->id));

        $this->expectValidation(fn () => $service->pay($sewa, [
            'metode_pembayaran' => PembayaranSewaPrinter::METODE_TUNAI,
            'dompet_id' => $kas->id,
            'jumlah_bayar' => 1149999,
            'paid_at' => '2026-07-31 08:00',
        ], $finance->id));

        $this->expectValidation(fn () => $service->pay($sewa, [
            'metode_pembayaran' => PembayaranSewaPrinter::METODE_TUNAI,
            'dompet_id' => $kas->id,
            'jumlah_bayar' => 1150000,
            'paid_at' => '2026-08-01 08:00',
        ], $finance->id));

        $paid = $service->pay($sewa, [
            'metode_pembayaran' => PembayaranSewaPrinter::METODE_TUNAI,
            'dompet_id' => $kas->id,
            'jumlah_bayar' => 1150000,
            'paid_at' => '2026-07-31 08:00',
        ], $finance->id);

        $this->assertSame('2150000.00', $kas->fresh()->saldo);
        $this->assertSame(SewaPrinter::PEMBAYARAN_PAID, $paid->status_pembayaran);
        $this->assertSame(1, MutasiKas::query()->where('referensi_tipe', PembayaranSewaPrinter::class)->where('tipe', 'masuk')->count());
        $this->assertDatabaseHas('jurnal_umum_detail', ['akun_kode' => '207', 'kredit' => '1150000.00']);
        $this->assertDatabaseMissing('jurnal_umum_detail', ['akun_kode' => '405', 'kredit' => '1000000.00']);
        $this->assertSame(0, PemakaianPotongGaji::query()->count());
    }

    public function test_lifecycle_start_complete_aset_dan_jurnal_split_idempotent(): void
    {
        $service = app(SewaPrinterService::class);
        $finance = $this->user('keuangan');
        $sewa = $this->confirmedSewa($service, $finance);
        $kas = $this->dompet(DompetKoperasi::JENIS_KAS);

        $this->expectValidation(fn () => $service->start($sewa, $finance->id));

        $paid = $service->pay($sewa, [
            'metode_pembayaran' => PembayaranSewaPrinter::METODE_TUNAI,
            'dompet_id' => $kas->id,
            'jumlah_bayar' => 1150000,
            'paid_at' => '2026-07-31 08:00',
        ], $finance->id);

        $running = $service->start($paid, $finance->id);
        $this->assertSame(SewaPrinter::STATUS_BERJALAN, $running->status);
        $this->assertTrue($running->details->every(fn ($detail) => $detail->aset->fresh()->status === AsetKoperasi::STATUS_DIGUNAKAN_DISEWA));

        $completed = $service->complete($running, $finance->id);
        $completedAgain = $service->complete($completed, $finance->id);

        $this->assertSame(SewaPrinter::STATUS_SELESAI, $completedAgain->status);
        $this->assertTrue($completedAgain->details->every(fn ($detail) => $detail->aset->fresh()->status === AsetKoperasi::STATUS_TERSEDIA));
        $this->assertSame(1, DB::table('jurnal_umum')->where('idempotency_key', 'like', 'sewa-printer:pengakuan-pendapatan:jurnal:%')->count());
        $this->assertDatabaseHas('jurnal_umum_detail', ['akun_kode' => '207', 'debit' => '1150000.00']);
        $this->assertDatabaseHas('jurnal_umum_detail', ['akun_kode' => '405', 'kredit' => '1000000.00']);
        $this->assertDatabaseHas('jurnal_umum_detail', ['akun_kode' => '406', 'kredit' => '150000.00']);
    }

    public function test_cancel_refund_penuh_dan_berjalan_tidak_bisa_dibatalkan(): void
    {
        $service = app(SewaPrinterService::class);
        $finance = $this->user('keuangan');
        $kas = $this->dompet(DompetKoperasi::JENIS_KAS, 2000000);

        $draft = $service->createDraft($this->payload(Karyawan::factory()->create(), [
            ['aset_koperasi_id' => $this->printer('PRN-F')->id, 'harga_dasar' => 100000],
        ], ['mulai_tanggal' => '2026-09-01', 'selesai_tanggal' => '2026-09-02']), $finance->id);
        $service->cancelByFinance($draft, 'Batal draft', $finance->id);
        $this->assertSame(0, MutasiKas::query()->where('referensi_tipe', PembayaranSewaPrinter::class)->count());

        $paid = $this->paidSewa($service, $finance, $kas);
        $cancelled = $service->cancelByFinance($paid, 'Refund sebelum berjalan', $finance->id);

        $this->assertSame(SewaPrinter::STATUS_DIBATALKAN, $cancelled->status);
        $this->assertSame(SewaPrinter::PEMBAYARAN_REFUNDED, $cancelled->status_pembayaran);
        $this->assertSame('2000000.00', $kas->fresh()->saldo);
        $this->assertSame(1, MutasiKas::query()->where('referensi_tipe', PembayaranSewaPrinter::class)->where('tipe', 'keluar')->count());
        $this->expectValidation(fn () => $service->cancelByFinance($cancelled->fresh(), 'Refund ganda', $finance->id));

        $running = $this->paidSewa($service, $finance, $this->dompet(DompetKoperasi::JENIS_KAS, 2000000), '2026-10-01', '2026-10-02');
        $running = $service->start($running, $finance->id);
        $this->expectValidation(fn () => $service->cancelByFinance($running, 'Tidak boleh', $finance->id));
    }

    public function test_authorization_request_manipulation_preflight_dan_seeder(): void
    {
        $finance = $this->user('keuangan');
        $kasir = $this->user('kasir');
        $karyawanUser = User::factory()->create(['role' => 'karyawan', 'is_active' => true, 'must_change_password' => false]);

        $this->get(route('sewa-printer.index'))->assertRedirect(route('login'));
        $this->actingAs($kasir)->get(route('sewa-printer.index'))->assertForbidden();
        $this->actingAs($karyawanUser)->get(route('sewa-printer.index'))->assertForbidden();
        $this->actingAs($finance)->get(route('sewa-printer.index'))->assertOk();

        $this->actingAs($finance)->post(route('sewa-printer.store'), [
            'karyawan_pic_id' => Karyawan::factory()->create()->id,
            'mulai_tanggal' => '2026-08-01',
            'selesai_tanggal' => '2026-08-02',
            'details' => [
                ['aset_koperasi_id' => $this->printer('PRN-G')->id, 'harga_dasar' => 100000],
            ],
            'grand_total' => 1,
        ])->assertSessionHasErrors('grand_total');

        $this->artisan('koperasi:preflight-sewa-printer')->assertExitCode(0);

        DB::table('sewa_printer_detail')->insert([
            'sewa_printer_id' => $this->confirmedSewa(app(SewaPrinterService::class), $finance, '2026-11-01', '2026-11-02')->id,
            'aset_koperasi_id' => $this->printer('PRN-H')->id,
            'kode_aset_snapshot' => 'BROKEN',
            'nomor_seri_snapshot' => 'BROKEN',
            'merek_snapshot' => 'Broken',
            'model_snapshot' => 'Broken',
            'harga_dasar' => 100000,
            'margin_persen_snapshot' => 15,
            'margin_nominal' => 1,
            'total_harga' => 100001,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->artisan('koperasi:preflight-sewa-printer')->assertExitCode(1);
    }

    public function test_seeder_menghasilkan_contoh_sewa_printer_valid(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertDatabaseHas('sewa_printer', ['status' => SewaPrinter::STATUS_DRAFT]);
        $this->assertDatabaseHas('sewa_printer', ['status' => SewaPrinter::STATUS_DIKONFIRMASI, 'status_pembayaran' => SewaPrinter::PEMBAYARAN_BELUM_BAYAR]);
        $this->assertDatabaseHas('sewa_printer', ['status' => SewaPrinter::STATUS_DIKONFIRMASI, 'status_pembayaran' => SewaPrinter::PEMBAYARAN_PAID]);
        $this->assertDatabaseHas('sewa_printer', ['status' => SewaPrinter::STATUS_BERJALAN]);
        $this->assertDatabaseHas('sewa_printer', ['status' => SewaPrinter::STATUS_SELESAI]);
        $this->assertDatabaseHas('sewa_printer', ['status' => SewaPrinter::STATUS_DIBATALKAN, 'status_pembayaran' => SewaPrinter::PEMBAYARAN_REFUNDED]);
        $this->assertTrue(SewaPrinter::query()->whereHas('details', fn ($q) => $q->select('sewa_printer_id')->groupBy('sewa_printer_id')->havingRaw('COUNT(*) > 1'))->exists());
        $this->assertSame(0, PemakaianPotongGaji::query()->whereIn('source_type', [SewaPrinter::class, PembayaranSewaPrinter::class])->count());
        $this->artisan('koperasi:preflight-sewa-printer')->assertExitCode(0);
    }

    private function confirmedSewa(SewaPrinterService $service, User $finance, string $mulai = '2026-08-01', string $selesai = '2026-08-03'): SewaPrinter
    {
        $pic = Karyawan::factory()->create();
        $printer = $this->printer('PRN-' . fake()->unique()->numberBetween(1000, 9999));
        $draft = $service->createDraft($this->payload($pic, [
            ['aset_koperasi_id' => $printer->id, 'harga_dasar' => 1000000],
        ], ['mulai_tanggal' => $mulai, 'selesai_tanggal' => $selesai]), $finance->id);

        return $service->confirm($draft, $finance->id);
    }

    private function paidSewa(SewaPrinterService $service, User $finance, DompetKoperasi $kas, string $mulai = '2026-08-01', string $selesai = '2026-08-03'): SewaPrinter
    {
        $sewa = $this->confirmedSewa($service, $finance, $mulai, $selesai);

        return $service->pay($sewa, [
            'metode_pembayaran' => PembayaranSewaPrinter::METODE_TUNAI,
            'dompet_id' => $kas->id,
            'jumlah_bayar' => 1150000,
            'paid_at' => '2026-07-31 08:00',
        ], $finance->id);
    }

    private function payload(Karyawan $pic, array $details, array $overrides = []): array
    {
        return array_merge([
            'karyawan_pic_id' => $pic->id,
            'mulai_tanggal' => '2026-08-01',
            'selesai_tanggal' => '2026-08-03',
            'details' => $details,
            'keterangan' => 'Unit test sewa printer',
        ], $overrides);
    }

    private function printer(string $serial): AsetKoperasi
    {
        return app(AsetKoperasiService::class)->createPrinter([
            'nomor_seri' => $serial . '-' . fake()->unique()->numberBetween(1000, 9999),
            'merek' => 'Epson',
            'model' => 'L3210',
            'lokasi' => 'Kantor',
            'keterangan' => 'Unit test printer',
        ], $this->user('keuangan')->id);
    }

    private function mobil(): AsetKoperasi
    {
        return app(AsetKoperasiService::class)->createMobil([
            'plat_nomor' => 'B ' . fake()->unique()->numberBetween(1000, 9999) . ' KBS',
            'merek' => 'Toyota',
            'model' => 'Avanza',
            'tahun' => 2022,
            'warna' => 'Hitam',
            'keterangan' => 'Unit test mobil',
        ], $this->user('keuangan')->id);
    }

    private function dompet(string $jenis, int $saldo = 0): DompetKoperasi
    {
        $this->seed(AkunSeeder::class);
        $accountKey = $jenis === DompetKoperasi::JENIS_BANK ? 'bank' : 'kas';
        $akun = Akun::query()->where('kode_akun', config("account_map.accounts.{$accountKey}.kode_akun"))->firstOrFail();

        return DompetKoperasi::query()->create([
            'akun_id' => $akun->id,
            'nama_dompet' => $jenis === DompetKoperasi::JENIS_BANK ? 'Bank Printer Test' . fake()->unique()->numberBetween(1, 9999) : 'Kas Printer Test' . fake()->unique()->numberBetween(1, 9999),
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
