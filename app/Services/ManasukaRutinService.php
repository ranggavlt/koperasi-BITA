<?php

namespace App\Services;

use App\Models\Anggota;
use App\Models\DompetKoperasi;
use App\Models\JenisSimpanan;
use App\Models\Karyawan;
use App\Models\KonfigurasiManasukaRutin;
use App\Models\LimitPotongGajiAnggota;
use App\Models\PemakaianPotongGaji;
use App\Models\SaldoSimpananManasuka;
use App\Models\SiklusKeanggotaan;
use App\Models\Simpanan;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class ManasukaRutinService
{
    public function __construct(
        private readonly AkuntansiService $akuntansiService,
        private readonly MutasiKasService $mutasiKasService
    ) {}

    public function businessTimezone(): string
    {
        return (string) config('app.timezone', 'Asia/Jakarta');
    }

    public function nextEffectivePeriod(): CarbonImmutable
    {
        return CarbonImmutable::now($this->businessTimezone())
            ->addMonthNoOverflow()
            ->startOfMonth()
            ->startOfDay();
    }

    public function latestScheduled(Anggota|int $anggota, ?int $siklusId = null): ?KonfigurasiManasukaRutin
    {
        $anggotaId = $anggota instanceof Anggota ? $anggota->id : $anggota;

        return KonfigurasiManasukaRutin::query()
            ->where('anggota_id', $anggotaId)
            ->when($siklusId, fn ($query) => $query->where('siklus_keanggotaan_id', $siklusId))
            ->orderByDesc('berlaku_mulai')
            ->orderByDesc('id')
            ->first();
    }

    public function effectiveFor(
        Anggota|int $anggota,
        int $siklusId,
        CarbonInterface|string $periode
    ): ?KonfigurasiManasukaRutin {
        $anggotaId = $anggota instanceof Anggota ? $anggota->id : $anggota;
        $periodeDate = CarbonImmutable::parse((string) $periode, $this->businessTimezone())
            ->startOfMonth()
            ->toDateString();

        return KonfigurasiManasukaRutin::query()
            ->where('anggota_id', $anggotaId)
            ->where('siklus_keanggotaan_id', $siklusId)
            ->whereDate('berlaku_mulai', '<=', $periodeDate)
            ->orderByDesc('berlaku_mulai')
            ->orderByDesc('id')
            ->first();
    }

    public function schedule(
        Anggota $anggota,
        string $status,
        int|string|null $nominal,
        ?int $userId,
        string $alasan,
        string $idempotencyKey,
        CarbonInterface|string|null $berlakuMulai = null
    ): KonfigurasiManasukaRutin {
        $status = trim($status);
        $alasan = trim($alasan);
        $idempotencyKey = trim($idempotencyKey);

        if (! in_array($status, KonfigurasiManasukaRutin::statuses(), true)) {
            throw ValidationException::withMessages([
                'manasuka_rutin_status' => 'Status Manasuka rutin tidak valid.',
            ]);
        }

        if (mb_strlen($alasan) < 5) {
            throw ValidationException::withMessages([
                'manasuka_rutin_alasan' => 'Alasan perubahan wajib diisi minimal 5 karakter.',
            ]);
        }

        if ($idempotencyKey === '') {
            throw ValidationException::withMessages([
                'manasuka_rutin_idempotency_key' => 'Kunci idempotensi perubahan wajib tersedia.',
            ]);
        }

        $effective = $berlakuMulai
            ? CarbonImmutable::parse((string) $berlakuMulai, $this->businessTimezone())->startOfMonth()
            : $this->nextEffectivePeriod();

        if ($effective->lessThan($this->nextEffectivePeriod())) {
            throw ValidationException::withMessages([
                'manasuka_rutin_status' => 'Perubahan Manasuka rutin paling cepat berlaku pada periode bulan berikutnya.',
            ]);
        }

        return DB::transaction(function () use (
            $anggota,
            $status,
            $nominal,
            $userId,
            $alasan,
            $idempotencyKey,
            $effective
        ): KonfigurasiManasukaRutin {
            $existing = KonfigurasiManasukaRutin::query()
                ->where('idempotency_key', $idempotencyKey)
                ->lockForUpdate()
                ->first();

            if ($existing) {
                if ((int) $existing->anggota_id !== (int) $anggota->id) {
                    throw ValidationException::withMessages([
                        'manasuka_rutin_idempotency_key' => 'Kunci idempotensi sudah dipakai untuk Anggota lain.',
                    ]);
                }

                return $existing;
            }

            $locked = Anggota::query()->with('karyawan')->lockForUpdate()->findOrFail($anggota->id);
            $siklus = SiklusKeanggotaan::query()
                ->where('anggota_id', $locked->id)
                ->where('status', SiklusKeanggotaan::STATUS_ACTIVE)
                ->lockForUpdate()
                ->first();

            if (! $siklus) {
                throw ValidationException::withMessages([
                    'manasuka_rutin_status' => 'Anggota tidak mempunyai siklus keanggotaan aktif.',
                ]);
            }

            $latest = $this->latestScheduled($locked, $siklus->id);
            $nominalCents = $nominal === null || trim((string) $nominal) === ''
                ? $this->decimalToCents($latest?->nominal_snapshot ?? $locked->manasuka_rutin_nominal ?? 0)
                : $this->decimalToCents($nominal);

            if ($status === KonfigurasiManasukaRutin::STATUS_AKTIF && $nominalCents <= 0) {
                throw ValidationException::withMessages([
                    'manasuka_rutin_nominal' => 'Nominal Manasuka rutin aktif harus lebih dari Rp0.',
                ]);
            }

            if ($nominalCents < 0) {
                throw ValidationException::withMessages([
                    'manasuka_rutin_nominal' => 'Nominal Manasuka rutin tidak boleh negatif.',
                ]);
            }

            $config = KonfigurasiManasukaRutin::query()->create([
                'anggota_id' => $locked->id,
                'siklus_keanggotaan_id' => $siklus->id,
                'status' => $status,
                'nominal_snapshot' => $this->decimalFromCents($nominalCents),
                'berlaku_mulai' => $effective->toDateString(),
                'alasan' => $alasan,
                'idempotency_key' => $idempotencyKey,
                'created_by' => $userId,
            ]);

            // Proyeksi kompatibilitas untuk kode lama; sumber kebenaran tetap tabel konfigurasi immutable.
            $locked->update([
                'is_manasuka_rutin_active' => $status === KonfigurasiManasukaRutin::STATUS_AKTIF,
                'manasuka_rutin_nominal' => $this->decimalFromCents($nominalCents),
            ]);

            return $config;
        });
    }

    public function pauseForInactive(Anggota $anggota, ?int $userId, string $reason): ?KonfigurasiManasukaRutin
    {
        $siklus = SiklusKeanggotaan::query()
            ->where('anggota_id', $anggota->id)
            ->where('status', SiklusKeanggotaan::STATUS_ACTIVE)
            ->first();

        if (! $siklus) {
            return null;
        }

        $latest = $this->latestScheduled($anggota, $siklus->id);
        if (! $latest || $latest->status !== KonfigurasiManasukaRutin::STATUS_AKTIF) {
            return $latest;
        }

        $effective = $this->nextEffectivePeriod();

        return $this->schedule(
            $anggota,
            KonfigurasiManasukaRutin::STATUS_DIJEDA,
            $latest->nominal_snapshot,
            $userId,
            $reason,
            'manasuka-rutin:auto-pause:'.$anggota->id.':'.$siklus->id.':'.$effective->format('Ym'),
            $effective
        );
    }

    public function reserveForLimit(LimitPotongGajiAnggota $limit, int $userId): ?Simpanan
    {
        return DB::transaction(function () use ($limit, $userId): ?Simpanan {
            $locked = LimitPotongGajiAnggota::query()
                ->with(['periodePotongGaji', 'anggota.karyawan'])
                ->lockForUpdate()
                ->findOrFail($limit->id);

            if ($locked->status !== LimitPotongGajiAnggota::STATUS_ACTIVE
                || $locked->anggota?->status !== Anggota::STATUS_AKTIF
                || $locked->anggota?->karyawan?->status_kerja !== Karyawan::STATUS_AKTIF) {
                return null;
            }

            $existing = Simpanan::query()
                ->where('idempotency_key', 'manasuka-rutin:transaksi:limit:'.$locked->id)
                ->lockForUpdate()
                ->first();

            if ($existing) {
                return $existing;
            }

            $siklus = SiklusKeanggotaan::query()
                ->where('anggota_id', $locked->anggota_id)
                ->where('status', SiklusKeanggotaan::STATUS_ACTIVE)
                ->lockForUpdate()
                ->first();

            if (! $siklus) {
                return null;
            }

            $config = $this->effectiveFor(
                $locked->anggota_id,
                $siklus->id,
                $locked->periodePotongGaji->periode
            );

            if (! $config || $config->status !== KonfigurasiManasukaRutin::STATUS_AKTIF) {
                return null;
            }

            $nominalCents = $this->decimalToCents($config->nominal_snapshot);
            $availableCents = $this->decimalToCents($locked->limit_nominal) - $this->reservedAndConsumedCents($locked);

            // Kebijakan PG-2: wajib penuh; bila limit kurang, seluruh nominal dilewati.
            if ($nominalCents <= 0 || $nominalCents > $availableCents) {
                return null;
            }

            $jenis = $this->activeManasukaMasterForUpdate();
            $tanggal = CarbonImmutable::parse(
                (string) $locked->periodePotongGaji->periode,
                $this->businessTimezone()
            )->startOfMonth();

            $simpanan = Simpanan::query()->create([
                'idempotency_key' => 'manasuka-rutin:transaksi:limit:'.$locked->id,
                'kode_transaksi' => $this->nextCode('simpanan_manasuka', 'SMN', $tanggal),
                'karyawan_id' => $locked->anggota->karyawan_id,
                'anggota_id' => $locked->anggota_id,
                'siklus_keanggotaan_id' => $siklus->id,
                'konfigurasi_manasuka_rutin_id' => $config->id,
                'jenis_simpanan_id' => $jenis->id,
                'kode_jenis_snapshot' => $jenis->kode,
                'nama_jenis_snapshot' => $jenis->nama_jenis,
                'nominal_snapshot' => $this->decimalFromCents($nominalCents),
                'jumlah' => $this->decimalFromCents($nominalCents),
                'jenis_transaksi' => Simpanan::JENIS_SETORAN,
                'metode_pembayaran' => Simpanan::METODE_POTONG_GAJI,
                'status' => Simpanan::STATUS_PENDING_PAYROLL,
                'tanggal' => $tanggal->toDateString(),
                'created_by' => $userId,
                'keterangan' => 'Simpanan Manasuka rutin periode '.$tanggal->format('Y-m'),
            ]);

            try {
                $ledger = PemakaianPotongGaji::query()->create([
                    'limit_potong_gaji_anggota_id' => $locked->id,
                    'kategori' => PemakaianPotongGaji::KATEGORI_SIMPANAN_MANASUKA,
                    'source_type' => Simpanan::class,
                    'source_id' => $simpanan->id,
                    'jenis' => PemakaianPotongGaji::JENIS_RESERVASI,
                    'nominal' => $this->decimalFromCents($nominalCents),
                    'status' => PemakaianPotongGaji::STATUS_RESERVED,
                    'idempotency_key' => 'potong-gaji:manasuka-rutin:limit:'.$locked->id,
                    'occurred_at' => now($this->businessTimezone()),
                    'created_by' => $userId,
                    'updated_by' => $userId,
                ]);
            } catch (UniqueConstraintViolationException) {
                $ledger = PemakaianPotongGaji::query()
                    ->where('idempotency_key', 'potong-gaji:manasuka-rutin:limit:'.$locked->id)
                    ->firstOrFail();
            }

            $simpanan->update(['pemakaian_potong_gaji_id' => $ledger->id]);

            return $simpanan->fresh(['konfigurasiManasukaRutin', 'ledger']);
        });
    }

    public function settleUsage(
        LimitPotongGajiAnggota $limit,
        PemakaianPotongGaji $usage,
        DompetKoperasi $dompetPayroll,
        int $userId,
        int $creditCents = 0
    ): Simpanan {
        return DB::transaction(function () use ($limit, $usage, $dompetPayroll, $userId, $creditCents): Simpanan {
            $lockedUsage = PemakaianPotongGaji::query()->lockForUpdate()->findOrFail($usage->id);
            $lockedLimit = LimitPotongGajiAnggota::query()->lockForUpdate()->findOrFail($limit->id);

            if ($lockedUsage->kategori !== PemakaianPotongGaji::KATEGORI_SIMPANAN_MANASUKA
                || $lockedUsage->source_type !== Simpanan::class
                || $lockedUsage->status !== PemakaianPotongGaji::STATUS_RESERVED
                || (int) $lockedUsage->limit_potong_gaji_anggota_id !== (int) $lockedLimit->id) {
                throw ValidationException::withMessages([
                    'simpanan_manasuka' => 'Ledger Simpanan Manasuka tidak valid untuk settlement payroll.',
                ]);
            }

            $simpanan = Simpanan::query()
                ->with(['jenisSimpanan.akun', 'konfigurasiManasukaRutin'])
                ->lockForUpdate()
                ->findOrFail($lockedUsage->source_id);

            if ((int) $simpanan->anggota_id !== (int) $lockedLimit->anggota_id || ! $simpanan->isSimpananManasuka()) {
                throw ValidationException::withMessages([
                    'simpanan_manasuka' => 'Transaksi Manasuka tidak sesuai dengan Anggota pada limit.',
                ]);
            }

            if (! $simpanan->konfigurasi_manasuka_rutin_id || ! $simpanan->konfigurasiManasukaRutin) {
                throw ValidationException::withMessages([
                    'simpanan_manasuka' => 'Snapshot konfigurasi Manasuka rutin tidak ditemukan.',
                ]);
            }

            $nominalCents = $this->decimalToCents($simpanan->nominal_snapshot ?? $simpanan->jumlah);
            if ($this->decimalToCents($lockedUsage->nominal) !== $nominalCents) {
                throw ValidationException::withMessages([
                    'simpanan_manasuka' => 'Nominal ledger tidak sama dengan snapshot transaksi Manasuka.',
                ]);
            }

            if ($simpanan->status === Simpanan::STATUS_SETTLED) {
                $lockedUsage->update([
                    'status' => PemakaianPotongGaji::STATUS_SETTLED,
                    'settled_at' => now($this->businessTimezone()),
                    'updated_by' => $userId,
                ]);

                return $simpanan;
            }

            if ($simpanan->status !== Simpanan::STATUS_PENDING_PAYROLL) {
                throw ValidationException::withMessages([
                    'simpanan_manasuka' => 'Status transaksi Manasuka tidak dapat disettle lewat payroll.',
                ]);
            }

            $saldo = $this->saldoRow(
                $simpanan->anggota_id,
                $simpanan->siklus_keanggotaan_id,
                $simpanan->jenis_simpanan_id
            );
            $saldoSebelumCents = $this->decimalToCents($saldo->saldo);
            $saldoSesudahCents = $saldoSebelumCents + $nominalCents;
            $creditCents = min(max(0, $creditCents), $nominalCents);
            $netCents = $nominalCents - $creditCents;

            $dompet = DompetKoperasi::query()
                ->with('akun')
                ->lockForUpdate()
                ->findOrFail($dompetPayroll->id);

            if ($netCents > 0) {
                $this->mutasiKasService->record([
                    'idempotency_key' => 'simpanan-manasuka:payroll:mutasi:'.$lockedUsage->id,
                    'dompet_id' => $dompet->id,
                    'tipe' => 'masuk',
                    'jumlah' => $this->decimalFromCents($netCents),
                    'keterangan' => 'Penerimaan payroll '.$simpanan->kode_transaksi,
                    'referensi_tipe' => Simpanan::class,
                    'referensi_id' => $simpanan->id,
                    'tanggal' => now($this->businessTimezone())->toDateString(),
                ]);
            }

            $this->akuntansiService->recordSimpananManasukaPayrollNet(
                $simpanan,
                $dompet->akun,
                (float) $this->decimalFromCents($creditCents),
                $userId
            );

            $saldo->update(['saldo' => $this->decimalFromCents($saldoSesudahCents)]);

            $simpanan->update([
                'pemakaian_potong_gaji_id' => $lockedUsage->id,
                'dompet_id' => $dompet->id,
                'saldo_sebelum_snapshot' => $this->decimalFromCents($saldoSebelumCents),
                'saldo_sesudah_snapshot' => $this->decimalFromCents($saldoSesudahCents),
                'metode_pembayaran' => Simpanan::METODE_POTONG_GAJI,
                'status' => Simpanan::STATUS_SETTLED,
                'settled_at' => now($this->businessTimezone()),
            ]);

            $lockedUsage->update([
                'status' => PemakaianPotongGaji::STATUS_SETTLED,
                'settled_at' => now($this->businessTimezone()),
                'updated_by' => $userId,
            ]);

            return $simpanan->fresh(['konfigurasiManasukaRutin', 'ledger', 'jurnal.details', 'mutasiKas']);
        });
    }

    public function releaseReservationsForLimit(LimitPotongGajiAnggota $limit, ?int $userId, string $reason): int
    {
        return DB::transaction(function () use ($limit, $userId, $reason): int {
            $locked = LimitPotongGajiAnggota::query()->lockForUpdate()->findOrFail($limit->id);
            $ledgers = PemakaianPotongGaji::query()
                ->where('limit_potong_gaji_anggota_id', $locked->id)
                ->where('kategori', PemakaianPotongGaji::KATEGORI_SIMPANAN_MANASUKA)
                ->where('source_type', Simpanan::class)
                ->where('status', PemakaianPotongGaji::STATUS_RESERVED)
                ->lockForUpdate()
                ->get();

            foreach ($ledgers as $ledger) {
                $ledger->update([
                    'status' => PemakaianPotongGaji::STATUS_RELEASED,
                    'released_at' => now($this->businessTimezone()),
                    'released_by' => $userId,
                    'release_reason' => $reason,
                    'updated_by' => $userId,
                ]);

                Simpanan::query()
                    ->whereKey($ledger->source_id)
                    ->where('status', Simpanan::STATUS_PENDING_PAYROLL)
                    ->update(['status' => Simpanan::STATUS_CANCELLED]);
            }

            return $ledgers->count();
        });
    }

    private function activeManasukaMasterForUpdate(): JenisSimpanan
    {
        $jenis = JenisSimpanan::query()
            ->with('akun')
            ->where('kode', JenisSimpanan::KODE_SIMPANAN_MANASUKA)
            ->where('kategori', JenisSimpanan::KATEGORI_MANASUKA)
            ->where('aktif', true)
            ->lockForUpdate()
            ->first();

        $akun = $jenis?->akun;
        if (! $jenis || ! $akun || ! $akun->is_aktif
            || ! in_array($akun->kategori, ['kewajiban', 'ekuitas'], true)
            || $akun->posisi_saldo !== 'kredit') {
            throw ValidationException::withMessages([
                'jenis_simpanan_id' => 'Master dan COA Simpanan Manasuka aktif belum valid.',
            ]);
        }

        return $jenis;
    }

    private function saldoRow(int $anggotaId, int $siklusId, int $jenisId): SaldoSimpananManasuka
    {
        $query = fn () => SaldoSimpananManasuka::query()
            ->where('anggota_id', $anggotaId)
            ->where('siklus_keanggotaan_id', $siklusId)
            ->where('jenis_simpanan_id', $jenisId)
            ->lockForUpdate();

        $saldo = $query()->first();
        if ($saldo) {
            return $saldo;
        }

        try {
            SaldoSimpananManasuka::query()->create([
                'anggota_id' => $anggotaId,
                'siklus_keanggotaan_id' => $siklusId,
                'jenis_simpanan_id' => $jenisId,
                'saldo' => '0.00',
            ]);
        } catch (QueryException) {
        }

        return $query()->first()
            ?? throw new RuntimeException('Saldo Simpanan Manasuka tidak dapat dibuat.');
    }

    private function reservedAndConsumedCents(LimitPotongGajiAnggota $limit): int
    {
        $sum = PemakaianPotongGaji::query()
            ->where('limit_potong_gaji_anggota_id', $limit->id)
            ->whereIn('status', [
                PemakaianPotongGaji::STATUS_RESERVED,
                PemakaianPotongGaji::STATUS_CONSUMED,
            ])
            ->sum('nominal');

        return $this->decimalToCents($sum);
    }

    private function nextCode(string $jenis, string $prefix, CarbonInterface $tanggal): string
    {
        $periode = CarbonImmutable::instance($tanggal)->format('Ym');

        try {
            DB::table('nomor_urut_transaksi')->insert([
                'jenis' => $jenis,
                'periode' => $periode,
                'last_number' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (QueryException) {
        }

        $counter = DB::table('nomor_urut_transaksi')
            ->where('jenis', $jenis)
            ->where('periode', $periode)
            ->lockForUpdate()
            ->first();

        if (! $counter) {
            throw new RuntimeException('Counter nomor transaksi Manasuka tidak dapat dibuat.');
        }

        $next = ((int) $counter->last_number) + 1;
        DB::table('nomor_urut_transaksi')->where('id', $counter->id)->update([
            'last_number' => $next,
            'updated_at' => now(),
        ]);

        return sprintf('%s-%s-%06d', $prefix, $periode, $next);
    }

    private function decimalToCents(mixed $value): int
    {
        $normalized = trim((string) $value);
        $negative = str_starts_with($normalized, '-');
        $normalized = ltrim($normalized, '+-');
        [$whole, $fraction] = array_pad(explode('.', $normalized, 2), 2, '');
        $whole = preg_replace('/\D/', '', $whole) ?: '0';
        $fraction = substr(str_pad(preg_replace('/\D/', '', $fraction) ?? '', 2, '0'), 0, 2);
        $cents = ((int) $whole * 100) + (int) $fraction;

        return $negative ? -$cents : $cents;
    }

    private function decimalFromCents(int $cents): string
    {
        return number_format($cents / 100, 2, '.', '');
    }
}
