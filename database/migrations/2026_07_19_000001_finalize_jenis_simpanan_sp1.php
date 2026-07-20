<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('jenis_simpanan', function (Blueprint $table): void {
            if (! Schema::hasColumn('jenis_simpanan', 'kategori')) {
                $table->string('kategori', 20)->nullable()->after('kode')->index();
            }

            if (! Schema::hasColumn('jenis_simpanan', 'interval_bulan')) {
                $table->unsignedTinyInteger('interval_bulan')->nullable()->after('kategori');
            }

            if (! Schema::hasColumn('jenis_simpanan', 'berlaku_mulai')) {
                $table->date('berlaku_mulai')->nullable()->after('interval_bulan')->index();
            }

            if (! Schema::hasColumn('jenis_simpanan', 'active_kategori_marker')) {
                $marker = $table->string('active_kategori_marker', 20)->nullable()->after('aktif');

                if (Schema::getConnection()->getDriverName() === 'mysql') {
                    $marker->storedAs("case when `aktif` = 1 and `kategori` is not null then `kategori` else null end");
                }

                $marker->unique('jenis_simpanan_active_kategori_unique');
            }

            if (! Schema::hasColumn('jenis_simpanan', 'created_by')) {
                $table->foreignId('created_by')
                    ->nullable()
                    ->after('keterangan')
                    ->constrained('users')
                    ->restrictOnDelete();
            }

            if (! Schema::hasColumn('jenis_simpanan', 'updated_by')) {
                $table->foreignId('updated_by')
                    ->nullable()
                    ->after('created_by')
                    ->constrained('users')
                    ->restrictOnDelete();
            }
        });

        $this->backfillOfficialCategories();

        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            DB::table('jenis_simpanan')
                ->where('aktif', true)
                ->whereNotNull('kategori')
                ->update([
                    'active_kategori_marker' => DB::raw('kategori'),
                ]);

            DB::table('jenis_simpanan')
                ->where(function ($query): void {
                    $query->where('aktif', false)
                        ->orWhereNull('kategori');
                })
                ->update(['active_kategori_marker' => null]);
        }

        if (! Schema::hasTable('riwayat_jenis_simpanan')) {
            Schema::create('riwayat_jenis_simpanan', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('jenis_simpanan_id')
                    ->constrained('jenis_simpanan')
                    ->restrictOnDelete();
                $table->json('konfigurasi_sebelum')->nullable();
                $table->json('konfigurasi_sesudah');
                $table->text('alasan');
                $table->foreignId('changed_by')
                    ->nullable()
                    ->constrained('users')
                    ->restrictOnDelete();
                $table->timestamp('changed_at');
                $table->timestamps();

                $table->index(['jenis_simpanan_id', 'changed_at'], 'riwayat_js_jenis_changed_index');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('riwayat_jenis_simpanan');

        Schema::table('jenis_simpanan', function (Blueprint $table): void {
            if (Schema::hasColumn('jenis_simpanan', 'updated_by')) {
                $table->dropConstrainedForeignId('updated_by');
            }

            if (Schema::hasColumn('jenis_simpanan', 'created_by')) {
                $table->dropConstrainedForeignId('created_by');
            }

            if (Schema::hasColumn('jenis_simpanan', 'active_kategori_marker')) {
                $table->dropUnique('jenis_simpanan_active_kategori_unique');
                $table->dropColumn('active_kategori_marker');
            }

            foreach (['berlaku_mulai', 'interval_bulan', 'kategori'] as $column) {
                if (Schema::hasColumn('jenis_simpanan', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }

    private function backfillOfficialCategories(): void
    {
        $today = now(config('app.timezone', 'Asia/Jakarta'))->toDateString();

        $this->backfillExactCode('SIMPANAN_POKOK', [
            'kategori' => 'pokok',
            'interval_bulan' => null,
            'berlaku_mulai' => DB::raw("COALESCE(DATE(created_at), '{$today}')"),
        ]);

        $this->backfillExactCode('SIMPANAN_SUKARELA', [
            'kategori' => 'sukarela',
            'interval_bulan' => null,
            'berlaku_mulai' => DB::raw("COALESCE(DATE(created_at), '{$today}')"),
        ]);

        $this->backfillExactCode('SIMPANAN_WAJIB', [
            'kategori' => 'wajib',
            'interval_bulan' => DB::raw('COALESCE(interval_bulan, 1)'),
            'berlaku_mulai' => DB::raw("COALESCE(DATE(created_at), '{$today}')"),
        ]);

        $legacyWajib = DB::table('jenis_simpanan')
            ->where('kode', 'SIMPANAN_WAJIB_BULANAN')
            ->get();
        $hasOfficialWajib = DB::table('jenis_simpanan')
            ->where('kode', 'SIMPANAN_WAJIB')
            ->exists();

        if ($legacyWajib->count() === 1 && ! $hasOfficialWajib) {
            DB::table('jenis_simpanan')
                ->where('id', $legacyWajib->first()->id)
                ->update([
                    'kode' => 'SIMPANAN_WAJIB',
                    'kategori' => 'wajib',
                    'interval_bulan' => 1,
                    'berlaku_mulai' => $legacyWajib->first()->created_at
                        ? substr((string) $legacyWajib->first()->created_at, 0, 10)
                        : $today,
                ]);
        }
    }

    private function backfillExactCode(string $kode, array $updates): void
    {
        DB::table('jenis_simpanan')
            ->where('kode', $kode)
            ->update($updates);
    }
};
