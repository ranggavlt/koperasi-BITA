<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            if (! Schema::hasColumn('users', 'karyawan_id')) {
                $table->foreignId('karyawan_id')
                    ->nullable()
                    ->after('role')
                    ->unique()
                    ->constrained('karyawan')
                    ->restrictOnDelete();
            }

            if (! Schema::hasColumn('users', 'is_active')) {
                $table->boolean('is_active')->default(true)->after('karyawan_id')->index();
            }

            if (! Schema::hasColumn('users', 'must_change_password')) {
                $table->boolean('must_change_password')->default(false)->after('is_active');
            }

            if (! Schema::hasColumn('users', 'password_changed_at')) {
                $table->timestamp('password_changed_at')->nullable()->after('must_change_password');
            }

            if (! Schema::hasColumn('users', 'account_created_by')) {
                $table->foreignId('account_created_by')
                    ->nullable()
                    ->after('password_changed_at')
                    ->constrained('users')
                    ->restrictOnDelete();
            }

            if (! Schema::hasColumn('users', 'account_updated_by')) {
                $table->foreignId('account_updated_by')
                    ->nullable()
                    ->after('account_created_by')
                    ->constrained('users')
                    ->restrictOnDelete();
            }

            if (! Schema::hasColumn('users', 'account_deactivated_by')) {
                $table->foreignId('account_deactivated_by')
                    ->nullable()
                    ->after('account_updated_by')
                    ->constrained('users')
                    ->restrictOnDelete();
            }

            if (! Schema::hasColumn('users', 'account_deactivated_at')) {
                $table->timestamp('account_deactivated_at')->nullable()->after('account_deactivated_by');
            }
        });

        Schema::create('sewa_mobil', function (Blueprint $table): void {
            $table->id();
            $table->string('kode_sewa', 24)->nullable()->unique();
            $table->foreignId('aset_koperasi_id')
                ->constrained('aset_koperasi')
                ->restrictOnDelete();
            $table->foreignId('karyawan_id')
                ->constrained('karyawan')
                ->restrictOnDelete();
            $table->foreignId('pemohon_user_id')
                ->constrained('users')
                ->restrictOnDelete();
            $table->string('nama_perusahaan_snapshot', 150);
            $table->string('nama_kegiatan', 150);
            $table->string('lokasi_kegiatan', 150);
            $table->dateTime('mulai_at');
            $table->dateTime('selesai_at');
            $table->decimal('tarif_total', 15, 2)->default(0);
            $table->string('status', 30)->default('draft');
            $table->string('status_pembayaran', 30)->default('belum_bayar');
            $table->foreignId('pengurus_penyetuju_id')
                ->nullable()
                ->constrained('pengurus_koperasi')
                ->restrictOnDelete();
            $table->string('nama_pengurus_snapshot', 150)->nullable();
            $table->string('jabatan_pengurus_snapshot', 100)->nullable();
            $table->foreignId('approval_recorded_by')
                ->nullable()
                ->constrained('users')
                ->restrictOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('alasan_penolakan')->nullable();
            $table->text('alasan_pembatalan')->nullable();
            $table->text('keterangan')->nullable();
            $table->boolean('needs_finance_review')->default(false)->index();
            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->restrictOnDelete();
            $table->foreignId('updated_by')
                ->nullable()
                ->constrained('users')
                ->restrictOnDelete();
            $table->string('idempotency_key', 191)->unique();
            $table->timestamps();

            $table->index(['aset_koperasi_id', 'mulai_at', 'selesai_at', 'status'], 'sewa_mobil_aset_jadwal_status_index');
            $table->index(['karyawan_id', 'status'], 'sewa_mobil_karyawan_status_index');
            $table->index(['status', 'status_pembayaran'], 'sewa_mobil_status_pembayaran_index');
        });

        Schema::create('pembayaran_sewa_mobil', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('sewa_mobil_id')
                ->unique()
                ->constrained('sewa_mobil')
                ->restrictOnDelete();
            $table->foreignId('dompet_id')
                ->constrained('dompet_koperasi')
                ->restrictOnDelete();
            $table->string('metode_pembayaran', 30);
            $table->decimal('jumlah_bayar', 15, 2);
            $table->string('status', 30)->default('paid')->index();
            $table->timestamp('paid_at');
            $table->timestamp('refunded_at')->nullable();
            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->restrictOnDelete();
            $table->string('idempotency_key', 191)->unique();
            $table->timestamps();

            $table->index(['metode_pembayaran', 'status'], 'pembayaran_sewa_mobil_metode_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pembayaran_sewa_mobil');
        Schema::dropIfExists('sewa_mobil');

        Schema::table('users', function (Blueprint $table): void {
            foreach (['account_deactivated_by', 'account_updated_by', 'account_created_by', 'karyawan_id'] as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $table->dropConstrainedForeignId($column);
                }
            }

            foreach (['account_deactivated_at', 'password_changed_at', 'must_change_password', 'is_active'] as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
