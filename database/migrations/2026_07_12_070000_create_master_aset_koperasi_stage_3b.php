<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nomor_urut_aset', function (Blueprint $table): void {
            $table->id();
            $table->string('jenis_aset', 20)->unique();
            $table->unsignedInteger('last_number')->default(0);
            $table->timestamps();
        });

        Schema::create('aset_koperasi', function (Blueprint $table): void {
            $table->id();
            $table->string('kode_aset', 20)->unique();
            $table->string('jenis_aset', 20)->index();
            $table->string('merek', 100);
            $table->string('model', 100);
            $table->string('status', 30)->default('tersedia')->index();
            $table->text('keterangan')->nullable();
            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->restrictOnDelete();
            $table->foreignId('updated_by')
                ->nullable()
                ->constrained('users')
                ->restrictOnDelete();
            $table->timestamp('nonaktif_at')->nullable();
            $table->foreignId('nonaktif_by')
                ->nullable()
                ->constrained('users')
                ->restrictOnDelete();
            $table->timestamps();

            $table->index(['jenis_aset', 'status'], 'aset_koperasi_jenis_status_index');
        });

        Schema::create('aset_mobil', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('aset_koperasi_id')
                ->unique()
                ->constrained('aset_koperasi')
                ->restrictOnDelete();
            $table->string('plat_nomor', 30)->unique();
            $table->unsignedSmallInteger('tahun');
            $table->string('warna', 50);
            $table->timestamps();
        });

        Schema::create('aset_printer', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('aset_koperasi_id')
                ->unique()
                ->constrained('aset_koperasi')
                ->restrictOnDelete();
            $table->string('nomor_seri', 100)->unique();
            $table->string('lokasi', 150);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('aset_printer');
        Schema::dropIfExists('aset_mobil');
        Schema::dropIfExists('aset_koperasi');
        Schema::dropIfExists('nomor_urut_aset');
    }
};
