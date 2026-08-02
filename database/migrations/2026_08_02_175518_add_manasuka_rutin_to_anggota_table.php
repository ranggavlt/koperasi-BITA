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
        if (! Schema::hasTable('anggota')) {
            return;
        }

        Schema::table('anggota', function (Blueprint $table) {
            if (! Schema::hasColumn('anggota', 'manasuka_rutin_nominal')) {
                $table->decimal('manasuka_rutin_nominal', 15, 2)->default(0)->after('plafon_pinjaman');
            }

            if (! Schema::hasColumn('anggota', 'is_manasuka_rutin_active')) {
                $table->boolean('is_manasuka_rutin_active')->default(false)->after('manasuka_rutin_nominal');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('anggota')) {
            return;
        }

        Schema::table('anggota', function (Blueprint $table) {
            foreach (['is_manasuka_rutin_active', 'manasuka_rutin_nominal'] as $column) {
                if (Schema::hasColumn('anggota', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
