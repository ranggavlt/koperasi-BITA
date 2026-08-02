<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('kebijakan_limit_potong_gaji')) {
            Schema::create('kebijakan_limit_potong_gaji', function (Blueprint $table): void {
                $table->id();
                $table->decimal('nominal_limit', 15, 2);
                $table->string('status', 20)->default('active')->index();
                $table->date('berlaku_mulai_periode')->index();
                $table->date('berlaku_sampai_periode')->nullable()->index();
                $table->text('alasan')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->restrictOnDelete();
                $table->foreignId('updated_by')->nullable()->constrained('users')->restrictOnDelete();
                $table->timestamps();

                $table->unique('berlaku_mulai_periode', 'kebijakan_limit_pg_mulai_unique');
                $table->index(['status', 'berlaku_mulai_periode'], 'kebijakan_limit_pg_status_mulai_index');
            });
        }

        if (! Schema::hasTable('riwayat_kebijakan_limit_potong_gaji')) {
            Schema::create('riwayat_kebijakan_limit_potong_gaji', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('kebijakan_limit_potong_gaji_id')->nullable();
                $table->decimal('nominal_sebelum', 15, 2)->nullable();
                $table->decimal('nominal_sesudah', 15, 2);
                $table->date('berlaku_mulai_periode');
                $table->text('alasan');
                $table->foreignId('changed_by')->nullable()->constrained('users')->restrictOnDelete();
                $table->timestamp('changed_at');

                $table->index(['kebijakan_limit_potong_gaji_id', 'changed_at'], 'riwayat_kebijakan_limit_pg_changed_index');
                $table->foreign('kebijakan_limit_potong_gaji_id', 'riwayat_kebijakan_limit_pg_policy_fk')
                    ->references('id')
                    ->on('kebijakan_limit_potong_gaji')
                    ->restrictOnDelete();
            });
        }

        if (! Schema::hasTable('override_limit_potong_gaji_anggota')) {
            Schema::create('override_limit_potong_gaji_anggota', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('anggota_id');
                $table->decimal('nominal_override', 15, 2)->nullable();
                $table->string('status', 20)->default('inactive')->index();
                $table->date('berlaku_mulai_periode')->nullable()->index();
                $table->text('alasan_limit_override')->nullable();
                $table->unsignedBigInteger('override_created_by')->nullable();
                $table->unsignedBigInteger('override_updated_by')->nullable();
                $table->timestamp('override_updated_at')->nullable();
                $table->unsignedBigInteger('reset_by')->nullable();
                $table->timestamp('reset_at')->nullable();
                $table->text('reset_reason')->nullable();
                $table->boolean('kredit_waserba_enabled')->default(true)->index();
                $table->unsignedBigInteger('kredit_waserba_disabled_by')->nullable();
                $table->timestamp('kredit_waserba_disabled_at')->nullable();
                $table->text('kredit_waserba_disabled_reason')->nullable();
                $table->unsignedBigInteger('kredit_waserba_enabled_by')->nullable();
                $table->timestamp('kredit_waserba_enabled_at')->nullable();
                $table->text('kredit_waserba_enabled_reason')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->unsignedBigInteger('updated_by')->nullable();
                $table->timestamps();

                $table->unique('anggota_id', 'override_limit_pg_anggota_unique');
                $table->index(['status', 'berlaku_mulai_periode'], 'override_limit_pg_status_mulai_index');
                $table->foreign('anggota_id', 'override_limit_pg_anggota_fk')->references('id')->on('anggota')->restrictOnDelete();
                $table->foreign('override_created_by', 'override_limit_pg_created_by_fk')->references('id')->on('users')->restrictOnDelete();
                $table->foreign('override_updated_by', 'override_limit_pg_updated_by_fk')->references('id')->on('users')->restrictOnDelete();
                $table->foreign('reset_by', 'override_limit_pg_reset_by_fk')->references('id')->on('users')->restrictOnDelete();
                $table->foreign('kredit_waserba_disabled_by', 'override_limit_pg_waserba_off_by_fk')->references('id')->on('users')->restrictOnDelete();
                $table->foreign('kredit_waserba_enabled_by', 'override_limit_pg_waserba_on_by_fk')->references('id')->on('users')->restrictOnDelete();
                $table->foreign('created_by', 'override_limit_pg_row_created_by_fk')->references('id')->on('users')->restrictOnDelete();
                $table->foreign('updated_by', 'override_limit_pg_row_updated_by_fk')->references('id')->on('users')->restrictOnDelete();
            });
        }

        if (! Schema::hasTable('riwayat_override_limit_potong_gaji')) {
            Schema::create('riwayat_override_limit_potong_gaji', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('override_limit_potong_gaji_anggota_id')->nullable();
                $table->unsignedBigInteger('anggota_id');
                $table->string('jenis_perubahan', 40);
                $table->decimal('nominal_sebelum', 15, 2)->nullable();
                $table->decimal('nominal_sesudah', 15, 2)->nullable();
                $table->boolean('kredit_waserba_sebelum')->nullable();
                $table->boolean('kredit_waserba_sesudah')->nullable();
                $table->text('alasan');
                $table->unsignedBigInteger('changed_by')->nullable();
                $table->timestamp('changed_at');

                $table->index(['anggota_id', 'changed_at'], 'riwayat_override_limit_pg_anggota_changed_index');
                $table->index(['jenis_perubahan'], 'riwayat_override_limit_pg_jenis_index');
                $table->foreign('override_limit_potong_gaji_anggota_id', 'riwayat_override_limit_pg_override_fk')
                    ->references('id')
                    ->on('override_limit_potong_gaji_anggota')
                    ->restrictOnDelete();
                $table->foreign('anggota_id', 'riwayat_override_limit_pg_anggota_fk')
                    ->references('id')
                    ->on('anggota')
                    ->restrictOnDelete();
                $table->foreign('changed_by', 'riwayat_override_limit_pg_changed_by_fk')
                    ->references('id')
                    ->on('users')
                    ->restrictOnDelete();
            });
        }

        $this->addLimitSnapshotColumns();
    }

    public function down(): void
    {
        $this->dropLimitSnapshotColumns();

        Schema::dropIfExists('riwayat_override_limit_potong_gaji');
        Schema::dropIfExists('override_limit_potong_gaji_anggota');
        Schema::dropIfExists('riwayat_kebijakan_limit_potong_gaji');
        Schema::dropIfExists('kebijakan_limit_potong_gaji');
    }

    private function addLimitSnapshotColumns(): void
    {
        if (! Schema::hasTable('limit_potong_gaji_anggota')) {
            return;
        }

        Schema::table('limit_potong_gaji_anggota', function (Blueprint $table): void {
            if (! Schema::hasColumn('limit_potong_gaji_anggota', 'sumber_limit')) {
                $table->string('sumber_limit', 30)->nullable()->after('limit_nominal')->index();
            }

            if (! Schema::hasColumn('limit_potong_gaji_anggota', 'kebijakan_limit_potong_gaji_id')) {
                $table->unsignedBigInteger('kebijakan_limit_potong_gaji_id')->nullable()->after('sumber_limit');
            }

            if (! Schema::hasColumn('limit_potong_gaji_anggota', 'override_limit_potong_gaji_anggota_id')) {
                $table->unsignedBigInteger('override_limit_potong_gaji_anggota_id')->nullable()->after('kebijakan_limit_potong_gaji_id');
            }

            if (! Schema::hasColumn('limit_potong_gaji_anggota', 'perusahaan_id_snapshot')) {
                $table->unsignedBigInteger('perusahaan_id_snapshot')->nullable()->after('override_limit_potong_gaji_anggota_id');
            }

            if (! Schema::hasColumn('limit_potong_gaji_anggota', 'perusahaan_kode_snapshot')) {
                $table->string('perusahaan_kode_snapshot', 20)->nullable()->after('perusahaan_id_snapshot');
            }

            if (! Schema::hasColumn('limit_potong_gaji_anggota', 'perusahaan_nama_snapshot')) {
                $table->string('perusahaan_nama_snapshot', 150)->nullable()->after('perusahaan_kode_snapshot');
            }

            if (! Schema::hasColumn('limit_potong_gaji_anggota', 'kredit_waserba_enabled_snapshot')) {
                $table->boolean('kredit_waserba_enabled_snapshot')->default(true)->after('perusahaan_nama_snapshot')->index();
            }

            if (! Schema::hasColumn('limit_potong_gaji_anggota', 'generated_at')) {
                $table->timestamp('generated_at')->nullable()->after('kredit_waserba_enabled_snapshot');
            }

            if (! Schema::hasColumn('limit_potong_gaji_anggota', 'generated_by')) {
                $table->unsignedBigInteger('generated_by')->nullable()->after('generated_at');
            }

            if (! Schema::hasColumn('limit_potong_gaji_anggota', 'generation_batch_key')) {
                $table->string('generation_batch_key', 191)->nullable()->after('generated_by')->index();
            }

            $table->foreign('kebijakan_limit_potong_gaji_id', 'limit_pg_kebijakan_fk')
                ->references('id')
                ->on('kebijakan_limit_potong_gaji')
                ->restrictOnDelete();
            $table->foreign('override_limit_potong_gaji_anggota_id', 'limit_pg_override_fk')
                ->references('id')
                ->on('override_limit_potong_gaji_anggota')
                ->restrictOnDelete();
            $table->foreign('perusahaan_id_snapshot', 'limit_pg_perusahaan_snapshot_fk')
                ->references('id')
                ->on('perusahaan')
                ->restrictOnDelete();
            $table->foreign('generated_by', 'limit_pg_generated_by_fk')
                ->references('id')
                ->on('users')
                ->restrictOnDelete();
        });
    }

    private function dropLimitSnapshotColumns(): void
    {
        if (! Schema::hasTable('limit_potong_gaji_anggota')) {
            return;
        }

        foreach ([
            'limit_pg_generated_by_fk',
            'limit_pg_perusahaan_snapshot_fk',
            'limit_pg_override_fk',
            'limit_pg_kebijakan_fk',
        ] as $foreign) {
            Schema::table('limit_potong_gaji_anggota', function (Blueprint $table) use ($foreign): void {
                $table->dropForeign($foreign);
            });
        }

        foreach ([
            'generation_batch_key',
            'generated_at',
            'kredit_waserba_enabled_snapshot',
            'perusahaan_nama_snapshot',
            'perusahaan_kode_snapshot',
            'sumber_limit',
        ] as $column) {
            if (Schema::hasColumn('limit_potong_gaji_anggota', $column)) {
                Schema::table('limit_potong_gaji_anggota', function (Blueprint $table) use ($column): void {
                    $table->dropColumn($column);
                });
            }
        }
    }
};
