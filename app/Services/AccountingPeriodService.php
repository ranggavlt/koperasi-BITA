<?php

namespace App\Services;

use App\Models\JurnalUmum;
use App\Models\PeriodeAkuntansi;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class AccountingPeriodService
{
    public function __construct(
        private readonly AkuntansiService $journal,
        private readonly AkunResolver $accounts,
    ) {}

    public function create(array $data, int $userId): PeriodeAkuntansi
    {
        return DB::transaction(function () use ($data, $userId): PeriodeAkuntansi {
            $overlap = PeriodeAkuntansi::query()
                ->where('tanggal_mulai', '<=', $data['tanggal_selesai'])
                ->where('tanggal_selesai', '>=', $data['tanggal_mulai'])
                ->lockForUpdate()
                ->exists();

            if ($overlap) {
                throw ValidationException::withMessages([
                    'tanggal_mulai' => 'Rentang periode bertumpang tindih dengan periode akuntansi yang sudah ada.',
                ]);
            }

            return PeriodeAkuntansi::query()->create([
                'kode' => trim((string) $data['kode']),
                'nama' => trim((string) $data['nama']),
                'tanggal_mulai' => $data['tanggal_mulai'],
                'tanggal_selesai' => $data['tanggal_selesai'],
                'status' => PeriodeAkuntansi::STATUS_OPEN,
                'created_by' => $userId,
                'idempotency_key' => (string) ($data['idempotency_key'] ?? 'accounting-period:'.sha1(json_encode($data))),
            ]);
        });
    }

    public function close(PeriodeAkuntansi $period, string $reason, int $userId): PeriodeAkuntansi
    {
        return DB::transaction(function () use ($period, $reason, $userId): PeriodeAkuntansi {
            $locked = PeriodeAkuntansi::query()->lockForUpdate()->findOrFail($period->id);

            if ($locked->status === PeriodeAkuntansi::STATUS_CLOSED) {
                return $locked;
            }

            if ($locked->status !== PeriodeAkuntansi::STATUS_OPEN) {
                throw ValidationException::withMessages(['status' => 'Periode tidak berada pada status open.']);
            }

            if ($locked->tanggal_selesai->isFuture()) {
                throw ValidationException::withMessages(['tanggal_selesai' => 'Periode yang belum berakhir tidak dapat ditutup.']);
            }

            $reason = trim($reason);
            if (mb_strlen($reason) < 5) {
                throw ValidationException::withMessages(['closing_reason' => 'Alasan tutup periode wajib diisi minimal 5 karakter.']);
            }

            $journals = JurnalUmum::query()
                ->with('details.akun')
                ->whereBetween('tanggal', [$locked->tanggal_mulai->toDateString(), $locked->tanggal_selesai->toDateString()])
                ->where(fn ($query) => $query->whereNull('idempotency_key')->orWhere('idempotency_key', '!=', 'accounting-period:closing:'.$locked->id))
                ->orderBy('tanggal')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            if ($journals->contains(fn (JurnalUmum $journal) => $journal->status !== JurnalUmum::STATUS_POSTED)) {
                throw ValidationException::withMessages(['status' => 'Semua jurnal dalam periode wajib berstatus posted sebelum periode ditutup.']);
            }

            foreach ($journals as $journal) {
                $debit = round((float) $journal->details->sum('debit'), 2);
                $credit = round((float) $journal->details->sum('kredit'), 2);
                if ($debit <= 0 || abs($debit - $credit) > 0.01 || $journal->details->contains(fn ($line) => $line->akun === null)) {
                    throw ValidationException::withMessages(['status' => 'Terdapat jurnal tidak balance atau detail tanpa master COA: '.$journal->nomor_bukti.'.']);
                }
            }

            $nominalByAccount = $journals->flatMap->details
                ->filter(fn ($line) => in_array($line->akun?->kategori, ['pendapatan', 'beban'], true))
                ->groupBy('akun_id')
                ->map(function ($lines) {
                    $account = $lines->first()->akun;
                    return [
                        'account' => $account,
                        'debit' => round((float) $lines->sum('debit'), 2),
                        'credit' => round((float) $lines->sum('kredit'), 2),
                    ];
                });

            $income = round((float) $nominalByAccount
                ->filter(fn ($row) => $row['account']->kategori === 'pendapatan')
                ->sum(fn ($row) => $row['credit'] - $row['debit']), 2);
            $expense = round((float) $nominalByAccount
                ->filter(fn ($row) => $row['account']->kategori === 'beban')
                ->sum(fn ($row) => $row['debit'] - $row['credit']), 2);
            $profit = round($income - $expense, 2);

            $snapshotRows = $journals->map(fn (JurnalUmum $journal) => [
                'id' => $journal->id,
                'tanggal' => $journal->tanggal->toDateString(),
                'nomor_bukti' => $journal->nomor_bukti,
                'debit' => number_format((float) $journal->details->sum('debit'), 2, '.', ''),
                'credit' => number_format((float) $journal->details->sum('kredit'), 2, '.', ''),
            ])->values()->all();
            $checksum = hash('sha256', json_encode($snapshotRows, JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION));

            $locked->update(['status' => PeriodeAkuntansi::STATUS_CLOSING]);
            $closingJournal = $this->createClosingJournal($locked, $nominalByAccount, $profit, $userId);

            if ($journals->isNotEmpty()) {
                JurnalUmum::query()->whereKey($journals->pluck('id'))->update(['periode_akuntansi_id' => $locked->id]);
            }

            $locked->update([
                'status' => PeriodeAkuntansi::STATUS_CLOSED,
                'total_pendapatan' => $income,
                'total_beban' => $expense,
                'laba_bersih' => $profit,
                'jumlah_jurnal' => $journals->count(),
                'checksum' => $checksum,
                'closing_snapshot' => [
                    'source' => 'posted_general_ledger',
                    'period' => [$locked->tanggal_mulai->toDateString(), $locked->tanggal_selesai->toDateString()],
                    'journal_ids' => $journals->pluck('id')->values()->all(),
                    'journal_count' => $journals->count(),
                    'checksum' => $checksum,
                    'total_pendapatan' => number_format($income, 2, '.', ''),
                    'total_beban' => number_format($expense, 2, '.', ''),
                    'laba_bersih' => number_format($profit, 2, '.', ''),
                ],
                'closing_journal_id' => $closingJournal?->id,
                'closed_by' => $userId,
                'closed_at' => now(),
                'closing_reason' => $reason,
            ]);

            return $locked->fresh(['closingJournal.details', 'closer']);
        });
    }

    private function createClosingJournal(PeriodeAkuntansi $period, $nominalByAccount, float $profit, int $userId): ?JurnalUmum
    {
        $lines = [];
        foreach ($nominalByAccount as $row) {
            $net = $row['account']->kategori === 'pendapatan'
                ? round($row['credit'] - $row['debit'], 2)
                : round($row['debit'] - $row['credit'], 2);
            if (abs($net) < 0.01) {
                continue;
            }
            $side = $row['account']->kategori === 'pendapatan'
                ? ($net > 0 ? 'debit' : 'kredit')
                : ($net > 0 ? 'kredit' : 'debit');
            $lines[] = $this->accounts->line($row['account'], $side, abs($net));
        }

        if (abs($profit) >= 0.01) {
            $lines[] = $this->accounts->line(
                $this->accounts->posting('shu.laba_belum_dibagi'),
                $profit > 0 ? 'kredit' : 'debit',
                abs($profit)
            );
        }

        if ($lines === []) {
            return null;
        }

        if (count($lines) < 2) {
            throw new RuntimeException('Jurnal penutup tidak mempunyai pasangan akun yang balance.');
        }

        return $this->journal->record([
            'idempotency_key' => 'accounting-period:closing:'.$period->id,
            'periode_akuntansi_id' => $period->id,
            'tanggal' => $period->tanggal_selesai->toDateString(),
            'nomor_bukti' => 'CLOSE-'.$period->kode,
            'keterangan' => 'Jurnal penutup periode '.$period->nama,
            'referensi_tipe' => PeriodeAkuntansi::class,
            'referensi_id' => $period->id,
            'created_by' => $userId,
            'is_period_operation' => true,
        ], $lines);
    }
}
