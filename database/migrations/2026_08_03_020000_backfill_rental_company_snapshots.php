<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['sewa_mobil', 'sewa_hardware'] as $table) {
            if (! Schema::hasTable($table)
                || ! Schema::hasColumn($table, 'perusahaan_id')
                || ! Schema::hasColumn($table, 'karyawan_id')) {
                continue;
            }

            DB::table($table)
                ->whereNull('perusahaan_id')
                ->orderBy('id')
                ->each(function (object $rental) use ($table): void {
                    $company = DB::table('karyawan as k')
                        ->join('perusahaan as p', 'p.id', '=', 'k.perusahaan_id')
                        ->where('k.id', $rental->karyawan_id)
                        ->select('p.id', 'p.kode', 'p.nama')
                        ->first();

                    if (! $company) {
                        return;
                    }

                    $updates = ['perusahaan_id' => $company->id];
                    if (Schema::hasColumn($table, 'kode_perusahaan_snapshot')) {
                        $updates['kode_perusahaan_snapshot'] = $company->kode;
                    }
                    if (Schema::hasColumn($table, 'nama_perusahaan_snapshot')) {
                        $updates['nama_perusahaan_snapshot'] = $company->nama;
                    }

                    DB::table($table)->where('id', $rental->id)->update($updates);
                });
        }
    }

    public function down(): void
    {
        // Data historis yang telah dilengkapi tidak dikosongkan kembali.
    }
};
