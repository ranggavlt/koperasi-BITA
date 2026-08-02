<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('sewa_mobil') && Schema::hasColumn('sewa_mobil', 'aset_koperasi_id')) {
            Schema::table('sewa_mobil', function (Blueprint $table): void {
                $table->unsignedBigInteger('aset_koperasi_id')->nullable()->change();
            });
        }
    }

    public function down(): void
    {
        // Tidak dikembalikan menjadi NOT NULL karena transaksi vendor final memang tidak memiliki Master Mobil.
    }
};
