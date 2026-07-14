<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('karyawan', function (Blueprint $table) {
            $table->string('status_kerja', 20)->default('aktif')->after('is_anggota')->index();
            $table->date('tanggal_berhenti')->nullable()->after('status_kerja');
        });

        Schema::create('anggota', function (Blueprint $table) {
            $table->id();
            $table->foreignId('karyawan_id')
                ->unique()
                ->constrained('karyawan')
                ->restrictOnDelete();
            $table->string('nomor_anggota', 20)->unique();
            $table->date('tanggal_bergabung');
            $table->text('alamat');
            $table->string('status', 20)->default('aktif')->index();
            $table->date('tanggal_nonaktif')->nullable();
            $table->decimal('plafon_pinjaman', 15, 2)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('anggota');

        Schema::table('karyawan', function (Blueprint $table) {
            $table->dropColumn(['status_kerja', 'tanggal_berhenti']);
        });
    }
};
