<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('periode_potong_gaji', function (Blueprint $table): void {
            $table->id();
            $table->date('periode')->unique();
            $table->string('status', 30)->default('draft')->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('activated_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('closed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('limit_potong_gaji_anggota', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('periode_potong_gaji_id')
                ->constrained('periode_potong_gaji')
                ->restrictOnDelete();
            $table->foreignId('anggota_id')
                ->constrained('anggota')
                ->restrictOnDelete();
            $table->decimal('limit_nominal', 15, 2);
            $table->string('status', 40)->default('draft')->index();
            $table->foreignId('activated_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamps();

            $table->unique(['periode_potong_gaji_id', 'anggota_id'], 'limit_pg_periode_anggota_unique');
            $table->index(['anggota_id', 'status'], 'limit_pg_anggota_status_index');
            $table->index(['periode_potong_gaji_id', 'status'], 'limit_pg_periode_status_index');
        });

        Schema::create('riwayat_limit_potong_gaji', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('limit_potong_gaji_anggota_id')
                ->constrained('limit_potong_gaji_anggota')
                ->restrictOnDelete();
            $table->decimal('nominal_sebelum', 15, 2);
            $table->decimal('nominal_sesudah', 15, 2);
            $table->text('alasan');
            $table->foreignId('changed_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('changed_at');

            $table->index(['limit_potong_gaji_anggota_id', 'changed_at'], 'riwayat_limit_pg_limit_changed_index');
        });

        Schema::create('pemakaian_potong_gaji', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('limit_potong_gaji_anggota_id')
                ->constrained('limit_potong_gaji_anggota')
                ->restrictOnDelete();
            $table->string('kategori', 30);
            $table->string('source_type', 191);
            $table->unsignedBigInteger('source_id');
            $table->string('jenis', 20);
            $table->decimal('nominal', 15, 2);
            $table->string('status', 30)->default('reserved');
            $table->string('idempotency_key', 191)->unique();
            $table->timestamp('occurred_at');
            $table->timestamp('settled_at')->nullable();
            $table->timestamp('released_at')->nullable();
            $table->timestamp('reversed_at')->nullable();
            $table->foreignId('reversal_of_id')
                ->nullable()
                ->constrained('pemakaian_potong_gaji')
                ->restrictOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('reversed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['source_type', 'source_id', 'kategori'], 'pemakaian_pg_source_unique');
            $table->index(['limit_potong_gaji_anggota_id', 'status'], 'pemakaian_pg_limit_status_index');
            $table->index(['limit_potong_gaji_anggota_id', 'kategori'], 'pemakaian_pg_limit_kategori_index');
            $table->index(['kategori', 'status'], 'pemakaian_pg_kategori_status_index');
            $table->index(['source_type', 'source_id'], 'pemakaian_pg_source_index');
            $table->index(['occurred_at'], 'pemakaian_pg_occurred_index');
        });

        $this->addAnggotaId('penjualan');
        $this->addAnggotaId('simpanan');
        $this->addAnggotaId('pinjaman');
        $this->addAnggotaId('shu_anggota');
        $this->addJenisSimpananKode();
        $this->backfillAnggotaIdFromKaryawan();
    }

    public function down(): void
    {
        $this->dropAnggotaId('shu_anggota');
        $this->dropAnggotaId('pinjaman');
        $this->dropAnggotaId('simpanan');
        $this->dropAnggotaId('penjualan');

        if (Schema::hasColumn('jenis_simpanan', 'kode')) {
            Schema::table('jenis_simpanan', function (Blueprint $table): void {
                $table->dropUnique('jenis_simpanan_kode_unique');
                $table->dropColumn('kode');
            });
        }

        if (Schema::hasColumn('jenis_simpanan', 'aktif')) {
            Schema::table('jenis_simpanan', function (Blueprint $table): void {
                $table->dropColumn('aktif');
            });
        }

        Schema::dropIfExists('pemakaian_potong_gaji');
        Schema::dropIfExists('riwayat_limit_potong_gaji');
        Schema::dropIfExists('limit_potong_gaji_anggota');
        Schema::dropIfExists('periode_potong_gaji');
    }

    private function addAnggotaId(string $tableName): void
    {
        if (! Schema::hasTable($tableName) || Schema::hasColumn($tableName, 'anggota_id')) {
            return;
        }

        Schema::table($tableName, function (Blueprint $table): void {
            $table->foreignId('anggota_id')
                ->nullable()
                ->after('karyawan_id')
                ->constrained('anggota')
                ->restrictOnDelete();
        });
    }

    private function dropAnggotaId(string $tableName): void
    {
        if (! Schema::hasTable($tableName) || ! Schema::hasColumn($tableName, 'anggota_id')) {
            return;
        }

        Schema::table($tableName, function (Blueprint $table): void {
            $table->dropConstrainedForeignId('anggota_id');
        });
    }

    private function addJenisSimpananKode(): void
    {
        if (! Schema::hasColumn('jenis_simpanan', 'kode')) {
            Schema::table('jenis_simpanan', function (Blueprint $table): void {
                $table->string('kode', 60)->nullable()->after('akun_id')->unique();
            });
        }

        if (! Schema::hasColumn('jenis_simpanan', 'aktif')) {
            Schema::table('jenis_simpanan', function (Blueprint $table): void {
                $table->boolean('aktif')->default(true)->after('wajib')->index();
            });
        }
    }

    private function backfillAnggotaIdFromKaryawan(): void
    {
        DB::table('anggota')
            ->select(['id', 'karyawan_id'])
            ->orderBy('id')
            ->chunkById(100, function ($anggotaRows): void {
                foreach ($anggotaRows as $anggota) {
                    foreach (['penjualan', 'simpanan', 'pinjaman', 'shu_anggota'] as $tableName) {
                        if (! Schema::hasColumn($tableName, 'anggota_id')) {
                            continue;
                        }

                        DB::table($tableName)
                            ->where('karyawan_id', $anggota->karyawan_id)
                            ->whereNull('anggota_id')
                            ->update(['anggota_id' => $anggota->id]);
                    }
                }
            });
    }
};
