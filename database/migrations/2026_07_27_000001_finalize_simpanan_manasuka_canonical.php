<?php

use App\Models\ReversalTransaksi;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private string $legacyTable = 'saldo_simpanan_sukarela';

    private string $canonicalTable = 'saldo_simpanan_manasuka';

    public function up(): void
    {
        $this->renameSaldoTableToCanonical();
        $this->addSaldoNonNegativeCheck();
        $this->assertNoSskCodeCollision();
        $this->assertNoCounterCollision();

        $this->canonicalizeJenisSimpanan();
        $this->canonicalizeSimpanan();
        $this->canonicalizeSettlement();
        $this->canonicalizeReversal();
        $this->canonicalizePostingReferences();
        $this->canonicalizeCounters();
        $this->canonicalizeCoa();
    }

    public function down(): void
    {
        if ($this->hasCanonicalRuntimeData()) {
            throw new RuntimeException(
                'Rollback canonical Simpanan Manasuka ditolak karena sudah ada data aktif. Gunakan migration koreksi/reversal, bukan downgrade data histori.'
            );
        }

        if (Schema::hasTable($this->canonicalTable) && ! Schema::hasTable($this->legacyTable)) {
            Schema::rename($this->canonicalTable, $this->legacyTable);
        }

        if (Schema::hasTable('jenis_simpanan')) {
            DB::table('jenis_simpanan')
                ->where('kode', 'SIMPANAN_MANASUKA')
                ->update(['kode' => 'SIMPANAN_SUKARELA']);

            DB::table('jenis_simpanan')
                ->where('kategori', 'manasuka')
                ->update(['kategori' => 'sukarela']);

            DB::table('jenis_simpanan')
                ->where('nama_jenis', 'Simpanan Manasuka')
                ->update(['nama_jenis' => 'Simpanan Sukarela']);

            if ($this->driver() !== 'mysql' && Schema::hasColumn('jenis_simpanan', 'active_kategori_marker')) {
                DB::table('jenis_simpanan')
                    ->where('active_kategori_marker', 'manasuka')
                    ->update(['active_kategori_marker' => 'sukarela']);
            }
        }
    }

    private function renameSaldoTableToCanonical(): void
    {
        if (Schema::hasTable($this->legacyTable) && Schema::hasTable($this->canonicalTable)) {
            $legacyRows = DB::table($this->legacyTable)->count();

            if ($legacyRows > 0) {
                throw new RuntimeException(
                    'Migration Simpanan Manasuka dibatalkan: tabel saldo legacy dan canonical sama-sama ada sehingga mapping histori tidak aman.'
                );
            }

            return;
        }

        if (Schema::hasTable($this->legacyTable) && ! Schema::hasTable($this->canonicalTable)) {
            Schema::rename($this->legacyTable, $this->canonicalTable);
        }
    }

    private function addSaldoNonNegativeCheck(): void
    {
        if ($this->driver() !== 'mysql' || ! Schema::hasTable($this->canonicalTable)) {
            return;
        }

        try {
            DB::statement('ALTER TABLE `saldo_simpanan_manasuka` ADD CONSTRAINT `saldo_manasuka_non_negative` CHECK (`saldo` >= 0)');
        } catch (Throwable) {
        }
    }

    private function assertNoSskCodeCollision(): void
    {
        if (! Schema::hasTable('simpanan') || ! Schema::hasColumn('simpanan', 'kode_transaksi')) {
            return;
        }

        DB::table('simpanan')
            ->where('kode_transaksi', 'like', 'SSK-%')
            ->orderBy('id')
            ->get(['id', 'kode_transaksi'])
            ->each(function ($row): void {
                $target = 'SMN-' . substr((string) $row->kode_transaksi, 4);
                $exists = DB::table('simpanan')
                    ->where('kode_transaksi', $target)
                    ->where('id', '!=', $row->id)
                    ->exists();

                if ($exists) {
                    throw new RuntimeException(
                        'Migration Simpanan Manasuka dibatalkan: kode transaksi ' . $target . ' sudah ada sehingga konversi SSK tidak aman.'
                    );
                }
            });
    }

    private function assertNoCounterCollision(): void
    {
        if (! Schema::hasTable('nomor_urut_transaksi')) {
            return;
        }

        $legacyPeriods = DB::table('nomor_urut_transaksi')
            ->where('jenis', 'simpanan_sukarela')
            ->pluck('periode');

        foreach ($legacyPeriods as $periode) {
            $hasCanonical = DB::table('nomor_urut_transaksi')
                ->where('jenis', 'simpanan_manasuka')
                ->where('periode', $periode)
                ->exists();

            if ($hasCanonical) {
                throw new RuntimeException(
                    'Migration Simpanan Manasuka dibatalkan: counter simpanan_sukarela dan simpanan_manasuka sama-sama ada untuk periode ' . $periode . '.'
                );
            }
        }
    }

    private function canonicalizeJenisSimpanan(): void
    {
        if (! Schema::hasTable('jenis_simpanan')) {
            return;
        }

        DB::table('jenis_simpanan')
            ->where('kode', 'SIMPANAN_SUKARELA')
            ->update(['kode' => 'SIMPANAN_MANASUKA']);

        DB::table('jenis_simpanan')
            ->where('kategori', 'sukarela')
            ->update(['kategori' => 'manasuka']);

        DB::table('jenis_simpanan')
            ->where('nama_jenis', 'Simpanan Sukarela')
            ->update(['nama_jenis' => 'Simpanan Manasuka']);

        if ($this->driver() !== 'mysql' && Schema::hasColumn('jenis_simpanan', 'active_kategori_marker')) {
            DB::table('jenis_simpanan')
                ->where('active_kategori_marker', 'sukarela')
                ->update(['active_kategori_marker' => 'manasuka']);
        }
    }

    private function canonicalizeSimpanan(): void
    {
        if (! Schema::hasTable('simpanan')) {
            return;
        }

        if (Schema::hasColumn('simpanan', 'kode_jenis_snapshot')) {
            DB::table('simpanan')
                ->where('kode_jenis_snapshot', 'SIMPANAN_SUKARELA')
                ->update(['kode_jenis_snapshot' => 'SIMPANAN_MANASUKA']);
        }

        if (Schema::hasColumn('simpanan', 'nama_jenis_snapshot')) {
            DB::table('simpanan')
                ->where('nama_jenis_snapshot', 'Simpanan Sukarela')
                ->update(['nama_jenis_snapshot' => 'Simpanan Manasuka']);
        }

        if (Schema::hasColumn('simpanan', 'kode_transaksi')) {
            DB::table('simpanan')
                ->where('kode_transaksi', 'like', 'SSK-%')
                ->orderBy('id')
                ->get(['id', 'kode_transaksi'])
                ->each(function ($row): void {
                    DB::table('simpanan')
                        ->where('id', $row->id)
                        ->update(['kode_transaksi' => 'SMN-' . substr((string) $row->kode_transaksi, 4)]);
                });
        }

        $this->replaceColumnText('simpanan', 'idempotency_key', 'simpanan-sukarela', 'simpanan-manasuka');
        $this->replaceColumnText('simpanan', 'keterangan', 'Simpanan Sukarela', 'Simpanan Manasuka');
    }

    private function canonicalizeSettlement(): void
    {
        if (! Schema::hasTable('penyelesaian_keanggotaan_detail')) {
            return;
        }

        $table = DB::table('penyelesaian_keanggotaan_detail');

        if (Schema::hasColumn('penyelesaian_keanggotaan_detail', 'kategori_sumber')) {
            $table->where('kategori_sumber', 'simpanan_sukarela')
                ->update(['kategori_sumber' => 'simpanan_manasuka']);
        }

        if (Schema::hasColumn('penyelesaian_keanggotaan_detail', 'source_type')) {
            DB::table('penyelesaian_keanggotaan_detail')
                ->where('source_type', 'App\\Models\\SaldoSimpananSukarela')
                ->update(['source_type' => 'App\\Models\\SaldoSimpananManasuka']);
        }

        $this->replaceColumnText('penyelesaian_keanggotaan_detail', 'idempotency_key', 'simpanan-sukarela', 'simpanan-manasuka');
        $this->replaceColumnText('penyelesaian_keanggotaan_detail', 'idempotency_key', 'simpanan_sukarela', 'simpanan_manasuka');
    }

    private function canonicalizeReversal(): void
    {
        if (! Schema::hasTable('reversal_transaksi')) {
            return;
        }

        if (Schema::hasColumn('reversal_transaksi', 'jenis_reversal')) {
            DB::table('reversal_transaksi')
                ->where('jenis_reversal', 'simpanan_sukarela_correction')
                ->update(['jenis_reversal' => 'simpanan_manasuka_correction']);
        }

        $this->replaceColumnText('reversal_transaksi', 'idempotency_key', 'simpanan-sukarela', 'simpanan-manasuka');
        $this->replaceColumnText('reversal_transaksi', 'alasan', 'Simpanan Sukarela', 'Simpanan Manasuka');
    }

    private function canonicalizePostingReferences(): void
    {
        foreach (['mutasi_kas', 'jurnal_umum'] as $table) {
            $this->replaceColumnText($table, 'idempotency_key', 'simpanan-sukarela', 'simpanan-manasuka');
            $this->replaceColumnText($table, 'keterangan', 'Simpanan Sukarela', 'Simpanan Manasuka');
        }
    }

    private function canonicalizeCounters(): void
    {
        if (! Schema::hasTable('nomor_urut_transaksi')) {
            return;
        }

        DB::table('nomor_urut_transaksi')
            ->where('jenis', 'simpanan_sukarela')
            ->update(['jenis' => 'simpanan_manasuka']);
    }

    private function canonicalizeCoa(): void
    {
        if (! Schema::hasTable('akun')) {
            return;
        }

        DB::table('akun')
            ->where('kode_akun', '202')
            ->where('nama_akun', 'Simpanan Sukarela Anggota')
            ->update([
                'nama_akun' => 'Simpanan Manasuka Anggota',
                'keterangan' => 'Simpanan anggota yang dapat ditarik sesuai ketentuan koperasi.',
            ]);
    }

    private function replaceColumnText(string $table, string $column, string $search, string $replace): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
            return;
        }

        DB::table($table)
            ->where($column, 'like', '%' . $search . '%')
            ->orderBy('id')
            ->get(['id', $column])
            ->each(function ($row) use ($table, $column, $search, $replace): void {
                DB::table($table)
                    ->where('id', $row->id)
                    ->update([$column => str_replace($search, $replace, (string) $row->{$column})]);
            });
    }

    private function hasCanonicalRuntimeData(): bool
    {
        if (Schema::hasTable($this->canonicalTable) && DB::table($this->canonicalTable)->exists()) {
            return true;
        }

        if (Schema::hasTable('simpanan') && Schema::hasColumn('simpanan', 'kode_transaksi')) {
            if (DB::table('simpanan')->where('kode_transaksi', 'like', 'SMN-%')->exists()) {
                return true;
            }
        }

        if (Schema::hasTable('jenis_simpanan')) {
            if (DB::table('jenis_simpanan')
                ->where(function ($query): void {
                    $query->where('kode', 'SIMPANAN_MANASUKA')
                        ->orWhere('kategori', 'manasuka')
                        ->orWhere('nama_jenis', 'Simpanan Manasuka');
                })
                ->exists()) {
                return true;
            }
        }

        if (Schema::hasTable('reversal_transaksi') && Schema::hasColumn('reversal_transaksi', 'jenis_reversal')) {
            if (DB::table('reversal_transaksi')
                ->where('jenis_reversal', ReversalTransaksi::JENIS_SIMPANAN_MANASUKA_CORRECTION)
                ->exists()) {
                return true;
            }
        }

        return false;
    }

    private function driver(): string
    {
        return Schema::getConnection()->getDriverName();
    }
};
