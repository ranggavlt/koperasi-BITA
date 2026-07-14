<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->extendHutangResellerStatus();
        $this->extendCicilanStatus();

        Schema::create('reversal_transaksi', function (Blueprint $table): void {
            $table->id();
            $table->string('kode_reversal', 32)->unique();
            $table->string('source_type', 160);
            $table->unsignedBigInteger('source_id');
            $table->string('jenis_reversal', 60);
            $table->decimal('nominal', 15, 2);
            $table->text('alasan');
            $table->string('status', 30)->default('processed')->index();
            $table->foreignId('original_ledger_id')->nullable()
                ->constrained('pemakaian_potong_gaji')
                ->restrictOnDelete();
            $table->foreignId('original_jurnal_id')->nullable()
                ->constrained('jurnal_umum')
                ->restrictOnDelete();
            $table->foreignId('original_mutasi_id')->nullable()
                ->constrained('mutasi_kas')
                ->restrictOnDelete();
            $table->foreignId('target_periode_potong_gaji_id')->nullable()
                ->constrained('periode_potong_gaji')
                ->restrictOnDelete();
            $table->foreignId('dompet_refund_id')->nullable()
                ->constrained('dompet_koperasi')
                ->restrictOnDelete();
            $table->foreignId('reversal_of_id')->nullable()
                ->constrained('reversal_transaksi')
                ->restrictOnDelete();
            $table->foreignId('created_by')->nullable()
                ->constrained('users')
                ->restrictOnDelete();
            $table->foreignId('processed_by')->nullable()
                ->constrained('users')
                ->restrictOnDelete();
            $table->timestamp('processed_at')->nullable();
            $table->string('idempotency_key', 160)->unique();
            $table->timestamps();

            $table->unique(['source_type', 'source_id'], 'reversal_source_unique');
            $table->index(['source_type', 'source_id', 'jenis_reversal'], 'reversal_source_jenis_index');
            $table->index(['jenis_reversal', 'status'], 'reversal_jenis_status_index');
        });

        Schema::create('kredit_potong_gaji_anggota', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('anggota_id')
                ->constrained('anggota')
                ->restrictOnDelete();
            $table->foreignId('reversal_transaksi_id')
                ->unique('kredit_pg_reversal_unique')
                ->constrained('reversal_transaksi')
                ->restrictOnDelete();
            $table->decimal('nominal_awal', 15, 2);
            $table->decimal('nominal_terpakai', 15, 2)->default(0);
            $table->decimal('nominal_sisa', 15, 2);
            $table->string('status', 30)->default('open')->index();
            $table->foreignId('created_by')->nullable()
                ->constrained('users')
                ->restrictOnDelete();
            $table->timestamps();

            $table->index(['anggota_id', 'status'], 'kredit_pg_anggota_status_index');
        });

        Schema::create('alokasi_kredit_potong_gaji', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('kredit_potong_gaji_anggota_id')
                ->constrained('kredit_potong_gaji_anggota')
                ->restrictOnDelete();
            $table->foreignId('limit_potong_gaji_anggota_id')
                ->constrained('limit_potong_gaji_anggota')
                ->restrictOnDelete();
            $table->decimal('nominal_dialokasikan', 15, 2);
            $table->decimal('nominal_diterapkan', 15, 2)->default(0);
            $table->string('status', 30)->default('applied')->index();
            $table->timestamp('applied_at')->nullable();
            $table->foreignId('created_by')->nullable()
                ->constrained('users')
                ->restrictOnDelete();
            $table->string('idempotency_key', 160)->unique();
            $table->timestamps();

            $table->index(['limit_potong_gaji_anggota_id', 'status'], 'alokasi_kredit_limit_status_index');
        });

        Schema::create('pembayaran_outstanding_cash', function (Blueprint $table): void {
            $table->id();
            $table->string('kode_pembayaran', 32)->unique();
            $table->string('source_type', 160);
            $table->unsignedBigInteger('source_id');
            $table->foreignId('anggota_id')->nullable()
                ->constrained('anggota')
                ->restrictOnDelete();
            $table->foreignId('karyawan_id')->nullable()
                ->constrained('karyawan')
                ->restrictOnDelete();
            $table->foreignId('dompet_id')
                ->constrained('dompet_koperasi')
                ->restrictOnDelete();
            $table->decimal('nominal', 15, 2);
            $table->string('status', 30)->default('paid')->index();
            $table->timestamp('paid_at')->nullable();
            $table->foreignId('created_by')->nullable()
                ->constrained('users')
                ->restrictOnDelete();
            $table->string('idempotency_key', 160)->unique();
            $table->timestamps();

            $table->unique(['source_type', 'source_id'], 'outstanding_cash_source_unique');
            $table->index(['anggota_id', 'status'], 'outstanding_cash_anggota_status_index');
            $table->index(['karyawan_id', 'status'], 'outstanding_cash_karyawan_status_index');
        });

        Schema::table('penjualan', function (Blueprint $table): void {
            if (! Schema::hasColumn('penjualan', 'status')) {
                $table->string('status', 30)->default('completed')->after('grand_total')->index();
            }
            if (! Schema::hasColumn('penjualan', 'reversal_transaksi_id')) {
                $table->foreignId('reversal_transaksi_id')->nullable()
                    ->after('status')
                    ->constrained('reversal_transaksi')
                    ->restrictOnDelete();
            }
            if (! Schema::hasColumn('penjualan', 'reversed_at')) {
                $table->timestamp('reversed_at')->nullable()->after('reversal_transaksi_id');
            }
            if (! Schema::hasColumn('penjualan', 'reversed_by')) {
                $table->foreignId('reversed_by')->nullable()
                    ->after('reversed_at')
                    ->constrained('users')
                    ->restrictOnDelete();
            }
        });

        Schema::table('pembayaran', function (Blueprint $table): void {
            if (! Schema::hasColumn('pembayaran', 'reversal_transaksi_id')) {
                $table->foreignId('reversal_transaksi_id')->nullable()
                    ->after('pemakaian_potong_gaji_id')
                    ->constrained('reversal_transaksi')
                    ->restrictOnDelete();
            }
        });

        Schema::table('simpanan', function (Blueprint $table): void {
            if (! Schema::hasColumn('simpanan', 'reversal_transaksi_id')) {
                $table->foreignId('reversal_transaksi_id')->nullable()
                    ->after('pemakaian_potong_gaji_id')
                    ->constrained('reversal_transaksi')
                    ->restrictOnDelete();
            }
            if (! Schema::hasColumn('simpanan', 'replacement_simpanan_id')) {
                $table->foreignId('replacement_simpanan_id')->nullable()
                    ->after('reversal_transaksi_id')
                    ->constrained('simpanan')
                    ->restrictOnDelete();
            }
        });

        $this->refreshSimpananPokokUniqueMarker();

        Schema::table('cicilan_pinjaman', function (Blueprint $table): void {
            if (! Schema::hasColumn('cicilan_pinjaman', 'reversal_transaksi_id')) {
                $table->foreignId('reversal_transaksi_id')->nullable()
                    ->after('jadwal_cicilan_pinjaman_id')
                    ->constrained('reversal_transaksi')
                    ->restrictOnDelete();
            }
        });

        Schema::table('pemakaian_potong_gaji', function (Blueprint $table): void {
            if (! Schema::hasColumn('pemakaian_potong_gaji', 'reversal_transaksi_id')) {
                $table->foreignId('reversal_transaksi_id')->nullable()
                    ->after('reversal_of_id')
                    ->constrained('reversal_transaksi')
                    ->restrictOnDelete();
            }
        });
    }

    public function down(): void
    {
        foreach ([
            ['pemakaian_potong_gaji', 'reversal_transaksi_id'],
            ['cicilan_pinjaman', 'reversal_transaksi_id'],
            ['simpanan', 'replacement_simpanan_id'],
            ['simpanan', 'reversal_transaksi_id'],
            ['pembayaran', 'reversal_transaksi_id'],
            ['penjualan', 'reversed_at'],
            ['penjualan', 'reversal_transaksi_id'],
            ['penjualan', 'status'],
        ] as [$tableName, $column]) {
            if (! Schema::hasColumn($tableName, $column)) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) use ($column): void {
                if (str_ends_with($column, '_id')) {
                    $table->dropConstrainedForeignId($column);
                    return;
                }

                $table->dropColumn($column);
            });
        }

        if (Schema::hasColumn('penjualan', 'reversed_by')) {
            Schema::table('penjualan', function (Blueprint $table): void {
                $table->dropConstrainedForeignId('reversed_by');
            });
        }

        $this->restoreSimpananPokokUniqueMarker();
        $this->restoreCicilanStatus();
        $this->restoreHutangResellerStatus();

        Schema::dropIfExists('pembayaran_outstanding_cash');
        Schema::dropIfExists('alokasi_kredit_potong_gaji');
        Schema::dropIfExists('kredit_potong_gaji_anggota');
        Schema::dropIfExists('reversal_transaksi');
    }

    private function refreshSimpananPokokUniqueMarker(): void
    {
        if (! Schema::hasColumn('simpanan', 'simpanan_pokok_anggota_id')) {
            return;
        }

        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            return;
        }

        Schema::table('simpanan', function (Blueprint $table): void {
            $table->dropUnique('simpanan_pokok_anggota_unique');
        });

        DB::statement(
            "ALTER TABLE `simpanan` MODIFY `simpanan_pokok_anggota_id` BIGINT UNSIGNED GENERATED ALWAYS AS (case when `kode_jenis_snapshot` = 'SIMPANAN_POKOK' and `status` <> 'reversed' then `anggota_id` else null end) STORED"
        );

        Schema::table('simpanan', function (Blueprint $table): void {
            $table->unique('simpanan_pokok_anggota_id', 'simpanan_pokok_anggota_unique');
        });
    }

    private function restoreSimpananPokokUniqueMarker(): void
    {
        if (! Schema::hasColumn('simpanan', 'simpanan_pokok_anggota_id')) {
            return;
        }

        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            return;
        }

        Schema::table('simpanan', function (Blueprint $table): void {
            $table->dropUnique('simpanan_pokok_anggota_unique');
        });

        DB::statement(
            "ALTER TABLE `simpanan` MODIFY `simpanan_pokok_anggota_id` BIGINT UNSIGNED GENERATED ALWAYS AS (case when `kode_jenis_snapshot` = 'SIMPANAN_POKOK' then `anggota_id` else null end) STORED"
        );

        Schema::table('simpanan', function (Blueprint $table): void {
            $table->unique('simpanan_pokok_anggota_id', 'simpanan_pokok_anggota_unique');
        });
    }

    private function extendHutangResellerStatus(): void
    {
        if (! Schema::hasColumn('hutang_reseller', 'status') || Schema::getConnection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("ALTER TABLE `hutang_reseller` MODIFY `status` ENUM('belum_dibayar', 'sudah_dibayar', 'dibatalkan') NOT NULL DEFAULT 'belum_dibayar'");
    }

    private function restoreHutangResellerStatus(): void
    {
        if (! Schema::hasColumn('hutang_reseller', 'status') || Schema::getConnection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::table('hutang_reseller')->where('status', 'dibatalkan')->update(['status' => 'belum_dibayar']);
        DB::statement("ALTER TABLE `hutang_reseller` MODIFY `status` ENUM('belum_dibayar', 'sudah_dibayar') NOT NULL DEFAULT 'belum_dibayar'");
    }

    private function extendCicilanStatus(): void
    {
        if (! Schema::hasColumn('cicilan_pinjaman', 'status') || Schema::getConnection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("ALTER TABLE `cicilan_pinjaman` MODIFY `status` ENUM('belum_bayar', 'sudah_bayar', 'reversed') NOT NULL DEFAULT 'belum_bayar'");
    }

    private function restoreCicilanStatus(): void
    {
        if (! Schema::hasColumn('cicilan_pinjaman', 'status') || Schema::getConnection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::table('cicilan_pinjaman')->where('status', 'reversed')->update(['status' => 'sudah_bayar']);
        DB::statement("ALTER TABLE `cicilan_pinjaman` MODIFY `status` ENUM('belum_bayar', 'sudah_bayar') NOT NULL DEFAULT 'belum_bayar'");
    }
};
