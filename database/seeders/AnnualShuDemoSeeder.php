<?php

namespace Database\Seeders;

use App\Models\Akun;
use App\Models\DompetKoperasi;
use App\Models\JenisSimpanan;
use App\Models\JurnalUmum;
use App\Models\Karyawan;
use App\Models\Pembayaran;
use App\Models\PengurusKoperasi;
use App\Models\PeriodeAkuntansi;
use App\Models\Produk;
use App\Models\ShuConfig;
use App\Models\Simpanan;
use App\Models\User;
use App\Services\AccountingPeriodService;
use App\Services\AkuntansiService;
use App\Services\MasterDataKoperasiService;
use App\Services\MutasiKasService;
use App\Services\PosCheckoutService;
use App\Services\SimpananManasukaService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class AnnualShuDemoSeeder extends Seeder
{
    public function run(): void
    {
        $maker = User::query()->where('email', 'keuangan@kbsm.test')->firstOrFail();
        User::query()->updateOrCreate(
            ['email' => 'persetujuan.shu@kbsm.test'],
            [
                'name' => 'Admin Persetujuan SHU',
                'password' => Hash::make('Kbsm12345!'),
                'role' => 'admin',
                'karyawan_id' => null,
                'is_active' => true,
                'must_change_password' => false,
                'password_changed_at' => now(),
                'email_verified_at' => now(),
            ]
        );

        $period = PeriodeAkuntansi::query()->where('kode', 'TB-2025-2026')->first();
        if (! $period) {
            $period = app(AccountingPeriodService::class)->create([
                'kode' => 'TB-2025-2026',
                'nama' => 'Tahun Buku 2025/2026',
                'tanggal_mulai' => '2025-07-01',
                'tanggal_selesai' => '2026-06-30',
            ], $maker->id);
        }

        $this->seedConfig($maker);
        $this->seedOfficerGroups();
        $this->seedMemberCapitalBasis($maker);
        $this->seedMemberBusinessBasis($maker);
        $this->seedProfitAndCashFlow($period, $maker);
    }

    private function seedConfig(User $maker): void
    {
        if (ShuConfig::query()->whereDate('berlaku_mulai', '2025-07-01')->where('dasar_keputusan', 'Data demonstrasi keputusan RAT Tahun Buku 2025/2026.')->exists()) {
            return;
        }

        ShuConfig::query()->create([
            'versi' => (int) ShuConfig::query()->max('versi') + 1,
            'berlaku_mulai' => '2025-07-01',
            'dasar_keputusan' => 'Data demonstrasi keputusan RAT Tahun Buku 2025/2026.',
            'persen_dana_cadangan' => 30,
            'persen_shu_anggota' => 40,
            'persen_pengurus' => 10,
            'persen_pengawas' => 5,
            'persen_pembina' => 5,
            'persen_dana_sosial' => 5,
            'persen_dana_pendidikan' => 5,
            'persen_jasa_modal' => 40,
            'persen_jasa_usaha' => 60,
            'created_by' => $maker->id,
        ]);
    }

    private function seedOfficerGroups(): void
    {
        $service = app(MasterDataKoperasiService::class);
        foreach ([
            ['rina.marlina@bita.test', 'Ketua Pengawas'],
            ['andi.saputra@bita.test', 'Anggota Pengawas'],
            ['fitri.handayani@bita.test', 'Pembina'],
        ] as [$email, $position]) {
            $member = Karyawan::query()->where('email', $email)->firstOrFail()->anggota()->firstOrFail();
            if (! PengurusKoperasi::query()->where('anggota_id', $member->id)->where('jabatan', $position)->exists()) {
                $service->createPengurus(['anggota_id' => $member->id, 'jabatan' => $position]);
            }
        }
    }

    private function seedMemberCapitalBasis(User $maker): void
    {
        $service = app(SimpananManasukaService::class);
        $wallet = DompetKoperasi::query()->kas()->with('akun')->orderBy('id')->firstOrFail();
        $type = JenisSimpanan::query()->aktif()->where('kategori', JenisSimpanan::KATEGORI_MANASUKA)->firstOrFail();
        foreach ([
            ['andi.saputra@bita.test', 1000000, '2025-09-10'],
            ['siti.rahmawati@bita.test', 2000000, '2025-11-10'],
            ['budi.santoso@bita.test', 3000000, '2026-02-10'],
        ] as [$email, $amount, $date]) {
            $member = Karyawan::query()->where('email', $email)->firstOrFail()->anggota()->firstOrFail();
            $service->create([
                'idempotency_key' => 'demo-shu:modal:' . $member->id,
                'anggota_id' => $member->id,
                'jenis_simpanan_id' => $type->id,
                'dompet_id' => $wallet->id,
                'jenis_transaksi' => Simpanan::JENIS_SETORAN,
                'metode_pembayaran' => Simpanan::METODE_TUNAI,
                'jumlah' => $amount,
                'tanggal' => $date,
                'keterangan' => 'Basis Jasa Modal demonstrasi SHU 2025/2026.',
            ], $maker->id);
        }
    }

    private function seedMemberBusinessBasis(User $maker): void
    {
        $service = app(PosCheckoutService::class);
        $wallet = DompetKoperasi::query()->kas()->orderBy('id')->firstOrFail();
        $product = Produk::query()->where('stok', '>=', 3)->orderBy('id')->firstOrFail();
        foreach ([
            ['andi.saputra@bita.test', '2025-10-15'],
            ['siti.rahmawati@bita.test', '2026-01-15'],
            ['rina.marlina@bita.test', '2026-04-15'],
        ] as [$email, $date]) {
            $member = Karyawan::query()->where('email', $email)->firstOrFail()->anggota()->firstOrFail();
            $key = 'demo-shu:usaha:' . $member->id;
            $sale = \App\Models\Penjualan::query()->where('idempotency_key', $key)->first();
            if (! $sale) {
                $sale = $service->checkout([
                    'idempotency_key' => $key,
                    'tipe_pelanggan' => 'anggota',
                    'anggota_id' => $member->id,
                    'metode_pembayaran' => Pembayaran::METODE_TUNAI,
                    'tanggal_transaksi' => $date,
                    'dompet_id' => $wallet->id,
                    'diskon' => 0,
                    'items' => [['produk_id' => $product->id, 'jumlah' => 1]],
                ], $maker->id);
            }
            DB::table('penjualan')->where('id', $sale->id)->update(['created_at' => $date . ' 10:00:00', 'updated_at' => $date . ' 10:00:00']);
        }
    }

    private function seedProfitAndCashFlow(PeriodeAkuntansi $period, User $maker): void
    {
        $current = $this->profitAndLoss($period);
        $revenueAmount = round(100000000 - $current['revenue'], 2);
        $expenseAmount = round(60000000 - $current['expense'], 2);
        if ($revenueAmount < 0 || $expenseAmount < 0) {
            throw new \RuntimeException('Data transaksi demo melebihi target laporan SHU 2025/2026.');
        }

        $wallet = DompetKoperasi::query()->kas()->with('akun')->orderBy('id')->firstOrFail();
        $revenue = $this->account('pendapatan_penjualan');
        $expense = $this->account('beban_operasional');
        $cash = app(MutasiKasService::class);
        $accounting = app(AkuntansiService::class);

        if ($revenueAmount > 0 && ! JurnalUmum::query()->where('idempotency_key', 'demo-shu:jurnal-pendapatan')->exists()) {
            $cash->record([
                'idempotency_key' => 'demo-shu:mutasi-pendapatan',
                'dompet_id' => $wallet->id,
                'tipe' => 'masuk',
                'jumlah' => $revenueAmount,
                'tanggal' => '2026-05-20',
                'keterangan' => 'Pendapatan demonstrasi Tahun Buku 2025/2026',
                'referensi_tipe' => PeriodeAkuntansi::class,
                'referensi_id' => $period->id,
            ]);
            $accounting->record([
                'idempotency_key' => 'demo-shu:jurnal-pendapatan',
                'tanggal' => '2026-05-20',
                'nomor_bukti' => 'DEMO-TB-2526-PEND',
                'keterangan' => 'Pendapatan demonstrasi Tahun Buku 2025/2026',
                'referensi_tipe' => PeriodeAkuntansi::class,
                'referensi_id' => $period->id,
                'created_by' => $maker->id,
            ], [$this->line($wallet->akun, 'debit', $revenueAmount), $this->line($revenue, 'kredit', $revenueAmount)]);
        }

        if ($expenseAmount > 0 && ! JurnalUmum::query()->where('idempotency_key', 'demo-shu:jurnal-beban')->exists()) {
            $cash->record([
                'idempotency_key' => 'demo-shu:mutasi-beban',
                'dompet_id' => $wallet->id,
                'tipe' => 'keluar',
                'jumlah' => $expenseAmount,
                'tanggal' => '2026-05-25',
                'keterangan' => 'Beban demonstrasi Tahun Buku 2025/2026',
                'referensi_tipe' => PeriodeAkuntansi::class,
                'referensi_id' => $period->id,
            ]);
            $accounting->record([
                'idempotency_key' => 'demo-shu:jurnal-beban',
                'tanggal' => '2026-05-25',
                'nomor_bukti' => 'DEMO-TB-2526-BEB',
                'keterangan' => 'Beban demonstrasi Tahun Buku 2025/2026',
                'referensi_tipe' => PeriodeAkuntansi::class,
                'referensi_id' => $period->id,
                'created_by' => $maker->id,
            ], [$this->line($expense, 'debit', $expenseAmount), $this->line($wallet->akun, 'kredit', $expenseAmount)]);
        }
    }

    private function profitAndLoss(PeriodeAkuntansi $period): array
    {
        $rows = DB::table('jurnal_umum_detail as d')
            ->join('jurnal_umum as j', 'j.id', '=', 'd.jurnal_umum_id')
            ->join('akun as a', 'a.id', '=', 'd.akun_id')
            ->whereBetween('j.tanggal', [$period->tanggal_mulai->toDateString(), $period->tanggal_selesai->toDateString()])
            ->whereIn('a.kategori', ['pendapatan', 'beban'])
            ->groupBy('a.kategori')
            ->selectRaw('a.kategori, SUM(d.debit) debit, SUM(d.kredit) kredit')
            ->get();

        return [
            'revenue' => (float) $rows->where('kategori', 'pendapatan')->sum(fn ($row) => (float) $row->kredit - (float) $row->debit),
            'expense' => (float) $rows->where('kategori', 'beban')->sum(fn ($row) => (float) $row->debit - (float) $row->kredit),
        ];
    }

    private function account(string $key): Akun
    {
        return Akun::query()->where('kode_akun', config("account_map.accounts.{$key}.kode_akun"))->firstOrFail();
    }

    private function line(Akun $account, string $side, float $amount): array
    {
        return [
            'akun_id' => $account->id,
            'akun_kode' => $account->kode_akun,
            'akun_nama' => $account->nama_akun,
            'debit' => $side === 'debit' ? $amount : 0,
            'kredit' => $side === 'kredit' ? $amount : 0,
        ];
    }
}
