<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('kebijakan_limit_potong_gaji')) {
            return;
        }

        Schema::table('kebijakan_limit_potong_gaji', function (Blueprint $table): void {
            if (! Schema::hasColumn('kebijakan_limit_potong_gaji', 'nominal_limit')) {
                $table->decimal('nominal_limit', 15, 2)->default(1500000);
            }
            if (! Schema::hasColumn('kebijakan_limit_potong_gaji', 'status')) {
                $table->string('status', 20)->default('active');
            }
            if (! Schema::hasColumn('kebijakan_limit_potong_gaji', 'berlaku_mulai_periode')) {
                $table->date('berlaku_mulai_periode')->nullable();
            }
            if (! Schema::hasColumn('kebijakan_limit_potong_gaji', 'berlaku_sampai_periode')) {
                $table->date('berlaku_sampai_periode')->nullable();
            }
            if (! Schema::hasColumn('kebijakan_limit_potong_gaji', 'updated_by')) {
                $table->unsignedBigInteger('updated_by')->nullable();
            }
        });

        if (Schema::hasColumn('kebijakan_limit_potong_gaji', 'limit_nominal')) {
            DB::table('kebijakan_limit_potong_gaji')->update([
                'nominal_limit' => DB::raw('limit_nominal'),
            ]);
        }
        if (Schema::hasColumn('kebijakan_limit_potong_gaji', 'aktif')) {
            DB::table('kebijakan_limit_potong_gaji')->update([
                'status' => DB::raw("CASE WHEN aktif = 1 THEN 'active' ELSE 'inactive' END"),
            ]);
        }
        if (Schema::hasColumn('kebijakan_limit_potong_gaji', 'berlaku_mulai')) {
            DB::table('kebijakan_limit_potong_gaji')->update([
                'berlaku_mulai_periode' => DB::raw('berlaku_mulai'),
            ]);
            Schema::table('kebijakan_limit_potong_gaji', function (Blueprint $table): void {
                $table->date('berlaku_mulai')->nullable()->change();
            });
        }
        if (Schema::hasColumn('kebijakan_limit_potong_gaji', 'berlaku_sampai')) {
            DB::table('kebijakan_limit_potong_gaji')->update([
                'berlaku_sampai_periode' => DB::raw('berlaku_sampai'),
            ]);
        }
        if (Schema::hasColumn('kebijakan_limit_potong_gaji', 'idempotency_key')) {
            Schema::table('kebijakan_limit_potong_gaji', function (Blueprint $table): void {
                $table->string('idempotency_key', 191)->nullable()->change();
            });
        }

        $this->migrateLegacyMemberSettings();
    }

    private function migrateLegacyMemberSettings(): void
    {
        if (! Schema::hasTable('pengaturan_payroll_anggota')
            || ! Schema::hasTable('override_limit_potong_gaji_anggota')) {
            return;
        }

        $rows = DB::table('pengaturan_payroll_anggota')
            ->orderBy('berlaku_mulai')
            ->orderBy('id')
            ->get()
            ->keyBy('anggota_id');

        foreach ($rows as $row) {
            DB::table('override_limit_potong_gaji_anggota')->updateOrInsert(
                ['anggota_id' => $row->anggota_id],
                [
                    'nominal_override' => $row->limit_override_nominal,
                    'status' => $row->limit_override_nominal === null ? 'inactive' : 'active',
                    'berlaku_mulai_periode' => $row->berlaku_mulai,
                    'alasan_limit_override' => $row->alasan,
                    'kredit_waserba_enabled' => (bool) $row->kredit_waserba_aktif,
                    'created_by' => $row->created_by,
                    'updated_by' => $row->created_by,
                    'created_at' => $row->created_at,
                    'updated_at' => $row->updated_at,
                ]
            );
        }
    }

    public function down(): void
    {
        // Kolom canonical dipertahankan agar rollback tidak merusak data PG-1.
    }
};
