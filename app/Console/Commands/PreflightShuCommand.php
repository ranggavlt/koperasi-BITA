<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PreflightShuCommand extends Command
{
    protected $signature = 'koperasi:preflight-shu';
    protected $description = 'Audit read-only integritas periode, pembagian, Hak Final, maker-checker, pembayaran, dan posting SHU.';

    public function handle(): int
    {
        if (! Schema::hasTable('shu_penerima') || ! Schema::hasTable('shu_alokasi')) {
            $this->error('Skema final SHU belum tersedia.'); return self::FAILURE;
        }
        $checks = [
            ['periode_ganda', 'Lebih dari satu SHU pada periode sama', $this->scalar("SELECT COUNT(*) FROM (SELECT periode_akuntansi_id FROM shu_koperasi WHERE periode_akuntansi_id IS NOT NULL GROUP BY periode_akuntansi_id HAVING COUNT(*)>1) x")],
            ['kategori_final', 'SHU final tidak memakai persentase 30/40/10/5/5/10 atau Pendidikan bukan 0', DB::table('shu_koperasi')->whereIn('status', ['ready_for_approval','approved'])->where(fn($q) => $q->where('persen_dana_cadangan','!=',30)->orWhere('persen_shu_anggota','!=',40)->orWhere('persen_pengurus','!=',10)->orWhere('persen_pengawas','!=',5)->orWhere('persen_pembina','!=',5)->orWhere('persen_dana_sosial','!=',10)->orWhere('persen_dana_pendidikan','!=',0))->count()],
            ['maker_checker', 'Approver sama dengan pembuat/penghitung/penyunting', DB::table('shu_koperasi')->where('status','approved')->where(fn($q) => $q->whereColumn('approved_by','created_by')->orWhereColumn('approved_by','calculated_by')->orWhereColumn('approved_by','submitted_by'))->count()],
            ['hak_kelompok', 'Total Hak Final included tidak sama dengan pool kelompok', $this->groupMismatches()],
            ['pembayaran', 'Pembayaran paid tidak penuh/tautannya tidak lengkap', DB::table('pembayaran_shu as p')->join('shu_penerima as r','r.id','=','p.shu_penerima_id')->where('p.status','paid')->where(fn($q) => $q->whereRaw('p.jumlah <> COALESCE(r.hak_final,r.hitungan_sistem,r.nominal_hak)')->orWhereNull('p.mutasi_kas_id')->orWhereNull('p.jurnal_id'))->count()],
            ['status_bayar', 'Status penerima tidak konsisten dengan pembayaran aktif', DB::table('shu_penerima as r')->leftJoin('pembayaran_shu as p',fn($j) => $j->on('p.shu_penerima_id','=','r.id')->where('p.status','=','paid'))->where(fn($q) => $q->where(fn($x) => $x->where('r.status_pembayaran','dibayar')->whereNull('p.id'))->orWhere(fn($x) => $x->where('r.status_pembayaran','!=','dibayar')->whereNotNull('p.id')))->count()],
            ['posting', 'SHU approved tanpa jurnal, dua alokasi, atau sumber sosial', DB::table('shu_koperasi as s')->select('s.id','s.allocation_journal_id','d.id as sumber_id')->selectRaw('COUNT(DISTINCT a.jenis) total_jenis')->leftJoin('shu_alokasi as a','a.shu_koperasi_id','=','s.id')->leftJoin('dana_sosial_sumber as d','d.shu_koperasi_id','=','s.id')->where('s.status','approved')->groupBy('s.id','s.allocation_journal_id','d.id')->havingRaw('s.allocation_journal_id IS NULL OR COUNT(DISTINCT a.jenis) <> 2 OR d.id IS NULL')->get()->count()],
        ];
        $this->info('Ringkasan preflight SHU final (read-only)'); $this->table(['Kode','Pemeriksaan','Count'],$checks);
        if (collect($checks)->contains(fn($row) => $row[2] > 0)) { $this->error('Preflight SHU menemukan konflik; tidak ada data diubah.'); return self::FAILURE; }
        $this->info('Preflight SHU bersih.'); return self::SUCCESS;
    }

    private function groupMismatches(): int
    {
        $count = 0;
        foreach (['anggota'=>'nominal_shu_anggota','pengurus'=>'nominal_pengurus','pengawas'=>'nominal_pengawas','pembina'=>'nominal_pembina'] as $group=>$column) {
            $count += DB::table('shu_koperasi as s')->select('s.id',"s.$column")->selectRaw('COALESCE(SUM(COALESCE(r.hak_final,r.hitungan_sistem,r.nominal_hak)),0) total_hak')->leftJoin('shu_penerima as r',fn($j) => $j->on('r.shu_koperasi_id','=','s.id')->where('r.jenis_penerima','=',$group)->where('r.diikutkan','=',1))
                ->whereIn('s.status',['ready_for_approval','approved'])->groupBy('s.id',"s.$column")
                ->havingRaw("COALESCE(SUM(COALESCE(r.hak_final,r.hitungan_sistem,r.nominal_hak)),0) <> s.$column")->get()->count();
        }
        return $count;
    }

    private function scalar(string $sql): int { return (int) (array_values((array) DB::selectOne($sql))[0] ?? 0); }
}
