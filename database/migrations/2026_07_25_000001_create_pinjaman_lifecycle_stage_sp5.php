<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->widenStatusColumn();

        Schema::table('pinjaman', function (Blueprint $table): void {
            if (! Schema::hasColumn('pinjaman', 'tanggal_pengajuan')) {
                $table->date('tanggal_pengajuan')->nullable()->after('tanggal_pinjaman')->index();
            }

            if (! Schema::hasColumn('pinjaman', 'submitted_by')) {
                $table->foreignId('submitted_by')->nullable()->after('created_by')->constrained('users')->restrictOnDelete();
                $table->timestamp('submitted_at')->nullable()->after('submitted_by');
            }

            if (! Schema::hasColumn('pinjaman', 'approved_by')) {
                $table->foreignId('approved_by')->nullable()->after('submitted_at')->constrained('users')->restrictOnDelete();
                $table->timestamp('approved_at')->nullable()->after('approved_by');
            }

            if (! Schema::hasColumn('pinjaman', 'rejected_by')) {
                $table->foreignId('rejected_by')->nullable()->after('approved_at')->constrained('users')->restrictOnDelete();
                $table->timestamp('rejected_at')->nullable()->after('rejected_by');
                $table->text('rejection_reason')->nullable()->after('rejected_at');
            }

            if (! Schema::hasColumn('pinjaman', 'cancelled_by')) {
                $table->foreignId('cancelled_by')->nullable()->after('rejection_reason')->constrained('users')->restrictOnDelete();
                $table->timestamp('cancelled_at')->nullable()->after('cancelled_by');
                $table->text('cancellation_reason')->nullable()->after('cancelled_at');
            }

            if (! Schema::hasColumn('pinjaman', 'disbursed_by')) {
                $table->foreignId('disbursed_by')->nullable()->after('cancellation_reason')->constrained('users')->restrictOnDelete();
                $table->timestamp('disbursed_at')->nullable()->after('disbursed_by');
            }
        });

        if (! Schema::hasColumn('pinjaman', 'anggota_pinjaman_terbuka_id')) {
            Schema::table('pinjaman', function (Blueprint $table): void {
                $column = $table->unsignedBigInteger('anggota_pinjaman_terbuka_id')
                    ->nullable()
                    ->after('anggota_aktif_id');

                if (Schema::getConnection()->getDriverName() === 'mysql') {
                    $column->storedAs("case when `status` in ('draft','diajukan','disetujui','aktif') then `anggota_id` else null end");
                }

                $column->unique('pinjaman_anggota_terbuka_unique');
            });
        }

        DB::table('pinjaman')
            ->whereNull('tanggal_pengajuan')
            ->update(['tanggal_pengajuan' => DB::raw('tanggal_pinjaman')]);
    }

    public function down(): void
    {
        $this->assertRollbackStatusCompatible();

        if (Schema::hasColumn('pinjaman', 'anggota_pinjaman_terbuka_id')) {
            Schema::table('pinjaman', function (Blueprint $table): void {
                $table->dropUnique('pinjaman_anggota_terbuka_unique');
                $table->dropColumn('anggota_pinjaman_terbuka_id');
            });
        }

        Schema::table('pinjaman', function (Blueprint $table): void {
            foreach ([
                'disbursed_by',
                'cancelled_by',
                'rejected_by',
                'approved_by',
                'submitted_by',
            ] as $foreignId) {
                if (Schema::hasColumn('pinjaman', $foreignId)) {
                    $table->dropConstrainedForeignId($foreignId);
                }
            }
        });

        Schema::table('pinjaman', function (Blueprint $table): void {
            foreach ([
                'disbursed_at',
                'cancellation_reason',
                'cancelled_at',
                'rejection_reason',
                'rejected_at',
                'approved_at',
                'submitted_at',
                'tanggal_pengajuan',
            ] as $column) {
                if (Schema::hasColumn('pinjaman', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        $this->restoreLegacyStatusColumn();
    }

    private function widenStatusColumn(): void
    {
        if (! Schema::hasTable('pinjaman') || ! Schema::hasColumn('pinjaman', 'status')) {
            return;
        }

        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE `pinjaman` MODIFY `status` VARCHAR(30) NOT NULL DEFAULT 'draft'");
        }
    }

    private function restoreLegacyStatusColumn(): void
    {
        if (! Schema::hasTable('pinjaman') || ! Schema::hasColumn('pinjaman', 'status')) {
            return;
        }

        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE `pinjaman` MODIFY `status` ENUM('aktif','lunas') NOT NULL DEFAULT 'aktif'");
        }
    }

    private function assertRollbackStatusCompatible(): void
    {
        if (! Schema::hasTable('pinjaman') || ! Schema::hasColumn('pinjaman', 'status')) {
            return;
        }

        $lifecycleRows = DB::table('pinjaman')
            ->whereNotIn('status', ['aktif', 'lunas'])
            ->count();

        if ($lifecycleRows > 0) {
            throw new \RuntimeException(
                "Rollback migration SP-5 ditolak karena terdapat {$lifecycleRows} Pinjaman dengan status lifecycle baru. " .
                "Rekonsiliasi atau migrasikan data Pinjaman terlebih dahulu; migration tidak akan menebak atau mengubah status secara otomatis."
            );
        }
    }
};
