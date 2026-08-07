<?php

namespace App\Services;

use App\Models\Akun;
use App\Models\JurnalUmum;
use App\Models\PeriodeAkuntansi;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AccountingPeriodService
{
    public function __construct(private readonly AkuntansiService $akuntansiService) {}

    public function create(array $data, int $userId): PeriodeAkuntansi
    {
        return DB::transaction(function () use ($data, $userId): PeriodeAkuntansi {
            $this->validateRange($data['tanggal_mulai'], $data['tanggal_selesai']);
            if (PeriodeAkuntansi::query()->whereIn('status', [PeriodeAkuntansi::STATUS_OPEN, PeriodeAkuntansi::STATUS_CLOSING])->lockForUpdate()->exists()) {
                throw ValidationException::withMessages(['tanggal_mulai' => 'Tutup periode yang sedang berjalan sebelum membuka periode baru.']);
            }
            $this->assertNoOverlap($data['tanggal_mulai'], $data['tanggal_selesai']);

            return PeriodeAkuntansi::query()->create([
                ...$data,
                'status' => PeriodeAkuntansi::STATUS_OPEN,
                'created_by' => $userId,
                'idempotency_key' => $data['idempotency_key'] ?? 'periode:' . $data['kode'],
            ]);
        });
    }

    public function update(PeriodeAkuntansi $period, array $data): PeriodeAkuntansi
    {
        return DB::transaction(function () use ($period, $data): PeriodeAkuntansi {
            $locked = PeriodeAkuntansi::query()->lockForUpdate()->findOrFail($period->id);
            if ($locked->status !== PeriodeAkuntansi::STATUS_OPEN) {
                throw ValidationException::withMessages(['periode' => 'Hanya periode yang masih berjalan yang dapat diubah.']);
            }
            $start = $data['tanggal_mulai'] ?? $locked->tanggal_mulai->toDateString();
            $end = $data['tanggal_selesai'] ?? $locked->tanggal_selesai->toDateString();
            $this->validateRange($start, $end);
            $this->assertNoOverlap($start, $end, $locked->id);

            if (JurnalUmum::query()->where('periode_akuntansi_id', $locked->id)
                ->where(fn ($q) => $q->whereDate('tanggal', '<', $start)->orWhereDate('tanggal', '>', $end))->exists()) {
                throw ValidationException::withMessages(['tanggal_mulai' => 'Rentang baru tidak boleh mengeluarkan jurnal yang sudah terkait dengan periode ini.']);
            }

            $locked->update($data);
            return $locked->fresh();
        });
    }

    public function close(PeriodeAkuntansi $period, string|int $reasonOrUserId, ?int $userId = null): PeriodeAkuntansi
    {
        $reason = is_string($reasonOrUserId) ? trim($reasonOrUserId) : 'Tutup buku tahunan';
        $actorId = is_int($reasonOrUserId) ? $reasonOrUserId : (int) $userId;
        if ($actorId <= 0) {
            throw ValidationException::withMessages(['periode' => 'Pengguna penutup periode tidak valid.']);
        }
        if (mb_strlen($reason) < 5) {
            throw ValidationException::withMessages(['closing_reason' => 'Alasan tutup buku wajib diisi minimal 5 karakter.']);
        }

        return DB::transaction(function () use ($period, $actorId, $reason): PeriodeAkuntansi {
            $locked = PeriodeAkuntansi::query()->lockForUpdate()->findOrFail($period->id);
            if ($locked->status === PeriodeAkuntansi::STATUS_CLOSED) {
                return $locked->fresh(['closingJournal.details']);
            }
            if ($locked->status !== PeriodeAkuntansi::STATUS_OPEN) {
                throw ValidationException::withMessages(['periode' => 'Periode tidak dalam keadaan siap ditutup.']);
            }

            $locked->update(['status' => PeriodeAkuntansi::STATUS_CLOSING]);
            $start = $locked->tanggal_mulai->toDateString();
            $end = $locked->tanggal_selesai->toDateString();
            $balances = DB::table('jurnal_umum_detail as detail')
                ->join('jurnal_umum as jurnal', 'jurnal.id', '=', 'detail.jurnal_umum_id')
                ->join('akun', 'akun.id', '=', 'detail.akun_id')
                ->where('jurnal.status', JurnalUmum::STATUS_POSTED)
                ->whereBetween('jurnal.tanggal', [$start, $end])
                ->whereIn('akun.kategori', ['pendapatan', 'beban'])
                ->groupBy('akun.id', 'akun.kode_akun', 'akun.nama_akun', 'akun.kategori')
                ->selectRaw('akun.id, akun.kode_akun, akun.nama_akun, akun.kategori, SUM(detail.debit) total_debit, SUM(detail.kredit) total_kredit')
                ->orderBy('akun.kode_akun')
                ->get();

            $revenue = (int) round($balances->where('kategori', 'pendapatan')->sum(fn ($row) => (float) $row->total_kredit - (float) $row->total_debit));
            $expense = (int) round($balances->where('kategori', 'beban')->sum(fn ($row) => (float) $row->total_debit - (float) $row->total_kredit));
            $net = $revenue - $expense;
            $lines = [];
            foreach ($balances as $row) {
                $balance = (int) round($row->kategori === 'pendapatan'
                    ? (float) $row->total_kredit - (float) $row->total_debit
                    : (float) $row->total_debit - (float) $row->total_kredit);
                if ($balance === 0) {
                    continue;
                }
                $normalPositive = $balance > 0;
                $debit = ($row->kategori === 'pendapatan') === $normalPositive ? abs($balance) : 0;
                $credit = $debit === 0 ? abs($balance) : 0;
                $lines[] = ['akun_id' => (int) $row->id, 'akun_kode' => $row->kode_akun, 'akun_nama' => $row->nama_akun, 'debit' => $debit, 'kredit' => $credit];
            }
            if ($net !== 0) {
                $shuAccount = Akun::query()->where('kode_akun', config('account_map.accounts.shu_belum_dibagi.kode_akun'))->firstOrFail();
                $lines[] = ['akun_id' => $shuAccount->id, 'akun_kode' => $shuAccount->kode_akun, 'akun_nama' => $shuAccount->nama_akun, 'debit' => $net < 0 ? abs($net) : 0, 'kredit' => $net > 0 ? $net : 0];
            }

            $journal = count($lines) >= 2 ? $this->akuntansiService->record([
                'idempotency_key' => 'tutup-buku:jurnal:' . $locked->id,
                'tanggal' => $end,
                'nomor_bukti' => 'TB-' . $locked->kode,
                'keterangan' => 'Jurnal penutup ' . $locked->nama,
                'referensi_tipe' => PeriodeAkuntansi::class,
                'referensi_id' => $locked->id,
                'created_by' => $actorId,
                'is_period_operation' => true,
            ], $lines) : null;

            $journalCount = JurnalUmum::query()->where('status', JurnalUmum::STATUS_POSTED)
                ->whereBetween('tanggal', [$start, $end])
                ->when($journal, fn ($query) => $query->whereKeyNot($journal->id))
                ->count();
            $snapshot = [
                'periode' => ['kode' => $locked->kode, 'tanggal_mulai' => $start, 'tanggal_selesai' => $end],
                'total_pendapatan' => $revenue,
                'total_beban' => $expense,
                'laba_bersih' => $net,
                'jumlah_jurnal_posted' => $journalCount,
                'saldo_nominal' => $balances->map(fn ($row) => [
                    'kode_akun' => $row->kode_akun,
                    'kategori' => $row->kategori,
                    'debit' => (int) round($row->total_debit),
                    'kredit' => (int) round($row->total_kredit),
                ])->values()->all(),
            ];
            $checksum = hash('sha256', json_encode($snapshot, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION));

            $locked->update([
                'status' => PeriodeAkuntansi::STATUS_CLOSED,
                'total_pendapatan' => $revenue,
                'total_beban' => $expense,
                'laba_bersih' => $net,
                'jumlah_jurnal' => $journalCount,
                'checksum' => $checksum,
                'closing_snapshot' => $snapshot,
                'closing_reason' => $reason,
                'closed_by' => $actorId,
                'closed_at' => now(),
                'closing_journal_id' => $journal?->id,
                'closing_idempotency_key' => 'tutup-buku:' . $locked->id,
            ]);

            return $locked->fresh(['closingJournal.details', 'closer']);
        });
    }

    public static function assertDateUnlocked(string $date): void
    {
        if (PeriodeAkuntansi::query()->whereIn('status', [PeriodeAkuntansi::STATUS_CLOSING, PeriodeAkuntansi::STATUS_CLOSED])
            ->whereDate('tanggal_mulai', '<=', $date)->whereDate('tanggal_selesai', '>=', $date)->exists()) {
            throw ValidationException::withMessages(['tanggal' => 'Tanggal berada pada periode yang sudah dikunci. Gunakan koreksi resmi pada periode terbuka.']);
        }
    }

    private function validateRange(string $start, string $end): void
    {
        $startDate = CarbonImmutable::parse($start);
        $expectedEnd = $startDate->addYear()->subDay();
        if (! $expectedEnd->isSameDay(CarbonImmutable::parse($end))) {
            throw ValidationException::withMessages(['tanggal_selesai' => 'Periode buku wajib tepat satu tahun (tanggal selesai = satu tahun setelah mulai dikurangi satu hari).']);
        }
    }

    private function assertNoOverlap(string $start, string $end, ?int $ignoreId = null): void
    {
        $query = PeriodeAkuntansi::query()->whereDate('tanggal_mulai', '<=', $end)->whereDate('tanggal_selesai', '>=', $start);
        if ($ignoreId) {
            $query->whereKeyNot($ignoreId);
        }
        if ($query->exists()) {
            throw ValidationException::withMessages(['tanggal_mulai' => 'Rentang periode tidak boleh bertumpang tindih.']);
        }
    }
}
