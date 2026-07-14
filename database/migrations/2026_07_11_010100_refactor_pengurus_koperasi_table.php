<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->ensureTableIsEmptyBeforeRebuild('upgrade');

        // Mapping identitas legacy tidak pernah ditebak dari nama atau email.
        // Rebuild hanya boleh dilakukan ketika tabel lama benar-benar kosong.
        Schema::dropIfExists('pengurus_koperasi');

        Schema::create('pengurus_koperasi', function (Blueprint $table) {
            $table->id();
            $table->foreignId('anggota_id')
                ->constrained('anggota')
                ->restrictOnDelete();
            $table->string('jabatan', 100);
            $table->string('status', 20)->default('aktif')->index();

            // Generated nullable keys merupakan pengganti partial unique index
            // yang aman pada MariaDB/MySQL: histori nonaktif menghasilkan NULL,
            // sedangkan record aktif wajib unik per Anggota dan per jabatan.
            $table->unsignedBigInteger('anggota_aktif_id')
                ->storedAs("CASE WHEN status = 'aktif' THEN anggota_id ELSE NULL END")
                ->unique();
            $table->string('jabatan_aktif', 100)
                ->storedAs("CASE WHEN status = 'aktif' THEN jabatan ELSE NULL END")
                ->unique();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        $this->ensureTableIsEmptyBeforeRebuild('rollback');

        Schema::dropIfExists('pengurus_koperasi');

        Schema::create('pengurus_koperasi', function (Blueprint $table) {
            $table->id();
            $table->string('nama');
            $table->string('email')->nullable()->unique();
            $table->string('telepon')->nullable();
            $table->string('jabatan');
            $table->timestamps();
        });
    }

    private function ensureTableIsEmptyBeforeRebuild(string $direction): void
    {
        if (! Schema::hasTable('pengurus_koperasi')) {
            return;
        }

        $recordCount = DB::table('pengurus_koperasi')->count();

        if ($recordCount === 0) {
            return;
        }

        if (! Schema::hasColumn('pengurus_koperasi', 'anggota_id')) {
            throw new RuntimeException(
                "Refactor pengurus_koperasi dibatalkan: ditemukan {$recordCount} record legacy "
                . 'yang belum mempunyai mapping anggota_id. Data tidak dihapus dan mapping tidak akan '
                . 'ditebak dari nama atau email. Petakan data secara eksplisit atau kosongkan tabel '
                . 'dummy sebelum menjalankan migration kembali.'
            );
        }

        throw new RuntimeException(
            ucfirst($direction) . " pengurus_koperasi dibatalkan: tabel masih berisi {$recordCount} record. "
            . 'Rebuild tidak dijalankan agar histori Pengurus tidak terhapus secara diam-diam.'
        );
    }
};
