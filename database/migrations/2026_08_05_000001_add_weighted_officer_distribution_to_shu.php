<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pengurus_koperasi', function (Blueprint $table): void {
            $table->string('kelompok', 20)->default('pengurus')->after('jabatan')->index('officer_group_idx');
        });

        Schema::table('shu_penerima', function (Blueprint $table): void {
            $table->decimal('bobot', 8, 3)->default(1)->after('jabatan_snapshot');
            $table->unique(['shu_koperasi_id', 'pengurus_koperasi_id'], 'shu_recipient_officer_uq');
        });
    }

    public function down(): void
    {
        Schema::table('shu_penerima', function (Blueprint $table): void {
            $table->dropUnique('shu_recipient_officer_uq');
            $table->dropColumn('bobot');
        });

        Schema::table('pengurus_koperasi', function (Blueprint $table): void {
            $table->dropIndex('officer_group_idx');
            $table->dropColumn('kelompok');
        });
    }
};
