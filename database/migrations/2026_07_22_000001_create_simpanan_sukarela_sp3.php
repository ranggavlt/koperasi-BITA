<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('saldo_simpanan_sukarela')) {
            Schema::create('saldo_simpanan_sukarela', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('anggota_id')
                    ->constrained('anggota')
                    ->restrictOnDelete();
                $table->foreignId('siklus_keanggotaan_id')
                    ->constrained('siklus_keanggotaan')
                    ->restrictOnDelete();
                $table->foreignId('jenis_simpanan_id')
                    ->constrained('jenis_simpanan')
                    ->restrictOnDelete();
                $table->decimal('saldo', 15, 2)->default(0);
                $table->timestamps();

                $table->unique(
                    ['anggota_id', 'siklus_keanggotaan_id', 'jenis_simpanan_id'],
                    'saldo_sukarela_anggota_siklus_jenis_unique'
                );
                $table->index(['anggota_id', 'siklus_keanggotaan_id'], 'saldo_sukarela_anggota_siklus_index');
                $table->index(['jenis_simpanan_id'], 'saldo_sukarela_jenis_index');
            });
        }

        $this->addSaldoNonNegativeCheck();

        Schema::table('simpanan', function (Blueprint $table): void {
            if (! Schema::hasColumn('simpanan', 'kode_transaksi')) {
                $table->string('kode_transaksi', 32)->nullable()->after('id')->unique();
            }

            if (! Schema::hasColumn('simpanan', 'jenis_transaksi')) {
                $table->string('jenis_transaksi', 20)->nullable()->after('jumlah')->index();
            }

            if (! Schema::hasColumn('simpanan', 'dompet_id')) {
                $table->foreignId('dompet_id')
                    ->nullable()
                    ->after('jenis_transaksi')
                    ->constrained('dompet_koperasi')
                    ->restrictOnDelete();
            }

            if (! Schema::hasColumn('simpanan', 'saldo_sebelum_snapshot')) {
                $table->decimal('saldo_sebelum_snapshot', 15, 2)->nullable()->after('dompet_id');
            }

            if (! Schema::hasColumn('simpanan', 'saldo_sesudah_snapshot')) {
                $table->decimal('saldo_sesudah_snapshot', 15, 2)->nullable()->after('saldo_sebelum_snapshot');
            }

            if (! Schema::hasColumn('simpanan', 'nomor_referensi')) {
                $table->string('nomor_referensi', 80)->nullable()->after('saldo_sesudah_snapshot');
            }

            $table->index(['anggota_id', 'jenis_transaksi', 'status', 'tanggal'], 'simpanan_sp3_anggota_jenis_status_tanggal_index');
            $table->index(['dompet_id', 'metode_pembayaran'], 'simpanan_sp3_dompet_metode_index');
            $table->index(['kode_jenis_snapshot', 'status'], 'simpanan_sp3_kode_status_index');
        });

        $this->backfillExistingSukarelaSaldo();
    }

    public function down(): void
    {
        if (Schema::hasTable('simpanan')) {
            Schema::table('simpanan', function (Blueprint $table): void {
                foreach ([
                    'simpanan_sp3_kode_status_index',
                    'simpanan_sp3_dompet_metode_index',
                    'simpanan_sp3_anggota_jenis_status_tanggal_index',
                ] as $indexName) {
                    try {
                        $table->dropIndex($indexName);
                    } catch (\Throwable) {
                    }
                }

                if (Schema::hasColumn('simpanan', 'dompet_id')) {
                    $table->dropConstrainedForeignId('dompet_id');
                }

                foreach ([
                    'nomor_referensi',
                    'saldo_sesudah_snapshot',
                    'saldo_sebelum_snapshot',
                    'jenis_transaksi',
                    'kode_transaksi',
                ] as $column) {
                    if (Schema::hasColumn('simpanan', $column)) {
                        if ($column === 'kode_transaksi') {
                            try {
                                $table->dropUnique('simpanan_kode_transaksi_unique');
                            } catch (\Throwable) {
                            }
                        }

                        $table->dropColumn($column);
                    }
                }
            });
        }

        Schema::dropIfExists('saldo_simpanan_sukarela');
    }

    private function addSaldoNonNegativeCheck(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver !== 'mysql') {
            return;
        }

        try {
            DB::statement('ALTER TABLE `saldo_simpanan_sukarela` ADD CONSTRAINT `saldo_sukarela_non_negative` CHECK (`saldo` >= 0)');
        } catch (\Throwable) {
        }
    }

    private function backfillExistingSukarelaSaldo(): void
    {
        if (! Schema::hasTable('saldo_simpanan_sukarela')
            || ! Schema::hasTable('simpanan')
            || ! Schema::hasColumn('simpanan', 'anggota_id')
            || ! Schema::hasColumn('simpanan', 'siklus_keanggotaan_id')) {
            return;
        }

        $now = now();

        DB::table('simpanan as s')
            ->join('jenis_simpanan as js', 'js.id', '=', 's.jenis_simpanan_id')
            ->select([
                's.anggota_id',
                's.siklus_keanggotaan_id',
                's.jenis_simpanan_id',
                DB::raw('SUM(s.jumlah) as total_saldo'),
            ])
            ->where('js.kode', 'SIMPANAN_SUKARELA')
            ->where('js.kategori', 'sukarela')
            ->whereNotNull('s.anggota_id')
            ->whereNotNull('s.siklus_keanggotaan_id')
            ->where(function ($query): void {
                $query->whereNull('s.status')
                    ->orWhere('s.status', 'settled')
                    ->orWhere('s.status', 'settled_cash');
            })
            ->where(function ($query): void {
                $query->whereNull('s.jenis_transaksi')
                    ->orWhere('s.jenis_transaksi', 'setoran');
            })
            ->groupBy('s.anggota_id', 's.siklus_keanggotaan_id', 's.jenis_simpanan_id')
            ->orderBy('s.anggota_id')
            ->get()
            ->each(function ($row) use ($now): void {
                if ((float) $row->total_saldo < 0) {
                    return;
                }

                DB::table('saldo_simpanan_sukarela')->insertOrIgnore([
                    'anggota_id' => $row->anggota_id,
                    'siklus_keanggotaan_id' => $row->siklus_keanggotaan_id,
                    'jenis_simpanan_id' => $row->jenis_simpanan_id,
                    'saldo' => number_format((float) $row->total_saldo, 2, '.', ''),
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            });
    }
};
