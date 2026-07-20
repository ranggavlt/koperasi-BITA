<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('penyelesaian_keanggotaan_detail', function (Blueprint $table): void {
            if (! Schema::hasColumn('penyelesaian_keanggotaan_detail', 'tipe_detail')) {
                $table->string('tipe_detail', 30)->default('kewajiban')->after('penyelesaian_keanggotaan_id')->index();
            }

            if (! Schema::hasColumn('penyelesaian_keanggotaan_detail', 'akun_id')) {
                $table->foreignId('akun_id')
                    ->nullable()
                    ->after('source_id')
                    ->constrained('akun')
                    ->restrictOnDelete();
            }

            if (! Schema::hasColumn('penyelesaian_keanggotaan_detail', 'akun_kode_snapshot')) {
                $table->string('akun_kode_snapshot', 30)->nullable()->after('akun_id');
            }

            if (! Schema::hasColumn('penyelesaian_keanggotaan_detail', 'akun_nama_snapshot')) {
                $table->string('akun_nama_snapshot')->nullable()->after('akun_kode_snapshot');
            }

            if (! Schema::hasColumn('penyelesaian_keanggotaan_detail', 'nominal_hak_awal')) {
                $table->decimal('nominal_hak_awal', 15, 2)->default(0)->after('akun_nama_snapshot');
            }

            if (! Schema::hasColumn('penyelesaian_keanggotaan_detail', 'nominal_dipakai_offset')) {
                $table->decimal('nominal_dipakai_offset', 15, 2)->default(0)->after('nominal_hak_awal');
            }

            if (! Schema::hasColumn('penyelesaian_keanggotaan_detail', 'nominal_direfund')) {
                $table->decimal('nominal_direfund', 15, 2)->default(0)->after('nominal_dipakai_offset');
            }

            if (! Schema::hasColumn('penyelesaian_keanggotaan_detail', 'nominal_dibatalkan')) {
                $table->decimal('nominal_dibatalkan', 15, 2)->default(0)->after('nominal_direfund');
            }

            if (! Schema::hasColumn('penyelesaian_keanggotaan_detail', 'processed_by')) {
                $table->foreignId('processed_by')
                    ->nullable()
                    ->after('status')
                    ->constrained('users')
                    ->restrictOnDelete();
            }

            if (! Schema::hasColumn('penyelesaian_keanggotaan_detail', 'processed_at')) {
                $table->timestamp('processed_at')->nullable()->after('processed_by');
            }

            if (! Schema::hasColumn('penyelesaian_keanggotaan_detail', 'idempotency_key')) {
                $table->string('idempotency_key', 191)->nullable()->after('processed_at')->unique();
            }
        });

        if (Schema::hasColumn('penyelesaian_keanggotaan_detail', 'tipe_detail')) {
            DB::table('penyelesaian_keanggotaan_detail')
                ->whereNull('tipe_detail')
                ->orWhere('tipe_detail', '')
                ->update(['tipe_detail' => 'kewajiban']);
        }

        Schema::table('jadwal_simpanan_wajib', function (Blueprint $table): void {
            if (! Schema::hasColumn('jadwal_simpanan_wajib', 'penyelesaian_keanggotaan_id')) {
                $table->foreignId('penyelesaian_keanggotaan_id')
                    ->nullable()
                    ->after('settled_by')
                    ->constrained('penyelesaian_keanggotaan')
                    ->restrictOnDelete();
            }

            if (! Schema::hasColumn('jadwal_simpanan_wajib', 'cancellation_reversal_id')) {
                $table->foreignId('cancellation_reversal_id')
                    ->nullable()
                    ->after('penyelesaian_keanggotaan_id')
                    ->constrained('reversal_transaksi')
                    ->restrictOnDelete();
            }

            if (! Schema::hasColumn('jadwal_simpanan_wajib', 'cancelled_at')) {
                $table->timestamp('cancelled_at')->nullable()->after('cancellation_reversal_id');
            }

            if (! Schema::hasColumn('jadwal_simpanan_wajib', 'cancelled_by')) {
                $table->foreignId('cancelled_by')
                    ->nullable()
                    ->after('cancelled_at')
                    ->constrained('users')
                    ->restrictOnDelete();
            }

            if (! Schema::hasColumn('jadwal_simpanan_wajib', 'cancel_reason')) {
                $table->string('cancel_reason')->nullable()->after('cancelled_by');
            }

            if (! Schema::hasIndex('jadwal_simpanan_wajib', 'jadwal_wajib_sp4_anggota_siklus_status_index')) {
                $table->index(['anggota_id', 'siklus_keanggotaan_id', 'status'], 'jadwal_wajib_sp4_anggota_siklus_status_index');
            }
        });

        Schema::table('saldo_simpanan_sukarela', function (Blueprint $table): void {
            if (! Schema::hasColumn('saldo_simpanan_sukarela', 'penyelesaian_keanggotaan_id')) {
                $table->foreignId('penyelesaian_keanggotaan_id')
                    ->nullable()
                    ->after('saldo')
                    ->constrained('penyelesaian_keanggotaan')
                    ->restrictOnDelete();
            }

            if (! Schema::hasColumn('saldo_simpanan_sukarela', 'frozen_at')) {
                $table->timestamp('frozen_at')->nullable()->after('penyelesaian_keanggotaan_id');
            }
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('saldo_simpanan_sukarela')) {
            Schema::table('saldo_simpanan_sukarela', function (Blueprint $table): void {
                if (Schema::hasColumn('saldo_simpanan_sukarela', 'penyelesaian_keanggotaan_id')) {
                    $table->dropConstrainedForeignId('penyelesaian_keanggotaan_id');
                }

                if (Schema::hasColumn('saldo_simpanan_sukarela', 'frozen_at')) {
                    $table->dropColumn('frozen_at');
                }
            });
        }

        if (Schema::hasTable('jadwal_simpanan_wajib')) {
            Schema::table('jadwal_simpanan_wajib', function (Blueprint $table): void {
                try {
                    $table->dropIndex('jadwal_wajib_sp4_anggota_siklus_status_index');
                } catch (\Throwable) {
                }

                if (Schema::hasColumn('jadwal_simpanan_wajib', 'cancellation_reversal_id')) {
                    $table->dropConstrainedForeignId('cancellation_reversal_id');
                }

                if (Schema::hasColumn('jadwal_simpanan_wajib', 'penyelesaian_keanggotaan_id')) {
                    $table->dropConstrainedForeignId('penyelesaian_keanggotaan_id');
                }

                foreach (['cancel_reason', 'cancelled_by', 'cancelled_at'] as $column) {
                    if (Schema::hasColumn('jadwal_simpanan_wajib', $column)) {
                        if ($column === 'cancelled_by') {
                            $table->dropConstrainedForeignId('cancelled_by');
                        } else {
                            $table->dropColumn($column);
                        }
                    }
                }
            });
        }

        if (Schema::hasTable('penyelesaian_keanggotaan_detail')) {
            Schema::table('penyelesaian_keanggotaan_detail', function (Blueprint $table): void {
                if (Schema::hasColumn('penyelesaian_keanggotaan_detail', 'idempotency_key')) {
                    try {
                        $table->dropUnique('penyelesaian_keanggotaan_detail_idempotency_key_unique');
                    } catch (\Throwable) {
                    }
                    $table->dropColumn('idempotency_key');
                }

                if (Schema::hasColumn('penyelesaian_keanggotaan_detail', 'processed_by')) {
                    $table->dropConstrainedForeignId('processed_by');
                }

                foreach ([
                    'processed_at',
                    'nominal_dibatalkan',
                    'nominal_direfund',
                    'nominal_dipakai_offset',
                    'nominal_hak_awal',
                    'akun_nama_snapshot',
                    'akun_kode_snapshot',
                ] as $column) {
                    if (Schema::hasColumn('penyelesaian_keanggotaan_detail', $column)) {
                        $table->dropColumn($column);
                    }
                }

                if (Schema::hasColumn('penyelesaian_keanggotaan_detail', 'akun_id')) {
                    $table->dropConstrainedForeignId('akun_id');
                }

                if (Schema::hasColumn('penyelesaian_keanggotaan_detail', 'tipe_detail')) {
                    $table->dropColumn('tipe_detail');
                }
            });
        }
    }
};
