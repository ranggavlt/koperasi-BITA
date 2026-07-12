<?php

use App\Models\Anggota;
use App\Models\JenisSimpanan;
use App\Models\Simpanan;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->hardenAkunBebanOperasional();
        $this->createSiklusKeanggotaan();
        $this->backfillSiklusKeanggotaan();
        $this->createPenyelesaianKeanggotaan();
        $this->extendSimpananForSiklus();
        $this->backfillSimpananSiklus();
        $this->extendJadwalForOffset();
    }

    public function down(): void
    {
        if (Schema::hasColumn('jadwal_cicilan_pinjaman', 'nominal_offset')) {
            Schema::table('jadwal_cicilan_pinjaman', function (Blueprint $table): void {
                $table->dropColumn(['nominal_offset', 'nominal_sisa']);
            });
        }

        foreach (['simpanan_pokok_siklus_id', 'penyelesaian_keanggotaan_id', 'siklus_keanggotaan_id'] as $column) {
            if (Schema::hasColumn('simpanan', $column)) {
                Schema::table('simpanan', function (Blueprint $table) use ($column): void {
                    if (str_ends_with($column, '_id') && $column !== 'simpanan_pokok_siklus_id') {
                        $table->dropConstrainedForeignId($column);
                    } else {
                        try {
                            $table->dropUnique('simpanan_pokok_siklus_unique');
                        } catch (\Throwable) {
                        }
                        $table->dropColumn($column);
                    }
                });
            }
        }

        try {
            Schema::table('simpanan', function (Blueprint $table): void {
                if (Schema::hasColumn('simpanan', 'simpanan_pokok_anggota_id')) {
                    $table->unique('simpanan_pokok_anggota_id', 'simpanan_pokok_anggota_unique');
                }
            });
        } catch (\Throwable) {
        }

        Schema::dropIfExists('penyelesaian_keanggotaan_detail');
        Schema::dropIfExists('penyelesaian_keanggotaan');
        Schema::dropIfExists('siklus_keanggotaan');
        Schema::dropIfExists('riwayat_akun_beban_operasional');

        foreach (['beban_operasional_updated_by', 'is_beban_operasional'] as $column) {
            if (Schema::hasColumn('akun', $column)) {
                Schema::table('akun', function (Blueprint $table) use ($column): void {
                    if ($column === 'beban_operasional_updated_by') {
                        $table->dropConstrainedForeignId($column);
                    } else {
                        $table->dropColumn($column);
                    }
                });
            }
        }
    }

    private function hardenAkunBebanOperasional(): void
    {
        Schema::table('akun', function (Blueprint $table): void {
            if (! Schema::hasColumn('akun', 'is_beban_operasional')) {
                $table->boolean('is_beban_operasional')
                    ->default(false)
                    ->after('is_sistem')
                    ->index();
            }

            if (! Schema::hasColumn('akun', 'beban_operasional_updated_by')) {
                $table->foreignId('beban_operasional_updated_by')
                    ->nullable()
                    ->after('is_beban_operasional')
                    ->constrained('users')
                    ->restrictOnDelete();
            }
        });

        Schema::create('riwayat_akun_beban_operasional', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('akun_id')
                ->constrained('akun')
                ->restrictOnDelete();
            $table->boolean('nilai_sebelum');
            $table->boolean('nilai_sesudah');
            $table->text('alasan');
            $table->foreignId('changed_by')
                ->nullable()
                ->constrained('users')
                ->restrictOnDelete();
            $table->timestamp('changed_at');
            $table->timestamps();

            $table->index(['akun_id', 'changed_at'], 'riwayat_akun_bop_akun_changed_index');
        });

        $eligibleCodes = collect(config('account_map.accounts', []))
            ->filter(fn (array $account) => (bool) ($account['is_beban_operasional'] ?? false))
            ->pluck('kode_akun')
            ->map(fn ($code) => (string) $code)
            ->all();

        if ($eligibleCodes !== []) {
            DB::table('akun')
                ->whereIn('kode_akun', $eligibleCodes)
                ->where('kategori', 'beban')
                ->where('posisi_saldo', 'debit')
                ->update(['is_beban_operasional' => true]);
        }
    }

    private function createSiklusKeanggotaan(): void
    {
        Schema::create('siklus_keanggotaan', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('anggota_id')
                ->constrained('anggota')
                ->restrictOnDelete();
            $table->unsignedInteger('siklus_ke');
            $table->date('tanggal_mulai')->nullable();
            $table->date('tanggal_selesai')->nullable();
            $table->string('status', 20)->default('active')->index();

            $marker = $table->unsignedBigInteger('active_anggota_id')->nullable();
            if (Schema::getConnection()->getDriverName() === 'mysql') {
                $marker->storedAs("case when `status` = 'active' then `anggota_id` else null end");
            }
            $marker->unique('siklus_keanggotaan_active_unique');

            $table->text('alasan_selesai')->nullable();
            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->restrictOnDelete();
            $table->foreignId('closed_by')
                ->nullable()
                ->constrained('users')
                ->restrictOnDelete();
            $table->timestamps();

            $table->unique(['anggota_id', 'siklus_ke'], 'siklus_keanggotaan_anggota_ke_unique');
            $table->index(['anggota_id', 'status'], 'siklus_keanggotaan_anggota_status_index');
        });
    }

    private function backfillSiklusKeanggotaan(): void
    {
        if (! Schema::hasTable('siklus_keanggotaan')) {
            return;
        }

        $now = now();
        $isMysql = Schema::getConnection()->getDriverName() === 'mysql';

        DB::table('anggota')
            ->orderBy('id')
            ->get()
            ->each(function ($anggota) use ($now, $isMysql): void {
                $status = $anggota->status === Anggota::STATUS_AKTIF ? 'active' : 'closed';
                $row = [
                    'anggota_id' => $anggota->id,
                    'siklus_ke' => 1,
                    'tanggal_mulai' => $anggota->tanggal_bergabung,
                    'tanggal_selesai' => $status === 'closed' ? $anggota->tanggal_nonaktif : null,
                    'status' => $status,
                    'alasan_selesai' => $status === 'closed' ? 'Backfill dari status Anggota nonaktif.' : null,
                    'created_by' => null,
                    'closed_by' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];

                if (! $isMysql) {
                    $row['active_anggota_id'] = $status === 'active' ? $anggota->id : null;
                }

                DB::table('siklus_keanggotaan')->insertOrIgnore($row);
            });
    }

    private function createPenyelesaianKeanggotaan(): void
    {
        Schema::create('penyelesaian_keanggotaan', function (Blueprint $table): void {
            $table->id();
            $table->string('kode_penyelesaian', 32)->unique();
            $table->foreignId('anggota_id')
                ->constrained('anggota')
                ->restrictOnDelete();
            $table->foreignId('siklus_keanggotaan_id')
                ->constrained('siklus_keanggotaan')
                ->restrictOnDelete();
            $table->date('tanggal_keluar')->nullable();
            $table->decimal('simpanan_pokok_snapshot', 15, 2)->default(0);
            $table->decimal('kredit_refund_snapshot', 15, 2)->default(0);
            $table->decimal('total_hak_anggota', 15, 2)->default(0);
            $table->decimal('total_kewajiban_awal', 15, 2)->default(0);
            $table->decimal('total_offset', 15, 2)->default(0);
            $table->decimal('total_refund', 15, 2)->default(0);
            $table->decimal('sisa_kewajiban', 15, 2)->default(0);
            $table->string('status', 40)->default('pending_review')->index();

            $marker = $table->unsignedBigInteger('siklus_final_id')->nullable();
            if (Schema::getConnection()->getDriverName() === 'mysql') {
                $marker->storedAs("case when `status` <> 'cancelled' then `siklus_keanggotaan_id` else null end");
            }
            $marker->unique('penyelesaian_keanggotaan_siklus_final_unique');

            $table->foreignId('dompet_refund_id')
                ->nullable()
                ->constrained('dompet_koperasi')
                ->restrictOnDelete();
            $table->string('metode_refund', 30)->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->text('alasan');
            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->restrictOnDelete();
            $table->foreignId('processed_by')
                ->nullable()
                ->constrained('users')
                ->restrictOnDelete();
            $table->foreignId('completed_by')
                ->nullable()
                ->constrained('users')
                ->restrictOnDelete();
            $table->string('idempotency_key', 191)->unique();
            $table->timestamps();

            $table->index(['anggota_id', 'status'], 'penyelesaian_keanggotaan_anggota_status_index');
        });

        Schema::create('penyelesaian_keanggotaan_detail', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('penyelesaian_keanggotaan_id')
                ->nullable(false);
            $table->string('kategori_sumber', 30);
            $table->string('source_type', 160);
            $table->unsignedBigInteger('source_id');
            $table->decimal('nominal_kewajiban_awal', 15, 2)->default(0);
            $table->decimal('nominal_offset', 15, 2)->default(0);
            $table->decimal('nominal_dibayar_tunai', 15, 2)->default(0);
            $table->decimal('nominal_sisa', 15, 2)->default(0);
            $table->unsignedInteger('urutan_alokasi')->default(0);
            $table->string('status', 30)->default('open')->index();
            $table->timestamps();

            $table->unique(['penyelesaian_keanggotaan_id', 'source_type', 'source_id'], 'penyelesaian_detail_source_unique');
            $table->index(['source_type', 'source_id'], 'penyelesaian_detail_source_index');
            $table->foreign('penyelesaian_keanggotaan_id', 'penyelesaian_detail_header_fk')
                ->references('id')
                ->on('penyelesaian_keanggotaan')
                ->restrictOnDelete();
        });
    }

    private function extendSimpananForSiklus(): void
    {
        try {
            Schema::table('simpanan', function (Blueprint $table): void {
                if (Schema::hasColumn('simpanan', 'simpanan_pokok_anggota_id')) {
                    try {
                        $table->dropUnique('simpanan_pokok_anggota_unique');
                    } catch (\Throwable) {
                    }
                }
            });
        } catch (\Throwable) {
        }

        Schema::table('simpanan', function (Blueprint $table): void {
            if (! Schema::hasColumn('simpanan', 'siklus_keanggotaan_id')) {
                $table->foreignId('siklus_keanggotaan_id')
                    ->nullable()
                    ->after('anggota_id')
                    ->constrained('siklus_keanggotaan')
                    ->restrictOnDelete();
            }

            if (! Schema::hasColumn('simpanan', 'penyelesaian_keanggotaan_id')) {
                $table->foreignId('penyelesaian_keanggotaan_id')
                    ->nullable()
                    ->after('replacement_simpanan_id')
                    ->constrained('penyelesaian_keanggotaan')
                    ->restrictOnDelete();
            }

            if (! Schema::hasColumn('simpanan', 'simpanan_pokok_siklus_id')) {
                $marker = $table->unsignedBigInteger('simpanan_pokok_siklus_id')
                    ->nullable()
                    ->after('simpanan_pokok_anggota_id');

                if (Schema::getConnection()->getDriverName() === 'mysql') {
                    $marker->storedAs("case when `kode_jenis_snapshot` = 'SIMPANAN_POKOK' and `status` not in ('reversed', 'reversed_due_to_exit') then `siklus_keanggotaan_id` else null end");
                }

                $marker->unique('simpanan_pokok_siklus_unique');
            }
        });
    }

    private function backfillSimpananSiklus(): void
    {
        if (! Schema::hasColumn('simpanan', 'siklus_keanggotaan_id')) {
            return;
        }

        DB::table('simpanan')
            ->whereNotNull('anggota_id')
            ->whereNull('siklus_keanggotaan_id')
            ->orderBy('id')
            ->get()
            ->each(function ($simpanan): void {
                $tanggal = $simpanan->tanggal ?? null;
                $siklus = DB::table('siklus_keanggotaan')
                    ->where('anggota_id', $simpanan->anggota_id)
                    ->when($tanggal, function ($query) use ($tanggal): void {
                        $query->where(function ($nested) use ($tanggal): void {
                            $nested->whereNull('tanggal_mulai')->orWhere('tanggal_mulai', '<=', $tanggal);
                        })->where(function ($nested) use ($tanggal): void {
                            $nested->whereNull('tanggal_selesai')->orWhere('tanggal_selesai', '>=', $tanggal);
                        });
                    })
                    ->orderByDesc('siklus_ke')
                    ->first();

                if (! $siklus) {
                    $siklus = DB::table('siklus_keanggotaan')
                        ->where('anggota_id', $simpanan->anggota_id)
                        ->orderByDesc('siklus_ke')
                        ->first();
                }

                if ($siklus) {
                    DB::table('simpanan')
                        ->where('id', $simpanan->id)
                        ->update(['siklus_keanggotaan_id' => $siklus->id]);
                }
            });
    }

    private function extendJadwalForOffset(): void
    {
        Schema::table('jadwal_cicilan_pinjaman', function (Blueprint $table): void {
            if (Schema::hasColumn('jadwal_cicilan_pinjaman', 'metode_penyelesaian')) {
                $table->string('metode_penyelesaian', 40)->nullable()->change();
            }

            if (! Schema::hasColumn('jadwal_cicilan_pinjaman', 'nominal_offset')) {
                $table->decimal('nominal_offset', 15, 2)->default(0)->after('nominal_pokok');
            }

            if (! Schema::hasColumn('jadwal_cicilan_pinjaman', 'nominal_sisa')) {
                $table->decimal('nominal_sisa', 15, 2)->nullable()->after('nominal_offset');
            }
        });

        DB::table('jadwal_cicilan_pinjaman')
            ->whereNull('nominal_sisa')
            ->update([
                'nominal_sisa' => DB::raw('nominal_pokok'),
            ]);
    }
};
