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
        Schema::table('pinjaman', function (Blueprint $table) {
            $table->decimal('biaya_admin', 15, 2)->default(50000)->after('jumlah_pinjaman');
            $table->enum('cara_bayar_admin', ['tunai', 'potong_pinjaman'])->default('potong_pinjaman')->after('biaya_admin');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pinjaman', function (Blueprint $table) {
            $table->dropColumn(['biaya_admin', 'cara_bayar_admin']);
        });
    }
};
