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
        Schema::table('shu_anggota', function (Blueprint $table) {
            $table->boolean('is_dicairkan')->default(false)->after('nominal_shu');
            $table->string('metode_pencairan')->nullable()->after('is_dicairkan'); // tunai / transfer
            $table->dateTime('tanggal_pencairan')->nullable()->after('metode_pencairan');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('shu_anggota', function (Blueprint $table) {
            $table->dropColumn(['is_dicairkan', 'metode_pencairan', 'tanggal_pencairan']);
        });
    }
};
