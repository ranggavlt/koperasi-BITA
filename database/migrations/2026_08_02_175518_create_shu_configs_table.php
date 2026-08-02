<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('shu_configs')) {
            return;
        }

        Schema::create('shu_configs', function (Blueprint $table) {
            $table->id();
            $table->decimal('persen_pembina', 5, 2)->default(0);
            $table->decimal('persen_pengawas', 5, 2)->default(0);
            $table->decimal('persen_pengurus', 5, 2)->default(0);
            $table->decimal('persen_anggota', 5, 2)->default(0);
            $table->decimal('persen_dana_sosial', 5, 2)->default(0);
            $table->decimal('persen_dana_cadangan', 5, 2)->default(0);
            $table->decimal('persen_dana_pendidikan', 5, 2)->default(0);
            $table->decimal('persen_jasa_modal', 5, 2)->default(0);
            $table->decimal('persen_jasa_usaha', 5, 2)->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shu_configs');
    }
};
