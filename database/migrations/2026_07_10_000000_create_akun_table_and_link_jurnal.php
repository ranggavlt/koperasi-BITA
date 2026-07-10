<?php

use App\Models\Akun;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('akun', function (Blueprint $table) {
            $table->id();
            $table->string('kode_akun', 20)->unique();
            $table->string('nama_akun', 150);
            $table->string('kategori', 20)->index();
            $table->string('posisi_saldo', 10);
            $table->boolean('is_aktif')->default(true)->index();
            $table->boolean('is_sistem')->default(false)->index();
            $table->text('keterangan')->nullable();
            $table->timestamps();
        });

        Schema::table('jurnal_umum_detail', function (Blueprint $table) {
            $table->foreignId('akun_id')
                ->nullable()
                ->after('jurnal_umum_id')
                ->constrained('akun')
                ->restrictOnDelete();
        });

        Schema::table('jenis_simpanan', function (Blueprint $table) {
            $table->foreignId('akun_id')
                ->nullable()
                ->after('id')
                ->constrained('akun')
                ->restrictOnDelete();
        });

        $now = now();
        $systemAccounts = collect(config('account_map.accounts', []))
            ->map(fn (array $account) => [
                'kode_akun' => (string) $account['kode_akun'],
                'nama_akun' => (string) $account['nama_akun'],
                'kategori' => (string) $account['kategori'],
                'posisi_saldo' => (string) $account['posisi_saldo'],
                'is_aktif' => true,
                'is_sistem' => true,
                'keterangan' => $account['keterangan'] ?? null,
                'created_at' => $now,
                'updated_at' => $now,
            ])
            ->values()
            ->all();

        if ($systemAccounts !== []) {
            DB::table('akun')->upsert(
                $systemAccounts,
                ['kode_akun'],
                ['nama_akun', 'kategori', 'posisi_saldo', 'is_aktif', 'is_sistem', 'keterangan', 'updated_at']
            );
        }

        $this->mapJenisSimpananKeAkun();
        $this->remapJurnalSimpananHistoris();

        DB::table('jurnal_umum_detail')
            ->select('akun_kode', 'akun_nama')
            ->whereNotNull('akun_kode')
            ->distinct()
            ->orderBy('akun_kode')
            ->get()
            ->each(function ($legacy) use ($now): void {
                $prefix = (int) substr(trim((string) $legacy->akun_kode), 0, 1);
                $kategori = match ($prefix) {
                    1 => 'aset',
                    2 => 'kewajiban',
                    3 => 'ekuitas',
                    4 => 'pendapatan',
                    5 => 'beban',
                    default => 'aset',
                };

                DB::table('akun')->insertOrIgnore([
                    'kode_akun' => (string) $legacy->akun_kode,
                    'nama_akun' => (string) $legacy->akun_nama,
                    'kategori' => $kategori,
                    'posisi_saldo' => Akun::posisiSaldoUntuk($kategori),
                    'is_aktif' => true,
                    'is_sistem' => false,
                    'keterangan' => 'Dibuat otomatis dari jurnal historis saat migrasi COA.',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            });

        DB::table('jurnal_umum_detail')
            ->select('id', 'akun_kode')
            ->orderBy('id')
            ->chunkById(200, function ($details): void {
                $accounts = DB::table('akun')
                    ->whereIn('kode_akun', $details->pluck('akun_kode')->filter()->unique())
                    ->pluck('id', 'kode_akun');

                foreach ($details as $detail) {
                    $akunId = $accounts[(string) $detail->akun_kode] ?? null;

                    if ($akunId) {
                        DB::table('jurnal_umum_detail')
                            ->where('id', $detail->id)
                            ->update(['akun_id' => $akunId]);
                    }
                }
            });
    }

    public function down(): void
    {
        Schema::table('jenis_simpanan', function (Blueprint $table) {
            $table->dropConstrainedForeignId('akun_id');
        });

        Schema::table('jurnal_umum_detail', function (Blueprint $table) {
            $table->dropConstrainedForeignId('akun_id');
        });

        Schema::dropIfExists('akun');
    }

    private function mapJenisSimpananKeAkun(): void
    {
        $fallbackKey = 'simpanan_belum_terklasifikasi';

        DB::table('jenis_simpanan')
            ->select('id', 'nama_jenis')
            ->orderBy('id')
            ->get()
            ->each(function ($jenis) use ($fallbackKey): void {
                $slug = Str::slug((string) $jenis->nama_jenis);
                $accountKey = config("account_map.postings.simpanan.jenis.{$slug}", $fallbackKey);
                $accountCode = config("account_map.accounts.{$accountKey}.kode_akun");
                $akunId = DB::table('akun')->where('kode_akun', $accountCode)->value('id');

                if ($akunId) {
                    DB::table('jenis_simpanan')
                        ->where('id', $jenis->id)
                        ->update(['akun_id' => $akunId]);
                }
            });
    }

    private function remapJurnalSimpananHistoris(): void
    {
        DB::table('jurnal_umum_detail as jud')
            ->join('jurnal_umum as ju', 'ju.id', '=', 'jud.jurnal_umum_id')
            ->join('simpanan as s', 's.id', '=', 'ju.referensi_id')
            ->join('jenis_simpanan as js', 'js.id', '=', 's.jenis_simpanan_id')
            ->join('akun as a', 'a.id', '=', 'js.akun_id')
            ->where('ju.referensi_tipe', 'App\\Models\\Simpanan')
            ->where('jud.kredit', '>', 0)
            ->select('jud.id', 'a.id as akun_id', 'a.kode_akun', 'a.nama_akun')
            ->orderBy('jud.id')
            ->get()
            ->each(function ($detail): void {
                DB::table('jurnal_umum_detail')
                    ->where('id', $detail->id)
                    ->update([
                        'akun_id' => $detail->akun_id,
                        'akun_kode' => $detail->kode_akun,
                        'akun_nama' => $detail->nama_akun,
                    ]);
            });
    }
};
