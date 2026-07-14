<?php

namespace Tests\Feature;

use App\Models\Anggota;
use App\Models\Akun;
use App\Models\DompetKoperasi;
use App\Models\JadwalCicilanPinjaman;
use App\Models\Karyawan;
use App\Models\MutasiKas;
use App\Models\PenyelesaianKeanggotaan;
use App\Models\Pinjaman;
use App\Models\SiklusKeanggotaan;
use App\Models\Simpanan;
use App\Models\User;
use App\Services\KeanggotaanLifecycleService;
use App\Services\MasterDataKoperasiService;
use App\Services\PinjamanKoperasiService;
use Database\Seeders\AkunSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class KeanggotaanLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_karyawan_keluar_membuat_siklus_penyelesaian_dan_offset_pinjaman_tanpa_cicilan_palsu(): void
    {
        $finance = $this->user('keuangan');
        $anggota = $this->anggota();
        $this->settleSimpananPokok($anggota);

        $pinjaman = app(PinjamanKoperasiService::class)->create([
            'anggota_id' => $anggota->id,
            'dompet_id' => $this->dompet(DompetKoperasi::JENIS_KAS, 1000000)->id,
            'jumlah_pinjaman' => 300000,
            'tenor_bulan' => 3,
            'tanggal_pinjaman' => '2026-07-01',
            'keterangan' => 'Pinjaman untuk uji offset keluar.',
        ], $finance->id);

        app(MasterDataKoperasiService::class)->updateKaryawan($anggota->karyawan, $this->karyawanLifecycleData(
            $anggota->karyawan,
            Karyawan::STATUS_BERHENTI,
            '2026-07-31'
        ));

        $penyelesaian = PenyelesaianKeanggotaan::query()->where('anggota_id', $anggota->id)->firstOrFail();
        $this->assertSame(PenyelesaianKeanggotaan::STATUS_PENDING_REVIEW, $penyelesaian->status);
        $this->assertDatabaseHas('siklus_keanggotaan', [
            'anggota_id' => $anggota->id,
            'status' => SiklusKeanggotaan::STATUS_CLOSED,
            'tanggal_selesai' => '2026-07-31 00:00:00',
        ]);

        $processed = app(KeanggotaanLifecycleService::class)->processOffset($penyelesaian, $finance->id);

        $this->assertSame(PenyelesaianKeanggotaan::STATUS_WAITING_SETTLEMENT, $processed->status);
        $this->assertSame('100000.00', $processed->total_offset);
        $this->assertSame('200000.00', $processed->sisa_kewajiban);
        $this->assertSame('200000.00', $pinjaman->fresh()->sisa_pinjaman);
        $this->assertSame(Pinjaman::STATUS_AKTIF, $pinjaman->fresh()->status);
        $this->assertSame(0, \App\Models\CicilanPinjaman::query()->where('pinjaman_id', $pinjaman->id)->count());
        $this->assertDatabaseHas('jadwal_cicilan_pinjaman', [
            'pinjaman_id' => $pinjaman->id,
            'status' => JadwalCicilanPinjaman::STATUS_PAID,
            'metode_penyelesaian' => JadwalCicilanPinjaman::METODE_OFFSET_SIMPANAN_POKOK,
            'nominal_offset' => '100000.00',
            'nominal_sisa' => '0.00',
        ]);
        $this->assertDatabaseHas('jurnal_umum', [
            'referensi_tipe' => PenyelesaianKeanggotaan::class,
            'referensi_id' => $penyelesaian->id,
            'idempotency_key' => 'keanggotaan:offset:jurnal:' . $penyelesaian->id,
        ]);

        $this->expectException(ValidationException::class);
        app(KeanggotaanLifecycleService::class)->complete($processed, $finance->id);
    }

    public function test_refund_penyelesaian_completed_mengizinkan_reaktivasi_siklus_baru(): void
    {
        $finance = $this->user('keuangan');
        $kasir = $this->user('kasir');
        $anggota = $this->anggota();
        $this->settleSimpananPokok($anggota);
        $refundDompet = $this->dompet(DompetKoperasi::JENIS_KAS, 500000);

        app(MasterDataKoperasiService::class)->updateKaryawan($anggota->karyawan, $this->karyawanLifecycleData(
            $anggota->karyawan,
            Karyawan::STATUS_BERHENTI,
            '2026-07-31'
        ));

        $penyelesaian = PenyelesaianKeanggotaan::query()->where('anggota_id', $anggota->id)->firstOrFail();
        $lifecycle = app(KeanggotaanLifecycleService::class);
        $ready = $lifecycle->processOffset($penyelesaian, $finance->id);
        $this->assertSame(PenyelesaianKeanggotaan::STATUS_READY_TO_COMPLETE, $ready->status);
        $this->assertSame('100000.00', $ready->total_refund);

        $refunded = $lifecycle->processRefund($ready, $refundDompet, $finance->id);
        $completed = $lifecycle->complete($refunded, $finance->id);

        $this->assertSame(PenyelesaianKeanggotaan::STATUS_COMPLETED, $completed->status);
        $this->assertSame('400000.00', $refundDompet->fresh()->saldo);
        $this->assertSame(1, MutasiKas::query()->where('referensi_tipe', PenyelesaianKeanggotaan::class)->where('tipe', 'keluar')->count());

        app(MasterDataKoperasiService::class)->updateKaryawan($anggota->karyawan, $this->karyawanLifecycleData(
            $anggota->karyawan,
            Karyawan::STATUS_AKTIF
        ));
        $this->assertSame(Anggota::STATUS_NONAKTIF, $anggota->fresh()->status);

        app(MasterDataKoperasiService::class)->activateAnggota($anggota);
        $this->assertSame(Anggota::STATUS_AKTIF, $anggota->fresh()->status);
        $this->assertSame(2, SiklusKeanggotaan::query()->where('anggota_id', $anggota->id)->count());
        $this->assertSame(2, Simpanan::query()->where('anggota_id', $anggota->id)->where('kode_jenis_snapshot', 'SIMPANAN_POKOK')->count());

        $this->actingAs($kasir)->get(route('penyelesaian-keanggotaan.index'))->assertForbidden();
        $this->actingAs($finance)->get(route('penyelesaian-keanggotaan.index'))->assertOk();
    }

    private function anggota(): Anggota
    {
        $this->seed(AkunSeeder::class);
        $karyawan = Karyawan::factory()->create(['status_kerja' => Karyawan::STATUS_AKTIF]);

        return app(MasterDataKoperasiService::class)->createAnggota([
            'karyawan_id' => $karyawan->id,
            'tanggal_bergabung' => '2026-01-01',
            'alamat' => 'Jl. Siklus Test No. 1',
            'plafon_pinjaman' => 1000000,
        ]);
    }

    private function settleSimpananPokok(Anggota $anggota): void
    {
        Simpanan::query()
            ->where('anggota_id', $anggota->id)
            ->where('kode_jenis_snapshot', 'SIMPANAN_POKOK')
            ->update([
                'status' => Simpanan::STATUS_SETTLED,
                'settled_at' => now(),
            ]);
    }

    private function dompet(string $jenis, int $saldo): DompetKoperasi
    {
        $this->seed(AkunSeeder::class);
        $akunKey = $jenis === DompetKoperasi::JENIS_BANK ? 'bank' : 'kas';
        $akun = Akun::query()->where('kode_akun', config("account_map.accounts.{$akunKey}.kode_akun"))->firstOrFail();

        return DompetKoperasi::query()->create([
            'akun_id' => $akun->id,
            'nama_dompet' => 'Dompet Lifecycle ' . fake()->unique()->numberBetween(1, 999999),
            'jenis_dompet' => $jenis,
            'saldo' => $saldo,
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

    private function karyawanLifecycleData(Karyawan $karyawan, string $status, ?string $tanggalBerhenti = null): array
    {
        return [
            'nama' => $karyawan->nama,
            'email' => $karyawan->email,
            'telepon' => $karyawan->telepon,
            'jabatan' => $karyawan->jabatan,
            'status_kerja' => $status,
            'tanggal_berhenti' => $tanggalBerhenti,
        ];
    }
}
