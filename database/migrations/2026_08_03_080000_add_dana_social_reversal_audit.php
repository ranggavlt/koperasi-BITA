<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('klaim_dana_sosial')) {
            return;
        }

        Schema::table('klaim_dana_sosial', function (Blueprint $table): void {
            if (! Schema::hasColumn('klaim_dana_sosial', 'reversed_by')) {
                $table->foreignId('reversed_by')->nullable()->after('paid_by')->constrained('users')->restrictOnDelete();
            }
            if (! Schema::hasColumn('klaim_dana_sosial', 'reversed_at')) {
                $table->timestamp('reversed_at')->nullable()->after('paid_at');
            }
            if (! Schema::hasColumn('klaim_dana_sosial', 'reversal_reason')) {
                $table->text('reversal_reason')->nullable()->after('alasan_penolakan');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('klaim_dana_sosial')) {
            return;
        }

        Schema::table('klaim_dana_sosial', function (Blueprint $table): void {
            if (Schema::hasColumn('klaim_dana_sosial', 'reversed_by')) {
                $table->dropConstrainedForeignId('reversed_by');
            }
            if (Schema::hasColumn('klaim_dana_sosial', 'reversed_at')) {
                $table->dropColumn('reversed_at');
            }
            if (Schema::hasColumn('klaim_dana_sosial', 'reversal_reason')) {
                $table->dropColumn('reversal_reason');
            }
        });
    }
};
