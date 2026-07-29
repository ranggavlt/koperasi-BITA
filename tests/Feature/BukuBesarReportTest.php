<?php

namespace Tests\Feature;

use App\Models\Akun;
use App\Models\JurnalUmum;
use App\Models\JurnalUmumDetail;
use App\Models\Karyawan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class BukuBesarReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_hanya_admin_keuangan_yang_dapat_membuka_buku_besar(): void
    {
        $admin = $this->user('admin', 'admin-buku-besar@kbsm.test');
        $kasir = $this->user('kasir', 'kasir-buku-besar@kbsm.test');
        $karyawan = $this->employeeUser('karyawan-buku-besar@kbsm.test');

        $this->actingAs($admin)
            ->get(route('akuntansi.buku-besar'))
            ->assertOk()
            ->assertSee('Buku Besar')
            ->assertSee('Filter Buku Besar');

        $this->actingAs($kasir)
            ->get(route('akuntansi.buku-besar'))
            ->assertForbidden();

        $this->actingAs($karyawan)
            ->get(route('akuntansi.buku-besar'))
            ->assertForbidden();

        auth()->logout();

        $this->get(route('akuntansi.buku-besar'))
            ->assertRedirect(route('login'));
    }

    public function test_filter_periode_akun_dan_perhitungan_saldo_buku_besar_tetap_sesuai_formula_existing(): void
    {
        $admin = $this->user('admin', 'admin-ledger-filter@kbsm.test');
        $kas = $this->akun('901', 'Kas Utama Test', 'aset', 'debit');
        $pendapatan = $this->akun('941', 'Pendapatan POS Test', 'pendapatan', 'kredit');

        $this->jurnal('2026-06-30', 'BB-JUN', 'Saldo sebelum periode', [
            [$kas, 100000, 0],
            [$pendapatan, 0, 100000],
        ]);

        $this->jurnal('2026-07-05', 'BB-JUL-001', 'Setoran POS tunai', [
            [$kas, 50000, 0],
            [$pendapatan, 0, 50000],
        ]);

        $this->jurnal('2026-07-08', 'BB-JUL-002', 'Refund penuh POS', [
            [$kas, 0, 30000],
            [$pendapatan, 30000, 0],
        ]);

        $this->jurnal('2026-08-01', 'BB-AUG-001', 'Transaksi luar periode', [
            [$kas, 999999, 0],
            [$pendapatan, 0, 999999],
        ]);

        $this->actingAs($admin)
            ->get(route('akuntansi.buku-besar', [
                'periode' => '2026-07',
                'akun' => $kas->kode_akun,
            ]))
            ->assertOk()
            ->assertSee('Laporan Akuntansi / Buku Besar')
            ->assertSee('01 Juli 2026 – 31 Juli 2026')
            ->assertSee('value="2026-07"', false)
            ->assertSee('901 - Kas Utama Test')
            ->assertSee('Saldo Normal Debit')
            ->assertSeeInOrder([
                'Saldo Awal',
                'Rp 100.000',
                'Total Debit',
                'Rp 50.000',
                'Total Kredit',
                'Rp 30.000',
                'Saldo Akhir',
                'Rp 120.000',
            ])
            ->assertSee('BB-JUL-001')
            ->assertSee('Setoran POS tunai')
            ->assertSee('Rp 150.000')
            ->assertSee('BB-JUL-002')
            ->assertSee('Refund penuh POS')
            ->assertSee('Rp 120.000')
            ->assertDontSee('BB-JUN')
            ->assertDontSee('BB-AUG-001')
            ->assertDontSee('Rp 999.999')
            ->assertDontSee('Transaksi Terakhir')
            ->assertDontSee('name="_method"', false)
            ->assertDontSee('Hapus');
    }

    public function test_saldo_berlawanan_dengan_saldo_normal_ditampilkan_sebagai_posisi_tidak_normal(): void
    {
        $admin = $this->user('admin', 'admin-ledger-abnormal@kbsm.test');
        $kas = $this->akun('902', 'Kas Abnormal Test', 'aset', 'debit');
        $utang = $this->akun('921', 'Utang Lainnya Test', 'kewajiban', 'kredit');

        $this->jurnal('2026-06-30', 'BB-ABNORMAL', 'Saldo kredit pada akun kas', [
            [$kas, 0, 200000],
            [$utang, 200000, 0],
        ]);

        $this->actingAs($admin)
            ->get(route('akuntansi.buku-besar', [
                'periode' => '2026-07',
                'akun' => $kas->kode_akun,
            ]))
            ->assertOk()
            ->assertSee('Saldo Kredit')
            ->assertSee('Posisi tidak normal')
            ->assertSee('Rp 200.000')
            ->assertSee('Tidak ada transaksi pada akun dan periode yang dipilih.');
    }

    public function test_empty_state_saat_belum_ada_akun_aktif(): void
    {
        $admin = $this->user('admin', 'admin-ledger-empty@kbsm.test');
        Akun::query()->update(['is_aktif' => false]);

        $this->actingAs($admin)
            ->get(route('akuntansi.buku-besar'))
            ->assertOk()
            ->assertSee('Pilih periode dan akun untuk menampilkan Buku Besar.')
            ->assertDontSee('Mutasi Akun');
    }

    private function user(string $role, string $email, array $overrides = []): User
    {
        return User::factory()->create($overrides + [
            'name' => ucfirst($role) . ' Buku Besar',
            'email' => $email,
            'password' => Hash::make('Kbsm12345!'),
            'role' => $role,
            'is_active' => true,
            'must_change_password' => false,
        ]);
    }

    private function employeeUser(string $email): User
    {
        $karyawan = Karyawan::factory()->create([
            'email' => $email,
            'status_kerja' => Karyawan::STATUS_AKTIF,
        ]);

        return $this->user('karyawan', 'user-' . $email, [
            'name' => $karyawan->nama,
            'karyawan_id' => $karyawan->id,
        ]);
    }

    private function akun(string $kode, string $nama, string $kategori, string $posisiSaldo): Akun
    {
        return Akun::query()->create([
            'kode_akun' => $kode,
            'nama_akun' => $nama,
            'kategori' => $kategori,
            'posisi_saldo' => $posisiSaldo,
            'is_aktif' => true,
            'is_sistem' => false,
            'keterangan' => 'Akun test Buku Besar.',
        ]);
    }

    /**
     * @param  array<int, array{0: Akun, 1: int, 2: int}>  $details
     */
    private function jurnal(string $tanggal, string $nomorBukti, string $keterangan, array $details): JurnalUmum
    {
        $jurnal = JurnalUmum::query()->create([
            'tanggal' => $tanggal,
            'nomor_bukti' => $nomorBukti,
            'keterangan' => $keterangan,
            'referensi_tipe' => null,
            'referensi_id' => null,
            'created_by' => null,
        ]);

        foreach ($details as [$akun, $debit, $kredit]) {
            JurnalUmumDetail::query()->create([
                'jurnal_umum_id' => $jurnal->id,
                'akun_id' => $akun->id,
                'akun_kode' => $akun->kode_akun,
                'akun_nama' => $akun->nama_akun,
                'debit' => $debit,
                'kredit' => $kredit,
            ]);
        }

        return $jurnal;
    }
}
