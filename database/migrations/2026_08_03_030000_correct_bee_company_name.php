<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('perusahaan')
            ->where('kode', 'BEE')
            ->where('nama', 'Bita Enercoon Engineering')
            ->update(['nama' => 'Bita Enarcon Engineering', 'updated_at' => now()]);

        foreach (['sewa_mobil', 'sewa_printer', 'invoice_penagihan', 'limit_potong_gaji_anggota', 'kebijakan_limit_potong_gaji'] as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'nama_perusahaan_snapshot')) {
                DB::table($table)
                    ->where('nama_perusahaan_snapshot', 'Bita Enercoon Engineering')
                    ->update(['nama_perusahaan_snapshot' => 'Bita Enarcon Engineering']);
            }
        }
    }

    public function down(): void
    {
        // Koreksi nama resmi tidak dikembalikan ke ejaan yang salah.
    }
};
