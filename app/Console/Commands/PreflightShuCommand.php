<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PreflightShuCommand extends Command
{
    protected $signature = 'koperasi:preflight-shu';
    protected $description = 'Audit read-only integritas pembagian SHU tahunan, penerima, pembayaran, dan maker-checker.';

    public function handle(): int
    {
        $checks = [
            ['periode_ganda', 'Lebih dari satu SHU pada periode yang sama', $this->duplicatePeriods()],
            ['penerima_ganda', 'Penerima Anggota ganda dalam satu SHU', $this->duplicateRecipients()],
            ['penerima_jabatan_ganda', 'Pejabat ganda dalam satu SHU', $this->duplicateOfficerRecipients()],
            ['bobot_jabatan_invalid', 'Penerima jabatan tanpa bobot positif', $this->invalidOfficerWeights()],
            ['penerima_jabatan_invalid', 'Penerima jabatan tanpa relasi atau kelompok yang sesuai', $this->invalidOfficerRecipients()],
            ['maker_checker', 'Penyetuju sama dengan pembuat/penghitung/pengaju', $this->makerCheckerConflicts()],
            ['pembayaran_nominal', 'Nominal pembayaran tidak sama dengan hak penerima', $this->paymentAmountMismatches()],
            ['status_pembayaran', 'Status penerima tidak sesuai record pembayaran', $this->paymentStatusMismatches()],
            ['total_penerima', 'Total hak seluruh penerima tidak sama dengan pool personal', $this->recipientTotalMismatches()],
            ['total_kelompok', 'Total hak per kelompok tidak sama dengan pool kelompok', $this->groupTotalMismatches()],
        ];

        $this->info('Ringkasan preflight SHU tahunan (read-only)');
        $this->table(['Kode', 'Pemeriksaan', 'Count'], $checks);
        if (collect($checks)->contains(fn (array $check): bool => $check[2] > 0)) {
            $this->error('Preflight SHU menemukan konflik. Tidak ada data yang diubah.');
            return self::FAILURE;
        }

        $this->info('Preflight SHU bersih.');
        return self::SUCCESS;
    }

    private function ready(): bool
    {
        return Schema::hasTable('shu_koperasi') && Schema::hasTable('shu_penerima') && Schema::hasTable('pembayaran_shu');
    }

    private function duplicatePeriods(): int
    {
        if (! $this->ready()) return 0;
        return DB::table('shu_koperasi')->select('periode_akuntansi_id')->whereNotNull('periode_akuntansi_id')->groupBy('periode_akuntansi_id')->havingRaw('COUNT(*) > 1')->get()->count();
    }

    private function duplicateRecipients(): int
    {
        if (! $this->ready()) return 0;
        return DB::table('shu_penerima')->select('shu_koperasi_id', 'anggota_id')->where('jenis_penerima', 'anggota')->whereNotNull('anggota_id')->groupBy('shu_koperasi_id', 'anggota_id')->havingRaw('COUNT(*) > 1')->get()->count();
    }

    private function duplicateOfficerRecipients(): int
    {
        if (! $this->ready()) return 0;
        return DB::table('shu_penerima')->select('shu_koperasi_id', 'pengurus_koperasi_id')->whereIn('jenis_penerima', ['pengurus', 'pengawas', 'pembina'])->whereNotNull('pengurus_koperasi_id')->groupBy('shu_koperasi_id', 'pengurus_koperasi_id')->havingRaw('COUNT(*) > 1')->get()->count();
    }

    private function invalidOfficerWeights(): int
    {
        if (! $this->ready() || ! Schema::hasColumn('shu_penerima', 'bobot')) return 0;
        return DB::table('shu_penerima')->whereIn('jenis_penerima', ['pengurus', 'pengawas', 'pembina'])->where('bobot', '<=', 0)->count();
    }

    private function invalidOfficerRecipients(): int
    {
        if (! $this->ready() || ! Schema::hasColumn('pengurus_koperasi', 'kelompok')) return 0;
        return DB::table('shu_penerima as r')->leftJoin('pengurus_koperasi as p', 'p.id', '=', 'r.pengurus_koperasi_id')->whereIn('r.jenis_penerima', ['pengurus', 'pengawas', 'pembina'])->where(function ($query): void {
            $query->whereNull('p.id')->orWhereColumn('p.kelompok', '!=', 'r.jenis_penerima');
        })->count();
    }

    private function makerCheckerConflicts(): int
    {
        if (! $this->ready()) return 0;
        return DB::table('shu_koperasi')->whereNotNull('approved_by')->where(function ($query): void {
            $query->whereColumn('approved_by', 'created_by')->orWhereColumn('approved_by', 'calculated_by')->orWhereColumn('approved_by', 'submitted_by');
        })->count();
    }

    private function paymentAmountMismatches(): int
    {
        if (! $this->ready()) return 0;
        return DB::table('pembayaran_shu as p')->join('shu_penerima as r', 'r.id', '=', 'p.shu_penerima_id')->whereRaw('ABS(p.jumlah - r.nominal_hak) > 0.01')->count();
    }

    private function paymentStatusMismatches(): int
    {
        if (! $this->ready()) return 0;
        return DB::table('shu_penerima as r')->leftJoin('pembayaran_shu as p', 'p.shu_penerima_id', '=', 'r.id')->where(function ($query): void {
            $query->where(fn ($q) => $q->where('r.status_pembayaran', 'dibayar')->whereNull('p.id'))
                ->orWhere(fn ($q) => $q->where('r.status_pembayaran', '!=', 'dibayar')->whereNotNull('p.id'));
        })->count();
    }

    private function recipientTotalMismatches(): int
    {
        if (! $this->ready()) return 0;
        return DB::table('shu_koperasi as s')->select('s.id', 's.nominal_shu_anggota', 's.nominal_pengurus', 's.nominal_pengawas', 's.nominal_pembina')->selectRaw('COALESCE(SUM(r.nominal_hak), 0) as total_penerima')->leftJoin('shu_penerima as r', function ($join): void {
            $join->on('r.shu_koperasi_id', '=', 's.id')->whereIn('r.jenis_penerima', ['anggota', 'pengurus', 'pengawas', 'pembina']);
        })->whereIn('s.status', ['calculated', 'submitted', 'approved', 'ready_to_pay', 'completed'])->groupBy('s.id', 's.nominal_shu_anggota', 's.nominal_pengurus', 's.nominal_pengawas', 's.nominal_pembina')->havingRaw('ABS(COALESCE(SUM(r.nominal_hak), 0) - (s.nominal_shu_anggota + s.nominal_pengurus + s.nominal_pengawas + s.nominal_pembina)) > 0.01')->get()->count();
    }

    private function groupTotalMismatches(): int
    {
        if (! $this->ready()) return 0;
        $mismatches = 0;
        foreach (['anggota' => 'nominal_shu_anggota', 'pengurus' => 'nominal_pengurus', 'pengawas' => 'nominal_pengawas', 'pembina' => 'nominal_pembina'] as $group => $column) {
            $mismatches += DB::table('shu_koperasi as s')->select('s.id', "s.{$column}")->selectRaw('COALESCE(SUM(r.nominal_hak), 0) as total_kelompok')->leftJoin('shu_penerima as r', function ($join) use ($group): void {
                $join->on('r.shu_koperasi_id', '=', 's.id')->where('r.jenis_penerima', '=', $group);
            })->whereIn('s.status', ['calculated', 'submitted', 'approved', 'ready_to_pay', 'completed'])->groupBy('s.id', "s.{$column}")->havingRaw("ABS(COALESCE(SUM(r.nominal_hak), 0) - s.{$column}) > 0.01")->get()->count();
        }
        return $mismatches;
    }
}
