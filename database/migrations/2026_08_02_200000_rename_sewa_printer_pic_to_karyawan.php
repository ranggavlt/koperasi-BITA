<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('sewa_printer')
            && Schema::hasColumn('sewa_printer', 'karyawan_pic_id')
            && ! Schema::hasColumn('sewa_printer', 'karyawan_id')) {
            Schema::table('sewa_printer', function (Blueprint $table): void {
                $table->renameColumn('karyawan_pic_id', 'karyawan_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('sewa_printer')
            && Schema::hasColumn('sewa_printer', 'karyawan_id')
            && ! Schema::hasColumn('sewa_printer', 'karyawan_pic_id')) {
            Schema::table('sewa_printer', function (Blueprint $table): void {
                $table->renameColumn('karyawan_id', 'karyawan_pic_id');
            });
        }
    }
};
