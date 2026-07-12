<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nomor_urut_transaksi', function (Blueprint $table): void {
            $table->id();
            $table->string('jenis', 50);
            $table->string('periode', 6);
            $table->unsignedInteger('last_number')->default(0);
            $table->timestamps();

            $table->unique(['jenis', 'periode'], 'nomor_urut_jenis_periode_unique');
        });

        Schema::table('dompet_koperasi', function (Blueprint $table): void {
            if (! Schema::hasColumn('dompet_koperasi', 'akun_id')) {
                $table->foreignId('akun_id')
                    ->nullable()
                    ->after('id')
                    ->constrained('akun')
                    ->restrictOnDelete();
            }
        });

        Schema::table('pinjaman', function (Blueprint $table): void {
            if (! Schema::hasColumn('pinjaman', 'kode_pinjaman')) {
                $table->string('kode_pinjaman', 20)->nullable()->after('id')->unique();
            }

            if (! Schema::hasColumn('pinjaman', 'dompet_id')) {
                $table->foreignId('dompet_id')
                    ->nullable()
                    ->after('karyawan_id')
                    ->constrained('dompet_koperasi')
                    ->restrictOnDelete();
            }

            if (! Schema::hasColumn('pinjaman', 'plafon_pinjaman_snapshot')) {
                $table->decimal('plafon_pinjaman_snapshot', 15, 2)
                    ->nullable()
                    ->after('jumlah_pinjaman');
            }

            if (! Schema::hasColumn('pinjaman', 'anggota_aktif_id')) {
                $anggotaAktifColumn = $table->unsignedBigInteger('anggota_aktif_id')
                    ->nullable()
                    ->after('anggota_id');

                if (Schema::getConnection()->getDriverName() === 'mysql') {
                    $anggotaAktifColumn->storedAs("case when `status` = 'aktif' then `anggota_id` else null end");
                }

                $anggotaAktifColumn->unique();
            }

            if (! Schema::hasColumn('pinjaman', 'created_by')) {
                $table->foreignId('created_by')
                    ->nullable()
                    ->after('keterangan')
                    ->constrained('users')
                    ->restrictOnDelete();
            }
        });

        Schema::create('jadwal_cicilan_pinjaman', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('pinjaman_id')
                ->constrained('pinjaman')
                ->restrictOnDelete();
            $table->unsignedSmallInteger('angsuran_ke');
            $table->date('periode');
            $table->decimal('nominal_pokok', 15, 2);
            $table->string('status', 20)->default('scheduled')->index();
            $table->string('metode_penyelesaian', 20)->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->unique(['pinjaman_id', 'angsuran_ke'], 'jadwal_cicilan_pinjaman_angsuran_unique');
            $table->unique(['pinjaman_id', 'periode'], 'jadwal_cicilan_pinjaman_periode_unique');
            $table->index(['periode', 'status'], 'jadwal_cicilan_pinjaman_periode_status_index');
        });

        Schema::table('cicilan_pinjaman', function (Blueprint $table): void {
            if (! Schema::hasColumn('cicilan_pinjaman', 'jadwal_cicilan_pinjaman_id')) {
                $table->foreignId('jadwal_cicilan_pinjaman_id')
                    ->nullable()
                    ->after('pinjaman_id')
                    ->unique()
                    ->constrained('jadwal_cicilan_pinjaman')
                    ->restrictOnDelete();
            }
        });

        Schema::table('mutasi_kas', function (Blueprint $table): void {
            if (! Schema::hasColumn('mutasi_kas', 'idempotency_key')) {
                $table->string('idempotency_key', 191)->nullable()->after('id')->unique();
            }
        });

        Schema::table('jurnal_umum', function (Blueprint $table): void {
            if (! Schema::hasColumn('jurnal_umum', 'idempotency_key')) {
                $table->string('idempotency_key', 191)->nullable()->after('id')->unique();
            }
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('jurnal_umum', 'idempotency_key')) {
            Schema::table('jurnal_umum', function (Blueprint $table): void {
                $table->dropUnique('jurnal_umum_idempotency_key_unique');
                $table->dropColumn('idempotency_key');
            });
        }

        if (Schema::hasColumn('mutasi_kas', 'idempotency_key')) {
            Schema::table('mutasi_kas', function (Blueprint $table): void {
                $table->dropUnique('mutasi_kas_idempotency_key_unique');
                $table->dropColumn('idempotency_key');
            });
        }

        if (Schema::hasColumn('cicilan_pinjaman', 'jadwal_cicilan_pinjaman_id')) {
            Schema::table('cicilan_pinjaman', function (Blueprint $table): void {
                $table->dropConstrainedForeignId('jadwal_cicilan_pinjaman_id');
            });
        }

        Schema::dropIfExists('jadwal_cicilan_pinjaman');

        Schema::table('pinjaman', function (Blueprint $table): void {
            if (Schema::hasColumn('pinjaman', 'created_by')) {
                $table->dropConstrainedForeignId('created_by');
            }

            if (Schema::hasColumn('pinjaman', 'anggota_aktif_id')) {
                $table->dropUnique('pinjaman_anggota_aktif_id_unique');
                $table->dropColumn('anggota_aktif_id');
            }

            if (Schema::hasColumn('pinjaman', 'plafon_pinjaman_snapshot')) {
                $table->dropColumn('plafon_pinjaman_snapshot');
            }

            if (Schema::hasColumn('pinjaman', 'dompet_id')) {
                $table->dropConstrainedForeignId('dompet_id');
            }

            if (Schema::hasColumn('pinjaman', 'kode_pinjaman')) {
                $table->dropUnique('pinjaman_kode_pinjaman_unique');
                $table->dropColumn('kode_pinjaman');
            }
        });

        if (Schema::hasColumn('dompet_koperasi', 'akun_id')) {
            Schema::table('dompet_koperasi', function (Blueprint $table): void {
                $table->dropConstrainedForeignId('akun_id');
            });
        }

        Schema::dropIfExists('nomor_urut_transaksi');
    }
};
