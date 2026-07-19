<?php

namespace App\Services;

use App\Models\AsetKoperasi;
use App\Models\AsetMobil;
use App\Models\AsetPrinter;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class AsetKoperasiService
{
    public function createMobil(array $data, ?int $userId = null): AsetKoperasi
    {
        return $this->friendlyUniqueFailure(function () use ($data, $userId): AsetKoperasi {
            return DB::transaction(function () use ($data, $userId): AsetKoperasi {
                $tarifSewaHarian = $this->rupiahInt($data['tarif_sewa_harian'] ?? 0);
                $this->assertValidTarifSewaHarian($tarifSewaHarian);

                $aset = AsetKoperasi::query()->create([
                    'kode_aset' => $this->nextKode(AsetKoperasi::JENIS_MOBIL),
                    'jenis_aset' => AsetKoperasi::JENIS_MOBIL,
                    'merek' => $this->normalizeText($data['merek']),
                    'model' => $this->normalizeText($data['model']),
                    'status' => AsetKoperasi::STATUS_TERSEDIA,
                    'keterangan' => $this->nullableText($data['keterangan'] ?? null),
                    'created_by' => $userId,
                    'updated_by' => $userId,
                ]);

                $aset->mobil()->create([
                    'plat_nomor' => $this->normalizeIdentity($data['plat_nomor']),
                    'tahun' => (int) $data['tahun'],
                    'warna' => $this->normalizeText($data['warna']),
                    'tarif_sewa_harian' => $tarifSewaHarian,
                ]);

                return $aset->fresh(['mobil', 'creator', 'updater']);
            });
        });
    }

    public function createPrinter(array $data, ?int $userId = null): AsetKoperasi
    {
        return $this->friendlyUniqueFailure(function () use ($data, $userId): AsetKoperasi {
            return DB::transaction(function () use ($data, $userId): AsetKoperasi {
                $aset = AsetKoperasi::query()->create([
                    'kode_aset' => $this->nextKode(AsetKoperasi::JENIS_PRINTER),
                    'jenis_aset' => AsetKoperasi::JENIS_PRINTER,
                    'merek' => $this->normalizeText($data['merek']),
                    'model' => $this->normalizeText($data['model']),
                    'status' => AsetKoperasi::STATUS_TERSEDIA,
                    'keterangan' => $this->nullableText($data['keterangan'] ?? null),
                    'created_by' => $userId,
                    'updated_by' => $userId,
                ]);

                $aset->printer()->create([
                    'nomor_seri' => $this->normalizeIdentity($data['nomor_seri']),
                    'lokasi' => $this->normalizeText($data['lokasi']),
                ]);

                return $aset->fresh(['printer', 'creator', 'updater']);
            });
        });
    }

    public function updateMobil(AsetKoperasi $aset, array $data, ?int $userId = null): AsetKoperasi
    {
        $this->assertJenis($aset, AsetKoperasi::JENIS_MOBIL);

        return $this->friendlyUniqueFailure(function () use ($aset, $data, $userId): AsetKoperasi {
            return DB::transaction(function () use ($aset, $data, $userId): AsetKoperasi {
                $tarifSewaHarian = $this->rupiahInt($data['tarif_sewa_harian'] ?? 0);
                $this->assertValidTarifSewaHarian($tarifSewaHarian);

                $locked = AsetKoperasi::query()
                    ->with('mobil')
                    ->lockForUpdate()
                    ->findOrFail($aset->id);

                $locked->update([
                    'merek' => $this->normalizeText($data['merek']),
                    'model' => $this->normalizeText($data['model']),
                    'keterangan' => $this->nullableText($data['keterangan'] ?? null),
                    'updated_by' => $userId,
                ]);

                $locked->mobil()->updateOrCreate(
                    ['aset_koperasi_id' => $locked->id],
                    [
                        'plat_nomor' => $this->normalizeIdentity($data['plat_nomor']),
                        'tahun' => (int) $data['tahun'],
                        'warna' => $this->normalizeText($data['warna']),
                        'tarif_sewa_harian' => $tarifSewaHarian,
                    ]
                );

                return $locked->fresh(['mobil', 'creator', 'updater', 'nonaktifBy']);
            });
        });
    }

    public function updatePrinter(AsetKoperasi $aset, array $data, ?int $userId = null): AsetKoperasi
    {
        $this->assertJenis($aset, AsetKoperasi::JENIS_PRINTER);

        return $this->friendlyUniqueFailure(function () use ($aset, $data, $userId): AsetKoperasi {
            return DB::transaction(function () use ($aset, $data, $userId): AsetKoperasi {
                $locked = AsetKoperasi::query()
                    ->with('printer')
                    ->lockForUpdate()
                    ->findOrFail($aset->id);

                $locked->update([
                    'merek' => $this->normalizeText($data['merek']),
                    'model' => $this->normalizeText($data['model']),
                    'keterangan' => $this->nullableText($data['keterangan'] ?? null),
                    'updated_by' => $userId,
                ]);

                $locked->printer()->updateOrCreate(
                    ['aset_koperasi_id' => $locked->id],
                    [
                        'nomor_seri' => $this->normalizeIdentity($data['nomor_seri']),
                        'lokasi' => $this->normalizeText($data['lokasi']),
                    ]
                );

                return $locked->fresh(['printer', 'creator', 'updater', 'nonaktifBy']);
            });
        });
    }

    public function updateStatus(AsetKoperasi $aset, string $status, ?int $userId = null): AsetKoperasi
    {
        if (! in_array($status, AsetKoperasi::statuses(), true)) {
            throw ValidationException::withMessages([
                'status' => 'Status aset tidak valid.',
            ]);
        }

        if ($status === AsetKoperasi::STATUS_NONAKTIF) {
            return $this->nonaktifkan($aset, $userId);
        }

        return DB::transaction(function () use ($aset, $status, $userId): AsetKoperasi {
            $locked = AsetKoperasi::query()
                ->lockForUpdate()
                ->findOrFail($aset->id);

            $locked->update([
                'status' => $status,
                'updated_by' => $userId,
                'nonaktif_at' => null,
                'nonaktif_by' => null,
            ]);

            return $locked->fresh(['mobil', 'printer', 'nonaktifBy']);
        });
    }

    public function nonaktifkan(AsetKoperasi $aset, ?int $userId = null): AsetKoperasi
    {
        return DB::transaction(function () use ($aset, $userId): AsetKoperasi {
            $locked = AsetKoperasi::query()
                ->lockForUpdate()
                ->findOrFail($aset->id);

            if ($locked->status === AsetKoperasi::STATUS_NONAKTIF && $locked->nonaktif_at) {
                return $locked->fresh(['mobil', 'printer', 'nonaktifBy']);
            }

            $locked->update([
                'status' => AsetKoperasi::STATUS_NONAKTIF,
                'updated_by' => $userId,
                'nonaktif_at' => $locked->nonaktif_at ?? now(),
                'nonaktif_by' => $locked->nonaktif_by ?? $userId,
            ]);

            return $locked->fresh(['mobil', 'printer', 'nonaktifBy']);
        });
    }

    public function aktifkan(AsetKoperasi $aset, ?int $userId = null): AsetKoperasi
    {
        return $this->updateStatus($aset, AsetKoperasi::STATUS_TERSEDIA, $userId);
    }

    public function delete(AsetKoperasi $aset, bool $confirmed = false, ?int $userId = null): void
    {
        $guard = $this->canDelete($aset);

        if (! $confirmed) {
            throw ValidationException::withMessages([
                'confirm_delete' => 'Konfirmasi penghapusan aset wajib dicentang.',
            ]);
        }

        if (! $guard['allowed']) {
            throw ValidationException::withMessages([
                'aset' => $guard['reason'] . ' Silakan nonaktifkan aset untuk mempertahankan histori.',
            ]);
        }

        DB::transaction(function () use ($aset, $userId): void {
            $locked = AsetKoperasi::query()
                ->with(['mobil', 'printer'])
                ->lockForUpdate()
                ->findOrFail($aset->id);

            $locked->update(['updated_by' => $userId]);

            if ($locked->mobil) {
                $locked->mobil->delete();
            }

            if ($locked->printer) {
                $locked->printer->delete();
            }

            $locked->delete();
        });
    }

    /**
     * @return array{allowed:bool, reason:string|null, dependencies:array<string,int>}
     */
    public function canDelete(AsetKoperasi $aset): array
    {
        $aset->loadMissing(['mobil', 'printer']);

        if (in_array($aset->status, [AsetKoperasi::STATUS_DIGUNAKAN_DISEWA, AsetKoperasi::STATUS_PERAWATAN], true)) {
            return [
                'allowed' => false,
                'reason' => 'Aset yang sedang digunakan/disewa atau perawatan tidak boleh dihapus.',
                'dependencies' => [],
            ];
        }

        $dependencies = $this->dependencyCounts($aset);

        if (array_sum($dependencies) > 0) {
            return [
                'allowed' => false,
                'reason' => 'Aset sudah mempunyai transaksi atau histori terkait.',
                'dependencies' => $dependencies,
            ];
        }

        return [
            'allowed' => true,
            'reason' => null,
            'dependencies' => [],
        ];
    }

    /**
     * @return array<string,int>
     */
    public function dependencyCounts(AsetKoperasi $aset): array
    {
        $aset->loadMissing(['mobil', 'printer']);

        $contracts = [
            ['label' => 'Sewa Mobil', 'table' => 'sewa_mobil', 'column' => 'aset_koperasi_id', 'id' => $aset->id],
            ['label' => 'Sewa Mobil Detail', 'table' => 'sewa_mobil', 'column' => 'aset_mobil_id', 'id' => $aset->mobil?->id],
            ['label' => 'Transaksi Sewa Mobil', 'table' => 'transaksi_sewa_mobil', 'column' => 'aset_koperasi_id', 'id' => $aset->id],
            ['label' => 'Transaksi Sewa Mobil Detail', 'table' => 'transaksi_sewa_mobil', 'column' => 'aset_mobil_id', 'id' => $aset->mobil?->id],
            ['label' => 'Sewa Printer', 'table' => 'sewa_printer', 'column' => 'aset_koperasi_id', 'id' => $aset->id],
            ['label' => 'Sewa Printer Detail', 'table' => 'sewa_printer_detail', 'column' => 'aset_koperasi_id', 'id' => $aset->id],
            ['label' => 'Sewa Printer Detail', 'table' => 'sewa_printer', 'column' => 'aset_printer_id', 'id' => $aset->printer?->id],
            ['label' => 'Beban Operasional Aset', 'table' => 'beban_operasional_detail', 'column' => 'aset_koperasi_id', 'id' => $aset->id],
            ['label' => 'Transaksi Sewa Printer', 'table' => 'transaksi_sewa_printer', 'column' => 'aset_koperasi_id', 'id' => $aset->id],
            ['label' => 'Transaksi Sewa Printer Detail', 'table' => 'transaksi_sewa_printer', 'column' => 'aset_printer_id', 'id' => $aset->printer?->id],
            ['label' => 'Riwayat Aset', 'table' => 'riwayat_aset_koperasi', 'column' => 'aset_koperasi_id', 'id' => $aset->id],
        ];

        $counts = [];

        foreach ($contracts as $contract) {
            if ($contract['id'] === null) {
                continue;
            }

            if (! Schema::hasTable($contract['table']) || ! Schema::hasColumn($contract['table'], $contract['column'])) {
                continue;
            }

            $count = DB::table($contract['table'])
                ->where($contract['column'], $contract['id'])
                ->count();

            if ($count > 0) {
                $counts[$contract['label']] = $count;
            }
        }

        return $counts;
    }

    private function nextKode(string $jenis): string
    {
        $prefix = match ($jenis) {
            AsetKoperasi::JENIS_MOBIL => 'MBL',
            AsetKoperasi::JENIS_PRINTER => 'PRT',
            default => throw ValidationException::withMessages(['jenis_aset' => 'Jenis aset tidak valid.']),
        };

        DB::table('nomor_urut_aset')->insertOrIgnore([
            'jenis_aset' => $jenis,
            'last_number' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $counter = DB::table('nomor_urut_aset')
            ->where('jenis_aset', $jenis)
            ->lockForUpdate()
            ->first();

        $next = ((int) $counter->last_number) + 1;

        DB::table('nomor_urut_aset')
            ->where('jenis_aset', $jenis)
            ->update([
                'last_number' => $next,
                'updated_at' => now(),
            ]);

        return sprintf('%s-%04d', $prefix, $next);
    }

    private function assertJenis(AsetKoperasi $aset, string $jenis): void
    {
        if ($aset->jenis_aset !== $jenis) {
            throw ValidationException::withMessages([
                'aset' => 'Jenis aset tidak sesuai dengan halaman yang sedang dibuka.',
            ]);
        }
    }

    private function normalizeText(string $value): string
    {
        return trim((string) preg_replace('/\s+/', ' ', $value));
    }

    private function nullableText(?string $value): ?string
    {
        $normalized = trim((string) preg_replace('/\s+/', ' ', (string) $value));

        return $normalized === '' ? null : $normalized;
    }

    private function normalizeIdentity(string $value): string
    {
        return strtoupper($this->normalizeText($value));
    }

    private function assertValidTarifSewaHarian(int $tarifSewaHarian): void
    {
        if ($tarifSewaHarian <= 0) {
            throw ValidationException::withMessages([
                'tarif_sewa_harian' => 'Tarif Sewa Harian wajib lebih besar dari nol.',
            ]);
        }
    }

    private function rupiahInt(mixed $value): int
    {
        return (int) preg_replace('/[^\d]/', '', (string) $value);
    }

    private function friendlyUniqueFailure(callable $callback): mixed
    {
        try {
            return $callback();
        } catch (QueryException $exception) {
            if ($this->isUniqueConstraintFailure($exception)) {
                throw ValidationException::withMessages([
                    'aset' => 'Kode aset, plat nomor, atau nomor seri sudah digunakan. Muat ulang halaman lalu coba lagi.',
                ]);
            }

            throw $exception;
        }
    }

    private function isUniqueConstraintFailure(QueryException $exception): bool
    {
        $sqlState = (string) ($exception->errorInfo[0] ?? '');
        $driverCode = (string) ($exception->errorInfo[1] ?? '');
        $message = strtolower($exception->getMessage());

        return in_array($sqlState, ['23000', '23505'], true)
            || in_array($driverCode, ['1062', '19'], true)
            || str_contains($message, 'unique constraint')
            || str_contains($message, 'duplicate entry');
    }

    public function maxReasonableYear(): int
    {
        return Carbon::now(config('app.timezone', 'Asia/Jakarta'))->year + 1;
    }
}
