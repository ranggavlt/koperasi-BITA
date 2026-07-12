<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dompet_koperasi', function (Blueprint $table): void {
            if (! Schema::hasColumn('dompet_koperasi', 'jenis_dompet')) {
                $table->string('jenis_dompet', 10)->default('kas')->after('nama_dompet')->index();
            }

            if (! Schema::hasColumn('dompet_koperasi', 'is_default_penerimaan_payroll')) {
                $table->boolean('is_default_penerimaan_payroll')
                    ->default(false)
                    ->after('jenis_dompet')
                    ->index();
            }

            if (! Schema::hasColumn('dompet_koperasi', 'default_payroll_marker')) {
                $marker = $table->unsignedTinyInteger('default_payroll_marker')
                    ->nullable()
                    ->after('is_default_penerimaan_payroll');

                if (Schema::getConnection()->getDriverName() === 'mysql') {
                    $marker->storedAs("case when `jenis_dompet` = 'bank' and `is_default_penerimaan_payroll` = 1 then 1 else null end");
                }

                $marker->unique('dompet_default_payroll_unique');
            }
        });

        Schema::table('limit_potong_gaji_anggota', function (Blueprint $table): void {
            if (! Schema::hasColumn('limit_potong_gaji_anggota', 'dompet_penerimaan_id')) {
                $table->foreignId('dompet_penerimaan_id')
                    ->nullable()
                    ->after('anggota_id')
                    ->constrained('dompet_koperasi')
                    ->restrictOnDelete();
            }

            if (! Schema::hasColumn('limit_potong_gaji_anggota', 'closed_by')) {
                $table->foreignId('closed_by')
                    ->nullable()
                    ->after('confirmed_by')
                    ->constrained('users')
                    ->restrictOnDelete();
            }

            if (! Schema::hasColumn('limit_potong_gaji_anggota', 'cancelled_by')) {
                $table->foreignId('cancelled_by')
                    ->nullable()
                    ->after('closed_by')
                    ->constrained('users')
                    ->restrictOnDelete();
            }

            if (! Schema::hasColumn('limit_potong_gaji_anggota', 'cancelled_at')) {
                $table->timestamp('cancelled_at')->nullable()->after('confirmed_at');
            }

            if (! Schema::hasColumn('limit_potong_gaji_anggota', 'cancellation_reason')) {
                $table->text('cancellation_reason')->nullable()->after('cancelled_at');
            }
        });

        Schema::table('pemakaian_potong_gaji', function (Blueprint $table): void {
            if (! Schema::hasColumn('pemakaian_potong_gaji', 'released_by')) {
                $table->foreignId('released_by')
                    ->nullable()
                    ->after('reversed_by')
                    ->constrained('users')
                    ->restrictOnDelete();
            }

            if (! Schema::hasColumn('pemakaian_potong_gaji', 'release_reason')) {
                $table->text('release_reason')->nullable()->after('released_by');
            }
        });

        Schema::table('cicilan_pinjaman', function (Blueprint $table): void {
            if (! Schema::hasColumn('cicilan_pinjaman', 'anggota_id')) {
                $table->foreignId('anggota_id')
                    ->nullable()
                    ->after('pinjaman_id')
                    ->constrained('anggota')
                    ->restrictOnDelete();
            }

            if (! Schema::hasColumn('cicilan_pinjaman', 'metode_pembayaran')) {
                $table->string('metode_pembayaran', 20)->nullable()->after('jumlah_cicilan')->index();
            }

            if (! Schema::hasColumn('cicilan_pinjaman', 'dompet_id')) {
                $table->foreignId('dompet_id')
                    ->nullable()
                    ->after('metode_pembayaran')
                    ->constrained('dompet_koperasi')
                    ->restrictOnDelete();
            }

            if (! Schema::hasColumn('cicilan_pinjaman', 'created_by')) {
                $table->foreignId('created_by')
                    ->nullable()
                    ->after('status')
                    ->constrained('users')
                    ->restrictOnDelete();
            }

            if (! Schema::hasColumn('cicilan_pinjaman', 'idempotency_key')) {
                $table->string('idempotency_key', 191)->nullable()->after('id')->unique();
            }
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('cicilan_pinjaman', 'idempotency_key')) {
            Schema::table('cicilan_pinjaman', function (Blueprint $table): void {
                $table->dropUnique('cicilan_pinjaman_idempotency_key_unique');
                $table->dropColumn('idempotency_key');
            });
        }

        if (Schema::hasColumn('cicilan_pinjaman', 'created_by')) {
            Schema::table('cicilan_pinjaman', function (Blueprint $table): void {
                $table->dropConstrainedForeignId('created_by');
            });
        }

        if (Schema::hasColumn('cicilan_pinjaman', 'dompet_id')) {
            Schema::table('cicilan_pinjaman', function (Blueprint $table): void {
                $table->dropConstrainedForeignId('dompet_id');
            });
        }

        if (Schema::hasColumn('cicilan_pinjaman', 'metode_pembayaran')) {
            Schema::table('cicilan_pinjaman', function (Blueprint $table): void {
                $table->dropColumn('metode_pembayaran');
            });
        }

        if (Schema::hasColumn('cicilan_pinjaman', 'anggota_id')) {
            Schema::table('cicilan_pinjaman', function (Blueprint $table): void {
                $table->dropConstrainedForeignId('anggota_id');
            });
        }

        if (Schema::hasColumn('pemakaian_potong_gaji', 'release_reason')) {
            Schema::table('pemakaian_potong_gaji', function (Blueprint $table): void {
                $table->dropColumn('release_reason');
            });
        }

        if (Schema::hasColumn('pemakaian_potong_gaji', 'released_by')) {
            Schema::table('pemakaian_potong_gaji', function (Blueprint $table): void {
                $table->dropConstrainedForeignId('released_by');
            });
        }

        if (Schema::hasColumn('limit_potong_gaji_anggota', 'cancellation_reason')) {
            Schema::table('limit_potong_gaji_anggota', function (Blueprint $table): void {
                $table->dropColumn('cancellation_reason');
            });
        }

        if (Schema::hasColumn('limit_potong_gaji_anggota', 'cancelled_at')) {
            Schema::table('limit_potong_gaji_anggota', function (Blueprint $table): void {
                $table->dropColumn('cancelled_at');
            });
        }

        foreach (['cancelled_by', 'closed_by', 'dompet_penerimaan_id'] as $column) {
            if (Schema::hasColumn('limit_potong_gaji_anggota', $column)) {
                Schema::table('limit_potong_gaji_anggota', function (Blueprint $table) use ($column): void {
                    $table->dropConstrainedForeignId($column);
                });
            }
        }

        if (Schema::hasColumn('dompet_koperasi', 'default_payroll_marker')) {
            Schema::table('dompet_koperasi', function (Blueprint $table): void {
                $table->dropUnique('dompet_default_payroll_unique');
                $table->dropColumn('default_payroll_marker');
            });
        }

        if (Schema::hasColumn('dompet_koperasi', 'is_default_penerimaan_payroll')) {
            Schema::table('dompet_koperasi', function (Blueprint $table): void {
                $table->dropColumn('is_default_penerimaan_payroll');
            });
        }

        if (Schema::hasColumn('dompet_koperasi', 'jenis_dompet')) {
            Schema::table('dompet_koperasi', function (Blueprint $table): void {
                $table->dropColumn('jenis_dompet');
            });
        }
    }
};
