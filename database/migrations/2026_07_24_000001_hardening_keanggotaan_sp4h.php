<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('penyelesaian_keanggotaan', function (Blueprint $table): void {
            if (! Schema::hasColumn('penyelesaian_keanggotaan', 'deactivation_cancelled_by')) {
                $table->foreignId('deactivation_cancelled_by')
                    ->nullable()
                    ->after('completed_by')
                    ->constrained('users')
                    ->restrictOnDelete();
            }

            if (! Schema::hasColumn('penyelesaian_keanggotaan', 'deactivation_cancelled_at')) {
                $table->timestamp('deactivation_cancelled_at')->nullable()->after('deactivation_cancelled_by');
            }

            if (! Schema::hasColumn('penyelesaian_keanggotaan', 'deactivation_cancel_reason')) {
                $table->text('deactivation_cancel_reason')->nullable()->after('deactivation_cancelled_at');
            }

            if (! Schema::hasColumn('penyelesaian_keanggotaan', 're_registered_by')) {
                $table->foreignId('re_registered_by')
                    ->nullable()
                    ->after('deactivation_cancel_reason')
                    ->constrained('users')
                    ->restrictOnDelete();
            }

            if (! Schema::hasColumn('penyelesaian_keanggotaan', 're_registered_at')) {
                $table->timestamp('re_registered_at')->nullable()->after('re_registered_by');
            }

            if (! Schema::hasColumn('penyelesaian_keanggotaan', 're_register_reason')) {
                $table->text('re_register_reason')->nullable()->after('re_registered_at');
            }

            if (! Schema::hasColumn('penyelesaian_keanggotaan', 're_registered_cycle_id')) {
                $table->foreignId('re_registered_cycle_id')
                    ->nullable()
                    ->after('re_register_reason')
                    ->constrained('siklus_keanggotaan')
                    ->restrictOnDelete();
            }

            if (! Schema::hasColumn('penyelesaian_keanggotaan', 're_registration_idempotency_key')) {
                $table->string('re_registration_idempotency_key', 191)
                    ->nullable()
                    ->after('re_registered_cycle_id')
                    ->unique('penyelesaian_re_registration_idempotency_unique');
            }
        });

        Schema::table('jadwal_simpanan_wajib', function (Blueprint $table): void {
            if (! Schema::hasColumn('jadwal_simpanan_wajib', 'recovery_jurnal_id')) {
                $table->foreignId('recovery_jurnal_id')
                    ->nullable()
                    ->after('cancel_reason')
                    ->constrained('jurnal_umum')
                    ->restrictOnDelete();
            }

            if (! Schema::hasColumn('jadwal_simpanan_wajib', 'recovered_at')) {
                $table->timestamp('recovered_at')->nullable()->after('recovery_jurnal_id');
            }

            if (! Schema::hasColumn('jadwal_simpanan_wajib', 'recovered_by')) {
                $table->foreignId('recovered_by')
                    ->nullable()
                    ->after('recovered_at')
                    ->constrained('users')
                    ->restrictOnDelete();
            }

            if (! Schema::hasColumn('jadwal_simpanan_wajib', 'recovery_reason')) {
                $table->string('recovery_reason')->nullable()->after('recovered_by');
            }
        });

        $this->refreshPenyelesaianFinalMarker();
    }

    public function down(): void
    {
        $this->restorePenyelesaianFinalMarker();

        if (Schema::hasTable('jadwal_simpanan_wajib')) {
            Schema::table('jadwal_simpanan_wajib', function (Blueprint $table): void {
                foreach (['recovery_reason', 'recovered_by', 'recovered_at', 'recovery_jurnal_id'] as $column) {
                    if (! Schema::hasColumn('jadwal_simpanan_wajib', $column)) {
                        continue;
                    }

                    if (str_ends_with($column, '_by') || str_ends_with($column, '_id')) {
                        $table->dropConstrainedForeignId($column);
                        continue;
                    }

                    $table->dropColumn($column);
                }
            });
        }

        if (Schema::hasTable('penyelesaian_keanggotaan')) {
            Schema::table('penyelesaian_keanggotaan', function (Blueprint $table): void {
                if (Schema::hasColumn('penyelesaian_keanggotaan', 're_registration_idempotency_key')) {
                    try {
                        $table->dropUnique('penyelesaian_re_registration_idempotency_unique');
                    } catch (\Throwable) {
                    }
                    $table->dropColumn('re_registration_idempotency_key');
                }

                foreach ([
                    're_registered_cycle_id',
                    're_register_reason',
                    're_registered_at',
                    're_registered_by',
                    'deactivation_cancel_reason',
                    'deactivation_cancelled_at',
                    'deactivation_cancelled_by',
                ] as $column) {
                    if (! Schema::hasColumn('penyelesaian_keanggotaan', $column)) {
                        continue;
                    }

                    if (str_ends_with($column, '_by') || str_ends_with($column, '_id')) {
                        $table->dropConstrainedForeignId($column);
                        continue;
                    }

                    $table->dropColumn($column);
                }
            });
        }
    }

    private function refreshPenyelesaianFinalMarker(): void
    {
        if (! Schema::hasColumn('penyelesaian_keanggotaan', 'siklus_final_id')) {
            return;
        }

        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            return;
        }

        try {
            Schema::table('penyelesaian_keanggotaan', function (Blueprint $table): void {
                $table->dropUnique('penyelesaian_keanggotaan_siklus_final_unique');
            });
        } catch (\Throwable) {
        }

        DB::statement(
            "ALTER TABLE `penyelesaian_keanggotaan` MODIFY `siklus_final_id` BIGINT UNSIGNED GENERATED ALWAYS AS (case when `status` not in ('cancelled', 'dibatalkan_penonaktifan') then `siklus_keanggotaan_id` else null end) STORED"
        );

        Schema::table('penyelesaian_keanggotaan', function (Blueprint $table): void {
            $table->unique('siklus_final_id', 'penyelesaian_keanggotaan_siklus_final_unique');
        });
    }

    private function restorePenyelesaianFinalMarker(): void
    {
        if (! Schema::hasColumn('penyelesaian_keanggotaan', 'siklus_final_id')) {
            return;
        }

        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            return;
        }

        try {
            Schema::table('penyelesaian_keanggotaan', function (Blueprint $table): void {
                $table->dropUnique('penyelesaian_keanggotaan_siklus_final_unique');
            });
        } catch (\Throwable) {
        }

        DB::statement(
            "ALTER TABLE `penyelesaian_keanggotaan` MODIFY `siklus_final_id` BIGINT UNSIGNED GENERATED ALWAYS AS (case when `status` <> 'cancelled' then `siklus_keanggotaan_id` else null end) STORED"
        );

        Schema::table('penyelesaian_keanggotaan', function (Blueprint $table): void {
            $table->unique('siklus_final_id', 'penyelesaian_keanggotaan_siklus_final_unique');
        });
    }
};
