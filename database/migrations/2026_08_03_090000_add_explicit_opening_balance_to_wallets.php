<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('dompet_koperasi')) {
            return;
        }

        if (! Schema::hasColumn('dompet_koperasi', 'saldo_awal')) {
            Schema::table('dompet_koperasi', function (Blueprint $table): void {
                $table->decimal('saldo_awal', 15, 2)->default(0)->after('saldo');
            });
        }

        DB::table('dompet_koperasi')->orderBy('id')->get()->each(function ($wallet): void {
            $incoming = Schema::hasTable('mutasi_kas')
                ? (int) DB::table('mutasi_kas')->where('dompet_id', $wallet->id)->where('tipe', 'masuk')->sum('jumlah')
                : 0;
            $outgoing = Schema::hasTable('mutasi_kas')
                ? (int) DB::table('mutasi_kas')->where('dompet_id', $wallet->id)->where('tipe', 'keluar')->sum('jumlah')
                : 0;

            DB::table('dompet_koperasi')->where('id', $wallet->id)->update([
                'saldo_awal' => (int) $wallet->saldo - $incoming + $outgoing,
            ]);
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('dompet_koperasi') && Schema::hasColumn('dompet_koperasi', 'saldo_awal')) {
            Schema::table('dompet_koperasi', function (Blueprint $table): void {
                $table->dropColumn('saldo_awal');
            });
        }
    }
};
