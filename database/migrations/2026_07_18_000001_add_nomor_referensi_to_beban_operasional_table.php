<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('beban_operasional') || Schema::hasColumn('beban_operasional', 'nomor_referensi')) {
            return;
        }

        Schema::table('beban_operasional', function (Blueprint $table): void {
            $table->string('nomor_referensi', 50)
                ->nullable()
                ->after('keterangan');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('beban_operasional') || ! Schema::hasColumn('beban_operasional', 'nomor_referensi')) {
            return;
        }

        Schema::table('beban_operasional', function (Blueprint $table): void {
            $table->dropColumn('nomor_referensi');
        });
    }
};
