<?php

namespace App\Services;

use App\Models\DanaSosialSumber;
use App\Models\KlaimDanaSosial;
use Illuminate\Validation\ValidationException;

/** @deprecated Gunakan SocialFundService sebagai satu-satunya service Dana Sosial runtime. */
class DanaSosialService
{
    public function __construct(private readonly SocialFundService $social) {}

    public function createSource(array $data, int $userId): never
    {
        throw ValidationException::withMessages(['sumber' => 'Sumber manual/donasi dinonaktifkan. Sumber aktif hanya alokasi SHU.']);
    }

    public function approveSource(DanaSosialSumber $source, int $userId, string $reason): never
    {
        throw ValidationException::withMessages(['sumber' => 'Sumber non-SHU lama hanya dapat dibaca.']);
    }

    public function reverseSource(DanaSosialSumber $source, string $reason, int $userId): never
    {
        throw ValidationException::withMessages(['sumber' => 'Sumber non-SHU lama hanya dapat dibaca.']);
    }

    public function createClaim(array $data, int $userId): KlaimDanaSosial
    {
        return $this->social->createClaim($data, $userId);
    }

    public function submit(KlaimDanaSosial $claim): KlaimDanaSosial
    {
        return $this->social->submitClaim($claim);
    }
}
