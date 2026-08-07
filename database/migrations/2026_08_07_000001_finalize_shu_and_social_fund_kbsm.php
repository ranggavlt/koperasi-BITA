<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->createOrganizationStructure();
        $this->hardenShuRecipientsAndPayments();
        $this->createShuAllocations();
        $this->createSocialBenefitPolicies();
        $this->hardenSocialFundSourcesAndClaims();
    }

    private function createOrganizationStructure(): void
    {
        if (! Schema::hasTable('struktur_koperasi')) {
            Schema::create('struktur_koperasi', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('anggota_id')->nullable()->constrained('anggota')->restrictOnDelete();
                $table->string('nama_penerima', 150)->nullable();
                $table->string('kelompok', 20)->index();
                $table->string('jabatan', 120);
                $table->date('tanggal_mulai')->index();
                $table->date('tanggal_selesai')->nullable()->index();
                $table->string('status', 20)->default('aktif')->index();
                $table->string('dasar_keputusan', 255);
                $table->foreignId('created_by')->nullable()->constrained('users')->restrictOnDelete();
                $table->timestamps();

                $table->index(
                    ['kelompok', 'status', 'tanggal_mulai', 'tanggal_selesai'],
                    'struktur_kelompok_status_periode_idx'
                );
            });
        }

        if (Schema::hasTable('pengurus_koperasi') && DB::table('struktur_koperasi')->count() === 0) {
            DB::table('pengurus_koperasi')->orderBy('id')->get()->each(function (object $row): void {
                DB::table('struktur_koperasi')->insert([
                    'anggota_id' => $row->anggota_id ?? null,
                    'nama_penerima' => null,
                    'kelompok' => $row->kelompok ?? 'pengurus',
                    'jabatan' => $row->jabatan,
                    'tanggal_mulai' => substr((string) ($row->created_at ?? now()), 0, 10),
                    'tanggal_selesai' => ($row->status ?? 'aktif') === 'aktif'
                        ? null
                        : substr((string) ($row->updated_at ?? now()), 0, 10),
                    'status' => $row->status ?? 'aktif',
                    'dasar_keputusan' => 'Migrasi histori Master Pengurus lama',
                    'created_by' => null,
                    'created_at' => $row->created_at ?? now(),
                    'updated_at' => $row->updated_at ?? now(),
                ]);
            });
        }
    }

    private function hardenShuRecipientsAndPayments(): void
    {
        Schema::table('shu_penerima', function (Blueprint $table): void {
            if (! Schema::hasColumn('shu_penerima', 'struktur_koperasi_id')) {
                $table->foreignId('struktur_koperasi_id')->nullable()->after('pengurus_koperasi_id')
                    ->constrained('struktur_koperasi')->restrictOnDelete();
            }
            if (! Schema::hasColumn('shu_penerima', 'kelompok_snapshot')) {
                $table->string('kelompok_snapshot', 30)->nullable()->after('jabatan_snapshot');
            }
            if (! Schema::hasColumn('shu_penerima', 'nomor_anggota_snapshot')) {
                $table->string('nomor_anggota_snapshot', 50)->nullable()->after('kelompok_snapshot');
            }
            if (! Schema::hasColumn('shu_penerima', 'simpanan_wajib_dihitung')) {
                $table->decimal('simpanan_wajib_dihitung', 15, 2)->default(0)->after('bobot');
            }
            if (! Schema::hasColumn('shu_penerima', 'simpanan_manasuka_dihitung')) {
                $table->decimal('simpanan_manasuka_dihitung', 15, 2)->default(0)->after('simpanan_wajib_dihitung');
            }
            if (! Schema::hasColumn('shu_penerima', 'hitungan_sistem')) {
                $table->decimal('hitungan_sistem', 15, 2)->default(0)->after('nominal_jasa_usaha');
            }
            if (! Schema::hasColumn('shu_penerima', 'hak_final')) {
                $table->decimal('hak_final', 15, 2)->nullable()->after('hitungan_sistem');
            }
            if (! Schema::hasColumn('shu_penerima', 'alasan_hak_final')) {
                $table->string('alasan_hak_final', 50)->nullable()->after('hak_final');
            }
            if (! Schema::hasColumn('shu_penerima', 'detail_alasan_hak_final')) {
                $table->text('detail_alasan_hak_final')->nullable()->after('alasan_hak_final');
            }
            if (! Schema::hasColumn('shu_penerima', 'hak_final_ditetapkan_by')) {
                $table->foreignId('hak_final_ditetapkan_by')->nullable()->after('detail_alasan_hak_final')
                    ->constrained('users')->restrictOnDelete();
            }
            if (! Schema::hasColumn('shu_penerima', 'hak_final_ditetapkan_at')) {
                $table->timestamp('hak_final_ditetapkan_at')->nullable()->after('hak_final_ditetapkan_by');
            }
            if (! Schema::hasColumn('shu_penerima', 'eligible')) {
                $table->boolean('eligible')->default(true)->after('hak_final_ditetapkan_at');
            }
            if (! Schema::hasColumn('shu_penerima', 'diikutkan')) {
                $table->boolean('diikutkan')->default(true)->after('eligible');
            }
            if (! Schema::hasColumn('shu_penerima', 'alasan_eligibility')) {
                $table->text('alasan_eligibility')->nullable()->after('diikutkan');
            }
            if (! Schema::hasColumn('shu_penerima', 'eligibility_set_by')) {
                $table->foreignId('eligibility_set_by')->nullable()->after('alasan_eligibility')
                    ->constrained('users')->restrictOnDelete();
            }
            if (! Schema::hasColumn('shu_penerima', 'eligibility_set_at')) {
                $table->timestamp('eligibility_set_at')->nullable()->after('eligibility_set_by');
            }
            if (! Schema::hasColumn('shu_penerima', 'idempotency_key')) {
                $table->string('idempotency_key', 191)->nullable()->after('formula_snapshot')->unique();
            }
        });

        Schema::table('pembayaran_shu', function (Blueprint $table): void {
            if (! Schema::hasColumn('pembayaran_shu', 'catatan')) {
                $table->text('catatan')->nullable()->after('nomor_referensi');
            }
            if (! Schema::hasColumn('pembayaran_shu', 'status')) {
                $table->string('status', 20)->default('paid')->after('catatan')->index();
            }
            if (! Schema::hasColumn('pembayaran_shu', 'mutasi_kas_id')) {
                $table->foreignId('mutasi_kas_id')->nullable()->after('created_by')
                    ->constrained('mutasi_kas')->restrictOnDelete();
            }
            if (! Schema::hasColumn('pembayaran_shu', 'jurnal_id')) {
                $table->foreignId('jurnal_id')->nullable()->after('mutasi_kas_id')
                    ->constrained('jurnal_umum')->restrictOnDelete();
            }
            if (! Schema::hasColumn('pembayaran_shu', 'reversal_mutasi_kas_id')) {
                $table->foreignId('reversal_mutasi_kas_id')->nullable()->after('jurnal_id')
                    ->constrained('mutasi_kas')->restrictOnDelete();
            }
            if (! Schema::hasColumn('pembayaran_shu', 'reversal_jurnal_id')) {
                $table->foreignId('reversal_jurnal_id')->nullable()->after('reversal_mutasi_kas_id')
                    ->constrained('jurnal_umum')->restrictOnDelete();
            }
            if (! Schema::hasColumn('pembayaran_shu', 'reversed_by')) {
                $table->foreignId('reversed_by')->nullable()->after('reversal_jurnal_id')
                    ->constrained('users')->restrictOnDelete();
            }
            if (! Schema::hasColumn('pembayaran_shu', 'reversed_at')) {
                $table->timestamp('reversed_at')->nullable()->after('reversed_by');
            }
            if (! Schema::hasColumn('pembayaran_shu', 'reversal_reason')) {
                $table->text('reversal_reason')->nullable()->after('reversed_at');
            }
        });
    }

    private function createShuAllocations(): void
    {
        if (Schema::hasTable('shu_alokasi')) {
            return;
        }

        Schema::create('shu_alokasi', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('shu_koperasi_id')->constrained('shu_koperasi')->restrictOnDelete();
            $table->string('jenis', 30);
            $table->decimal('nominal', 15, 2);
            $table->foreignId('jurnal_id')->constrained('jurnal_umum')->restrictOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('idempotency_key', 191)->unique();
            $table->timestamps();

            $table->unique(['shu_koperasi_id', 'jenis'], 'shu_alokasi_jenis_unique');
        });
    }

    private function createSocialBenefitPolicies(): void
    {
        if (! Schema::hasTable('jenis_manfaat_dana_sosial')) {
            Schema::create('jenis_manfaat_dana_sosial', function (Blueprint $table): void {
                $table->id();
                $table->string('kode', 40)->unique();
                $table->string('nama', 120);
                $table->boolean('is_active')->default(true)->index();
                $table->foreignId('created_by')->nullable()->constrained('users')->restrictOnDelete();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('kebijakan_manfaat_dana_sosial')) {
            Schema::create('kebijakan_manfaat_dana_sosial', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('jenis_manfaat_id')->constrained('jenis_manfaat_dana_sosial')->restrictOnDelete();
                $table->decimal('batas_maksimal', 15, 2);
                $table->date('berlaku_mulai')->index();
                $table->string('dasar_keputusan', 255);
                $table->text('dokumen_diperlukan')->nullable();
                $table->text('deskripsi')->nullable();
                $table->boolean('is_active')->default(true)->index();
                $table->foreignId('created_by')->nullable()->constrained('users')->restrictOnDelete();
                $table->string('idempotency_key', 191)->unique();
                $table->timestamps();

                $table->unique(['jenis_manfaat_id', 'berlaku_mulai'], 'kebijakan_manfaat_berlaku_unique');
            });
        }
    }

    private function hardenSocialFundSourcesAndClaims(): void
    {
        Schema::table('dana_sosial_sumber', function (Blueprint $table): void {
            if (! Schema::hasColumn('dana_sosial_sumber', 'periode_akuntansi_id')) {
                $table->foreignId('periode_akuntansi_id')->nullable()->after('shu_koperasi_id')
                    ->constrained('periode_akuntansi')->restrictOnDelete();
            }
            if (! Schema::hasColumn('dana_sosial_sumber', 'shu_config_id')) {
                $table->foreignId('shu_config_id')->nullable()->after('periode_akuntansi_id')
                    ->constrained('shu_config')->restrictOnDelete();
            }
            if (! Schema::hasColumn('dana_sosial_sumber', 'allocation_journal_id')) {
                $table->foreignId('allocation_journal_id')->nullable()->after('shu_config_id')
                    ->constrained('jurnal_umum')->restrictOnDelete();
            }
            if (! Schema::hasColumn('dana_sosial_sumber', 'is_legacy')) {
                $table->boolean('is_legacy')->default(false)->after('status')->index();
            }
        });

        DB::table('dana_sosial_sumber')
            ->where(function ($query): void {
                $query->whereNull('shu_koperasi_id')
                    ->orWhere(function ($inner): void {
                        $inner->whereNotNull('jenis')->where('jenis', '!=', 'alokasi_shu');
                    });
            })
            ->update(['is_legacy' => true]);

        Schema::table('klaim_dana_sosial', function (Blueprint $table): void {
            if (! Schema::hasColumn('klaim_dana_sosial', 'kebijakan_manfaat_id')) {
                $table->foreignId('kebijakan_manfaat_id')->nullable()->after('kategori')
                    ->constrained('kebijakan_manfaat_dana_sosial')->restrictOnDelete();
            }
            if (! Schema::hasColumn('klaim_dana_sosial', 'hubungan_penerima')) {
                $table->string('hubungan_penerima', 150)->nullable()->after('penerima_manfaat');
            }
            if (! Schema::hasColumn('klaim_dana_sosial', 'nominal_disetujui')) {
                $table->decimal('nominal_disetujui', 15, 2)->nullable()->after('nominal_diajukan');
            }
            if (! Schema::hasColumn('klaim_dana_sosial', 'kode_manfaat_snapshot')) {
                $table->string('kode_manfaat_snapshot', 40)->nullable()->after('nominal_disetujui');
            }
            if (! Schema::hasColumn('klaim_dana_sosial', 'nama_manfaat_snapshot')) {
                $table->string('nama_manfaat_snapshot', 120)->nullable()->after('kode_manfaat_snapshot');
            }
            if (! Schema::hasColumn('klaim_dana_sosial', 'dasar_keputusan_snapshot')) {
                $table->string('dasar_keputusan_snapshot', 255)->nullable()->after('nama_manfaat_snapshot');
            }
            if (! Schema::hasColumn('klaim_dana_sosial', 'dokumen_diperlukan_snapshot')) {
                $table->text('dokumen_diperlukan_snapshot')->nullable()->after('dasar_keputusan_snapshot');
            }
            if (! Schema::hasColumn('klaim_dana_sosial', 'catatan_persetujuan')) {
                $table->text('catatan_persetujuan')->nullable()->after('approval_reason');
            }
            if (! Schema::hasColumn('klaim_dana_sosial', 'catatan_pencairan')) {
                $table->text('catatan_pencairan')->nullable()->after('nomor_referensi');
            }
            if (! Schema::hasColumn('klaim_dana_sosial', 'payout_idempotency_key')) {
                $table->string('payout_idempotency_key', 191)->nullable()->after('idempotency_key')->unique();
            }
            if (! Schema::hasColumn('klaim_dana_sosial', 'reversal_journal_id')) {
                $table->foreignId('reversal_journal_id')->nullable()->after('reversal_reason')
                    ->constrained('jurnal_umum')->restrictOnDelete();
            }
        });
    }

    public function down(): void
    {
        // Finalisasi ini sengaja irreversible: tabel/kolom menyimpan histori keuangan dan audit.
    }
};
