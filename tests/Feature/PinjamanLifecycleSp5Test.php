<?php

namespace Tests\Feature;

use App\Models\Akun;
use App\Models\Anggota;
use App\Models\CicilanPinjaman;
use App\Models\DompetKoperasi;
use App\Models\JadwalCicilanPinjaman;
use App\Models\JurnalUmum;
use App\Models\Karyawan;
use App\Models\MutasiKas;
use App\Models\PemakaianPotongGaji;
use App\Models\Pinjaman;
use App\Models\User;
use App\Services\MasterDataKoperasiService;
use App\Services\PinjamanKoperasiService;
use App\Services\PotongGajiBulananService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PinjamanLifecycleSp5Test extends TestCase
{
    use RefreshDatabase;

    public function test_finance_membuat_edit_ajukan_setujui_dan_cairkan_terpisah_tanpa_posting_sebelum_cair(): void
    {
        $finance = $this->user('admin');
        $service = app(PinjamanKoperasiService::class);
        $anggota = $this->anggota(5000000);
        $dompet = $this->dompet(7000000);

        $draft = $service->createDraft($this->payload($anggota, ['jumlah_pinjaman' => 5000000, 'tenor_bulan' => 3]), $finance->id);

        $this->assertSame(Pinjaman::STATUS_DRAFT, $draft->status);
        $this->assertSame(0, $draft->jadwalCicilan()->count());
        $this->assertSame(0, MutasiKas::query()->where('referensi_tipe', Pinjaman::class)->where('referensi_id', $draft->id)->count());
        $this->assertSame(0, JurnalUmum::query()->where('referensi_tipe', Pinjaman::class)->where('referensi_id', $draft->id)->count());

        $draft = $service->updateDraft($draft, $this->payload($anggota, [
            'jumlah_pinjaman' => 5000000,
            'tenor_bulan' => 3,
            'keterangan' => 'Draft setelah edit',
        ]), $finance->id);
        $this->assertSame('Draft setelah edit', $draft->keterangan);

        $submitted = $service->submit($draft, $finance->id);
        $this->assertSame(Pinjaman::STATUS_DIAJUKAN, $submitted->status);
        $this->assertNotNull($submitted->submitted_at);

        $approved = $service->approve($submitted, $finance->id);
        $this->assertSame(Pinjaman::STATUS_DISETUJUI, $approved->status);
        $this->assertNotNull($approved->approved_at);
        $this->assertSame('5000000.00', $approved->plafon_pinjaman_snapshot);
        $this->assertSame(0, $approved->jadwalCicilan()->count());

        $active = $service->disburse($approved, [
            'dompet_id' => $dompet->id,
            'tanggal_pencairan' => '2026-07-20',
        ], $finance->id);

        $this->assertSame(Pinjaman::STATUS_AKTIF, $active->status);
        $this->assertNotNull($active->disbursed_at);
        $this->assertSame('2000000.00', $dompet->fresh()->saldo);
        $this->assertSame(1, $active->jadwalCicilan()->count() > 0 ? 1 : 0);
        $this->assertSame(1, MutasiKas::query()->where('referensi_tipe', Pinjaman::class)->where('referensi_id', $active->id)->count());
        $this->assertSame(1, JurnalUmum::query()->where('referensi_tipe', Pinjaman::class)->where('referensi_id', $active->id)->count());
        $this->assertSame(0, PemakaianPotongGaji::query()->count());

        $jadwal = $active->jadwalCicilan()->orderBy('angsuran_ke')->get();
        $this->assertCount(3, $jadwal);
        $this->assertSame('2026-08-01', $jadwal[0]->periode->toDateString());
        $this->assertSame('1666666.00', $jadwal[0]->nominal_pokok);
        $this->assertSame('1666666.00', $jadwal[1]->nominal_pokok);
        $this->assertSame('1666668.00', $jadwal[2]->nominal_pokok);
        $this->assertSame(5000000.0, (float) $jadwal->sum('nominal_pokok'));

        $service->disburse($active->fresh(), [
            'dompet_id' => $dompet->id,
            'tanggal_pencairan' => '2026-07-20',
        ], $finance->id);

        $this->assertSame('2000000.00', $dompet->fresh()->saldo);
        $this->assertSame(1, MutasiKas::query()->where('referensi_tipe', Pinjaman::class)->where('referensi_id', $active->id)->count());
        $this->assertSame(1, JurnalUmum::query()->where('referensi_tipe', Pinjaman::class)->where('referensi_id', $active->id)->count());
    }

    public function test_reject_cancel_validation_dan_open_process_guard(): void
    {
        $finance = $this->user('admin');
        $service = app(PinjamanKoperasiService::class);
        $anggota = $this->anggota(2000000);

        $this->expectValidation(fn () => $service->createDraft($this->payload($anggota, ['jumlah_pinjaman' => 5000001]), $finance->id));
        $this->expectValidation(fn () => $service->createDraft($this->payload($anggota, ['jumlah_pinjaman' => 2500000]), $finance->id));

        $draft = $service->createDraft($this->payload($anggota, ['jumlah_pinjaman' => 1000000]), $finance->id);
        $this->expectValidation(fn () => $service->createDraft($this->payload($anggota, ['jumlah_pinjaman' => 500000]), $finance->id));

        $this->expectValidation(fn () => $service->disburse($draft, [
            'dompet_id' => $this->dompet()->id,
            'tanggal_pencairan' => '2026-07-20',
        ], $finance->id));

        $cancelled = $service->cancel($draft, 'Karyawan membatalkan pengajuan sebelum diproses.', $finance->id);
        $this->assertSame(Pinjaman::STATUS_DIBATALKAN, $cancelled->status);
        $this->assertSame(0, $cancelled->jadwalCicilan()->count());

        $rejectedDraft = $service->createDraft($this->payload($anggota, ['jumlah_pinjaman' => 500000]), $finance->id);
        $submitted = $service->submit($rejectedDraft, $finance->id);
        $rejected = $service->reject($submitted, 'Tidak lolos verifikasi dokumen internal.', $finance->id);
        $this->assertSame(Pinjaman::STATUS_DITOLAK, $rejected->status);
        $this->assertSame(0, MutasiKas::query()->where('referensi_tipe', Pinjaman::class)->where('referensi_id', $rejected->id)->count());
        $this->assertSame(0, JurnalUmum::query()->where('referensi_tipe', Pinjaman::class)->where('referensi_id', $rejected->id)->count());
    }

    public function test_anggota_nonaktif_karyawan_berhenti_saldo_kurang_dan_cancel_setelah_cair_ditolak(): void
    {
        $finance = $this->user('admin');
        $service = app(PinjamanKoperasiService::class);

        $nonaktif = $this->anggota(1000000);
        $nonaktif->update(['status' => Anggota::STATUS_NONAKTIF, 'tanggal_nonaktif' => '2026-07-01']);
        $this->expectValidation(fn () => $service->createDraft($this->payload($nonaktif), $finance->id));

        $berhenti = $this->anggota(1000000);
        $berhenti->karyawan->update(['status_kerja' => Karyawan::STATUS_BERHENTI, 'tanggal_berhenti' => '2026-07-01']);
        $this->expectValidation(fn () => $service->createDraft($this->payload($berhenti), $finance->id));

        $anggota = $this->anggota(2000000);
        $approved = $service->approve($service->submit($service->createDraft($this->payload($anggota), $finance->id), $finance->id), $finance->id);
        $saldoAwal = $this->dompet(100000);

        $this->expectValidation(fn () => $service->disburse($approved, [
            'dompet_id' => $saldoAwal->id,
            'tanggal_pencairan' => '2026-07-20',
        ], $finance->id));
        $this->assertSame('100000.00', $saldoAwal->fresh()->saldo);
        $this->assertSame(Pinjaman::STATUS_DISETUJUI, $approved->fresh()->status);
        $this->assertSame(0, $approved->jadwalCicilan()->count());

        $active = $service->disburse($approved, [
            'dompet_id' => $this->dompet(2000000)->id,
            'tanggal_pencairan' => '2026-07-20',
        ], $finance->id);

        $this->expectValidation(fn () => $service->cancel($active, 'Tidak boleh batal setelah cair.', $finance->id));
    }

    public function test_database_unique_melindungi_satu_proses_terbuka_per_anggota(): void
    {
        $anggota = $this->anggota(3000000);

        Pinjaman::query()->create($this->rawPinjaman($anggota, [
            'kode_pinjaman' => 'PJM-202607-910001',
            'status' => Pinjaman::STATUS_DRAFT,
        ]));

        $this->expectException(QueryException::class);

        Pinjaman::query()->create($this->rawPinjaman($anggota, [
            'kode_pinjaman' => 'PJM-202607-910002',
            'status' => Pinjaman::STATUS_DIAJUKAN,
        ]));
    }

    public function test_http_authorization_filter_pagination_dan_route_hard_delete_tidak_tersedia(): void
    {
        $finance = $this->user('admin');
        $kasir = $this->user('kasir');
        $karyawanUser = $this->user('karyawan');
        $service = app(PinjamanKoperasiService::class);

        $draft = $service->createDraft($this->payload($this->anggota(3000000), ['jumlah_pinjaman' => 1000000]), $finance->id);
        $submitted = $service->submit($service->createDraft($this->payload($this->anggota(3000000), ['jumlah_pinjaman' => 1200000]), $finance->id), $finance->id);

        for ($i = 0; $i < 11; $i++) {
            $service->submit($service->createDraft($this->payload($this->anggota(3000000), [
                'jumlah_pinjaman' => 500000 + ($i * 1000),
            ]), $finance->id), $finance->id);
        }

        $this->actingAs($finance)->get(route('pinjaman.index', ['status' => Pinjaman::STATUS_DRAFT]))
            ->assertOk()
            ->assertSee($draft->kode_pinjaman)
            ->assertDontSee($submitted->kode_pinjaman);

        $this->actingAs($finance)->get(route('pinjaman.index', ['status' => Pinjaman::STATUS_DIAJUKAN, 'page' => 1]))
            ->assertOk()
            ->assertSee('status=diajukan', false);

        $this->actingAs($kasir)->get(route('pinjaman.create'))->assertForbidden();
        $this->actingAs($karyawanUser)->post(route('pinjaman.store'), [])->assertForbidden();
        $this->get(route('pinjaman.index'))->assertRedirect(route('login'));

        $this->assertFalse(Route::has('pinjaman.destroy'));
        $this->assertFalse(Route::has('cicilan-pinjaman.store'));
        $this->assertFalse(Route::has('cicilan-pinjaman.destroy'));
    }

    public function test_pinjaman_aktif_tetap_terintegrasi_dengan_pelunasan_tunai_mantan_karyawan(): void
    {
        $finance = $this->user('admin');
        $anggota = $this->anggota(2000000);
        $pinjaman = app(PinjamanKoperasiService::class)->create([
            'anggota_id' => $anggota->id,
            'dompet_id' => $this->dompet(3000000)->id,
            'jumlah_pinjaman' => 600000,
            'tenor_bulan' => 3,
            'tanggal_pinjaman' => '2026-07-20',
            'keterangan' => 'Pinjaman cash legacy test',
        ], $finance->id);

        $anggota->update(['status' => Anggota::STATUS_NONAKTIF, 'tanggal_nonaktif' => '2026-08-01']);
        $anggota->karyawan->update(['status_kerja' => Karyawan::STATUS_BERHENTI, 'tanggal_berhenti' => '2026-08-01']);

        app(PotongGajiBulananService::class)->payFullCash($pinjaman, $this->dompet(1000000), $finance->id);

        $this->assertSame(Pinjaman::STATUS_LUNAS, $pinjaman->fresh()->status);
        $this->assertSame('0.00', $pinjaman->fresh()->sisa_pinjaman);
        $this->assertSame(3, CicilanPinjaman::query()->where('pinjaman_id', $pinjaman->id)->count());
    }

    public function test_preflight_pinjaman_bersih_dan_mendeteksi_konflik(): void
    {
        $finance = $this->user('admin');
        $pinjaman = app(PinjamanKoperasiService::class)->create([
            'anggota_id' => $this->anggota(3000000)->id,
            'dompet_id' => $this->dompet(4000000)->id,
            'jumlah_pinjaman' => 900000,
            'tenor_bulan' => 3,
            'tanggal_pinjaman' => '2026-07-20',
            'keterangan' => 'Preflight valid',
        ], $finance->id);

        $this->artisan('koperasi:preflight-pinjaman')->assertExitCode(0);

        DB::table('pinjaman')->where('id', $pinjaman->id)->update(['bunga_persen' => 1]);

        $this->artisan('koperasi:preflight-pinjaman')->assertExitCode(1);
    }

    private function user(string $role): User
    {
        return User::factory()->create(['role' => $role]);
    }

    private function anggota(int $plafon = 5000000): Anggota
    {
        $karyawan = Karyawan::factory()->create();

        return app(MasterDataKoperasiService::class)->createAnggota([
            'karyawan_id' => $karyawan->id,
            'tanggal_bergabung' => '2026-01-01',
            'alamat' => 'Jl. Pinjaman SP5 Test',
            'plafon_pinjaman' => $plafon,
        ])->fresh('karyawan');
    }

    private function dompet(int $saldo = 10000000): DompetKoperasi
    {
        return DompetKoperasi::query()->create([
            'akun_id' => Akun::query()->where('kode_akun', '101')->value('id'),
            'nama_dompet' => 'Kas SP5 ' . uniqid(),
            'saldo' => $saldo,
        ]);
    }

    private function payload(Anggota $anggota, array $overrides = []): array
    {
        return $overrides + [
            'anggota_id' => $anggota->id,
            'jumlah_pinjaman' => 1000000,
            'tenor_bulan' => 10,
            'tanggal_pengajuan' => '2026-07-10',
            'keterangan' => 'Pinjaman SP5 test',
        ];
    }

    private function rawPinjaman(Anggota $anggota, array $overrides = []): array
    {
        return $overrides + [
            'anggota_id' => $anggota->id,
            'karyawan_id' => $anggota->karyawan_id,
            'jumlah_pinjaman' => 1000000,
            'plafon_pinjaman_snapshot' => 3000000,
            'bunga_persen' => 0,
            'tenor_bulan' => 10,
            'sisa_pinjaman' => 1000000,
            'tanggal_pengajuan' => '2026-07-10',
            'tanggal_pinjaman' => '2026-07-10',
            'created_by' => $this->user('admin')->id,
        ];
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
