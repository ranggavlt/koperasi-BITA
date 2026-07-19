<?php

namespace Tests\Feature;

use App\Models\Akun;
use App\Models\BebanOperasional;
use App\Models\BebanOperasionalDetail;
use App\Models\DompetKoperasi;
use App\Models\JurnalUmum;
use App\Models\MutasiKas;
use App\Models\PemakaianPotongGaji;
use App\Models\ReversalTransaksi;
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

    public function test_draft_satu_detail_umum_edit_cancel_tanpa_posting_dan_aset_diabaikan(): void
    {
        $service = app(BebanOperasionalService::class);
        $finance = $this->user('keuangan');
        $dompet = $this->dompet(DompetKoperasi::JENIS_KAS, 1000000);

        $draft = $service->createDraft($this->payload($this->expenseAkun('beban_atk_kantor'), $dompet, [
            'nominal' => 125000,
            'nomor_referensi' => 'REF-ATK-001',
            'keterangan' => 'ATK koperasi',
            'aset_koperasi_id' => 999999,
            'details' => [
                ['akun_id' => $this->expenseAkun('beban_transportasi_operasional')->id, 'aset_koperasi_id' => 999999, 'keterangan' => 'Payload legacy harus diabaikan', 'nominal' => 999999],
            ],
        ]), $finance->id);

        $this->assertMatchesRegularExpression('/^BOP-\d{6}-\d{6}$/', $draft->kode_beban);
        $this->assertSame(BebanOperasional::STATUS_DRAFT, $draft->status);
        $this->assertSame('125000.00', $draft->total_beban);
        $this->assertSame('REF-ATK-001', $draft->nomor_referensi);
        $this->assertSame($dompet->id, $draft->dompet_id);
        $this->assertSame(1, $draft->details()->count());
        $this->assertDatabaseHas('beban_operasional_detail', [
            'beban_operasional_id' => $draft->id,
            'aset_koperasi_id' => null,
            'nominal' => '125000.00',
        ]);
        $this->assertSame(0, MutasiKas::query()->where('referensi_tipe', BebanOperasional::class)->count());
        $this->assertSame(0, JurnalUmum::query()->where('referensi_tipe', BebanOperasional::class)->count());

        $updated = $service->updateDraft($draft, $this->payload($this->expenseAkun('beban_operasional'), $dompet, [
            'nominal' => 300000,
            'nomor_referensi' => 'REF-REV-001',
            'keterangan' => 'Kebutuhan kantor revisi',
        ]), $finance->id);

        $this->assertSame('300000.00', $updated->total_beban);
        $this->assertSame('REF-REV-001', $updated->nomor_referensi);
        $this->assertSame(1, $updated->details()->count());

        $service->cancelDraft($updated, $finance->id);
        $this->assertDatabaseMissing('beban_operasional', ['id' => $updated->id]);
        $this->assertDatabaseMissing('beban_operasional_detail', ['beban_operasional_id' => $updated->id]);
    }

    public function test_posting_mengurangi_dompet_membuat_mutasi_keluar_jurnal_seimbang_dan_tanpa_payroll(): void
    {
        $service = app(BebanOperasionalService::class);
        $finance = $this->user('keuangan');
        $dompet = $this->dompet(DompetKoperasi::JENIS_KAS, 1000000);
        $akunAtk = $this->expenseAkun('beban_atk_kantor');

        $draft = $service->createDraft($this->payload($akunAtk, $dompet, [
            'nominal' => 450000,
            'keterangan' => 'ATK dan administrasi kantor',
        ]), $finance->id);

        $posted = $service->post($draft, null, $finance->id);

        $this->assertSame(BebanOperasional::STATUS_POSTED, $posted->status);
        $this->assertSame(BebanOperasional::METODE_TUNAI, $posted->metode_pembayaran);
        $this->assertSame('550000.00', $dompet->fresh()->saldo);
        $this->assertSame(1, $posted->details()->count());
        $this->assertSame(1, MutasiKas::query()->where('referensi_tipe', BebanOperasional::class)->where('tipe', 'keluar')->count());
        $this->assertSame(1, JurnalUmum::query()->where('referensi_tipe', BebanOperasional::class)->count());
        $this->assertDatabaseHas('jurnal_umum_detail', ['akun_kode' => $akunAtk->kode_akun, 'debit' => '450000.00']);
        $this->assertDatabaseHas('jurnal_umum_detail', ['akun_kode' => $dompet->akun->kode_akun, 'kredit' => '450000.00']);
        $this->assertDatabaseHas('beban_operasional_detail', ['beban_operasional_id' => $posted->id, 'aset_koperasi_id' => null]);
        $this->assertSame(0, PemakaianPotongGaji::query()->whereIn('source_type', [BebanOperasional::class, BebanOperasionalDetail::class])->count());
    }

    public function test_validasi_akun_dompet_saldo_hpp_dan_guard_immutable(): void
    {
        $service = app(BebanOperasionalService::class);
        $finance = $this->user('keuangan');
        $dompet = $this->dompet(DompetKoperasi::JENIS_KAS, 1000000);
        $kasAkun = $this->assetAkun('kas');
        $hpp = $this->expenseAkun('harga_pokok_penjualan');

        $this->expectValidation(fn () => $service->createDraft($this->payload($kasAkun, $dompet, ['keterangan' => 'Akun salah']), $finance->id));
        $this->expectValidation(fn () => $service->createDraft($this->payload($hpp, $dompet, ['keterangan' => 'HPP tidak boleh']), $finance->id));
        $this->expectValidation(fn () => $service->createDraft($this->payload($this->expenseAkun('beban_atk_kantor'), $this->dompet(DompetKoperasi::JENIS_KAS, 1000000, false)), $finance->id));

        $dompetKurang = $this->dompet(DompetKoperasi::JENIS_KAS, 100000);
        $draft = $service->createDraft($this->payload($this->expenseAkun('beban_atk_kantor'), $dompetKurang, [
            'nominal' => 500000,
            'keterangan' => 'Saldo tidak cukup saat posting',
        ]), $finance->id);

        $this->expectValidation(fn () => $service->post($draft, null, $finance->id));
        $this->assertSame(BebanOperasional::STATUS_DRAFT, $draft->fresh()->status);
        $this->assertSame('100000.00', $dompetKurang->fresh()->saldo);

        $posted = $service->post($draft, $dompet->id, $finance->id);
        $this->assertSame(BebanOperasional::STATUS_POSTED, $posted->fresh()->status);
        $this->expectValidation(fn () => $service->updateDraft($posted->fresh(), $this->payload($this->expenseAkun('beban_atk_kantor'), $dompet), $finance->id));

        $this->expectException(RuntimeException::class);
        $posted->delete();
    }

    public function test_reversal_penuh_membuat_audit_mutasi_masuk_jurnal_terbalik_dan_menolak_ganda(): void
    {
        $service = app(BebanOperasionalService::class);
        $finance = $this->user('keuangan');
        $dompet = $this->dompet(DompetKoperasi::JENIS_BANK, 1000000);
        $detailAkun = $this->expenseAkun('beban_operasional');
        $posted = $service->post($service->createDraft($this->payload($detailAkun, $dompet, [
            'nominal' => 275000,
            'keterangan' => 'Beban salah input',
        ]), $finance->id), null, $finance->id);

        $reversed = $service->reverse($posted, 'Reversal penuh karena duplikasi input.', $finance->id);

        $this->assertSame(BebanOperasional::STATUS_REVERSED, $reversed->status);
        $this->assertSame('1000000.00', $dompet->fresh()->saldo);
        $this->assertSame(1, ReversalTransaksi::query()->where('source_type', BebanOperasional::class)->where('source_id', $posted->id)->count());
        $this->assertDatabaseHas('mutasi_kas', ['referensi_tipe' => ReversalTransaksi::class, 'tipe' => 'masuk', 'jumlah' => '275000.00']);
        $this->assertDatabaseHas('jurnal_umum_detail', ['akun_kode' => $dompet->akun->kode_akun, 'debit' => '275000.00']);
        $this->assertDatabaseHas('jurnal_umum_detail', ['akun_kode' => $detailAkun->kode_akun, 'kredit' => '275000.00']);
        $this->expectValidation(fn () => $service->reverse($reversed->fresh(), 'Reversal ulang ditolak.', $finance->id));

        $this->expectException(RuntimeException::class);
        $reversed->details()->firstOrFail()->delete();
    }

    public function test_index_form_terpisah_authorization_field_aset_absen_dan_get_read_only(): void
    {
        $finance = $this->user('keuangan');
        $kasir = $this->user('kasir');
        $karyawan = $this->user('karyawan');
        $akunAtk = $this->expenseAkun('beban_atk_kantor');
        $hpp = $this->expenseAkun('harga_pokok_penjualan');
        $dompetValid = $this->dompet(DompetKoperasi::JENIS_KAS, 1000000);
        $dompetInvalid = $this->dompet(DompetKoperasi::JENIS_KAS, 1000000, false);

        $this->get(route('beban-operasional.index'))->assertRedirect(route('login'));
        $this->actingAs($kasir)->get(route('beban-operasional.index'))->assertForbidden();
        $this->actingAs($kasir)->get(route('beban-operasional.create'))->assertForbidden();
        $this->actingAs($karyawan)->get(route('beban-operasional.index'))->assertForbidden();
        $this->actingAs($karyawan)->get(route('beban-operasional.create'))->assertForbidden();

        $countBefore = BebanOperasional::query()->count();
        $mutasiBefore = MutasiKas::query()->count();
        $jurnalBefore = JurnalUmum::query()->count();

        $this->actingAs($finance)
            ->get(route('beban-operasional.index'))
            ->assertOk()
            ->assertSee('Filter Beban Operasional')
            ->assertSee('Daftar Beban Operasional')
            ->assertSee('+ INPUT BEBAN')
            ->assertSee('href="' . route('beban-operasional.create') . '"', false)
            ->assertDontSee('data-beban-operasional-form', false)
            ->assertDontSee('name="aset_koperasi_id"', false);

        $this->assertSame($countBefore, BebanOperasional::query()->count());
        $this->assertSame($mutasiBefore, MutasiKas::query()->count());
        $this->assertSame($jurnalBefore, JurnalUmum::query()->count());

        $createResponse = $this->actingAs($finance)->get(route('beban-operasional.create'));
        $createResponse->assertOk()
            ->assertSee('Input Beban Operasional')
            ->assertSee('Kembali ke Daftar Beban Operasional')
            ->assertSee('data-beban-operasional-form', false)
            ->assertSee($akunAtk->kode_akun . ' - ' . $akunAtk->nama_akun)
            ->assertSee($dompetValid->nama_dompet)
            ->assertDontSee($hpp->kode_akun . ' - ' . $hpp->nama_akun)
            ->assertDontSee($dompetInvalid->nama_dompet)
            ->assertDontSee('name="aset_koperasi_id"', false);

        $draft = app(BebanOperasionalService::class)->createDraft($this->payload($akunAtk, $dompetValid), $finance->id);
        $this->actingAs($finance)
            ->get(route('beban-operasional.edit', $draft))
            ->assertOk()
            ->assertSee('Edit Draft Beban Operasional')
            ->assertSee('data-beban-operasional-form', false)
            ->assertDontSee('name="aset_koperasi_id"', false);
    }

    public function test_http_store_mengabaikan_aset_legacy_dan_request_manipulation_ditolak(): void
    {
        $finance = $this->user('keuangan');
        $akun = $this->expenseAkun('beban_atk_kantor');
        $dompet = $this->dompet(DompetKoperasi::JENIS_KAS, 1000000);

        $this->actingAs($finance)
            ->post(route('beban-operasional.store'), $this->payload($akun, $dompet, [
                'keterangan' => 'ATK lewat HTTP',
                'aset_koperasi_id' => 999999,
                'total_beban' => 1,
            ]))
            ->assertSessionHasErrors('total_beban');

        $this->actingAs($finance)
            ->post(route('beban-operasional.store'), $this->payload($akun, $dompet, [
                'keterangan' => 'ATK lewat HTTP',
                'aset_koperasi_id' => 999999,
            ]))
            ->assertRedirect(route('beban-operasional.index'));

        $this->assertDatabaseHas('beban_operasional_detail', [
            'akun_id' => $akun->id,
            'aset_koperasi_id' => null,
            'keterangan' => 'ATK lewat HTTP',
        ]);
    }

    public function test_filter_status_dompet_akun_tanggal_dan_pagination_query(): void
    {
        $service = app(BebanOperasionalService::class);
        $finance = $this->user('keuangan');
        $akunAtk = $this->expenseAkun('beban_atk_kantor');
        $akunTransport = $this->expenseAkun('beban_transportasi_operasional');
        $kas = $this->dompet(DompetKoperasi::JENIS_KAS, 5000000);
        $bank = $this->dompet(DompetKoperasi::JENIS_BANK, 5000000);

        $draft = $service->createDraft($this->payload($akunAtk, $kas, [
            'tanggal_beban' => '2026-08-05',
            'keterangan' => 'Filter ATK Masuk',
        ]), $finance->id);
        $posted = $service->post($service->createDraft($this->payload($akunTransport, $bank, [
            'tanggal_beban' => '2026-08-20',
            'keterangan' => 'Filter Transport Posted',
        ]), $finance->id), null, $finance->id);

        $this->actingAs($finance)
            ->get(route('beban-operasional.index', [
                'status' => BebanOperasional::STATUS_DRAFT,
                'dompet_id' => $kas->id,
                'akun_id' => $akunAtk->id,
                'tanggal_dari' => '2026-08-01',
                'tanggal_sampai' => '2026-08-10',
            ]))
            ->assertOk()
            ->assertSee($draft->kode_beban)
            ->assertSee('Filter ATK Masuk')
            ->assertDontSee($posted->kode_beban)
            ->assertDontSee('Filter Transport Posted');

        $this->actingAs($finance)
            ->get(route('beban-operasional.index', [
                'tanggal_dari' => '2026-08-10',
                'tanggal_sampai' => '2026-08-01',
            ]))
            ->assertSessionHasErrors('tanggal_sampai');

        for ($i = 1; $i <= 11; $i++) {
            $service->createDraft($this->payload($akunAtk, $kas, [
                'tanggal_beban' => '2026-09-01',
                'keterangan' => 'Pagination Beban ' . $i,
            ]), $finance->id);
        }

        $this->actingAs($finance)
            ->get(route('beban-operasional.index', [
                'status' => BebanOperasional::STATUS_DRAFT,
                'dompet_id' => $kas->id,
                'akun_id' => $akunAtk->id,
                'tanggal_dari' => '2026-09-01',
                'tanggal_sampai' => '2026-09-30',
            ]))
            ->assertOk()
            ->assertSee('status=' . BebanOperasional::STATUS_DRAFT, false)
            ->assertSee('dompet_id=' . $kas->id, false)
            ->assertSee('akun_id=' . $akunAtk->id, false)
            ->assertSee('tanggal_dari=2026-09-01', false)
            ->assertSee('tanggal_sampai=2026-09-30', false);
    }

    public function test_preflight_read_only_dan_route_delete_tidak_tersedia(): void
    {
        $finance = $this->user('keuangan');
        $service = app(BebanOperasionalService::class);
        $draft = $service->createDraft($this->payload($this->expenseAkun('beban_operasional'), $this->dompet(DompetKoperasi::JENIS_KAS, 1000000), [
            'keterangan' => 'Korupsi total untuk preflight',
            'nominal' => 200000,
        ]), $finance->id);
        DB::table('beban_operasional')->where('id', $draft->id)->update(['total_beban' => 1]);
        $before = BebanOperasional::query()->count();

        $this->artisan('koperasi:preflight-beban-operasional')->assertExitCode(1);
        $this->assertSame($before, BebanOperasional::query()->count());
        $this->assertSame('1.00', $draft->fresh()->total_beban);

        $deleteRoutes = collect(Route::getRoutes())
            ->filter(fn ($route) => in_array('DELETE', $route->methods(), true) && str_contains($route->uri(), 'beban-operasional'))
            ->count();
        $this->assertSame(0, $deleteRoutes);

    }

    public function test_seeder_menghasilkan_beban_umum_tanpa_aset(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertDatabaseHas('beban_operasional', ['status' => BebanOperasional::STATUS_DRAFT]);
        $this->assertDatabaseHas('beban_operasional', ['status' => BebanOperasional::STATUS_POSTED]);
        $this->assertDatabaseHas('beban_operasional', ['status' => BebanOperasional::STATUS_REVERSED]);
        $this->assertFalse(BebanOperasional::query()->whereHas('details', fn ($query) => $query->whereNotNull('aset_koperasi_id'))->exists());
        $this->assertSame(BebanOperasional::query()->count(), BebanOperasionalDetail::query()->count());
        $this->assertSame(0, PemakaianPotongGaji::query()->whereIn('source_type', [BebanOperasional::class, BebanOperasionalDetail::class])->count());
        $this->artisan('koperasi:preflight-beban-operasional')->assertExitCode(0);
    }

    public function test_eligibility_coa_beban_operasional_ditegakkan_dan_diaudit(): void
    {
        $service = app(BebanOperasionalService::class);
        $finance = $this->user('keuangan');
        $dompet = $this->dompet(DompetKoperasi::JENIS_KAS, 1000000);
        $akunAtk = $this->expenseAkun('beban_atk_kantor');
        $draft = $service->createDraft($this->payload($akunAtk, $dompet), $finance->id);

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

        $this->expectValidation(fn () => $service->post($draft, null, $finance->id));
    }

    private function payload(Akun $akun, DompetKoperasi $dompet, array $overrides = []): array
    {
        return array_merge([
            'tanggal_beban' => '2026-07-13',
            'akun_id' => $akun->id,
            'dompet_id' => $dompet->id,
            'nominal' => 250000,
            'nomor_referensi' => null,
            'keterangan' => 'Unit test Beban Operasional',
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
