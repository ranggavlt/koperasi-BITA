<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('penjualan', function (Blueprint $table): void {
            if (! Schema::hasColumn('penjualan', 'tipe_pelanggan')) {
                $table->string('tipe_pelanggan', 20)->nullable()->after('kode_transaksi')->index();
            }

            if (Schema::hasColumn('penjualan', 'karyawan_id')) {
                try {
                    $table->dropForeign(['karyawan_id']);
                } catch (\Throwable) {
                }

                try {
                    $table->unsignedBigInteger('karyawan_id')->nullable()->change();
                } catch (\Throwable) {
                }

                try {
                    $table->foreign('karyawan_id')->references('id')->on('karyawan')->restrictOnDelete();
                } catch (\Throwable) {
                }
            }

            if (! Schema::hasColumn('penjualan', 'tanggal_transaksi')) {
                $table->timestamp('tanggal_transaksi')->nullable()->after('anggota_id')->index();
            }

            if (! Schema::hasColumn('penjualan', 'created_by')) {
                $table->foreignId('created_by')
                    ->nullable()
                    ->after('grand_total')
                    ->constrained('users')
                    ->restrictOnDelete();
            }

            if (! Schema::hasColumn('penjualan', 'idempotency_key')) {
                $table->string('idempotency_key', 191)->nullable()->after('id')->unique();
            }
        });

        Schema::table('pembayaran', function (Blueprint $table): void {
            if (! Schema::hasColumn('pembayaran', 'status')) {
                $table->string('status', 30)->default('paid')->after('metode_pembayaran')->index();
            }

            if (! Schema::hasColumn('pembayaran', 'dompet_id')) {
                $table->foreignId('dompet_id')
                    ->nullable()
                    ->after('status')
                    ->constrained('dompet_koperasi')
                    ->restrictOnDelete();
            }

            if (! Schema::hasColumn('pembayaran', 'pemakaian_potong_gaji_id')) {
                $table->foreignId('pemakaian_potong_gaji_id')
                    ->nullable()
                    ->after('dompet_id')
                    ->unique()
                    ->constrained('pemakaian_potong_gaji')
                    ->restrictOnDelete();
            }

            if (! Schema::hasColumn('pembayaran', 'paid_at')) {
                $table->timestamp('paid_at')->nullable()->after('jumlah_bayar');
            }

            if (! Schema::hasColumn('pembayaran', 'created_by')) {
                $table->foreignId('created_by')
                    ->nullable()
                    ->after('paid_at')
                    ->constrained('users')
                    ->restrictOnDelete();
            }

            if (! Schema::hasColumn('pembayaran', 'idempotency_key')) {
                $table->string('idempotency_key', 191)->nullable()->after('id')->unique();
            }
        });

        if (Schema::hasColumn('pembayaran', 'penjualan_id')) {
            $duplicates = DB::table('pembayaran')
                ->select('penjualan_id', DB::raw('COUNT(*) as total'))
                ->whereNotNull('penjualan_id')
                ->groupBy('penjualan_id')
                ->having('total', '>', 1)
                ->get();

            if ($duplicates->isNotEmpty()) {
                throw new RuntimeException('Migration 2D dibatalkan: terdapat Pembayaran ganda untuk satu Penjualan. Jalankan preflight dan rapikan data sebelum menambah unique constraint.');
            }

            Schema::table('pembayaran', function (Blueprint $table): void {
                $table->unique('penjualan_id', 'pembayaran_penjualan_unique');
            });
        }

        Schema::table('simpanan', function (Blueprint $table): void {
            if (! Schema::hasColumn('simpanan', 'pemakaian_potong_gaji_id')) {
                $table->foreignId('pemakaian_potong_gaji_id')
                    ->nullable()
                    ->after('anggota_id')
                    ->unique()
                    ->constrained('pemakaian_potong_gaji')
                    ->restrictOnDelete();
            }

            if (! Schema::hasColumn('simpanan', 'kode_jenis_snapshot')) {
                $table->string('kode_jenis_snapshot', 60)->nullable()->after('jenis_simpanan_id');
            }

            if (! Schema::hasColumn('simpanan', 'simpanan_pokok_anggota_id')) {
                $marker = $table->unsignedBigInteger('simpanan_pokok_anggota_id')
                    ->nullable()
                    ->after('kode_jenis_snapshot');

                if (Schema::getConnection()->getDriverName() === 'mysql') {
                    $marker->storedAs("case when `kode_jenis_snapshot` = 'SIMPANAN_POKOK' then `anggota_id` else null end");
                }

                $marker->unique('simpanan_pokok_anggota_unique');
            }

            if (! Schema::hasColumn('simpanan', 'nama_jenis_snapshot')) {
                $table->string('nama_jenis_snapshot', 120)->nullable()->after('simpanan_pokok_anggota_id');
            }

            if (! Schema::hasColumn('simpanan', 'nominal_snapshot')) {
                $table->decimal('nominal_snapshot', 15, 2)->nullable()->after('nama_jenis_snapshot');
            }

            if (! Schema::hasColumn('simpanan', 'metode_pembayaran')) {
                $table->string('metode_pembayaran', 20)->nullable()->after('jumlah')->index();
            }

            if (! Schema::hasColumn('simpanan', 'status')) {
                $table->string('status', 30)->default('settled')->after('metode_pembayaran')->index();
            }

            if (! Schema::hasColumn('simpanan', 'settled_at')) {
                $table->timestamp('settled_at')->nullable()->after('tanggal');
            }

            if (! Schema::hasColumn('simpanan', 'created_by')) {
                $table->foreignId('created_by')
                    ->nullable()
                    ->after('keterangan')
                    ->constrained('users')
                    ->restrictOnDelete();
            }

            if (! Schema::hasColumn('simpanan', 'idempotency_key')) {
                $table->string('idempotency_key', 191)->nullable()->after('id')->unique();
            }
        });
    }

    public function down(): void
    {
        foreach ([
            'idempotency_key',
            'created_by',
            'settled_at',
            'status',
            'metode_pembayaran',
            'nominal_snapshot',
            'nama_jenis_snapshot',
            'simpanan_pokok_anggota_id',
            'kode_jenis_snapshot',
            'pemakaian_potong_gaji_id',
        ] as $column) {
            if (Schema::hasColumn('simpanan', $column)) {
                Schema::table('simpanan', function (Blueprint $table) use ($column): void {
                    if (in_array($column, ['created_by', 'pemakaian_potong_gaji_id'], true)) {
                        $table->dropConstrainedForeignId($column);
                    } elseif ($column === 'simpanan_pokok_anggota_id') {
                        try {
                            $table->dropUnique('simpanan_pokok_anggota_unique');
                        } catch (\Throwable) {
                        }

                        $table->dropColumn($column);
                    } else {
                        $table->dropColumn($column);
                    }
                });
            }
        }

        if (Schema::hasColumn('pembayaran', 'penjualan_id')) {
            Schema::table('pembayaran', function (Blueprint $table): void {
                try {
                    $table->dropUnique('pembayaran_penjualan_unique');
                } catch (\Throwable) {
                }
            });
        }

        foreach ([
            'idempotency_key',
            'created_by',
            'paid_at',
            'pemakaian_potong_gaji_id',
            'dompet_id',
            'status',
        ] as $column) {
            if (Schema::hasColumn('pembayaran', $column)) {
                Schema::table('pembayaran', function (Blueprint $table) use ($column): void {
                    if (in_array($column, ['created_by', 'dompet_id', 'pemakaian_potong_gaji_id'], true)) {
                        $table->dropConstrainedForeignId($column);
                    } else {
                        $table->dropColumn($column);
                    }
                });
            }
        }

        foreach (['idempotency_key', 'created_by', 'tanggal_transaksi', 'tipe_pelanggan'] as $column) {
            if (Schema::hasColumn('penjualan', $column)) {
                Schema::table('penjualan', function (Blueprint $table) use ($column): void {
                    if ($column === 'created_by') {
                        $table->dropConstrainedForeignId($column);
                    } else {
                        $table->dropColumn($column);
                    }
                });
            }
        }
    }
};
