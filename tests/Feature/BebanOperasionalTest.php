<?php

namespace Tests\Feature;

use App\Models\Akun;
use App\Models\AsetKoperasi;
use App\Models\BebanOperasional;
use App\Models\BebanOperasionalDetail;
use App\Models\DompetKoperasi;
use App\Models\JurnalUmum;
use App\Models\MutasiKas;
use App\Models\PemakaianPotongGaji;
use App\Models\ReversalTransaksi;
use App\Services\AsetKoperasiService;
use App\Models\User;
use App\Services\BebanOperasionalService;
use Database\Seeders\AkunSeeder;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Tests\TestCase;

class BebanOperasionalTest extends TestCase
{
    use RefreshDatabase;

    public function test_draft_multi_detail_kode_total_edit_dan_cancel_tanpa_posting(): void
    {
        $service = app(BebanOperasionalService::class);
        $finance = $this->user('keuangan');
        $aset = $this->asset();

        $draft = $service->createDraft($this->payload([
            ['akun_id' => $this->expenseAkun('beban_atk_kantor')->id, 'keterangan' => 'ATK koperasi', 'nominal' => 125000],
            ['akun_id' => $this->expenseAkun('beban_perawatan_aset')->id, 'aset_koperasi_id' => $aset->id, 'keterangan' => 'Servis printer', 'nominal' => 75000],
        ]), $finance->id);

        $this->assertMatchesRegularExpression('/^BOP-\d{6}-\d{6}$/', $draft->kode_beban);
        $this->assertSame(BebanOperasional::STATUS_DRAFT, $draft->status);
        $this->assertSame('200000.00', $draft->total_beban);
        $this->assertSame(0, MutasiKas::query()->where('referensi_tipe', BebanOperasional::class)->count());
        $this->assertSame(0, JurnalUmum::query()->where('referensi_tipe', BebanOperasional::class)->count());

        $updated = $service->updateDraft($draft, $this->payload([
            ['akun_id' => $this->expenseAkun('beban_operasional')->id, 'keterangan' => 'Kebutuhan kantor revisi', 'nominal' => 300000],
        ], ['keterangan' => 'Draft direvisi']), $finance->id);

        $this->assertSame('300000.00', $updated->total_beban);
        $this->assertSame(1, $updated->details()->count());

        $service->cancelDraft($updated, $finance->id);
        $this->assertDatabaseMissing('beban_operasional', ['id' => $updated->id]);
        $this->assertDatabaseMissing('beban_operasional_detail', ['beban_operasional_id' => $updated->id]);
    }

    public function test_posting_mengurangi_dompet_membuat_mutasi_jurnal_snapshot_dan_tanpa_payroll(): void
    {
        $service = app(BebanOperasionalService::class);
        $finance = $this->user('keuangan');
        $dompet = $this->dompet(DompetKoperasi::JENIS_KAS, 1000000);
        $akunAtk = $this->expenseAkun('beban_atk_kantor');
        $akunPerawatan = $this->expenseAkun('beban_perawatan_aset');
        $aset = $this->asset();

        $draft = $service->createDraft($this->payload([
            ['akun_id' => $akunAtk->id, 'keterangan' => 'ATK', 'nominal' => 100000],
            ['akun_id' => $akunPerawatan->id, 'aset_koperasi_id' => $aset->id, 'keterangan' => 'Servis aset', 'nominal' => 350000],
        ]), $finance->id);

        $posted = $service->post($draft, $dompet->id, $finance->id);

        $this->assertSame(BebanOperasional::STATUS_POSTED, $posted->status);
        $this->assertSame(BebanOperasional::METODE_TUNAI, $posted->metode_pembayaran);
        $this->assertSame('550000.00', $dompet->fresh()->saldo);
        $this->assertSame(1, MutasiKas::query()->where('referensi_tipe', BebanOperasional::class)->where('tipe', 'keluar')->count());
        $this->assertSame(1, JurnalUmum::query()->where('referensi_tipe', BebanOperasional::class)->count());
        $this->assertDatabaseHas('jurnal_umum_detail', ['akun_kode' => $akunAtk->kode_akun, 'debit' => '100000.00']);
        $this->assertDatabaseHas('jurnal_umum_detail', ['akun_kode' => $akunPerawatan->kode_akun, 'debit' => '350000.00']);
        $this->assertDatabaseHas('jurnal_umum_detail', ['akun_kode' => $dompet->akun->kode_akun, 'kredit' => '450000.00']);
        $this->assertDatabaseHas('beban_operasional_detail', ['aset_koperasi_id' => $aset->id, 'kode_aset_snapshot' => $aset->kode_aset]);
        $this->assertSame(0, PemakaianPotongGaji::query()->whereIn('source_type', [BebanOperasional::class, BebanOperasionalDetail::class])->count());
    }

    public function test_validasi_akun_dompet_saldo_rollback_dan_guard_delete_aset(): void
    {
        $service = app(BebanOperasionalService::class);
        $finance = $this->user('keuangan');
        $aset = $this->asset();
        $kasAkun = $this->assetAkun('kas');

        $this->expectValidation(fn () => $service->createDraft($this->payload([
            ['akun_id' => $kasAkun->id, 'keterangan' => 'Akun salah', 'nominal' => 100000],
        ]), $finance->id));

        $draft = $service->createDraft($this->payload([
            ['akun_id' => $this->expenseAkun('beban_perawatan_aset')->id, 'aset_koperasi_id' => $aset->id, 'keterangan' => 'Servis aset', 'nominal' => 500000],
        ]), $finance->id);

        $dompetKurang = $this->dompet(DompetKoperasi::JENIS_KAS, 100000);
        $this->expectValidation(fn () => $service->post($draft, $dompetKurang->id, $finance->id));
        $this->assertSame(BebanOperasional::STATUS_DRAFT, $draft->fresh()->status);
        $this->assertSame('100000.00', $dompetKurang->fresh()->saldo);

        $dompetTanpaCoa = $this->dompet(DompetKoperasi::JENIS_KAS, 1000000, false);
        $this->expectValidation(fn () => $service->post($draft, $dompetTanpaCoa->id, $finance->id));

        $posted = $service->post($draft, $this->dompet(DompetKoperasi::JENIS_KAS, 1000000)->id, $finance->id);
        $guard = app(AsetKoperasiService::class)->canDelete($aset->fresh());

        $this->assertSame(BebanOperasional::STATUS_POSTED, $posted->fresh()->status);
        $this->assertFalse($guard['allowed']);
        $this->assertSame(1, $guard['dependencies']['Beban Operasional Aset'] ?? 0);
    }

    public function test_reversal_penuh_membuat_audit_mutasi_jurnal_dan_menolak_ganda(): void
    {
        $service = app(BebanOperasionalService::class);
        $finance = $this->user('keuangan');
        $dompet = $this->dompet(DompetKoperasi::JENIS_BANK, 1000000);
        $posted = $service->post($service->createDraft($this->payload([
            ['akun_id' => $this->expenseAkun('beban_operasional')->id, 'keterangan' => 'Beban salah input', 'nominal' => 275000],
        ]), $finance->id), $dompet->id, $finance->id);

        $this->assertSame('725000.00', $dompet->fresh()->saldo);
        $this->expectException(RuntimeException::class);
        $posted->delete();
    }

    public function test_reversal_flow_setelah_posted_immutable(): void
    {
        $service = app(BebanOperasionalService::class);
        $finance = $this->user('keuangan');
        $dompet = $this->dompet(DompetKoperasi::JENIS_BANK, 1000000);
        $detailAkun = $this->expenseAkun('beban_operasional');
        $posted = $service->post($service->createDraft($this->payload([
            ['akun_id' => $detailAkun->id, 'keterangan' => 'Beban salah input', 'nominal' => 275000],
        ]), $finance->id), $dompet->id, $finance->id);

        $reversed = $service->reverse($posted, 'Reversal penuh karena duplikasi input.', $finance->id);

        $this->assertSame(BebanOperasional::STATUS_REVERSED, $reversed->status);
        $this->assertSame('1000000.00', $dompet->fresh()->saldo);
        $this->assertSame(1, ReversalTransaksi::query()->where('source_type', BebanOperasional::class)->where('source_id', $posted->id)->count());
        $this->assertDatabaseHas('mutasi_kas', ['referensi_tipe' => ReversalTransaksi::class, 'tipe' => 'masuk', 'jumlah' => '275000.00']);
        $this->assertDatabaseHas('jurnal_umum_detail', ['akun_kode' => $dompet->akun->kode_akun, 'debit' => '275000.00']);
        $this->assertDatabaseHas('jurnal_umum_detail', ['akun_kode' => $detailAkun->kode_akun, 'kredit' => '275000.00']);
        $this->expectValidation(fn () => $service->reverse($reversed->fresh(), 'Reversal ulang ditolak.', $finance->id));
        $this->expectValidation(fn () => $service->updateDraft($reversed->fresh(), $this->payload([
            ['akun_id' => $detailAkun->id, 'keterangan' => 'Edit tidak boleh', 'nominal' => 100000],
        ]), $finance->id));

        $this->expectException(RuntimeException::class);
        $reversed->details()->firstOrFail()->delete();
    }

    public function test_authorization_request_manipulation_preflight_read_only_dan_route_delete_tidak_tersedia(): void
    {
        $finance = $this->user('keuangan');
        $kasir = $this->user('kasir');
        $karyawan = $this->user('karyawan');

        $this->get(route('beban-operasional.index'))->assertRedirect(route('login'));
        $this->actingAs($kasir)->get(route('beban-operasional.index'))->assertForbidden();
        $this->actingAs($karyawan)->get(route('beban-operasional.index'))->assertForbidden();
        $this->actingAs($finance)->get(route('beban-operasional.index'))->assertOk();

        $this->actingAs($finance)->post(route('beban-operasional.store'), $this->payload([
            ['akun_id' => $this->expenseAkun('beban_atk_kantor')->id, 'keterangan' => 'ATK', 'nominal' => 100000],
        ], ['total_beban' => 1]))->assertSessionHasErrors('total_beban');

        $deleteRoutes = collect(Route::getRoutes())
            ->filter(fn ($route) => in_array('DELETE', $route->methods(), true) && str_contains($route->uri(), 'beban-operasional'))
            ->count();
        $this->assertSame(0, $deleteRoutes);

        $service = app(BebanOperasionalService::class);
        $draft = $service->createDraft($this->payload([
            ['akun_id' => $this->expenseAkun('beban_operasional')->id, 'keterangan' => 'Korupsi total untuk preflight', 'nominal' => 200000],
        ]), $finance->id);
        DB::table('beban_operasional')->where('id', $draft->id)->update(['total_beban' => 1]);
        $before = BebanOperasional::query()->count();

        $this->artisan('koperasi:preflight-beban-operasional')->assertExitCode(1);
        $this->assertSame($before, BebanOperasional::query()->count());
        $this->assertSame('1.00', $draft->fresh()->total_beban);
    }

    public function test_eligibility_coa_beban_operasional_ditegakkan_dan_diaudit(): void
    {
        $service = app(BebanOperasionalService::class);
        $finance = $this->user('keuangan');
        $dompet = $this->dompet(DompetKoperasi::JENIS_KAS, 1000000);
        $akunAtk = $this->expenseAkun('beban_atk_kantor');
        $hpp = $this->expenseAkun('harga_pokok_penjualan');

        $this->expectValidation(fn () => $service->createDraft($this->payload([
            ['akun_id' => $hpp->id, 'keterangan' => 'HPP tidak boleh untuk BOP', 'nominal' => 100000],
        ]), $finance->id));

        $draft = $service->createDraft($this->payload([
            ['akun_id' => $akunAtk->id, 'keterangan' => 'ATK eligible', 'nominal' => 100000],
        ]), $finance->id);

        $this->actingAs($finance)
            ->patch(route('akun.beban-operasional-eligibility', $akunAtk), [
                'is_beban_operasional' => false,
                'alasan' => 'Tidak lagi dipakai untuk BOP.',
            ])
            ->assertRedirect();

        $this->assertFalse($akunAtk->fresh()->is_beban_operasional);
        $this->assertDatabaseHas('riwayat_akun_beban_operasional', [
            'akun_id' => $akunAtk->id,
            'nilai_sebelum' => true,
            'nilai_sesudah' => false,
        ]);

        $this->expectValidation(fn () => $service->post($draft, $dompet->id, $finance->id));

        DB::table('akun')->where('id', $hpp->id)->update(['is_beban_operasional' => true]);
        $this->artisan('koperasi:preflight-beban-operasional')->assertExitCode(1);
    }

    public function test_seeder_menghasilkan_contoh_valid_beban_operasional(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertDatabaseHas('beban_operasional', ['status' => BebanOperasional::STATUS_DRAFT]);
        $this->assertDatabaseHas('beban_operasional', ['status' => BebanOperasional::STATUS_POSTED]);
        $this->assertDatabaseHas('beban_operasional', ['status' => BebanOperasional::STATUS_REVERSED]);
        $this->assertTrue(BebanOperasional::query()->whereHas('details', fn ($query) => $query->whereNotNull('aset_koperasi_id'))->exists());
        $this->assertTrue(BebanOperasional::query()->whereHas('details', fn ($query) => $query->whereNull('aset_koperasi_id'))->exists());
        $this->assertDatabaseHas('jurnal_umum_detail', ['akun_kode' => '506']);
        $this->assertSame(0, PemakaianPotongGaji::query()->whereIn('source_type', [BebanOperasional::class, BebanOperasionalDetail::class])->count());
        $this->artisan('koperasi:preflight-beban-operasional')->assertExitCode(0);
    }

    private function payload(array $details, array $overrides = []): array
    {
        return array_merge([
            'tanggal_beban' => '2026-07-13',
            'keterangan' => 'Unit test Beban Operasional',
            'details' => $details,
        ], $overrides);
    }

    private function expenseAkun(string $key): Akun
    {
        $this->seed(AkunSeeder::class);

        return Akun::query()->where('kode_akun', config("account_map.accounts.{$key}.kode_akun"))->firstOrFail();
    }

    private function assetAkun(string $key): Akun
    {
        $this->seed(AkunSeeder::class);

        return Akun::query()->where('kode_akun', config("account_map.accounts.{$key}.kode_akun"))->firstOrFail();
    }

    private function dompet(string $jenis, int $saldo, bool $withCoa = true): DompetKoperasi
    {
        $akun = null;
        if ($withCoa) {
            $akun = $this->assetAkun($jenis === DompetKoperasi::JENIS_BANK ? 'bank' : 'kas');
        }

        return DompetKoperasi::query()->create([
            'akun_id' => $akun?->id,
            'nama_dompet' => ($jenis === DompetKoperasi::JENIS_BANK ? 'Bank' : 'Kas') . ' Beban Test ' . fake()->unique()->numberBetween(1, 999999),
            'jenis_dompet' => $jenis,
            'saldo' => $saldo,
            'is_default_penerimaan_payroll' => false,
        ]);
    }

    private function asset(): AsetKoperasi
    {
        return AsetKoperasi::factory()->printer()->create([
            'merek' => 'Epson',
            'model' => 'L3210',
            'status' => AsetKoperasi::STATUS_TERSEDIA,
        ]);
    }

    private function user(string $role): User
    {
        return User::factory()->create([
            'role' => $role,
            'is_active' => true,
            'must_change_password' => false,
            'password_changed_at' => now(),
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
