<?php

namespace App\Services;

use App\Models\Akun;
use App\Models\JenisSimpanan;
use App\Models\RiwayatJenisSimpanan;
use App\Models\Simpanan;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class JenisSimpananService
{
    public function create(array $data, ?int $userId): JenisSimpanan
    {
        try {
            return DB::transaction(function () use ($data, $userId): JenisSimpanan {
                $payload = $this->normalizePayload($data);
                $this->assertValidConfiguration($payload);
                $this->assertAkunValid($payload['kategori'], (int) $payload['akun_id']);
                $this->assertSingleActiveCategory($payload['kategori'], (bool) $payload['aktif']);

                $jenis = JenisSimpanan::query()->create([
                    'akun_id' => $payload['akun_id'],
                    'kode' => JenisSimpanan::kodeUntukKategori($payload['kategori']),
                    'kategori' => $payload['kategori'],
                    'interval_bulan' => $payload['interval_bulan'],
                    'berlaku_mulai' => $payload['berlaku_mulai'],
                    'nama_jenis' => $payload['nama_jenis'],
                    'wajib' => $payload['kategori'] !== JenisSimpanan::KATEGORI_MANASUKA,
                    'aktif' => $payload['aktif'],
                    'nominal_default' => $this->rupiahDecimal($payload['nominal_default']),
                    'keterangan' => $payload['keterangan'],
                    'created_by' => $userId,
                    'updated_by' => $userId,
                ]);

                $this->recordHistory(
                    $jenis,
                    null,
                    $this->snapshot($jenis->fresh('akun')),
                    $payload['alasan_perubahan'] ?: 'Pembuatan Master Jenis Simpanan.',
                    $userId
                );

                return $jenis->fresh(['akun', 'creator', 'updater', 'latestRiwayat.changedBy']);
            });
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages([
                'kategori' => 'Kategori ini sudah mempunyai Master Jenis Simpanan aktif.',
            ]);
        }
    }

    public function update(JenisSimpanan $jenis, array $data, ?int $userId): JenisSimpanan
    {
        try {
            return DB::transaction(function () use ($jenis, $data, $userId): JenisSimpanan {
                $locked = JenisSimpanan::query()
                    ->with('akun')
                    ->lockForUpdate()
                    ->findOrFail($jenis->id);

                $before = $this->snapshot($locked);
                $used = $this->isUsed($locked);
                $payload = $this->normalizePayload($data, $locked);

                if ($used && $payload['kategori'] !== $locked->kategori) {
                    throw ValidationException::withMessages([
                        'kategori' => 'Kategori sistem tidak boleh diubah karena master sudah dipakai transaksi.',
                    ]);
                }

                $kodeTarget = JenisSimpanan::kodeUntukKategori($payload['kategori']);
                if ($used && $kodeTarget !== $locked->kode) {
                    throw ValidationException::withMessages([
                        'kode' => 'Kode sistem tidak boleh diubah karena master sudah dipakai transaksi.',
                    ]);
                }

                $this->assertValidConfiguration($payload);
                $this->assertAkunValid($payload['kategori'], (int) $payload['akun_id']);
                $this->assertSingleActiveCategory($payload['kategori'], (bool) $payload['aktif'], $locked->id);

                $afterPreview = [
                    'akun_id' => $payload['akun_id'],
                    'kode' => $kodeTarget,
                    'kategori' => $payload['kategori'],
                    'interval_bulan' => $payload['interval_bulan'],
                    'berlaku_mulai' => $payload['berlaku_mulai'],
                    'nama_jenis' => $payload['nama_jenis'],
                    'wajib' => $payload['kategori'] !== JenisSimpanan::KATEGORI_MANASUKA,
                    'aktif' => $payload['aktif'],
                    'nominal_default' => $this->rupiahDecimal($payload['nominal_default']),
                    'keterangan' => $payload['keterangan'],
                ];

                $changedAuditedFields = $this->changedAuditedFields($before, $afterPreview);
                $needsReason = $used || (bool) $locked->aktif || $changedAuditedFields !== [];

                if ($needsReason && trim((string) $payload['alasan_perubahan']) === '') {
                    throw ValidationException::withMessages([
                        'alasan_perubahan' => 'Alasan perubahan wajib diisi saat mengubah master aktif atau master yang sudah dipakai transaksi.',
                    ]);
                }

                $locked->update($afterPreview + [
                    'updated_by' => $userId,
                ]);

                $after = $this->snapshot($locked->fresh('akun'));

                if ($before !== $after) {
                    $this->recordHistory(
                        $locked,
                        $before,
                        $after,
                        $payload['alasan_perubahan'] ?: 'Perubahan Master Jenis Simpanan.',
                        $userId
                    );
                }

                return $locked->fresh(['akun', 'creator', 'updater', 'latestRiwayat.changedBy']);
            });
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages([
                'kategori' => 'Kategori ini sudah mempunyai Master Jenis Simpanan aktif.',
            ]);
        }
    }

    public function deleteUnused(JenisSimpanan $jenis): void
    {
        DB::transaction(function () use ($jenis): void {
            $locked = JenisSimpanan::query()
                ->lockForUpdate()
                ->findOrFail($jenis->id);

            if ($this->isUsed($locked)) {
                throw ValidationException::withMessages([
                    'jenis_simpanan' => 'Jenis simpanan yang sudah dipakai transaksi tidak boleh dihapus permanen. Nonaktifkan dengan alasan perubahan.',
                ]);
            }

            $locked->delete();
        });
    }

    public function officialCodes(): array
    {
        return JenisSimpanan::KODE_BY_KATEGORI;
    }

    public function missingActiveCategories(): array
    {
        $active = JenisSimpanan::query()
            ->where('aktif', true)
            ->whereIn('kategori', array_keys(JenisSimpanan::KATEGORI))
            ->pluck('kategori')
            ->all();

        return array_values(array_diff(array_keys(JenisSimpanan::KATEGORI), $active));
    }

    private function normalizePayload(array $data, ?JenisSimpanan $existing = null): array
    {
        $kategori = (string) ($data['kategori'] ?? $existing?->kategori ?? '');
        $rawInterval = $data['interval_bulan'] ?? $existing?->interval_bulan ?? null;

        return [
            'akun_id' => (int) ($data['akun_id'] ?? $existing?->akun_id ?? 0),
            'kategori' => $kategori,
            'interval_bulan' => $rawInterval === null || $rawInterval === '' ? null : (int) $rawInterval,
            'berlaku_mulai' => $this->normalizeDate($data['berlaku_mulai'] ?? $existing?->berlaku_mulai ?? now(config('app.timezone', 'Asia/Jakarta'))),
            'nama_jenis' => trim((string) ($data['nama_jenis'] ?? $existing?->nama_jenis ?? '')),
            'aktif' => (bool) ((int) ($data['aktif'] ?? ($existing ? ($existing->aktif ? 1 : 0) : 1))),
            'nominal_default' => $this->rupiahInt($data['nominal_default'] ?? $existing?->nominal_default ?? 0),
            'keterangan' => $this->nullableText($data['keterangan'] ?? $existing?->keterangan ?? null),
            'alasan_perubahan' => $this->nullableText($data['alasan_perubahan'] ?? null),
        ];
    }

    private function assertValidConfiguration(array $payload): void
    {
        if (! array_key_exists($payload['kategori'], JenisSimpanan::KATEGORI)) {
            throw ValidationException::withMessages([
                'kategori' => 'Kategori Jenis Simpanan tidak dikenal.',
            ]);
        }

        if ($payload['nama_jenis'] === '') {
            throw ValidationException::withMessages([
                'nama_jenis' => 'Nama Jenis Simpanan wajib diisi.',
            ]);
        }

        if ($payload['berlaku_mulai'] === null) {
            throw ValidationException::withMessages([
                'berlaku_mulai' => 'Tanggal Berlaku Mulai wajib diisi.',
            ]);
        }

        if (in_array($payload['kategori'], [JenisSimpanan::KATEGORI_POKOK, JenisSimpanan::KATEGORI_MANASUKA], true)
            && $payload['interval_bulan'] !== null) {
            throw ValidationException::withMessages([
                'interval_bulan' => 'Interval hanya boleh diisi untuk Simpanan Wajib.',
            ]);
        }

        if ($payload['kategori'] === JenisSimpanan::KATEGORI_WAJIB
            && ($payload['interval_bulan'] < 1 || $payload['interval_bulan'] > 12)) {
            throw ValidationException::withMessages([
                'interval_bulan' => 'Interval Simpanan Wajib wajib antara 1 sampai 12 bulan.',
            ]);
        }

        if (in_array($payload['kategori'], [JenisSimpanan::KATEGORI_POKOK, JenisSimpanan::KATEGORI_WAJIB], true)
            && $payload['nominal_default'] <= 0) {
            throw ValidationException::withMessages([
                'nominal_default' => 'Nominal default Simpanan Pokok/Wajib wajib lebih besar dari nol.',
            ]);
        }

        if ($payload['kategori'] === JenisSimpanan::KATEGORI_MANASUKA && $payload['nominal_default'] < 0) {
            throw ValidationException::withMessages([
                'nominal_default' => 'Nominal default Simpanan Manasuka tidak boleh negatif.',
            ]);
        }
    }

    private function assertAkunValid(string $kategori, int $akunId): void
    {
        $akun = Akun::query()->find($akunId);

        if (! $akun || ! $akun->is_aktif || $akun->posisi_saldo !== 'kredit') {
            throw ValidationException::withMessages([
                'akun_id' => 'Akun COA Jenis Simpanan wajib aktif dengan saldo normal Kredit.',
            ]);
        }

        $expectedKategori = match ($kategori) {
            JenisSimpanan::KATEGORI_POKOK, JenisSimpanan::KATEGORI_WAJIB => 'ekuitas',
            JenisSimpanan::KATEGORI_MANASUKA => 'kewajiban',
            default => null,
        };

        if ($expectedKategori !== null && $akun->kategori !== $expectedKategori) {
            throw ValidationException::withMessages([
                'akun_id' => $kategori === JenisSimpanan::KATEGORI_MANASUKA
                    ? 'Simpanan Manasuka wajib memakai akun Kewajiban aktif.'
                    : 'Simpanan Pokok/Wajib wajib memakai akun Ekuitas aktif.',
            ]);
        }
    }

    private function assertSingleActiveCategory(string $kategori, bool $active, ?int $ignoreId = null): void
    {
        if (! $active) {
            return;
        }

        $query = JenisSimpanan::query()
            ->where('kategori', $kategori)
            ->where('aktif', true)
            ->lockForUpdate();

        if ($ignoreId !== null) {
            $query->whereKeyNot($ignoreId);
        }

        if ($query->exists()) {
            throw ValidationException::withMessages([
                'kategori' => 'Hanya boleh ada satu Master Jenis Simpanan aktif untuk setiap kategori.',
            ]);
        }
    }

    private function recordHistory(
        JenisSimpanan $jenis,
        ?array $before,
        array $after,
        string $reason,
        ?int $userId
    ): void {
        RiwayatJenisSimpanan::query()->create([
            'jenis_simpanan_id' => $jenis->id,
            'konfigurasi_sebelum' => $before,
            'konfigurasi_sesudah' => $after,
            'alasan' => $reason,
            'changed_by' => $userId,
            'changed_at' => now(),
        ]);
    }

    private function snapshot(JenisSimpanan $jenis): array
    {
        return [
            'akun_id' => $jenis->akun_id,
            'kode' => $jenis->kode,
            'kategori' => $jenis->kategori,
            'interval_bulan' => $jenis->interval_bulan,
            'berlaku_mulai' => optional($jenis->berlaku_mulai)->toDateString() ?? (is_string($jenis->berlaku_mulai) ? $jenis->berlaku_mulai : null),
            'nama_jenis' => $jenis->nama_jenis,
            'wajib' => (bool) $jenis->wajib,
            'aktif' => (bool) $jenis->aktif,
            'nominal_default' => $this->rupiahDecimal($jenis->nominal_default),
            'keterangan' => $jenis->keterangan,
        ];
    }

    private function changedAuditedFields(array $before, array $after): array
    {
        return collect(['nominal_default', 'interval_bulan', 'aktif', 'berlaku_mulai'])
            ->filter(fn (string $field): bool => ($before[$field] ?? null) !== ($after[$field] ?? null))
            ->values()
            ->all();
    }

    private function isUsed(JenisSimpanan $jenis): bool
    {
        return Simpanan::query()->where('jenis_simpanan_id', $jenis->id)->exists();
    }

    private function normalizeDate(mixed $value): ?string
    {
        if ($value instanceof Carbon) {
            return $value->toDateString();
        }

        if ($value instanceof \DateTimeInterface) {
            return Carbon::instance($value)->toDateString();
        }

        $string = trim((string) $value);

        if ($string === '') {
            return null;
        }

        return Carbon::parse($string, config('app.timezone', 'Asia/Jakarta'))->toDateString();
    }

    private function nullableText(mixed $value): ?string
    {
        $normalized = trim((string) preg_replace('/\s+/', ' ', (string) $value));

        return $normalized === '' ? null : $normalized;
    }

    private function rupiahInt(mixed $value): int
    {
        if (is_int($value)) {
            return $value;
        }

        $string = trim((string) $value);

        if ($string === '') {
            return 0;
        }

        if (preg_match('/^\d+(\.\d{1,2})?$/', $string) === 1) {
            return (int) explode('.', $string)[0];
        }

        return (int) preg_replace('/[^\d]/', '', $string);
    }

    private function rupiahDecimal(mixed $value): string
    {
        return number_format($this->rupiahInt($value), 2, '.', '');
    }
}
