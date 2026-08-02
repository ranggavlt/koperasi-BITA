<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->finalizePayrollPolicy();
        $this->finalizeRentalsAndB2b();
        $this->createDanaSosial();
        $this->hardenShuSchema();
    }

    private function finalizePayrollPolicy(): void
    {
        if (! Schema::hasTable('kebijakan_limit_potong_gaji')) {
            Schema::create('kebijakan_limit_potong_gaji', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('perusahaan_id')->nullable()->constrained('perusahaan')->restrictOnDelete();
                $table->decimal('limit_nominal', 15, 2)->default(1500000);
                $table->date('berlaku_mulai');
                $table->date('berlaku_sampai')->nullable();
                $table->boolean('aktif')->default(true);
                $table->string('kode_perusahaan_snapshot', 10)->nullable();
                $table->string('nama_perusahaan_snapshot', 150)->nullable();
                $table->text('alasan');
                $table->foreignId('created_by')->nullable()->constrained('users')->restrictOnDelete();
                $table->string('idempotency_key', 191)->unique();
                $table->timestamps();

                $table->index(['perusahaan_id', 'berlaku_mulai', 'aktif'], 'kebijakan_limit_scope_period_index');
            });
        }

        if (! Schema::hasTable('pengaturan_payroll_anggota')) {
            Schema::create('pengaturan_payroll_anggota', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('anggota_id')->constrained('anggota')->restrictOnDelete();
                $table->date('berlaku_mulai');
                $table->decimal('limit_override_nominal', 15, 2)->nullable();
                $table->boolean('kredit_waserba_aktif')->default(true);
                $table->text('alasan');
                $table->foreignId('created_by')->nullable()->constrained('users')->restrictOnDelete();
                $table->string('idempotency_key', 191)->unique();
                $table->timestamps();

                $table->unique(['anggota_id', 'berlaku_mulai'], 'pengaturan_payroll_anggota_period_unique');
            });
        }

        Schema::table('limit_potong_gaji_anggota', function (Blueprint $table): void {
            if (! Schema::hasColumn('limit_potong_gaji_anggota', 'sumber_limit_snapshot')) {
                $table->string('sumber_limit_snapshot', 30)->nullable()->after('limit_nominal');
            }
            if (! Schema::hasColumn('limit_potong_gaji_anggota', 'perusahaan_id_snapshot')) {
                $table->unsignedBigInteger('perusahaan_id_snapshot')->nullable()->after('sumber_limit_snapshot');
            }
            if (! Schema::hasColumn('limit_potong_gaji_anggota', 'kode_perusahaan_snapshot')) {
                $table->string('kode_perusahaan_snapshot', 10)->nullable()->after('perusahaan_id_snapshot');
            }
            if (! Schema::hasColumn('limit_potong_gaji_anggota', 'nama_perusahaan_snapshot')) {
                $table->string('nama_perusahaan_snapshot', 150)->nullable()->after('kode_perusahaan_snapshot');
            }
            if (! Schema::hasColumn('limit_potong_gaji_anggota', 'kredit_waserba_aktif_snapshot')) {
                $table->boolean('kredit_waserba_aktif_snapshot')->default(true)->after('nama_perusahaan_snapshot');
            }
        });
    }

    private function finalizeRentalsAndB2b(): void
    {
        Schema::table('dompet_koperasi', function (Blueprint $table): void {
            if (! Schema::hasColumn('dompet_koperasi', 'is_kas_operasional')) {
                $table->boolean('is_kas_operasional')->default(false);
            }
        });

        Schema::table('sewa_mobil', function (Blueprint $table): void {
            if (! Schema::hasColumn('sewa_mobil', 'perusahaan_id')) {
                $table->foreignId('perusahaan_id')->nullable()->constrained('perusahaan')->restrictOnDelete();
            }
            foreach ([
                'kode_perusahaan_snapshot' => 10,
                'vendor_nama_snapshot' => 150,
                'vendor_kontak_snapshot' => 100,
                'kendaraan_jenis_snapshot' => 80,
                'kendaraan_merk_tipe_snapshot' => 150,
                'nomor_polisi_snapshot' => 30,
                'model_sumber' => 20,
            ] as $column => $length) {
                if (! Schema::hasColumn('sewa_mobil', $column)) {
                    $table->string($column, $length)->nullable();
                }
            }
            if (! Schema::hasColumn('sewa_mobil', 'vendor_alamat_snapshot')) {
                $table->text('vendor_alamat_snapshot')->nullable();
            }
            foreach (['harga_vendor_total', 'markup_total', 'total_tagihan_perusahaan'] as $column) {
                if (! Schema::hasColumn('sewa_mobil', $column)) {
                    $table->decimal($column, 15, 2)->default(0);
                }
            }
        });

        Schema::table('sewa_printer', function (Blueprint $table): void {
            if (! Schema::hasColumn('sewa_printer', 'perusahaan_id')) {
                $table->foreignId('perusahaan_id')->nullable()->constrained('perusahaan')->restrictOnDelete();
            }
            if (! Schema::hasColumn('sewa_printer', 'kode_perusahaan_snapshot')) {
                $table->string('kode_perusahaan_snapshot', 10)->nullable();
            }
            if (! Schema::hasColumn('sewa_printer', 'model_sumber')) {
                $table->string('model_sumber', 20)->default('vendor');
            }
        });

        if (! Schema::hasTable('pembayaran_vendor_sewa')) {
            Schema::create('pembayaran_vendor_sewa', function (Blueprint $table): void {
                $table->id();
                $table->string('kode_pembayaran', 30)->unique();
                $table->morphs('sewa');
                $table->foreignId('dompet_id')->constrained('dompet_koperasi')->restrictOnDelete();
                $table->string('metode_pembayaran', 30);
                $table->decimal('jumlah_bayar', 15, 2);
                $table->string('vendor_nama_snapshot', 150);
                $table->string('vendor_kontak_snapshot', 100)->nullable();
                $table->text('vendor_alamat_snapshot')->nullable();
                $table->date('tanggal_bayar');
                $table->string('status', 20)->default('paid');
                $table->foreignId('created_by')->nullable()->constrained('users')->restrictOnDelete();
                $table->string('idempotency_key', 191)->unique();
                $table->timestamps();

                $table->unique(['sewa_type', 'sewa_id'], 'pembayaran_vendor_sewa_source_unique');
            });
        }

        Schema::table('invoice_penagihan', function (Blueprint $table): void {
            if (! Schema::hasColumn('invoice_penagihan', 'jumlah_dibayar')) {
                $table->decimal('jumlah_dibayar', 15, 2)->default(0)->after('total_tagihan');
            }
            if (! Schema::hasColumn('invoice_penagihan', 'sisa_tagihan')) {
                $table->decimal('sisa_tagihan', 15, 2)->default(0)->after('jumlah_dibayar');
            }
            if (! Schema::hasColumn('invoice_penagihan', 'kode_perusahaan_snapshot')) {
                $table->string('kode_perusahaan_snapshot', 10)->nullable();
            }
            if (! Schema::hasColumn('invoice_penagihan', 'nama_perusahaan_snapshot')) {
                $table->string('nama_perusahaan_snapshot', 150)->nullable();
            }
            if (! Schema::hasColumn('invoice_penagihan', 'finalized_at')) {
                $table->timestamp('finalized_at')->nullable();
            }
            if (! Schema::hasColumn('invoice_penagihan', 'created_by')) {
                $table->foreignId('created_by')->nullable()->constrained('users')->restrictOnDelete();
            }
            if (! Schema::hasColumn('invoice_penagihan', 'idempotency_key')) {
                $table->string('idempotency_key', 191)->nullable()->unique();
            }
        });

        Schema::table('invoice_penagihan_detail', function (Blueprint $table): void {
            if (! Schema::hasColumn('invoice_penagihan_detail', 'kode_sewa_snapshot')) {
                $table->string('kode_sewa_snapshot', 30)->nullable();
            }
            if (! Schema::hasColumn('invoice_penagihan_detail', 'vendor_nama_snapshot')) {
                $table->string('vendor_nama_snapshot', 150)->nullable();
            }
            if (! Schema::hasColumn('invoice_penagihan_detail', 'harga_vendor_snapshot')) {
                $table->decimal('harga_vendor_snapshot', 15, 2)->default(0);
            }
            if (! Schema::hasColumn('invoice_penagihan_detail', 'margin_snapshot')) {
                $table->decimal('margin_snapshot', 15, 2)->default(0);
            }
            $table->unique(['referensi_type', 'referensi_id'], 'invoice_detail_reference_unique');
        });

        if (! Schema::hasTable('pembayaran_invoice_perusahaan')) {
            Schema::create('pembayaran_invoice_perusahaan', function (Blueprint $table): void {
                $table->id();
                $table->string('kode_pembayaran', 30)->unique();
                $table->foreignId('invoice_penagihan_id')->constrained('invoice_penagihan')->restrictOnDelete();
                $table->foreignId('dompet_id')->constrained('dompet_koperasi')->restrictOnDelete();
                $table->string('metode_pembayaran', 30);
                $table->decimal('jumlah_bayar', 15, 2);
                $table->date('tanggal_bayar');
                $table->string('nomor_referensi', 100)->nullable();
                $table->string('status', 20)->default('paid');
                $table->foreignId('created_by')->nullable()->constrained('users')->restrictOnDelete();
                $table->string('idempotency_key', 191)->unique();
                $table->timestamps();
            });
        }
    }

    private function createDanaSosial(): void
    {
        if (! Schema::hasTable('dana_sosial_sumber')) {
            Schema::create('dana_sosial_sumber', function (Blueprint $table): void {
                $table->id();
                $table->string('kode_sumber', 30)->unique();
                $table->string('nama_sumber', 150);
                $table->string('jenis_sumber', 30);
                $table->foreignId('shu_koperasi_id')->nullable()->constrained('shu_koperasi')->restrictOnDelete();
                $table->decimal('nominal_awal', 15, 2);
                $table->decimal('saldo_tersedia', 15, 2);
                $table->string('status', 20)->default('draft');
                $table->text('keterangan')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->restrictOnDelete();
                $table->foreignId('approved_by')->nullable()->constrained('users')->restrictOnDelete();
                $table->timestamp('approved_at')->nullable();
                $table->string('idempotency_key', 191)->unique();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('klaim_dana_sosial')) {
            Schema::create('klaim_dana_sosial', function (Blueprint $table): void {
                $table->id();
                $table->string('kode_klaim', 30)->unique();
                $table->foreignId('anggota_id')->nullable()->constrained('anggota')->restrictOnDelete();
                $table->foreignId('karyawan_id')->nullable()->constrained('karyawan')->restrictOnDelete();
                $table->string('nama_penerima_snapshot', 150);
                $table->string('kategori', 30);
                $table->decimal('nominal', 15, 2);
                $table->date('tanggal_pengajuan');
                $table->text('keterangan');
                $table->string('status', 20)->default('draft');
                $table->foreignId('sumber_dana_sosial_id')->nullable()->constrained('dana_sosial_sumber')->restrictOnDelete();
                $table->foreignId('dompet_id')->nullable()->constrained('dompet_koperasi')->restrictOnDelete();
                $table->string('metode_pembayaran', 30)->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->restrictOnDelete();
                $table->foreignId('approved_by')->nullable()->constrained('users')->restrictOnDelete();
                $table->foreignId('paid_by')->nullable()->constrained('users')->restrictOnDelete();
                $table->timestamp('submitted_at')->nullable();
                $table->timestamp('approved_at')->nullable();
                $table->timestamp('rejected_at')->nullable();
                $table->timestamp('paid_at')->nullable();
                $table->text('alasan_penolakan')->nullable();
                $table->string('idempotency_key', 191)->unique();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('mutasi_dana_sosial')) {
            Schema::create('mutasi_dana_sosial', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('dana_sosial_sumber_id')->constrained('dana_sosial_sumber')->restrictOnDelete();
                $table->foreignId('klaim_dana_sosial_id')->nullable()->constrained('klaim_dana_sosial')->restrictOnDelete();
                $table->string('tipe', 20);
                $table->decimal('nominal', 15, 2);
                $table->decimal('saldo_setelah', 15, 2);
                $table->text('keterangan');
                $table->foreignId('created_by')->nullable()->constrained('users')->restrictOnDelete();
                $table->string('idempotency_key', 191)->unique();
                $table->timestamps();
            });
        }
    }

    private function hardenShuSchema(): void
    {
        Schema::table('shu_configs', function (Blueprint $table): void {
            if (! Schema::hasColumn('shu_configs', 'status_persetujuan')) {
                $table->string('status_persetujuan', 20)->default('draft');
                $table->date('berlaku_mulai')->nullable();
                $table->text('dasar_persetujuan')->nullable();
                $table->foreignId('approved_by')->nullable()->constrained('users')->restrictOnDelete();
                $table->timestamp('approved_at')->nullable();
            }
        });

        Schema::table('shu_koperasi', function (Blueprint $table): void {
            if (! Schema::hasColumn('shu_koperasi', 'status')) {
                $table->string('status', 20)->default('draft');
                $table->json('config_snapshot')->nullable();
                $table->json('source_snapshot')->nullable();
                $table->timestamp('closed_at')->nullable();
                $table->foreignId('closed_by')->nullable()->constrained('users')->restrictOnDelete();
                $table->string('idempotency_key', 191)->nullable()->unique();
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mutasi_dana_sosial');
        Schema::dropIfExists('klaim_dana_sosial');
        Schema::dropIfExists('dana_sosial_sumber');
        Schema::dropIfExists('pembayaran_invoice_perusahaan');
        Schema::dropIfExists('pembayaran_vendor_sewa');
        Schema::dropIfExists('pengaturan_payroll_anggota');
        Schema::dropIfExists('kebijakan_limit_potong_gaji');
    }
};
