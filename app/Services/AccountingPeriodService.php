<?php

namespace App\Services;

use App\Models\Akun;
use App\Models\JurnalUmum;
use App\Models\PeriodeAkuntansi;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AccountingPeriodService
{
    public function __construct(private readonly AkuntansiService $akuntansiService) {}

    public function create(array $data, int $userId): PeriodeAkuntansi
    {
        return DB::transaction(function () use ($data, $userId): PeriodeAkuntansi {
            if (PeriodeAkuntansi::query()->where('status', PeriodeAkuntansi::STATUS_OPEN)->lockForUpdate()->exists()) {
                throw ValidationException::withMessages(['tanggal_mulai' => 'Tutup periode yang sedang berjalan sebelum membuka periode baru.']);
            }
            if (PeriodeAkuntansi::query()->where('tanggal_mulai', '<=', $data['tanggal_selesai'])->where('tanggal_selesai', '>=', $data['tanggal_mulai'])->exists()) {
                throw ValidationException::withMessages(['tanggal_mulai' => 'Rentang periode tidak boleh bertumpang tindih.']);
            }

            return PeriodeAkuntansi::query()->create([
                ...$data,
                'status' => PeriodeAkuntansi::STATUS_OPEN,
                'created_by' => $userId,
            ]);
        });
    }

    public function close(PeriodeAkuntansi $period, int $userId): PeriodeAkuntansi
    {
        return DB::transaction(function () use ($period, $userId): PeriodeAkuntansi {
            $locked = PeriodeAkuntansi::query()->lockForUpdate()->findOrFail($period->id);
            if ($locked->status === PeriodeAkuntansi::STATUS_CLOSED) {
                return $locked->fresh(['closingJournal.details']);
            }

            $balances = DB::table('jurnal_umum_detail as detail')
                ->join('jurnal_umum as jurnal', 'jurnal.id', '=', 'detail.jurnal_umum_id')
                ->join('akun', 'akun.id', '=', 'detail.akun_id')
                ->whereBetween('jurnal.tanggal', [$locked->tanggal_mulai->toDateString(), $locked->tanggal_selesai->toDateString()])
                ->whereIn('akun.kategori', ['pendapatan', 'beban'])
                ->groupBy('akun.id', 'akun.kode_akun', 'akun.nama_akun', 'akun.kategori', 'akun.posisi_saldo')
                ->selectRaw('akun.id, akun.kode_akun, akun.nama_akun, akun.kategori, akun.posisi_saldo, SUM(detail.debit) total_debit, SUM(detail.kredit) total_kredit')
                ->get();

            $revenue = round((float) $balances->where('kategori', 'pendapatan')->sum(fn ($row) => (float) $row->total_kredit - (float) $row->total_debit), 2);
            $expense = round((float) $balances->where('kategori', 'beban')->sum(fn ($row) => (float) $row->total_debit - (float) $row->total_kredit), 2);
            $net = round($revenue - $expense, 2);
            $lines = [];
            foreach ($balances as $row) {
                $balance = $row->kategori === 'pendapatan'
                    ? (float) $row->total_kredit - (float) $row->total_debit
                    : (float) $row->total_debit - (float) $row->total_kredit;
                if ($balance <= 0) continue;
                $lines[] = ['akun_id' => (int) $row->id, 'akun_kode' => $row->kode_akun, 'akun_nama' => $row->nama_akun, 'debit' => $row->kategori === 'pendapatan' ? $balance : 0, 'kredit' => $row->kategori === 'beban' ? $balance : 0];
            }

            if ($net != 0.0) {
                $shuAccount = Akun::query()->where('kode_akun', config('account_map.accounts.shu_belum_dibagi.kode_akun'))->firstOrFail();
                $lines[] = ['akun_id' => $shuAccount->id, 'akun_kode' => $shuAccount->kode_akun, 'akun_nama' => $shuAccount->nama_akun, 'debit' => $net < 0 ? abs($net) : 0, 'kredit' => $net > 0 ? $net : 0];
            }

            $journal = null;
            if (count($lines) >= 2) {
                $journal = $this->akuntansiService->record([
                    'idempotency_key' => 'tutup-buku:jurnal:' . $locked->id,
                    'tanggal' => $locked->tanggal_selesai->toDateString(),
                    'nomor_bukti' => 'TB-' . $locked->kode,
                    'keterangan' => 'Jurnal penutup ' . $locked->nama,
                    'referensi_tipe' => PeriodeAkuntansi::class,
                    'referensi_id' => $locked->id,
                    'created_by' => $userId,
                ], $lines);
            }

            $locked->update([
                'status' => PeriodeAkuntansi::STATUS_CLOSED,
                'total_pendapatan' => $revenue,
                'total_beban' => $expense,
                'laba_bersih' => $net,
                'closed_by' => $userId,
                'closed_at' => now(),
                'closing_journal_id' => $journal?->id,
                'closing_idempotency_key' => 'tutup-buku:' . $locked->id,
            ]);

            return $locked->fresh(['closingJournal.details', 'closer']);
        });
    }

    public static function assertDateUnlocked(string $date): void
    {
        if (PeriodeAkuntansi::query()->where('status', PeriodeAkuntansi::STATUS_CLOSED)->whereDate('tanggal_mulai', '<=', $date)->whereDate('tanggal_selesai', '>=', $date)->exists()) {
            throw ValidationException::withMessages(['tanggal' => 'Tanggal berada pada periode yang sudah ditutup.']);
        }
    }
}
