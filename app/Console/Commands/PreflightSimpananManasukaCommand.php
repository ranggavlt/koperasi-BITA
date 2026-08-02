<?php

namespace App\Console\Commands;

use App\Models\JenisSimpanan;
use App\Models\JurnalUmum;
use App\Models\MutasiKas;
use App\Models\ReversalTransaksi;
use App\Models\SaldoSimpananManasuka;
use App\Models\Simpanan;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PreflightSimpananManasukaCommand extends Command
{
    protected $signature = 'koperasi:preflight-simpanan-manasuka';

    protected $description = 'Audit read-only saldo, setoran, penarikan, dan koreksi Simpanan Manasuka.';

    public function handle(): int
    {
        $checks = [
            $this->check('schema_missing', 'Schema SP-3 Simpanan Manasuka belum lengkap', $this->schemaMissing()),
            $this->check('legacy_sukarela_table', 'Tabel legacy saldo_simpanan_sukarela masih aktif', $this->legacySukarelaTable()),
            $this->check('jenis_duplicate_active', 'Master Jenis Simpanan aktif duplikat per kategori', $this->duplicateActiveJenisSimpananKategori()),
            $this->check('legacy_sukarela_master', 'Master legacy Sukarela masih ada', $this->legacySukarelaMaster()),
            $this->check('manasuka_without_master', 'Transaksi Manasuka tanpa master Manasuka aktif', $this->manasukaTransactionWithoutActiveMaster()),
            $this->check('saldo_manasuka_orphan', 'Saldo Manasuka orphan atau memakai master non-Manasuka', $this->orphanSaldoManasukaMaster()),
            $this->check('saldo_duplicate', 'Saldo duplikat per Anggota/Siklus/Jenis', $this->duplicateSaldo()),
            $this->check('saldo_negative', 'Saldo cached Simpanan Manasuka negatif', $this->negativeSaldo()),
            $this->check('saldo_cached_mismatch', 'Saldo cached berbeda dari transaksi immutable', $this->cachedBalanceMismatch()),
            $this->check('reference_invalid', 'Referensi Anggota/Siklus/Jenis/Dompet tidak valid', $this->invalidReferences()),
            $this->check('manual_pokok_wajib', 'Pokok/Wajib terindikasi dibuat lewat form manual Manasuka', $this->manualPokokWajib()),
            $this->check('setoran_mutasi_keluar', 'Setoran Manasuka mempunyai Mutasi keluar', $this->setoranWithMutasiKeluar()),
            $this->check('penarikan_mutasi_masuk', 'Penarikan Manasuka mempunyai Mutasi masuk', $this->penarikanWithMutasiMasuk()),
            $this->check('method_dompet_mismatch', 'Metode pembayaran tidak sesuai jenis Dompet', $this->methodDompetMismatch()),
            $this->check('posting_missing', 'Mutasi/Jurnal Simpanan Manasuka hilang atau ganda', $this->missingOrDuplicatePosting()),
            $this->check('journal_mismatch', 'Jurnal Simpanan Manasuka tidak sesuai COA/metode', $this->journalMismatch()),
            $this->check('journal_unbalanced', 'Jurnal Simpanan Manasuka/Koreksi tidak berimbang', $this->unbalancedJournal()),
            $this->check('code_duplicate', 'Kode transaksi SMN duplikat/invalid atau SSK masih aktif', $this->duplicateOrInvalidCode()),
            $this->check('idempotency_duplicate', 'Idempotency Simpanan/Mutasi/Jurnal/Koreksi duplikat', $this->duplicateIdempotency()),
            $this->check('legacy_source_type', 'Polymorphic source_type legacy Sukarela masih aktif', $this->legacySourceType()),
            $this->check('manasuka_in_payroll', 'Simpanan Manasuka masuk ledger potong gaji secara tidak sah', $this->manasukaInPayroll()),
            $this->check('double_correction', 'Lebih dari satu koreksi untuk satu transaksi', $this->doubleCorrection()),
            $this->check('corrected_without_reversal', 'Transaksi Dikoreksi tanpa record reversal', $this->correctedWithoutReversal()),
            $this->check('historical_overdraw', 'Riwayat penarikan pernah membuat saldo negatif', $this->historicalOverdraw()),
            $this->check('nonactive_direct_transaction', 'Anggota/Karyawan nonaktif memiliki transaksi langsung baru', $this->nonactiveDirectTransaction()),
            $this->check('old_cycle_balance_moved', 'Saldo siklus lama berpotensi dipindahkan otomatis ke siklus baru', $this->oldCycleBalanceMoved()),
            $this->check('orphan_settlement', 'Penyelesaian Keanggotaan merujuk Simpanan Manasuka yang tidak valid', $this->orphanSettlement()),
        ];

        $this->newLine();
        $this->info('Ringkasan preflight Simpanan Manasuka');
        $this->table(
            ['Kode', 'Pemeriksaan', 'Count', 'Severity'],
            array_map(fn (array $check) => [
                $check['code'],
                $check['label'],
                $check['count'],
                $check['critical'] ? 'critical' : 'info',
            ], $checks)
        );

        $criticalCount = collect($checks)
            ->filter(fn (array $check) => $check['critical'] && $check['count'] > 0)
            ->count();

        if ($criticalCount > 0) {
            $this->error('Preflight Simpanan Manasuka menemukan konflik kritis. Command ini tidak menulis database.');

            return self::FAILURE;
        }

        $this->info('Preflight Simpanan Manasuka bersih: tidak ada konflik kritis.');

        return self::SUCCESS;
    }

    private function check(string $code, string $label, int $count, bool $critical = true): array
    {
        return compact('code', 'label', 'count', 'critical');
    }

    private function schemaMissing(): int
    {
        if (! $this->hasTables(['saldo_simpanan_manasuka', 'simpanan', 'mutasi_kas', 'jurnal_umum', 'jurnal_umum_detail'])) {
            return 1;
        }

        foreach (['kode_transaksi', 'jenis_transaksi', 'dompet_id', 'saldo_sebelum_snapshot', 'saldo_sesudah_snapshot', 'nomor_referensi'] as $column) {
            if (! Schema::hasColumn('simpanan', $column)) {
                return 1;
            }
        }

        return 0;
    }

    private function duplicateActiveJenisSimpananKategori(): int
    {
        if (! Schema::hasTable('jenis_simpanan')) {
            return 0;
        }

        return DB::table('jenis_simpanan')
            ->select('kategori', DB::raw('COUNT(*) as total'))
            ->where('aktif', true)
            ->whereIn('kategori', [
                JenisSimpanan::KATEGORI_POKOK,
                JenisSimpanan::KATEGORI_WAJIB,
                JenisSimpanan::KATEGORI_MANASUKA,
            ])
            ->groupBy('kategori')
            ->having('total', '>', 1)
            ->get()
            ->count();
    }

    private function legacySukarelaTable(): int
    {
        return Schema::hasTable('saldo_simpanan_sukarela') ? 1 : 0;
    }

    private function legacySukarelaMaster(): int
    {
        if (! Schema::hasTable('jenis_simpanan')) {
            return 0;
        }

        return DB::table('jenis_simpanan')
            ->where(function ($query): void {
                $query->where('kode', 'SIMPANAN_SUKARELA')
                    ->orWhere('kategori', 'sukarela')
                    ->orWhere('nama_jenis', 'Simpanan Sukarela');
            })
            ->count();
    }

    private function manasukaTransactionWithoutActiveMaster(): int
    {
        if (! $this->hasTables(['simpanan', 'jenis_simpanan']) || ! Schema::hasColumn('simpanan', 'kode_jenis_snapshot')) {
            return 0;
        }

        $hasActiveMaster = DB::table('jenis_simpanan')
            ->where('kode', JenisSimpanan::KODE_SIMPANAN_MANASUKA)
            ->where('kategori', JenisSimpanan::KATEGORI_MANASUKA)
            ->where('aktif', true)
            ->exists();

        if ($hasActiveMaster) {
            return 0;
        }

        return DB::table('simpanan as s')
            ->leftJoin('jenis_simpanan as js', 'js.id', '=', 's.jenis_simpanan_id')
            ->where(function ($query): void {
                $query->where('s.kode_jenis_snapshot', JenisSimpanan::KODE_SIMPANAN_MANASUKA)
                    ->orWhere('js.kode', JenisSimpanan::KODE_SIMPANAN_MANASUKA)
                    ->orWhere('js.kategori', JenisSimpanan::KATEGORI_MANASUKA);
            })
            ->count('s.id');
    }

    private function orphanSaldoManasukaMaster(): int
    {
        if (! $this->hasTables(['saldo_simpanan_manasuka', 'jenis_simpanan'])) {
            return 0;
        }

        return DB::table('saldo_simpanan_manasuka as saldo')
            ->leftJoin('jenis_simpanan as js', 'js.id', '=', 'saldo.jenis_simpanan_id')
            ->where(function ($query): void {
                $query->whereNull('js.id')
                    ->orWhere('js.kode', '!=', JenisSimpanan::KODE_SIMPANAN_MANASUKA)
                    ->orWhere('js.kategori', '!=', JenisSimpanan::KATEGORI_MANASUKA);
            })
            ->count('saldo.id');
    }

    private function duplicateSaldo(): int
    {
        if (! Schema::hasTable('saldo_simpanan_manasuka')) {
            return 0;
        }

        return DB::table('saldo_simpanan_manasuka')
            ->select('anggota_id', 'siklus_keanggotaan_id', 'jenis_simpanan_id', DB::raw('COUNT(*) as total'))
            ->groupBy('anggota_id', 'siklus_keanggotaan_id', 'jenis_simpanan_id')
            ->having('total', '>', 1)
            ->get()
            ->count();
    }

    private function negativeSaldo(): int
    {
        if (! Schema::hasTable('saldo_simpanan_manasuka')) {
            return 0;
        }

        return DB::table('saldo_simpanan_manasuka')
            ->where('saldo', '<', 0)
            ->count();
    }

    private function cachedBalanceMismatch(): int
    {
        if (! $this->hasTables(['saldo_simpanan_manasuka', 'simpanan']) || ! $this->hasSimpananSp3Columns()) {
            return 0;
        }

        return DB::table('saldo_simpanan_manasuka')
            ->orderBy('id')
            ->get()
            ->filter(function ($row): bool {
                $calculated = DB::table('simpanan')
                    ->where('anggota_id', $row->anggota_id)
                    ->where('siklus_keanggotaan_id', $row->siklus_keanggotaan_id)
                    ->where('jenis_simpanan_id', $row->jenis_simpanan_id)
                    ->where('status', Simpanan::STATUS_SETTLED)
                    ->whereIn('jenis_transaksi', [Simpanan::JENIS_SETORAN, Simpanan::JENIS_PENARIKAN])
                    ->get(['jenis_transaksi', 'jumlah'])
                    ->reduce(function (float $saldo, $simpanan): float {
                        return $simpanan->jenis_transaksi === Simpanan::JENIS_SETORAN
                            ? $saldo + (float) $simpanan->jumlah
                            : $saldo - (float) $simpanan->jumlah;
                    }, 0.0);

                $settlementUsed = $this->settlementAllocationForSaldo((int) $row->id);

                return abs(($calculated - $settlementUsed) - (float) $row->saldo) > 0.01;
            })
            ->count();
    }

    private function settlementAllocationForSaldo(int $saldoId): float
    {
        if (! Schema::hasTable('penyelesaian_keanggotaan_detail')
            || ! Schema::hasColumn('penyelesaian_keanggotaan_detail', 'nominal_dipakai_offset')
            || ! Schema::hasColumn('penyelesaian_keanggotaan_detail', 'nominal_direfund')) {
            return 0.0;
        }

        return (float) DB::table('penyelesaian_keanggotaan_detail')
            ->where('source_type', SaldoSimpananManasuka::class)
            ->where('source_id', $saldoId)
            ->sum(DB::raw('CAST(nominal_dipakai_offset AS DECIMAL(15,2)) + CAST(nominal_direfund AS DECIMAL(15,2))'));
    }

    private function invalidReferences(): int
    {
        if (! $this->hasTables(['simpanan', 'anggota', 'siklus_keanggotaan', 'jenis_simpanan', 'dompet_koperasi']) || ! $this->hasSimpananSp3Columns()) {
            return 0;
        }

        return $this->manasukaBase()
            ->leftJoin('anggota as a', 'a.id', '=', 's.anggota_id')
            ->leftJoin('siklus_keanggotaan as sk', 'sk.id', '=', 's.siklus_keanggotaan_id')
            ->leftJoin('dompet_koperasi as d', 'd.id', '=', 's.dompet_id')
            ->whereIn('s.jenis_transaksi', [Simpanan::JENIS_SETORAN, Simpanan::JENIS_PENARIKAN])
            ->where(function ($query): void {
                $query->whereNull('a.id')
                    ->orWhereNull('sk.id')
                    ->orWhereNull('d.id')
                    ->orWhereNull('s.kode_transaksi')
                    ->orWhereNull('s.saldo_sebelum_snapshot')
                    ->orWhereNull('s.saldo_sesudah_snapshot');
            })
            ->count('s.id');
    }

    private function manualPokokWajib(): int
    {
        if (! $this->hasTables(['simpanan', 'jenis_simpanan']) || ! $this->hasSimpananSp3Columns()) {
            return 0;
        }

        return DB::table('simpanan as s')
            ->join('jenis_simpanan as js', 'js.id', '=', 's.jenis_simpanan_id')
            ->whereIn('js.kategori', [JenisSimpanan::KATEGORI_POKOK, JenisSimpanan::KATEGORI_WAJIB])
            ->whereIn('s.jenis_transaksi', [Simpanan::JENIS_SETORAN, Simpanan::JENIS_PENARIKAN])
            ->where(function ($query): void {
                $query->where('s.kode_transaksi', 'like', 'SMN-%')
                    ->orWhere('s.idempotency_key', 'like', 'simpanan-manasuka:%');
            })
            ->count('s.id');
    }

    private function setoranWithMutasiKeluar(): int
    {
        return $this->manasukaMutasiDirectionCount(Simpanan::JENIS_SETORAN, 'keluar');
    }

    private function penarikanWithMutasiMasuk(): int
    {
        return $this->manasukaMutasiDirectionCount(Simpanan::JENIS_PENARIKAN, 'masuk');
    }

    private function methodDompetMismatch(): int
    {
        if (! $this->hasTables(['simpanan', 'jenis_simpanan', 'dompet_koperasi']) || ! $this->hasSimpananSp3Columns()) {
            return 0;
        }

        return $this->manasukaBase()
            ->join('dompet_koperasi as d', 'd.id', '=', 's.dompet_id')
            ->whereIn('s.jenis_transaksi', [Simpanan::JENIS_SETORAN, Simpanan::JENIS_PENARIKAN])
            ->where(function ($query): void {
                $query->where(function ($nested): void {
                    $nested->where('s.metode_pembayaran', Simpanan::METODE_TUNAI)
                        ->where('d.jenis_dompet', '!=', 'kas');
                })->orWhere(function ($nested): void {
                    $nested->where('s.metode_pembayaran', Simpanan::METODE_TRANSFER_BANK)
                        ->where('d.jenis_dompet', '!=', 'bank');
                })->orWhereNotIn('s.metode_pembayaran', [Simpanan::METODE_TUNAI, Simpanan::METODE_TRANSFER_BANK]);
            })
            ->count('s.id');
    }

    private function missingOrDuplicatePosting(): int
    {
        if (! $this->hasTables(['simpanan', 'jenis_simpanan', 'mutasi_kas', 'jurnal_umum']) || ! $this->hasSimpananSp3Columns()) {
            return 0;
        }

        return $this->manasukaBase()
            ->where('s.status', Simpanan::STATUS_SETTLED)
            ->whereIn('s.jenis_transaksi', [Simpanan::JENIS_SETORAN, Simpanan::JENIS_PENARIKAN])
            ->get(['s.id'])
            ->filter(function ($row): bool {
                $mutasiCount = DB::table('mutasi_kas')
                    ->where('referensi_tipe', Simpanan::class)
                    ->where('referensi_id', $row->id)
                    ->count();
                $jurnalCount = DB::table('jurnal_umum')
                    ->where('referensi_tipe', Simpanan::class)
                    ->where('referensi_id', $row->id)
                    ->count();

                return $mutasiCount !== 1 || $jurnalCount !== 1;
            })
            ->count();
    }

    private function journalMismatch(): int
    {
        if (! $this->hasTables(['simpanan', 'jenis_simpanan', 'dompet_koperasi', 'jurnal_umum', 'jurnal_umum_detail']) || ! $this->hasSimpananSp3Columns()) {
            return 0;
        }

        return Simpanan::query()
            ->with(['jenisSimpanan.akun', 'dompet.akun', 'jurnal.details'])
            ->whereHas('jenisSimpanan', fn ($query) => $query
                ->where('kode', JenisSimpanan::KODE_SIMPANAN_MANASUKA)
                ->orWhere('kategori', JenisSimpanan::KATEGORI_MANASUKA))
            ->where('status', Simpanan::STATUS_SETTLED)
            ->whereIn('jenis_transaksi', [Simpanan::JENIS_SETORAN, Simpanan::JENIS_PENARIKAN])
            ->get()
            ->filter(function (Simpanan $simpanan): bool {
                $jurnal = $simpanan->jurnal;
                $dompetAkunId = $simpanan->dompet?->akun_id;
                $simpananAkunId = $simpanan->jenisSimpanan?->akun_id;
                $nominal = number_format((float) $simpanan->jumlah, 2, '.', '');

                if (! $jurnal || ! $dompetAkunId || ! $simpananAkunId) {
                    return true;
                }

                $debitAkun = $simpanan->jenis_transaksi === Simpanan::JENIS_SETORAN ? $dompetAkunId : $simpananAkunId;
                $kreditAkun = $simpanan->jenis_transaksi === Simpanan::JENIS_SETORAN ? $simpananAkunId : $dompetAkunId;

                $hasDebit = $jurnal->details->contains(fn ($detail) => (int) $detail->akun_id === (int) $debitAkun && $detail->debit === $nominal);
                $hasKredit = $jurnal->details->contains(fn ($detail) => (int) $detail->akun_id === (int) $kreditAkun && $detail->kredit === $nominal);

                return ! ($hasDebit && $hasKredit);
            })
            ->count();
    }

    private function unbalancedJournal(): int
    {
        if (! $this->hasTables(['jurnal_umum', 'jurnal_umum_detail'])) {
            return 0;
        }

        return DB::table('jurnal_umum as j')
            ->join('jurnal_umum_detail as d', 'd.jurnal_umum_id', '=', 'j.id')
            ->where(function ($query): void {
                $query->where('j.idempotency_key', 'like', 'simpanan:%')
                    ->orWhere('j.idempotency_key', 'like', 'simpanan-manasuka:%')
                    ->orWhere('j.idempotency_key', 'like', 'reversal:jurnal:%');
            })
            ->select('j.id', DB::raw('ABS(SUM(d.debit) - SUM(d.kredit)) as diff'))
            ->groupBy('j.id')
            ->having('diff', '>', 0.01)
            ->get()
            ->count();
    }

    private function duplicateOrInvalidCode(): int
    {
        if (! Schema::hasTable('simpanan') || ! $this->hasSimpananSp3Columns()) {
            return 0;
        }

        $duplicates = DB::table('simpanan')
            ->whereNotNull('kode_transaksi')
            ->select('kode_transaksi', DB::raw('COUNT(*) as total'))
            ->groupBy('kode_transaksi')
            ->having('total', '>', 1)
            ->get()
            ->count();

        $invalid = $this->manasukaBase()
            ->whereNotNull('s.kode_transaksi')
            ->where('s.kode_transaksi', 'not like', 'SMN-%')
            ->count('s.id');

        $invalid += $this->manasukaBase()
            ->whereNotNull('s.kode_transaksi')
            ->get(['s.kode_transaksi'])
            ->filter(fn ($row) => preg_match('/^SMN-\d{6}-\d{6}$/', (string) $row->kode_transaksi) !== 1)
            ->count();

        $invalid += DB::table('simpanan')
            ->whereNotNull('kode_transaksi')
            ->where('kode_transaksi', 'like', 'SSK-%')
            ->count();

        return $duplicates + $invalid;
    }

    private function duplicateIdempotency(): int
    {
        $total = 0;

        foreach (['simpanan', 'mutasi_kas', 'jurnal_umum', 'reversal_transaksi'] as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'idempotency_key')) {
                continue;
            }

            $total += DB::table($table)
                ->whereNotNull('idempotency_key')
                ->where(function ($query): void {
                    $query->where('idempotency_key', 'like', 'simpanan:%')
                        ->orWhere('idempotency_key', 'like', 'simpanan-manasuka:%')
                        ->orWhere('idempotency_key', 'like', 'reversal:simpanan-manasuka:%');
                })
                ->select('idempotency_key', DB::raw('COUNT(*) as total'))
                ->groupBy('idempotency_key')
                ->having('total', '>', 1)
                ->get()
                ->count();
        }

        return $total;
    }

    private function doubleCorrection(): int
    {
        if (! Schema::hasTable('reversal_transaksi')) {
            return 0;
        }

        return DB::table('reversal_transaksi')
            ->where('source_type', Simpanan::class)
            ->where('jenis_reversal', ReversalTransaksi::JENIS_SIMPANAN_MANASUKA_CORRECTION)
            ->select('source_id', DB::raw('COUNT(*) as total'))
            ->groupBy('source_id')
            ->having('total', '>', 1)
            ->get()
            ->count();
    }

    private function legacySourceType(): int
    {
        $total = 0;

        foreach (['penyelesaian_keanggotaan_detail', 'reversal_transaksi'] as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'source_type')) {
                continue;
            }

            $total += DB::table($table)
                ->where('source_type', 'App\\Models\\SaldoSimpananSukarela')
                ->count();
        }

        return $total;
    }

    private function manasukaInPayroll(): int
    {
        if (! $this->hasTables(['pemakaian_potong_gaji', 'simpanan', 'jenis_simpanan']) || ! Schema::hasColumn('simpanan', 'pemakaian_potong_gaji_id')) {
            return 0;
        }

        return $this->manasukaBase()
            ->join('pemakaian_potong_gaji as p', 'p.id', '=', 's.pemakaian_potong_gaji_id')
            ->whereNotNull('s.pemakaian_potong_gaji_id')
            ->count('s.id');
    }

    private function correctedWithoutReversal(): int
    {
        if (! $this->hasTables(['simpanan', 'jenis_simpanan', 'reversal_transaksi']) || ! $this->hasSimpananSp3Columns()) {
            return 0;
        }

        return $this->manasukaBase()
            ->leftJoin('reversal_transaksi as r', 'r.id', '=', 's.reversal_transaksi_id')
            ->where('s.status', Simpanan::STATUS_REVERSED)
            ->where(function ($query): void {
                $query->whereNull('s.reversal_transaksi_id')
                    ->orWhereNull('r.id');
            })
            ->count('s.id');
    }

    private function historicalOverdraw(): int
    {
        if (! $this->hasTables(['simpanan', 'jenis_simpanan']) || ! $this->hasSimpananSp3Columns()) {
            return 0;
        }

        $issues = 0;

        $groups = $this->manasukaBase()
            ->where('s.status', Simpanan::STATUS_SETTLED)
            ->whereIn('s.jenis_transaksi', [Simpanan::JENIS_SETORAN, Simpanan::JENIS_PENARIKAN])
            ->select('s.anggota_id', 's.siklus_keanggotaan_id', 's.jenis_simpanan_id')
            ->groupBy('s.anggota_id', 's.siklus_keanggotaan_id', 's.jenis_simpanan_id')
            ->get();

        foreach ($groups as $group) {
            $saldo = 0.0;
            $rows = DB::table('simpanan')
                ->where('anggota_id', $group->anggota_id)
                ->where('siklus_keanggotaan_id', $group->siklus_keanggotaan_id)
                ->where('jenis_simpanan_id', $group->jenis_simpanan_id)
                ->where('status', Simpanan::STATUS_SETTLED)
                ->whereIn('jenis_transaksi', [Simpanan::JENIS_SETORAN, Simpanan::JENIS_PENARIKAN])
                ->orderBy('tanggal')
                ->orderBy('id')
                ->get(['jenis_transaksi', 'jumlah']);

            foreach ($rows as $row) {
                $saldo += $row->jenis_transaksi === Simpanan::JENIS_SETORAN
                    ? (float) $row->jumlah
                    : -1 * (float) $row->jumlah;

                if ($saldo < -0.01) {
                    $issues++;
                    break;
                }
            }
        }

        return $issues;
    }

    private function nonactiveDirectTransaction(): int
    {
        if (! $this->hasTables(['simpanan', 'jenis_simpanan', 'anggota', 'karyawan']) || ! $this->hasSimpananSp3Columns()) {
            return 0;
        }

        return $this->manasukaBase()
            ->join('anggota as a', 'a.id', '=', 's.anggota_id')
            ->join('karyawan as k', 'k.id', '=', 'a.karyawan_id')
            ->where('s.status', Simpanan::STATUS_SETTLED)
            ->whereIn('s.jenis_transaksi', [Simpanan::JENIS_SETORAN, Simpanan::JENIS_PENARIKAN])
            ->where(function ($query): void {
                $query->where(function ($anggota): void {
                    $anggota->where('a.status', '!=', 'aktif')
                        ->where(function ($tanggal): void {
                            $tanggal->whereNull('a.tanggal_nonaktif')
                                ->orWhereColumn('s.tanggal', '>=', 'a.tanggal_nonaktif');
                        });
                })->orWhere(function ($karyawan): void {
                    $karyawan->where('k.status_kerja', '!=', 'aktif')
                        ->where(function ($tanggal): void {
                            $tanggal->whereNull('k.tanggal_berhenti')
                                ->orWhereColumn('s.tanggal', '>=', 'k.tanggal_berhenti');
                        });
                    });
            })
            ->count('s.id');
    }

    private function oldCycleBalanceMoved(): int
    {
        if (! $this->hasTables(['saldo_simpanan_manasuka', 'siklus_keanggotaan'])) {
            return 0;
        }

        return DB::table('saldo_simpanan_manasuka as active_saldo')
            ->join('siklus_keanggotaan as active_siklus', 'active_siklus.id', '=', 'active_saldo.siklus_keanggotaan_id')
            ->join('saldo_simpanan_manasuka as closed_saldo', function ($join): void {
                $join->on('closed_saldo.anggota_id', '=', 'active_saldo.anggota_id')
                    ->on('closed_saldo.jenis_simpanan_id', '=', 'active_saldo.jenis_simpanan_id')
                    ->whereColumn('closed_saldo.siklus_keanggotaan_id', '!=', 'active_saldo.siklus_keanggotaan_id');
            })
            ->join('siklus_keanggotaan as closed_siklus', 'closed_siklus.id', '=', 'closed_saldo.siklus_keanggotaan_id')
            ->where('active_siklus.status', 'active')
            ->where('closed_siklus.status', 'closed')
            ->where('closed_saldo.saldo', '>', 0)
            ->where('active_saldo.saldo', '>', 0)
            ->count('active_saldo.id');
    }

    private function orphanSettlement(): int
    {
        if (! $this->hasTables(['penyelesaian_keanggotaan_detail', 'simpanan'])) {
            return 0;
        }

        return DB::table('penyelesaian_keanggotaan_detail as d')
            ->leftJoin('simpanan as s', 's.id', '=', 'd.source_id')
            ->where('d.source_type', Simpanan::class)
            ->where('d.kategori_sumber', 'simpanan_manasuka')
            ->whereNull('s.id')
            ->count('d.id');
    }

    private function manasukaMutasiDirectionCount(string $jenisTransaksi, string $tipeMutasi): int
    {
        if (! $this->hasTables(['simpanan', 'jenis_simpanan', 'mutasi_kas']) || ! $this->hasSimpananSp3Columns()) {
            return 0;
        }

        return $this->manasukaBase()
            ->join('mutasi_kas as m', function ($join): void {
                $join->on('m.referensi_id', '=', 's.id')
                    ->where('m.referensi_tipe', '=', Simpanan::class);
            })
            ->where('s.jenis_transaksi', $jenisTransaksi)
            ->where('m.tipe', $tipeMutasi)
            ->count('s.id');
    }

    private function manasukaBase()
    {
        return DB::table('simpanan as s')
            ->join('jenis_simpanan as js', 'js.id', '=', 's.jenis_simpanan_id')
            ->where(function ($query): void {
                $query->where('js.kode', JenisSimpanan::KODE_SIMPANAN_MANASUKA)
                    ->orWhere('js.kategori', JenisSimpanan::KATEGORI_MANASUKA)
                    ->orWhere('s.kode_jenis_snapshot', JenisSimpanan::KODE_SIMPANAN_MANASUKA);
            });
    }

    /**
     * @param  array<int, string>  $tables
     */
    private function hasTables(array $tables): bool
    {
        foreach ($tables as $table) {
            if (! Schema::hasTable($table)) {
                return false;
            }
        }

        return true;
    }

    private function hasSimpananSp3Columns(): bool
    {
        if (! Schema::hasTable('simpanan')) {
            return false;
        }

        foreach (['kode_transaksi', 'jenis_transaksi', 'dompet_id', 'saldo_sebelum_snapshot', 'saldo_sesudah_snapshot', 'nomor_referensi'] as $column) {
            if (! Schema::hasColumn('simpanan', $column)) {
                return false;
            }
        }

        return true;
    }
}
