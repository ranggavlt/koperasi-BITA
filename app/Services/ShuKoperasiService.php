<?php

namespace App\Services;

use App\Models\PeriodeAkuntansi;
use App\Models\ShuKoperasi;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/** @deprecated Gunakan AnnualShuService sebagai satu-satunya mesin SHU runtime. */
class ShuKoperasiService
{
    public function __construct(private readonly AnnualShuService $annual) {}

    public function create(array $data, ?int $userId = null): ShuKoperasi
    {
        $period = PeriodeAkuntansi::query()->findOrFail((int) ($data['periode_akuntansi_id'] ?? 0));
        return $this->annual->applyPeriod($period, (int) $userId);
    }

    public function calculate(ShuKoperasi $shu, ?int $userId = null): ShuKoperasi
    {
        return $this->annual->calculate($shu, (int) $userId);
    }

    public function approve(ShuKoperasi $shu, string $reason, ?int $userId = null): ShuKoperasi
    {
        $this->annual->prepareApproval($shu, (int) $userId);
        return $this->annual->approve($shu->fresh(), (int) $userId);
    }

    public function post(ShuKoperasi $shu, ?int $userId = null): ShuKoperasi
    {
        if ($shu->status !== ShuKoperasi::STATUS_APPROVED) {
            throw ValidationException::withMessages(['shu' => 'Posting terpisah telah dihapus; persetujuan kanonik mem-posting alokasi secara atomik.']);
        }
        return $shu->fresh();
    }

    public function addTransaksi(ShuKoperasi $shu, array $data): never
    {
        throw new RuntimeException('Input transaksi SHU manual dinonaktifkan. Gunakan AnnualShuService dari periode akuntansi tertutup.');
    }
}
