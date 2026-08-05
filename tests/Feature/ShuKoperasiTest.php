<?php

namespace Tests\Feature;

use App\Models\Akun;
use App\Models\Anggota;
use App\Models\DanaSosialSumber;
use App\Models\JenisSimpanan;
use App\Models\Karyawan;
use App\Models\Penjualan;
use App\Models\ShuConfig;
use App\Models\ShuKoperasi;
use App\Models\Simpanan;
use App\Models\User;
use App\Services\AccountingPeriodService;
use App\Services\AkuntansiService;
use App\Services\AkunResolver;
use App\Services\ShuKoperasiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

class ShuKoperasiTest extends TestCase
{
    use RefreshDatabase;

    public function test_shu_uses_closed_ledger_snapshot_and_posts_exact_allocation(): void
    {
        config(['features.shu_enabled' => true]);
        $admin = User::factory()->create(['role' => 'admin']);
        $this->createConfig($admin);
        [$memberA, $memberB] = $this->createMembersAndWeights();
        $period = $this->closedProfitPeriod($admin, 37000000, 13000000);

        $service = app(ShuKoperasiService::class);
        $shu = $service->create(['periode_akuntansi_id' => $period->id, 'judul' => 'SHU Tahun 2025'], $admin->id);

        $this->assertSame('24000000.00', $shu->shu_total);
        $this->assertSame('9600000.00', $shu->nominal_dana_cadangan);
        $this->assertSame('9600000.00', $shu->nominal_shu_anggota);
        $this->assertSame('1200000.00', $shu->nominal_dana_sosial);
        $this->assertSame($period->checksum, $shu->source_snapshot['checksum']);
        $this->assertSame(24000000, collect(['nominal_dana_cadangan','nominal_shu_anggota','nominal_pengawas','nominal_pembina','nominal_pengurus','nominal_dana_sosial','nominal_dana_pendidikan'])->sum(fn ($field) => (int) $shu->{$field}));
        $this->assertSame(9600000, (int) $shu->anggotaPembagian->sum('nominal_shu'));
        $this->assertTrue($shu->anggotaPembagian->pluck('karyawan_id')->contains($memberA->id));
        $this->assertTrue($shu->anggotaPembagian->pluck('karyawan_id')->contains($memberB->id));

        $service->approve($shu, 'Disetujui berdasarkan RAT 2025', $admin->id);
        $posted = $service->post($shu->fresh(), $admin->id);
        $this->assertSame('closed', $posted->status);
        $this->assertNotNull($posted->allocation_journal_id);
        $this->assertEquals($posted->allocationJournal->details->sum('debit'), $posted->allocationJournal->details->sum('kredit'));
        $this->assertSame('24000000.00', $posted->allocationJournal->details->firstWhere('akun_kode', '304')->debit);
        $source = DanaSosialSumber::query()->where('shu_koperasi_id', $posted->id)->sole();
        $this->assertSame('1200000.00', $source->saldo_tersedia);
        $this->assertSame(DanaSosialSumber::STATUS_ACTIVE, $source->status);

        $retried = $service->post($posted, $admin->id);
        $this->assertSame($posted->allocation_journal_id, $retried->allocation_journal_id);
        $this->assertDatabaseCount('dana_sosial_sumber', 1);
    }

    public function test_manual_shu_transaction_input_is_disabled(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('manual dinonaktifkan');
        app(ShuKoperasiService::class)->addTransaksi(new ShuKoperasi(), ['jumlah' => 100]);
    }

    private function createConfig(User $admin): void
    {
        ShuConfig::query()->create(['persen_dana_cadangan' => 40, 'persen_anggota' => 40, 'persen_pengawas' => 0, 'persen_pembina' => 0, 'persen_pengurus' => 10, 'persen_dana_sosial' => 5, 'persen_dana_pendidikan' => 5, 'persen_jasa_modal' => 50, 'persen_jasa_usaha' => 50, 'status_persetujuan' => ShuConfig::STATUS_APPROVED, 'berlaku_mulai' => '2025-01-01', 'dasar_persetujuan' => 'Konfigurasi test SHU', 'approved_by' => $admin->id, 'approved_at' => now()]);
    }

    private function createMembersAndWeights(): array
    {
        $a = Karyawan::query()->create(['nama' => 'Andi']); $b = Karyawan::query()->create(['nama' => 'Budi']);
        $anggotaA = Anggota::query()->create(['karyawan_id' => $a->id, 'nomor_anggota' => 'AG-001', 'tanggal_bergabung' => '2025-01-01', 'alamat' => 'A', 'status' => 'aktif']);
        $anggotaB = Anggota::query()->create(['karyawan_id' => $b->id, 'nomor_anggota' => 'AG-002', 'tanggal_bergabung' => '2025-01-01', 'alamat' => 'B', 'status' => 'aktif']);
        $type = JenisSimpanan::query()->where('kode', JenisSimpanan::KODE_SIMPANAN_MANASUKA)->firstOrFail();
        Simpanan::query()->create(['karyawan_id' => $a->id, 'anggota_id' => $anggotaA->id, 'jenis_simpanan_id' => $type->id, 'jumlah' => 100000, 'tanggal' => '2025-02-01']);
        Simpanan::query()->create(['karyawan_id' => $b->id, 'anggota_id' => $anggotaB->id, 'jenis_simpanan_id' => $type->id, 'jumlah' => 300000, 'tanggal' => '2025-02-01']);
        $saleA = Penjualan::query()->create(['kode_transaksi' => 'SALE-A', 'karyawan_id' => $a->id, 'total_harga' => 200000, 'diskon' => 0, 'grand_total' => 200000]);
        $saleB = Penjualan::query()->create(['kode_transaksi' => 'SALE-B', 'karyawan_id' => $b->id, 'total_harga' => 300000, 'diskon' => 0, 'grand_total' => 300000]);
        DB::table('penjualan')->where('id', $saleA->id)->update(['created_at' => '2025-03-01 09:00:00', 'updated_at' => '2025-03-01 09:00:00']);
        DB::table('penjualan')->where('id', $saleB->id)->update(['created_at' => '2025-03-02 09:00:00', 'updated_at' => '2025-03-02 09:00:00']);
        return [$a, $b];
    }

    private function closedProfitPeriod(User $admin, int $income, int $expense)
    {
        $period = app(AccountingPeriodService::class)->create(['kode' => 'FY-2025', 'nama' => 'Tahun Buku 2025', 'tanggal_mulai' => '2025-01-01', 'tanggal_selesai' => '2025-12-31'], $admin->id);
        $resolver = app(AkunResolver::class); $journal = app(AkuntansiService::class);
        $cash = Akun::query()->where('kode_akun', '101')->firstOrFail();
        $revenue = Akun::query()->where('kode_akun', '401')->firstOrFail();
        $cost = Akun::query()->where('kode_akun', '502')->firstOrFail();
        $journal->record(['tanggal' => '2025-06-01', 'nomor_bukti' => 'REV-SHU'], [$resolver->line($cash, 'debit', $income), $resolver->line($revenue, 'kredit', $income)]);
        $journal->record(['tanggal' => '2025-07-01', 'nomor_bukti' => 'EXP-SHU'], [$resolver->line($cost, 'debit', $expense), $resolver->line($cash, 'kredit', $expense)]);
        return app(AccountingPeriodService::class)->close($period, 'Tutup buku test SHU', $admin->id);
    }
}
