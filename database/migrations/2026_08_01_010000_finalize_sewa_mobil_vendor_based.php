<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('sewa_mobil')) {
            Schema::table('sewa_mobil', function (Blueprint $table): void {
                if (Schema::hasColumn('sewa_mobil', 'aset_koperasi_id')) {
                    $table->unsignedBigInteger('aset_koperasi_id')->nullable()->change();
                }

                if (! Schema::hasColumn('sewa_mobil', 'vendor_nama')) {
                    $table->string('vendor_nama', 150)->nullable()->after('lokasi_kegiatan');
                }
                if (! Schema::hasColumn('sewa_mobil', 'vendor_kontak')) {
                    $table->string('vendor_kontak', 80)->nullable()->after('vendor_nama');
                }
                if (! Schema::hasColumn('sewa_mobil', 'vendor_alamat')) {
                    $table->text('vendor_alamat')->nullable()->after('vendor_kontak');
                }
                if (! Schema::hasColumn('sewa_mobil', 'jenis_kendaraan')) {
                    $table->string('jenis_kendaraan', 80)->nullable()->after('vendor_alamat');
                }
                if (! Schema::hasColumn('sewa_mobil', 'merek_kendaraan')) {
                    $table->string('merek_kendaraan', 100)->nullable()->after('jenis_kendaraan');
                }
                if (! Schema::hasColumn('sewa_mobil', 'model_kendaraan')) {
                    $table->string('model_kendaraan', 120)->nullable()->after('merek_kendaraan');
                }
                if (! Schema::hasColumn('sewa_mobil', 'plat_nomor_snapshot')) {
                    $table->string('plat_nomor_snapshot', 30)->nullable()->after('model_kendaraan');
                }
                if (! Schema::hasColumn('sewa_mobil', 'plat_nomor_normalized')) {
                    $table->string('plat_nomor_normalized', 30)->nullable()->after('plat_nomor_snapshot');
                }
                if (! Schema::hasColumn('sewa_mobil', 'tahun_kendaraan')) {
                    $table->unsignedSmallInteger('tahun_kendaraan')->nullable()->after('plat_nomor_normalized');
                }
                if (! Schema::hasColumn('sewa_mobil', 'warna_kendaraan')) {
                    $table->string('warna_kendaraan', 80)->nullable()->after('tahun_kendaraan');
                }
                if (! Schema::hasColumn('sewa_mobil', 'keterangan_kendaraan')) {
                    $table->text('keterangan_kendaraan')->nullable()->after('warna_kendaraan');
                }
                if (! Schema::hasColumn('sewa_mobil', 'total_harga_vendor')) {
                    $table->unsignedBigInteger('total_harga_vendor')->default(0)->after('total_sewa');
                }
                if (! Schema::hasColumn('sewa_mobil', 'total_markup')) {
                    $table->unsignedBigInteger('total_markup')->default(0)->after('total_harga_vendor');
                }
                if (! Schema::hasColumn('sewa_mobil', 'total_tagihan_perusahaan')) {
                    $table->unsignedBigInteger('total_tagihan_perusahaan')->default(0)->after('total_markup');
                }
                if (! Schema::hasColumn('sewa_mobil', 'started_by')) {
                    $table->foreignId('started_by')->nullable()->after('started_at')->constrained('users')->restrictOnDelete();
                }
                if (! Schema::hasColumn('sewa_mobil', 'completed_by')) {
                    $table->foreignId('completed_by')->nullable()->after('completed_at')->constrained('users')->restrictOnDelete();
                }
                if (! Schema::hasColumn('sewa_mobil', 'cancelled_by')) {
                    $table->foreignId('cancelled_by')->nullable()->after('cancelled_at')->constrained('users')->restrictOnDelete();
                }
                if (! Schema::hasColumn('sewa_mobil', 'refunded_at')) {
                    $table->timestamp('refunded_at')->nullable()->after('cancelled_by');
                }
                if (! Schema::hasColumn('sewa_mobil', 'refunded_by')) {
                    $table->foreignId('refunded_by')->nullable()->after('refunded_at')->constrained('users')->restrictOnDelete();
                }
                if (! Schema::hasColumn('sewa_mobil', 'refund_reason')) {
                    $table->text('refund_reason')->nullable()->after('refunded_by');
                }
                if (! Schema::hasColumn('sewa_mobil', 'reversal_transaksi_id') && Schema::hasTable('reversal_transaksi')) {
                    $table->foreignId('reversal_transaksi_id')->nullable()->after('refund_reason')->constrained('reversal_transaksi')->restrictOnDelete();
                }

                $table->index(['plat_nomor_normalized', 'tanggal_mulai', 'tanggal_selesai'], 'sewa_mobil_plat_periode_index');
                $table->index(['vendor_nama', 'status'], 'sewa_mobil_vendor_status_index');
            });
        }

        if (Schema::hasTable('pembayaran_sewa_mobil')) {
            Schema::table('pembayaran_sewa_mobil', function (Blueprint $table): void {
                if (! Schema::hasColumn('pembayaran_sewa_mobil', 'dompet_penerimaan_id')) {
                    $table->foreignId('dompet_penerimaan_id')->nullable()->after('dompet_id')->constrained('dompet_koperasi')->restrictOnDelete();
                }
                if (! Schema::hasColumn('pembayaran_sewa_mobil', 'metode_penerimaan')) {
                    $table->string('metode_penerimaan', 30)->nullable()->after('metode_pembayaran');
                }
                if (! Schema::hasColumn('pembayaran_sewa_mobil', 'jumlah_diterima')) {
                    $table->unsignedBigInteger('jumlah_diterima')->default(0)->after('jumlah_bayar');
                }
                if (! Schema::hasColumn('pembayaran_sewa_mobil', 'received_at')) {
                    $table->timestamp('received_at')->nullable()->after('paid_at');
                }
                if (! Schema::hasColumn('pembayaran_sewa_mobil', 'dompet_vendor_id')) {
                    $table->foreignId('dompet_vendor_id')->nullable()->after('received_at')->constrained('dompet_koperasi')->restrictOnDelete();
                }
                if (! Schema::hasColumn('pembayaran_sewa_mobil', 'metode_pembayaran_vendor')) {
                    $table->string('metode_pembayaran_vendor', 30)->nullable()->after('dompet_vendor_id');
                }
                if (! Schema::hasColumn('pembayaran_sewa_mobil', 'jumlah_bayar_vendor')) {
                    $table->unsignedBigInteger('jumlah_bayar_vendor')->default(0)->after('metode_pembayaran_vendor');
                }
                if (! Schema::hasColumn('pembayaran_sewa_mobil', 'vendor_paid_at')) {
                    $table->timestamp('vendor_paid_at')->nullable()->after('jumlah_bayar_vendor');
                }
                if (! Schema::hasColumn('pembayaran_sewa_mobil', 'refunded_by')) {
                    $table->foreignId('refunded_by')->nullable()->after('refunded_at')->constrained('users')->restrictOnDelete();
                }
                if (! Schema::hasColumn('pembayaran_sewa_mobil', 'refund_reason')) {
                    $table->text('refund_reason')->nullable()->after('refunded_by');
                }
                if (! Schema::hasColumn('pembayaran_sewa_mobil', 'reversal_transaksi_id') && Schema::hasTable('reversal_transaksi')) {
                    $table->foreignId('reversal_transaksi_id')->nullable()->after('refund_reason')->constrained('reversal_transaksi')->restrictOnDelete();
                }

                $table->index(['dompet_penerimaan_id', 'status'], 'pembayaran_sewa_mobil_penerimaan_status_index');
                $table->index(['dompet_vendor_id', 'status'], 'pembayaran_sewa_mobil_vendor_status_index');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('pembayaran_sewa_mobil')) {
            Schema::table('pembayaran_sewa_mobil', function (Blueprint $table): void {
                foreach ([
                    'pembayaran_sewa_mobil_penerimaan_status_index',
                    'pembayaran_sewa_mobil_vendor_status_index',
                ] as $index) {
                    try {
                        $table->dropIndex($index);
                    } catch (Throwable) {
                    }
                }

                foreach ([
                    'reversal_transaksi_id',
                    'refunded_by',
                    'dompet_vendor_id',
                    'dompet_penerimaan_id',
                ] as $column) {
                    if (Schema::hasColumn('pembayaran_sewa_mobil', $column)) {
                        try {
                            $table->dropForeign([$column]);
                        } catch (Throwable) {
                        }
                    }
                }

                foreach ([
                    'reversal_transaksi_id',
                    'refund_reason',
                    'refunded_by',
                    'vendor_paid_at',
                    'jumlah_bayar_vendor',
                    'metode_pembayaran_vendor',
                    'dompet_vendor_id',
                    'received_at',
                    'jumlah_diterima',
                    'metode_penerimaan',
                    'dompet_penerimaan_id',
                ] as $column) {
                    if (Schema::hasColumn('pembayaran_sewa_mobil', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        if (Schema::hasTable('sewa_mobil')) {
            Schema::table('sewa_mobil', function (Blueprint $table): void {
                foreach ([
                    'sewa_mobil_plat_periode_index',
                    'sewa_mobil_vendor_status_index',
                ] as $index) {
                    try {
                        $table->dropIndex($index);
                    } catch (Throwable) {
                    }
                }

                foreach ([
                    'reversal_transaksi_id',
                    'refunded_by',
                    'cancelled_by',
                    'completed_by',
                    'started_by',
                ] as $column) {
                    if (Schema::hasColumn('sewa_mobil', $column)) {
                        try {
                            $table->dropForeign([$column]);
                        } catch (Throwable) {
                        }
                    }
                }

                foreach ([
                    'reversal_transaksi_id',
                    'refund_reason',
                    'refunded_by',
                    'refunded_at',
                    'cancelled_by',
                    'completed_by',
                    'started_by',
                    'total_tagihan_perusahaan',
                    'total_markup',
                    'total_harga_vendor',
                    'keterangan_kendaraan',
                    'warna_kendaraan',
                    'tahun_kendaraan',
                    'plat_nomor_normalized',
                    'plat_nomor_snapshot',
                    'model_kendaraan',
                    'merek_kendaraan',
                    'jenis_kendaraan',
                    'vendor_alamat',
                    'vendor_kontak',
                    'vendor_nama',
                ] as $column) {
                    if (Schema::hasColumn('sewa_mobil', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};
