<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->guardTableCollision('sewa_printer', 'sewa_hardware');
        $this->guardTableCollision('sewa_printer_detail', 'sewa_hardware_detail');
        $this->guardTableCollision('pembayaran_sewa_printer', 'pembayaran_sewa_hardware');

        if (Schema::hasTable('sewa_printer') && ! Schema::hasTable('sewa_hardware')) {
            $this->guardCodeCollision('sewa_printer', 'SWP-', 'SWH-');
            Schema::rename('sewa_printer', 'sewa_hardware');
        }

        if (Schema::hasTable('sewa_printer_detail') && ! Schema::hasTable('sewa_hardware_detail')) {
            Schema::rename('sewa_printer_detail', 'sewa_hardware_detail');
        }

        if (Schema::hasTable('pembayaran_sewa_printer') && ! Schema::hasTable('pembayaran_sewa_hardware')) {
            Schema::rename('pembayaran_sewa_printer', 'pembayaran_sewa_hardware');
        }

        $this->renameColumnIfExists('sewa_hardware', 'karyawan_pic_id', 'karyawan_id');
        $this->renameColumnIfExists('sewa_hardware_detail', 'sewa_printer_id', 'sewa_hardware_id');
        $this->renameColumnIfExists('pembayaran_sewa_hardware', 'sewa_printer_id', 'sewa_hardware_id');

        if (Schema::hasTable('sewa_hardware_detail')) {
            if (! Schema::hasColumn('sewa_hardware_detail', 'jenis_hardware')) {
                Schema::table('sewa_hardware_detail', function (Blueprint $table): void {
                    $table->string('jenis_hardware', 30)->nullable()->after('sewa_hardware_id')->index('sewa_hardware_detail_jenis_index');
                });
            }

            if (Schema::hasColumn('sewa_hardware_detail', 'jenis_model_printer')
                && ! Schema::hasColumn('sewa_hardware_detail', 'nama_model_hardware')) {
                Schema::table('sewa_hardware_detail', function (Blueprint $table): void {
                    $table->renameColumn('jenis_model_printer', 'nama_model_hardware');
                });
            }

            if (Schema::hasColumn('sewa_hardware_detail', 'jenis_hardware')) {
                DB::table('sewa_hardware_detail')
                    ->whereNull('jenis_hardware')
                    ->update(['jenis_hardware' => 'printer']);
            }
        }

        $this->addHardwareAuditColumns();
        $this->addHardwarePaymentRefundColumns();
        $this->convertLegacyDataForward();
        $this->renameAccountMapRowsForward();
    }

    public function down(): void
    {
        $this->guardTableCollision('sewa_hardware', 'sewa_printer');
        $this->guardTableCollision('sewa_hardware_detail', 'sewa_printer_detail');
        $this->guardTableCollision('pembayaran_sewa_hardware', 'pembayaran_sewa_printer');

        if (Schema::hasTable('sewa_hardware_detail') && Schema::hasColumn('sewa_hardware_detail', 'jenis_hardware')) {
            $nonPrinter = DB::table('sewa_hardware_detail')
                ->whereNotNull('jenis_hardware')
                ->where('jenis_hardware', '<>', 'printer')
                ->count();

            if ($nonPrinter > 0) {
                throw new RuntimeException('Rollback Sewa Hardware dibatalkan: terdapat detail non-printer yang tidak dapat direpresentasikan pada schema legacy Sewa Printer tanpa kehilangan informasi.');
            }
        }

        $this->guardCodeCollision('sewa_hardware', 'SWH-', 'SWP-');
        $this->convertLegacyDataBackward();
        $this->renameAccountMapRowsBackward();

        $this->renameColumnIfExists('sewa_hardware_detail', 'nama_model_hardware', 'jenis_model_printer');
        $this->renameColumnIfExists('sewa_hardware_detail', 'sewa_hardware_id', 'sewa_printer_id');
        $this->renameColumnIfExists('pembayaran_sewa_hardware', 'sewa_hardware_id', 'sewa_printer_id');
        $this->renameColumnIfExists('sewa_hardware', 'karyawan_id', 'karyawan_pic_id');

        if (Schema::hasTable('sewa_hardware') && ! Schema::hasTable('sewa_printer')) {
            Schema::rename('sewa_hardware', 'sewa_printer');
        }

        if (Schema::hasTable('sewa_hardware_detail') && ! Schema::hasTable('sewa_printer_detail')) {
            Schema::rename('sewa_hardware_detail', 'sewa_printer_detail');
        }

        if (Schema::hasTable('pembayaran_sewa_hardware') && ! Schema::hasTable('pembayaran_sewa_printer')) {
            Schema::rename('pembayaran_sewa_hardware', 'pembayaran_sewa_printer');
        }
    }

    private function guardTableCollision(string $old, string $new): void
    {
        if (! Schema::hasTable($old) || ! Schema::hasTable($new)) {
            return;
        }

        $oldCount = DB::table($old)->count();
        $newCount = DB::table($new)->count();

        if ($oldCount > 0 && $newCount > 0) {
            throw new RuntimeException(sprintf(
                'Migration Sewa Hardware dibatalkan: tabel %s dan %s sama-sama memiliki data. Rekonsiliasi manual diperlukan; migration tidak akan menggabungkan data diam-diam.',
                $old,
                $new
            ));
        }
    }

    private function guardCodeCollision(string $table, string $fromPrefix, string $toPrefix): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'kode_sewa')) {
            return;
        }

        $codes = DB::table($table)->pluck('kode_sewa')->map(fn ($code) => (string) $code);
        $existing = $codes->flip();

        foreach ($codes as $code) {
            if (! str_starts_with($code, $fromPrefix)) {
                continue;
            }

            $target = $toPrefix.substr($code, strlen($fromPrefix));
            if ($existing->has($target)) {
                throw new RuntimeException(sprintf('Migration Sewa Hardware dibatalkan: kode target %s sudah ada.', $target));
            }
        }
    }

    private function renameColumnIfExists(string $table, string $from, string $to): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $from) || Schema::hasColumn($table, $to)) {
            return;
        }

        Schema::table($table, function (Blueprint $table) use ($from, $to): void {
            $table->renameColumn($from, $to);
        });
    }

    private function addHardwareAuditColumns(): void
    {
        if (! Schema::hasTable('sewa_hardware')) {
            return;
        }

        Schema::table('sewa_hardware', function (Blueprint $table): void {
            if (! Schema::hasColumn('sewa_hardware', 'started_by')) {
                $table->foreignId('started_by')->nullable()->after('started_at')->constrained('users')->restrictOnDelete();
            }
            if (! Schema::hasColumn('sewa_hardware', 'completed_by')) {
                $table->foreignId('completed_by')->nullable()->after('completed_at')->constrained('users')->restrictOnDelete();
            }
            if (! Schema::hasColumn('sewa_hardware', 'cancelled_by')) {
                $table->foreignId('cancelled_by')->nullable()->after('cancelled_at')->constrained('users')->restrictOnDelete();
            }
            if (! Schema::hasColumn('sewa_hardware', 'refunded_at')) {
                $table->timestamp('refunded_at')->nullable()->after('cancelled_by');
            }
            if (! Schema::hasColumn('sewa_hardware', 'refunded_by')) {
                $table->foreignId('refunded_by')->nullable()->after('refunded_at')->constrained('users')->restrictOnDelete();
            }
            if (! Schema::hasColumn('sewa_hardware', 'refund_reason')) {
                $table->text('refund_reason')->nullable()->after('refunded_by');
            }
            if (! Schema::hasColumn('sewa_hardware', 'reversal_transaksi_id') && Schema::hasTable('reversal_transaksi')) {
                $table->foreignId('reversal_transaksi_id')->nullable()->after('refund_reason')->constrained('reversal_transaksi')->restrictOnDelete();
            }
        });
    }

    private function addHardwarePaymentRefundColumns(): void
    {
        if (! Schema::hasTable('pembayaran_sewa_hardware')) {
            return;
        }

        Schema::table('pembayaran_sewa_hardware', function (Blueprint $table): void {
            if (! Schema::hasColumn('pembayaran_sewa_hardware', 'refunded_at')) {
                $table->timestamp('refunded_at')->nullable()->after('paid_at');
            }
            if (! Schema::hasColumn('pembayaran_sewa_hardware', 'refunded_by')) {
                $table->foreignId('refunded_by')->nullable()->after('refunded_at')->constrained('users')->restrictOnDelete();
            }
            if (! Schema::hasColumn('pembayaran_sewa_hardware', 'refund_reason')) {
                $table->text('refund_reason')->nullable()->after('refunded_by');
            }
            if (! Schema::hasColumn('pembayaran_sewa_hardware', 'reversal_transaksi_id') && Schema::hasTable('reversal_transaksi')) {
                $table->foreignId('reversal_transaksi_id')->nullable()->after('refund_reason')->constrained('reversal_transaksi')->restrictOnDelete();
            }
        });
    }

    private function convertLegacyDataForward(): void
    {
        $this->replacePrefix('sewa_hardware', 'kode_sewa', 'SWP-', 'SWH-');
        $this->replaceText('sewa_hardware', 'idempotency_key', 'sewa-printer', 'sewa-hardware');
        $this->replaceText('pembayaran_sewa_hardware', 'idempotency_key', 'sewa-printer', 'sewa-hardware');
        $this->replaceText('mutasi_kas', 'idempotency_key', 'sewa-printer', 'sewa-hardware');
        $this->replaceText('jurnal_umum', 'idempotency_key', 'sewa-printer', 'sewa-hardware');
        $this->replaceText('reversal_transaksi', 'idempotency_key', 'sewa-printer', 'sewa-hardware');
        $this->replaceText('reversal_transaksi', 'jenis_reversal', 'sewa_printer', 'sewa_hardware');
        $this->replaceText('reversal_transaksi', 'alasan', 'Sewa Printer', 'Sewa Hardware');

        $this->replaceExact('mutasi_kas', 'referensi_tipe', 'App\\Models\\SewaPrinter', 'App\\Models\\SewaHardware');
        $this->replaceExact('mutasi_kas', 'referensi_tipe', 'App\\Models\\PembayaranSewaPrinter', 'App\\Models\\PembayaranSewaHardware');
        $this->replaceExact('jurnal_umum', 'referensi_tipe', 'App\\Models\\SewaPrinter', 'App\\Models\\SewaHardware');
        $this->replaceExact('jurnal_umum', 'referensi_tipe', 'App\\Models\\PembayaranSewaPrinter', 'App\\Models\\PembayaranSewaHardware');
        $this->replaceExact('reversal_transaksi', 'source_type', 'App\\Models\\SewaPrinter', 'App\\Models\\SewaHardware');
        $this->replaceExact('reversal_transaksi', 'source_type', 'App\\Models\\PembayaranSewaPrinter', 'App\\Models\\PembayaranSewaHardware');

        if (Schema::hasTable('nomor_urut_transaksi')) {
            $collision = DB::table('nomor_urut_transaksi as p')
                ->join('nomor_urut_transaksi as h', function ($join): void {
                    $join->on('h.periode', '=', 'p.periode')
                        ->where('h.jenis', '=', 'sewa_hardware');
                })
                ->where('p.jenis', 'sewa_printer')
                ->exists();

            if ($collision) {
                throw new RuntimeException('Migration Sewa Hardware dibatalkan: counter sewa_printer dan sewa_hardware memiliki periode yang sama.');
            }

            DB::table('nomor_urut_transaksi')->where('jenis', 'sewa_printer')->update(['jenis' => 'sewa_hardware']);
        }
    }

    private function convertLegacyDataBackward(): void
    {
        $this->replacePrefix('sewa_hardware', 'kode_sewa', 'SWH-', 'SWP-');
        $this->replaceText('sewa_hardware', 'idempotency_key', 'sewa-hardware', 'sewa-printer');
        $this->replaceText('pembayaran_sewa_hardware', 'idempotency_key', 'sewa-hardware', 'sewa-printer');
        $this->replaceText('mutasi_kas', 'idempotency_key', 'sewa-hardware', 'sewa-printer');
        $this->replaceText('jurnal_umum', 'idempotency_key', 'sewa-hardware', 'sewa-printer');
        $this->replaceText('reversal_transaksi', 'idempotency_key', 'sewa-hardware', 'sewa-printer');
        $this->replaceText('reversal_transaksi', 'jenis_reversal', 'sewa_hardware', 'sewa_printer');
        $this->replaceText('reversal_transaksi', 'alasan', 'Sewa Hardware', 'Sewa Printer');

        $this->replaceExact('mutasi_kas', 'referensi_tipe', 'App\\Models\\SewaHardware', 'App\\Models\\SewaPrinter');
        $this->replaceExact('mutasi_kas', 'referensi_tipe', 'App\\Models\\PembayaranSewaHardware', 'App\\Models\\PembayaranSewaPrinter');
        $this->replaceExact('jurnal_umum', 'referensi_tipe', 'App\\Models\\SewaHardware', 'App\\Models\\SewaPrinter');
        $this->replaceExact('jurnal_umum', 'referensi_tipe', 'App\\Models\\PembayaranSewaHardware', 'App\\Models\\PembayaranSewaPrinter');
        $this->replaceExact('reversal_transaksi', 'source_type', 'App\\Models\\SewaHardware', 'App\\Models\\SewaPrinter');
        $this->replaceExact('reversal_transaksi', 'source_type', 'App\\Models\\PembayaranSewaHardware', 'App\\Models\\PembayaranSewaPrinter');

        if (Schema::hasTable('nomor_urut_transaksi')) {
            DB::table('nomor_urut_transaksi')->where('jenis', 'sewa_hardware')->update(['jenis' => 'sewa_printer']);
        }
    }

    private function renameAccountMapRowsForward(): void
    {
        if (! Schema::hasTable('akun')) {
            return;
        }

        if (Schema::hasColumn('akun', 'nama_akun')) {
            DB::table('akun')->where('nama_akun', 'like', '%Sewa Printer%')
                ->update(['nama_akun' => DB::raw("REPLACE(nama_akun, 'Sewa Printer', 'Sewa Hardware')")]);
        }

        if (Schema::hasColumn('akun', 'keterangan')) {
            DB::table('akun')->where('keterangan', 'like', '%sewa printer%')
                ->update(['keterangan' => DB::raw("REPLACE(keterangan, 'sewa printer', 'sewa hardware')")]);
        }
    }

    private function renameAccountMapRowsBackward(): void
    {
        if (! Schema::hasTable('akun')) {
            return;
        }

        if (Schema::hasColumn('akun', 'nama_akun')) {
            DB::table('akun')->where('nama_akun', 'like', '%Sewa Hardware%')
                ->update(['nama_akun' => DB::raw("REPLACE(nama_akun, 'Sewa Hardware', 'Sewa Printer')")]);
        }

        if (Schema::hasColumn('akun', 'keterangan')) {
            DB::table('akun')->where('keterangan', 'like', '%sewa hardware%')
                ->update(['keterangan' => DB::raw("REPLACE(keterangan, 'sewa hardware', 'sewa printer')")]);
        }
    }

    private function replacePrefix(string $table, string $column, string $fromPrefix, string $toPrefix): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
            return;
        }

        DB::table($table)
            ->where($column, 'like', $fromPrefix.'%')
            ->orderBy('id')
            ->lazyById()
            ->each(function ($row) use ($table, $column, $fromPrefix, $toPrefix): void {
                DB::table($table)
                    ->where('id', $row->id)
                    ->update([$column => $toPrefix.substr((string) $row->{$column}, strlen($fromPrefix))]);
            });
    }

    private function replaceText(string $table, string $column, string $from, string $to): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
            return;
        }

        DB::table($table)
            ->where($column, 'like', '%'.$from.'%')
            ->orderBy('id')
            ->lazyById()
            ->each(function ($row) use ($table, $column, $from, $to): void {
                DB::table($table)
                    ->where('id', $row->id)
                    ->update([$column => str_replace($from, $to, (string) $row->{$column})]);
            });
    }

    private function replaceExact(string $table, string $column, string $from, string $to): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
            return;
        }

        DB::table($table)->where($column, $from)->update([$column => $to]);
    }
};
