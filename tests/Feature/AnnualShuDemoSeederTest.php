<?php

namespace Tests\Feature;

use App\Models\DompetKoperasi;
use App\Models\PeriodeAkuntansi;
use App\Models\ShuConfig;
use App\Models\ShuKoperasi;
use App\Models\User;
use App\Services\AccountingPeriodService;
use App\Services\AnnualShuService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AnnualShuDemoSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seed_menyediakan_demo_tahun_buku_2025_2026_yang_belum_ditutup(): void
    {
        $this->seed();

        $period = PeriodeAkuntansi::query()->where('kode', 'TB-2025-2026')->firstOrFail();
        $this->assertSame(PeriodeAkuntansi::STATUS_OPEN, $period->status);
        $this->assertSame('2025-07-01', $period->tanggal_mulai->toDateString());
        $this->assertSame('2026-06-30', $period->tanggal_selesai->toDateString());
        $this->assertFalse(ShuKoperasi::query()->where('periode_akuntansi_id', $period->id)->exists());
        $this->assertGreaterThanOrEqual(2, User::query()->where('role', 'admin')->where('is_active', true)->count());
        $config = ShuConfig::query()->whereDate('berlaku_mulai', '2025-07-01')->firstOrFail();
        $this->assertSame('2025-07-01', $config->berlaku_mulai->toDateString());
        $this->assertSame(30.0, (float) $config->persen_dana_cadangan);
        $this->assertSame(40.0, (float) $config->persen_shu_anggota);
        $this->assertSame(10.0, (float) $config->persen_pengurus);
        $this->assertSame(5.0, (float) $config->persen_pengawas);
        $this->assertSame(5.0, (float) $config->persen_pembina);
        $this->assertSame(100.0, (float) collect($config->only([
            'persen_dana_cadangan', 'persen_shu_anggota', 'persen_pengurus', 'persen_pengawas',
            'persen_pembina', 'persen_dana_sosial', 'persen_dana_pendidikan',
        ]))->sum());

        $totals = DB::table('jurnal_umum_detail as d')
            ->join('jurnal_umum as j', 'j.id', '=', 'd.jurnal_umum_id')
            ->join('akun as a', 'a.id', '=', 'd.akun_id')
            ->whereBetween('j.tanggal', ['2025-07-01', '2026-06-30'])
            ->whereIn('a.kategori', ['pendapatan', 'beban'])
            ->groupBy('a.kategori')
            ->selectRaw('a.kategori, SUM(d.debit) debit, SUM(d.kredit) kredit')
            ->get();
        $this->assertSame(100000000.0, (float) $totals->where('kategori', 'pendapatan')->sum(fn ($row) => $row->kredit - $row->debit));
        $this->assertSame(60000000.0, (float) $totals->where('kategori', 'beban')->sum(fn ($row) => $row->debit - $row->kredit));
        $this->assertDatabaseCount('pengurus_koperasi', 6);
        $this->assertSame(3, DB::table('pengurus_koperasi')->where('kelompok', 'pengurus')->count());
        $this->assertSame(2, DB::table('pengurus_koperasi')->where('kelompok', 'pengawas')->count());
        $this->assertSame(1, DB::table('pengurus_koperasi')->where('kelompok', 'pembina')->count());

        $maker = User::query()->where('email', 'keuangan@kbsm.test')->firstOrFail();
        $approver = User::query()->where('email', 'persetujuan.shu@kbsm.test')->firstOrFail();
        $closed = app(AccountingPeriodService::class)->close($period, $maker->id);
        $this->assertSame(100000000.0, (float) $closed->total_pendapatan);
        $this->assertSame(60000000.0, (float) $closed->total_beban);
        $this->assertSame(40000000.0, (float) $closed->laba_bersih);

        $annual = app(AnnualShuService::class);
        $shu = $annual->applyPeriod($closed, $maker->id);
        $this->assertSame(12000000.0, (float) $shu->nominal_dana_cadangan);
        $this->assertSame(16000000.0, (float) $shu->nominal_shu_anggota);
        $this->assertSame(4000000.0, (float) $shu->nominal_pengurus);
        $this->assertSame(2000000.0, (float) $shu->nominal_pengawas);
        $this->assertSame(2000000.0, (float) $shu->nominal_pembina);
        $this->assertSame(2000000.0, (float) $shu->nominal_dana_sosial);
        $this->assertSame(2000000.0, (float) $shu->nominal_dana_pendidikan);
        $this->assertGreaterThanOrEqual(4, $shu->recipients->where('jenis_penerima', 'anggota')->count());
        $this->assertSame(16000000.0, (float) $shu->recipients->where('jenis_penerima', 'anggota')->sum('nominal_hak'));
        $this->assertSame(3, $shu->recipients->where('jenis_penerima', 'pengurus')->count());
        $this->assertSame(2, $shu->recipients->where('jenis_penerima', 'pengawas')->count());
        $this->assertSame(1, $shu->recipients->where('jenis_penerima', 'pembina')->count());
        $this->assertGreaterThan(0, (float) $shu->recipients->firstWhere('nama_snapshot', 'Andi Saputra')->nominal_hak);
        $this->assertGreaterThan(0, (float) $shu->recipients->firstWhere('nama_snapshot', 'Siti Rahmawati')->nominal_hak);

        $annual->submit($shu, $maker->id);
        $annual->approve($shu->fresh(), $approver->id);
        $this->assertDatabaseHas('dana_sosial_sumber', ['shu_koperasi_id' => $shu->id, 'jumlah' => 2000000]);
        $memberRecipient = $shu->recipients()->where('jenis_penerima', 'anggota')->where('nama_snapshot', 'Andi Saputra')->firstOrFail();
        $wallet = DompetKoperasi::query()->kas()->firstOrFail();
        $balanceBefore = (float) $wallet->saldo;
        $memberAmount = (float) $memberRecipient->nominal_hak;
        $annual->pay($memberRecipient, ['metode' => 'tunai', 'dompet_id' => $wallet->id, 'tanggal_bayar' => '2026-07-15'], $approver->id);
        $this->assertSame($balanceBefore - $memberAmount, (float) $wallet->fresh()->saldo);
        $this->assertSame(1, DB::table('pembayaran_shu')->where('shu_penerima_id', $memberRecipient->id)->count());
        $this->assertSame(0, \Illuminate\Support\Facades\Artisan::call('koperasi:preflight-shu'));
    }
}
