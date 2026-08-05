<?php

namespace Tests\Feature;

use App\Models\Akun;
use App\Models\DanaSosialLimit;
use App\Models\DompetKoperasi;
use App\Models\JenisSimpanan;
use App\Models\Karyawan;
use App\Models\Penjualan;
use App\Models\PeriodeAkuntansi;
use App\Models\PengurusKoperasi;
use App\Models\ShuConfig;
use App\Models\ShuKoperasi;
use App\Models\Simpanan;
use App\Models\User;
use App\Services\AnnualShuService;
use App\Services\MasterDataKoperasiService;
use App\Services\SocialFundService;
use Database\Seeders\AkunSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class AnnualShuAndSocialFundTest extends TestCase
{
    use RefreshDatabase;

    public function test_lifecycle_shu_tahunan_dan_klaim_dana_sosial_tercatat_utuh(): void
    {
        $this->seed(AkunSeeder::class);
        $maker = User::factory()->create(['role' => 'admin']);
        $approver = User::factory()->create(['role' => 'admin']);
        $member = $this->member();
        $period = PeriodeAkuntansi::query()->create([
            'kode' => 'FY-2025', 'nama' => 'Tahun Buku 2025', 'tanggal_mulai' => '2025-01-01', 'tanggal_selesai' => '2025-12-31',
            'status' => PeriodeAkuntansi::STATUS_CLOSED, 'total_pendapatan' => 1500000, 'total_beban' => 500000, 'laba_bersih' => 1000000,
            'created_by' => $maker->id, 'closed_by' => $maker->id, 'closed_at' => '2026-01-02 08:00:00',
        ]);
        ShuConfig::query()->create([
            'versi' => 1, 'berlaku_mulai' => '2025-01-01', 'dasar_keputusan' => 'RAT-2025',
            'persen_dana_cadangan' => 40, 'persen_shu_anggota' => 45, 'persen_pengurus' => 0, 'persen_pengawas' => 0,
            'persen_pembina' => 0, 'persen_dana_sosial' => 10, 'persen_dana_pendidikan' => 5,
            'persen_jasa_modal' => 50, 'persen_jasa_usaha' => 50, 'created_by' => $maker->id,
        ]);
        $savingType = JenisSimpanan::query()->create(['nama_jenis' => 'Modal SHU', 'wajib' => false, 'nominal_default' => 0]);
        Simpanan::query()->create(['karyawan_id' => $member->karyawan_id, 'jenis_simpanan_id' => $savingType->id, 'jumlah' => 300000, 'tanggal' => '2025-06-01', 'status' => 'posted']);
        $sale = Penjualan::query()->create(['kode_transaksi' => 'PJL-SHU-1', 'karyawan_id' => $member->karyawan_id, 'total_harga' => 500000, 'diskon' => 0, 'grand_total' => 500000]);
        DB::table('penjualan')->where('id', $sale->id)->update(['created_at' => '2025-07-01 10:00:00', 'updated_at' => '2025-07-01 10:00:00']);

        $service = app(AnnualShuService::class);
        $shu = $service->create($period, $maker->id);
        $this->assertSame($shu->id, $service->create($period, $maker->id)->id);
        $shu = $service->calculate($shu, $maker->id);
        $this->assertSame(450000.0, (float) $shu->nominal_shu_anggota);
        $this->assertSame(100000.0, (float) $shu->nominal_dana_sosial);
        $this->assertSame(450000.0, (float) $shu->recipients->sum('nominal_hak'));
        $service->submit($shu, $maker->id);

        try {
            $service->approve($shu->fresh(), $maker->id);
            $this->fail('Maker tidak boleh menyetujui SHU sendiri.');
        } catch (ValidationException) {
            $this->assertSame(ShuKoperasi::STATUS_SUBMITTED, $shu->fresh()->status);
        }
        $shu = $service->approve($shu->fresh(), $approver->id);
        $this->assertSame(ShuKoperasi::STATUS_READY_TO_PAY, $shu->status);
        $this->assertDatabaseHas('dana_sosial_sumber', ['shu_koperasi_id' => $shu->id, 'saldo_tersedia' => 100000]);

        $wallet = DompetKoperasi::query()->create(['akun_id' => Akun::query()->where('kode_akun', '101')->value('id'), 'nama_dompet' => 'Kas Utama', 'jenis_dompet' => 'kas', 'saldo' => 2000000]);
        $recipient = $shu->recipients()->firstOrFail();
        $service->pay($recipient, ['metode' => 'tunai', 'dompet_id' => $wallet->id, 'tanggal_bayar' => '2026-02-01'], $approver->id);
        $this->assertSame(ShuKoperasi::STATUS_COMPLETED, $shu->fresh()->status);
        $this->assertDatabaseHas('pembayaran_shu', ['shu_penerima_id' => $recipient->id, 'jumlah' => 450000]);

        $social = app(SocialFundService::class);
        DanaSosialLimit::query()->create(['kategori' => 'kematian', 'label' => 'Santunan Kematian', 'maksimal' => 100000, 'is_active' => true]);
        $claim = $social->createClaim(['anggota_id' => $member->id, 'penerima_manfaat' => 'Keluarga Anggota', 'kategori' => 'kematian', 'tanggal_kejadian' => '2026-02-02', 'nominal_diajukan' => 50000, 'catatan' => null], $maker->id);
        $social->approveClaim($claim, $approver->id);
        $claim = $social->payClaim($claim->fresh(), ['dompet_id' => $wallet->id, 'metode_pembayaran' => 'tunai', 'tanggal_bayar' => '2026-02-03'], $approver->id);
        $this->assertSame('dibayar', $claim->status);
        $this->assertSame(50000.0, (float) $claim->allocations()->sum('jumlah'));
        $this->assertDatabaseHas('dana_sosial_sumber', ['shu_koperasi_id' => $shu->id, 'saldo_tersedia' => 50000]);
        $this->assertSame(0, Artisan::call('koperasi:preflight-shu'));
        $this->assertSame(0, Artisan::call('koperasi:preflight-dana-sosial'));
    }

    public function test_pool_jabatan_dibagi_dengan_bobot_rat_dan_dikunci_setelah_approval(): void
    {
        $this->seed(AkunSeeder::class);
        $maker = User::factory()->create(['role' => 'admin']);
        $approver = User::factory()->create(['role' => 'admin']);
        $period = PeriodeAkuntansi::query()->create(['kode' => 'FY-X', 'nama' => 'Tahun Buku X', 'tanggal_mulai' => '2025-07-01', 'tanggal_selesai' => '2026-06-30', 'status' => 'closed', 'total_pendapatan' => 1600000, 'total_beban' => 600000, 'laba_bersih' => 1000000, 'created_by' => $maker->id]);
        ShuConfig::query()->create([
            'versi' => 1, 'berlaku_mulai' => '2025-07-01', 'dasar_keputusan' => 'RAT Bobot',
            'persen_dana_cadangan' => 80, 'persen_shu_anggota' => 0, 'persen_pengurus' => 10,
            'persen_pengawas' => 5, 'persen_pembina' => 5, 'persen_dana_sosial' => 0,
            'persen_dana_pendidikan' => 0, 'persen_jasa_modal' => 40, 'persen_jasa_usaha' => 60,
            'created_by' => $maker->id,
        ]);
        $service = app(MasterDataKoperasiService::class);
        foreach ([
            ['Ketua', 'ketua@example.test', 'Ketua Pengurus'],
            ['Bendahara', 'bendahara@example.test', 'Bendahara'],
            ['Ketua Pengawas', 'pengawas1@example.test', 'Ketua Pengawas'],
            ['Anggota Pengawas', 'pengawas2@example.test', 'Anggota Pengawas'],
            ['Pembina', 'pembina@example.test', 'Pembina'],
        ] as [$name, $email, $position]) {
            $member = $this->member($name, $email);
            $service->createPengurus(['anggota_id' => $member->id, 'jabatan' => $position]);
        }

        $annual = app(AnnualShuService::class);
        $shu = $annual->applyPeriod($period, $maker->id);
        $this->assertSame(2, $shu->recipients->where('jenis_penerima', 'pengurus')->count());
        $this->assertSame(50000.0, (float) $shu->recipients->firstWhere('jenis_penerima', 'pengurus')->nominal_hak);
        $alternate = PeriodeAkuntansi::query()->create(['kode' => 'FY-Y', 'nama' => 'Tahun Buku Y', 'tanggal_mulai' => '2028-07-01', 'tanggal_selesai' => '2029-06-30', 'status' => 'closed', 'total_pendapatan' => 2600000, 'total_beban' => 600000, 'laba_bersih' => 2000000, 'created_by' => $maker->id]);
        $shu = $annual->changePeriod($shu, $alternate, $maker->id);
        $this->assertSame($alternate->id, $shu->periode_akuntansi_id);
        $this->assertSame(200000.0, (float) $shu->nominal_pengurus);
        $shu = $annual->changePeriod($shu, $period, $maker->id);
        $this->assertSame($period->id, $shu->periode_akuntansi_id);
        $pengurus = $shu->recipients->where('jenis_penerima', 'pengurus')->values();
        $annual->applyWeights($shu, [$pengurus[0]->id => 1, $pengurus[1]->id => 3], $maker->id);
        $shares = $shu->fresh()->recipients()->where('jenis_penerima', 'pengurus')->orderBy('id')->pluck('nominal_hak')->map(fn ($value) => (float) $value)->all();
        $this->assertSame([25000.0, 75000.0], $shares);
        $annual->submit($shu->fresh(), $maker->id);
        $annual->approve($shu->fresh(), $approver->id);
        $this->assertSame(ShuKoperasi::STATUS_READY_TO_PAY, $shu->fresh()->status);

        try {
            $annual->changePeriod($shu->fresh(), $alternate, $maker->id);
            $this->fail('Periode tidak boleh diganti setelah approval.');
        } catch (ValidationException) {
            $this->assertSame($period->id, $shu->fresh()->periode_akuntansi_id);
        }

        $this->expectException(ValidationException::class);
        $annual->applyWeights($shu->fresh(), [$pengurus[0]->id => 2], $maker->id);
    }

    public function test_donasi_resmi_memakai_maker_checker_dan_masuk_kas_tepat_sekali(): void
    {
        $this->seed(AkunSeeder::class);
        $maker = User::factory()->create(['role' => 'admin']);
        $approver = User::factory()->create(['role' => 'admin']);
        $wallet = DompetKoperasi::query()->create(['akun_id' => Akun::query()->where('kode_akun', '101')->value('id'), 'nama_dompet' => 'Kas Donasi', 'jenis_dompet' => 'kas', 'saldo' => 0]);
        $service = app(SocialFundService::class);
        $source = $service->createDonation(['dompet_id' => $wallet->id, 'jumlah' => 75000, 'tanggal' => '2026-03-01', 'keterangan' => 'Donasi resmi mitra'], $maker->id);

        try {
            $service->approveDonation($source, $maker->id);
            $this->fail('Pembuat donasi tidak boleh menyetujui donasi sendiri.');
        } catch (ValidationException) {
            $this->assertSame('pending', $source->fresh()->status);
        }
        $service->approveDonation($source->fresh(), $approver->id);
        $service->approveDonation($source->fresh(), $approver->id);
        $this->assertSame(75000.0, (float) $wallet->fresh()->saldo);
        $this->assertSame(1, DB::table('mutasi_kas')->where('idempotency_key', 'dana-sosial:donasi:mutasi:' . $source->id)->count());
        $this->assertSame(1, DB::table('jurnal_umum')->where('idempotency_key', 'dana-sosial:donasi:jurnal:' . $source->id)->count());
    }

    private function member(string $name = 'Anggota SHU', string $email = 'anggota.shu@example.test')
    {
        $employee = Karyawan::query()->create(['nama' => $name, 'email' => $email, 'telepon' => '08' . str_pad((string) Karyawan::query()->count(), 8, '0', STR_PAD_LEFT), 'jabatan' => 'Anggota']);
        return app(MasterDataKoperasiService::class)->createAnggota(['karyawan_id' => $employee->id, 'tanggal_bergabung' => '2025-01-01', 'alamat' => 'Alamat test', 'plafon_pinjaman' => 1000000]);
    }
}
