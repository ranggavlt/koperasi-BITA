<?php

namespace Database\Seeders;

use App\Models\Akun;
use App\Models\Anggota;
use App\Models\DompetKoperasi;
use App\Models\JenisManfaatDanaSosial;
use App\Models\JenisSimpanan;
use App\Models\JurnalUmum;
use App\Models\KebijakanManfaatDanaSosial;
use App\Models\KlaimDanaSosial;
use App\Models\Pembayaran;
use App\Models\Penjualan;
use App\Models\PeriodeAkuntansi;
use App\Models\Produk;
use App\Models\ShuConfig;
use App\Models\ShuKoperasi;
use App\Models\ShuPenerima;
use App\Models\Simpanan;
use App\Models\StrukturKoperasi;
use App\Models\User;
use App\Services\AccountingPeriodService;
use App\Services\AkuntansiService;
use App\Services\AnnualShuService;
use App\Services\MutasiKasService;
use App\Services\PosCheckoutService;
use App\Services\SocialFundService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class AnnualShuDemoSeeder extends Seeder
{
    private const PERIOD_CODE = 'TB-2025-2026';

    public function run(): void
    {
        if (app()->environment('production')) {
            throw new RuntimeException('AnnualShuDemoSeeder dilarang berjalan di production.');
        }
        $maker = User::query()->where('email', 'keuangan@kbsm.test')->first()
            ?? User::query()->where('role', 'admin')->firstOrFail();
        $checker = User::query()->updateOrCreate(['email' => 'persetujuan.shu@kbsm.test'], [
            'name' => 'Admin Persetujuan SHU', 'password' => Hash::make('Kbsm12345!'), 'role' => 'admin',
            'karyawan_id' => null, 'is_active' => true, 'must_change_password' => false,
            'password_changed_at' => now(), 'email_verified_at' => now(),
        ]);
        $members = Anggota::query()->with(['karyawan', 'siklusKeanggotaan'])->orderBy('id')->limit(8)->get();
        if ($members->count() < 6) throw new RuntimeException('Demo SHU memerlukan minimal enam Anggota. Jalankan seeder data dasar terlebih dahulu.');

        $this->seedConfig($maker);
        $this->seedStructures($members, $maker);
        $period = PeriodeAkuntansi::query()->where('kode', self::PERIOD_CODE)->first();
        if (! $period) {
            $period = app(AccountingPeriodService::class)->create([
                'kode' => self::PERIOD_CODE, 'nama' => 'Tahun Buku 2025/2026',
                'tanggal_mulai' => '2025-07-01', 'tanggal_selesai' => '2026-06-30',
            ], $maker->id);
        }
        if ($period->status === PeriodeAkuntansi::STATUS_OPEN) {
            $this->seedWajibForAll($members, $maker);
            $this->seedManasuka($members, $maker);
            $this->seedSales($members, $maker);
            $this->seedProfitAndCashFlow($period, $maker);
            $period = app(AccountingPeriodService::class)->close($period, 'Penutupan data demonstrasi untuk RAT dan SHU 2025/2026', $maker->id);
        }

        $annual = app(AnnualShuService::class);
        $shu = ShuKoperasi::query()->where('periode_akuntansi_id', $period->id)->first()
            ?? $annual->applyPeriod($period, $maker->id);
        if ($shu->status !== ShuKoperasi::STATUS_APPROVED) {
            $shu = $annual->calculate($shu, $maker->id);
            $this->makeAuditedFinalDifference($shu, $maker, $annual);
            $annual->prepareApproval($shu->fresh(), $maker->id);
            $shu = $annual->approve($shu->fresh(), $checker->id);
        }
        if ((int) $shu->nominal_dana_sosial !== 4_000_000) {
            throw new RuntimeException('Demo wajib menghasilkan alokasi Dana Sosial tepat Rp4.000.000.');
        }
        $this->seedShuPayments($shu, $checker, $annual);
        $this->seedSocialPoliciesAndClaims($members, $maker, $checker);
    }

    private function seedConfig(User $maker): void
    {
        if (ShuConfig::query()->whereDate('berlaku_mulai', '2025-07-01')
            ->where('dasar_keputusan', 'Data demonstrasi keputusan RAT Tahun Buku 2025/2026 (final).')->exists()) return;

        ShuConfig::query()->create([
            'versi' => (int) ShuConfig::query()->max('versi') + 1,
            'berlaku_mulai' => '2025-07-01', 'dasar_keputusan' => 'Data demonstrasi keputusan RAT Tahun Buku 2025/2026 (final).',
            'persen_dana_cadangan' => 30, 'persen_shu_anggota' => 40,
            'persen_pengurus' => 10, 'persen_pengawas' => 5, 'persen_pembina' => 5,
            'persen_dana_sosial' => 10, 'persen_dana_pendidikan' => 0,
            'persen_jasa_modal' => 40, 'persen_jasa_usaha' => 60, 'created_by' => $maker->id,
        ]);
    }

    private function seedStructures($members, User $maker): void
    {
        $rows = [
            [0, 'pengurus', 'Ketua'], [1, 'pengurus', 'Sekretaris'], [2, 'pengurus', 'Bendahara'],
            [3, 'pengawas', 'Ketua Pengawas'], [4, 'pengawas', 'Anggota Pengawas'],
            [5, 'pembina', 'Pembina'],
        ];
        foreach ($rows as [$index, $group, $position]) {
            $exists = StrukturKoperasi::query()->where('anggota_id', $members[$index]->id)
                ->where('kelompok', $group)->where('jabatan', $position)
                ->whereDate('tanggal_mulai', '2025-07-01')->exists();
            if (! $exists) {
                StrukturKoperasi::query()->create([
                    'anggota_id' => $members[$index]->id, 'kelompok' => $group,
                    'jabatan' => $position, 'tanggal_mulai' => '2025-07-01',
                    'status' => StrukturKoperasi::STATUS_AKTIF,
                    'dasar_keputusan' => 'SK Struktur Demo RAT 2025/2026', 'created_by' => $maker->id,
                ]);
            }
        }
    }

    private function seedWajibForAll($members, User $maker): void
    {
        $type = JenisSimpanan::query()->where('kode', JenisSimpanan::KODE_SIMPANAN_WAJIB)->firstOrFail();
        foreach ($members as $member) {
            $cycle = $member->siklusKeanggotaan->first();
            $existingWajib = Simpanan::query()->where('anggota_id', $member->id)
                ->where('kode_jenis_snapshot', JenisSimpanan::KODE_SIMPANAN_WAJIB)
                ->whereNotIn('status', [Simpanan::STATUS_REVERSED, Simpanan::STATUS_REVERSED_DUE_TO_EXIT, Simpanan::STATUS_CANCELLED])
                ->first();
            if ($existingWajib) {
                if (! in_array($existingWajib->status, [Simpanan::STATUS_SETTLED, Simpanan::STATUS_SETTLED_CASH, Simpanan::STATUS_SETTLED_OFFSET], true)) {
                    $existingWajib->update(['status' => Simpanan::STATUS_SETTLED, 'tanggal' => '2025-07-10', 'settled_at' => '2025-07-10 09:00:00']);
                }
                continue;
            }
            Simpanan::query()->firstOrCreate(['idempotency_key' => 'demo-shu:wajib:' . $member->id], [
                'kode_transaksi' => 'SW-DEMO-' . str_pad((string) $member->id, 5, '0', STR_PAD_LEFT),
                'karyawan_id' => $member->karyawan_id, 'anggota_id' => $member->id,
                'siklus_keanggotaan_id' => $cycle?->id, 'jenis_simpanan_id' => $type->id,
                'kode_jenis_snapshot' => JenisSimpanan::KODE_SIMPANAN_WAJIB,
                'nama_jenis_snapshot' => 'Simpanan Wajib', 'nominal_snapshot' => 10_000,
                'jumlah' => 10_000, 'jenis_transaksi' => Simpanan::JENIS_SETORAN,
                'status' => Simpanan::STATUS_SETTLED, 'tanggal' => '2025-07-10',
                'settled_at' => '2025-07-10 09:00:00', 'created_by' => $maker->id,
                'keterangan' => 'Wajib Rp10.000 demo per siklus',
            ]);
        }
    }

    private function seedManasuka($members, User $maker): void
    {
        $type = JenisSimpanan::query()->where('kode', JenisSimpanan::KODE_SIMPANAN_MANASUKA)->firstOrFail();
        foreach ([0 => 1_000_000, 1 => 2_000_000, 2 => 3_000_000] as $index => $amount) {
            $member = $members[$index]; $cycle = $member->siklusKeanggotaan->first();
            Simpanan::query()->firstOrCreate(['idempotency_key' => 'demo-shu:manasuka:' . $member->id], [
                'kode_transaksi' => 'SM-DEMO-' . str_pad((string) $member->id, 5, '0', STR_PAD_LEFT),
                'karyawan_id' => $member->karyawan_id, 'anggota_id' => $member->id,
                'siklus_keanggotaan_id' => $cycle?->id, 'jenis_simpanan_id' => $type->id,
                'kode_jenis_snapshot' => JenisSimpanan::KODE_SIMPANAN_MANASUKA,
                'nama_jenis_snapshot' => 'Simpanan Manasuka', 'jumlah' => $amount,
                'jenis_transaksi' => Simpanan::JENIS_SETORAN, 'status' => Simpanan::STATUS_SETTLED,
                'tanggal' => '2025-09-10', 'settled_at' => '2025-09-10 09:00:00',
                'created_by' => $maker->id, 'keterangan' => 'Basis Jasa Modal demo',
            ]);
        }
    }

    private function seedSales($members, User $maker): void
    {
        $service = app(PosCheckoutService::class);
        $wallet = DompetKoperasi::query()->kas()->orderBy('id')->firstOrFail();
        $product = Produk::query()->where('stok', '>=', 3)->orderBy('id')->firstOrFail();
        foreach ([0 => '2025-10-15', 1 => '2026-01-15', 2 => '2026-04-15'] as $index => $date) {
            $member = $members[$index]; $key = 'demo-shu:usaha:' . $member->id;
            if (Penjualan::query()->where('idempotency_key', $key)->exists()) continue;
            $service->checkout([
                'idempotency_key' => $key, 'tipe_pelanggan' => Penjualan::TIPE_ANGGOTA,
                'anggota_id' => $member->id, 'metode_pembayaran' => Pembayaran::METODE_TUNAI,
                'tanggal_transaksi' => $date, 'dompet_id' => $wallet->id, 'diskon' => 0,
                'items' => [['produk_id' => $product->id, 'jumlah' => 1]],
            ], $maker->id);
        }
    }

    private function seedProfitAndCashFlow(PeriodeAkuntansi $period, User $maker): void
    {
        $rows = \DB::table('jurnal_umum_detail as d')->join('jurnal_umum as j', 'j.id', '=', 'd.jurnal_umum_id')
            ->join('akun as a', 'a.id', '=', 'd.akun_id')->where('j.status', 'posted')
            ->whereBetween('j.tanggal', [$period->tanggal_mulai, $period->tanggal_selesai])
            ->whereIn('a.kategori', ['pendapatan', 'beban'])->groupBy('a.kategori')
            ->selectRaw('a.kategori, SUM(d.debit) debit, SUM(d.kredit) kredit')->get();
        $currentRevenue = (int) $rows->where('kategori', 'pendapatan')->sum(fn($row) => $row->kredit - $row->debit);
        $currentExpense = (int) $rows->where('kategori', 'beban')->sum(fn($row) => $row->debit - $row->kredit);
        $revenueAmount = 100_000_000 - $currentRevenue; $expenseAmount = 60_000_000 - $currentExpense;
        $wallet = DompetKoperasi::query()->kas()->with('akun')->orderBy('id')->firstOrFail();
        $accounting = app(AkuntansiService::class); $cash = app(MutasiKasService::class);
        foreach ([['pendapatan', $revenueAmount, '2026-05-20'], ['beban', $expenseAmount, '2026-05-25']] as [$kind, $difference, $date]) {
            if ($difference === 0) continue;
            $amount = abs($difference);
            $direction = $difference > 0 ? 'tambah' : 'koreksi';
            $key = 'demo-shu:jurnal-' . $kind . '-' . $direction . '-' . $amount;
            if (JurnalUmum::query()->where('idempotency_key', $key)->exists()) continue;
            $other = $this->account($kind === 'pendapatan' ? 'pendapatan_penjualan' : 'beban_operasional');
            $cashIn = ($kind === 'pendapatan' && $difference > 0) || ($kind === 'beban' && $difference < 0);
            $cash->record(['idempotency_key' => str_replace('jurnal', 'mutasi', $key), 'dompet_id' => $wallet->id,
                'tipe' => $cashIn ? 'masuk' : 'keluar', 'jumlah' => $amount, 'tanggal' => $date,
                'keterangan' => ucfirst($kind) . ' demo tahun buku', 'referensi_tipe' => PeriodeAkuntansi::class, 'referensi_id' => $period->id]);
            $accounting->record(['idempotency_key' => $key, 'tanggal' => $date, 'nomor_bukti' => strtoupper(str_replace(':', '-', $key)),
                'keterangan' => ucfirst($kind) . ' demo tahun buku', 'referensi_tipe' => PeriodeAkuntansi::class,
                'referensi_id' => $period->id, 'created_by' => $maker->id], $cashIn
                ? [$this->line($wallet->akun, 'debit', $amount), $this->line($other, 'kredit', $amount)]
                : [$this->line($other, 'debit', $amount), $this->line($wallet->akun, 'kredit', $amount)]);
        }
    }

    private function makeAuditedFinalDifference(ShuKoperasi $shu, User $maker, AnnualShuService $annual): void
    {
        $rows = $shu->recipients()->where('jenis_penerima', 'anggota')->where('diikutkan', true)->orderByDesc('hak_final')->limit(2)->get();
        if ($rows->count() < 2 || $rows[1]->finalRight() < 10_000) return;
        $annual->setFinalRight($rows[0], $rows[0]->finalRight() + 10_000, 'keputusan_rat', 'Penyesuaian demonstrasi keputusan RAT.', $maker->id);
        $annual->setFinalRight($rows[1], $rows[1]->finalRight() - 10_000, 'keputusan_rat', 'Penyeimbang demonstrasi keputusan RAT.', $maker->id);
    }

    private function seedShuPayments(ShuKoperasi $shu, User $checker, AnnualShuService $annual): void
    {
        $paidCount = $shu->recipients()->whereHas('pembayaran', fn ($query) => $query->where('status', 'paid'))->count();
        if ($paidCount >= 2) return;

        $cashWallet = DompetKoperasi::query()->kas()->with('akun')->orderByDesc('saldo')->firstOrFail();
        $bankWallet = DompetKoperasi::query()->bank()->with('akun')->orderByDesc('saldo')->first();
        $recipients = $shu->recipients()->where('diikutkan', true)->where('hak_final', '>', 0)
            ->whereDoesntHave('pembayaran')->orderBy('id')->limit(2 - $paidCount)->get();
        foreach ($recipients as $index => $recipient) {
            $wallet = $index === 1 && $bankWallet && (int) $bankWallet->saldo >= $recipient->finalRight() ? $bankWallet : $cashWallet;
            $method = $wallet->jenis_dompet === DompetKoperasi::JENIS_BANK ? 'transfer_bank' : 'tunai';
            $annual->pay($recipient, ['metode' => $method, 'dompet_id' => $wallet->id,
                'tanggal_bayar' => '2026-08-03', 'nomor_referensi' => 'DEMO-SHU-' . $recipient->id,
                'catatan' => 'Pembayaran demonstrasi'], $checker->id);
        }
    }

    private function seedSocialPoliciesAndClaims($members, User $maker, User $checker): void
    {
        $service = app(SocialFundService::class);
        $benefitNames = [
            'MENINGGAL' => 'Keluarga Meninggal', 'MELAHIRKAN' => 'Melahirkan',
            'KHITAN' => 'Anak Khitan', 'MENIKAH' => 'Menikah', 'SANTUNAN_ANGGOTA' => 'Santunan Anggota',
        ];
        foreach ($benefitNames as $code => $name) {
            $benefit = JenisManfaatDanaSosial::query()->firstOrCreate(['kode' => $code], ['nama' => $name, 'is_active' => true, 'created_by' => $maker->id]);
            $service->saveBenefitPolicy(['jenis_manfaat_id' => $benefit->id, 'batas_maksimal' => 500_000,
                'berlaku_mulai' => '2026-07-01', 'dasar_keputusan' => 'Kebijakan demo RAT 2025/2026',
                'dokumen_diperlukan' => 'Identitas dan bukti kejadian'], $maker->id);
        }
        $benefits = JenisManfaatDanaSosial::query()->whereIn('kode', array_keys($benefitNames))->orderBy('id')->get();
        $claims = collect();
        foreach (['submitted', 'approved', 'paid', 'rejected', 'waiting_funds'] as $index => $targetStatus) {
            $key = 'demo:dana-sosial:klaim:' . $targetStatus;
            $claim = KlaimDanaSosial::query()->where('idempotency_key', $key)->first();
            if (! $claim) {
                $claim = $service->createClaim(['anggota_id' => $members[$index]->id,
                    'penerima_manfaat' => $members[$index]->karyawan?->nama ?? 'Penerima Demo',
                    'hubungan_penerima' => 'Diri sendiri', 'jenis_manfaat_id' => $benefits[$index]->id,
                    'tanggal_kejadian' => '2026-07-' . str_pad((string) ($index + 10), 2, '0', STR_PAD_LEFT),
                    'nominal_diajukan' => 200_000 + ($index * 25_000), 'catatan' => 'Klaim demo ' . $targetStatus,
                    'idempotency_key' => $key], $maker->id);
            }
            $claims->put($targetStatus, $claim->fresh());
        }
        if ($claims['approved']->status === KlaimDanaSosial::STATUS_SUBMITTED)
            $service->approveClaim($claims['approved'], 200_000, 'Dokumen demo telah diverifikasi.', $checker->id);
        if ($claims['paid']->status === KlaimDanaSosial::STATUS_SUBMITTED)
            $service->approveClaim($claims['paid'], 200_000, 'Dokumen demo telah diverifikasi.', $checker->id);
        if ($claims['rejected']->status === KlaimDanaSosial::STATUS_SUBMITTED)
            $service->rejectClaim($claims['rejected'], 'Dokumen demo belum memenuhi kebijakan.', $checker->id);
        if ($claims['waiting_funds']->status === KlaimDanaSosial::STATUS_SUBMITTED)
            $service->approveClaim($claims['waiting_funds'], 200_000, 'Disetujui untuk demo menunggu dana.', $checker->id);
        $paid = $claims['paid']->fresh();
        if ($paid->status === KlaimDanaSosial::STATUS_APPROVED) {
            $wallet = DompetKoperasi::query()->kas()->orderByDesc('saldo')->firstOrFail();
            $service->payClaim($paid, ['dompet_id' => $wallet->id, 'metode_pembayaran' => 'tunai',
                'tanggal_bayar' => '2026-08-04', 'nomor_referensi' => 'DEMO-KLAIM-PAID'], $checker->id);
        }
        $waiting = $claims['waiting_funds']->fresh();
        if ($waiting->status === KlaimDanaSosial::STATUS_APPROVED) {
            $emptyWallet = DompetKoperasi::query()->firstOrCreate(['nama_dompet' => 'Kas Demo Menunggu Dana'], [
                'jenis_dompet' => DompetKoperasi::JENIS_KAS, 'akun_id' => $this->account('kas')->id,
                'saldo' => 0, 'saldo_awal' => 0,
            ]);
            $service->payClaim($waiting, ['dompet_id' => $emptyWallet->id, 'metode_pembayaran' => 'tunai',
                'tanggal_bayar' => '2026-08-05', 'nomor_referensi' => 'DEMO-KLAIM-WAIT'], $checker->id);
        }
    }

    private function account(string $key): Akun
    {
        return Akun::query()->where('kode_akun', config("account_map.accounts.{$key}.kode_akun"))->firstOrFail();
    }

    private function line(Akun $account, string $side, int $amount): array
    {
        return ['akun_id' => $account->id, 'akun_kode' => $account->kode_akun, 'akun_nama' => $account->nama_akun,
            'debit' => $side === 'debit' ? $amount : 0, 'kredit' => $side === 'kredit' ? $amount : 0];
    }
}
