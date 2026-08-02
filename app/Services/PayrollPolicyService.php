<?php

namespace App\Services;

use App\Models\Anggota;
use App\Models\KebijakanLimitPotongGaji;
use App\Models\PengaturanPayrollAnggota;
use App\Models\Perusahaan;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PayrollPolicyService
{
    public const DEFAULT_LIMIT = 1500000;

    public function ensureGeneralPolicy(?int $userId = null): KebijakanLimitPotongGaji
    {
        return KebijakanLimitPotongGaji::query()->firstOrCreate(
            ['idempotency_key' => 'payroll-policy:general:2026-01'],
            [
                'perusahaan_id' => null,
                'limit_nominal' => self::DEFAULT_LIMIT,
                'berlaku_mulai' => '2026-01-01',
                'berlaku_sampai' => null,
                'aktif' => true,
                'alasan' => 'Limit umum seluruh perusahaan sesuai keputusan bisnis final.',
                'created_by' => $userId,
            ]
        );
    }

    /** @return array{nominal:int,sumber:string,kredit_waserba_aktif:bool,perusahaan_id:?int,kode_perusahaan:?string,nama_perusahaan:?string} */
    public function resolveFor(Anggota $anggota, CarbonInterface|string $periode): array
    {
        $period = $this->period($periode);
        $anggota->loadMissing('karyawan.perusahaan');
        $company = $anggota->karyawan?->perusahaan;

        $setting = PengaturanPayrollAnggota::query()
            ->where('anggota_id', $anggota->id)
            ->whereDate('berlaku_mulai', '<=', $period->toDateString())
            ->latest('berlaku_mulai')
            ->latest('id')
            ->first();

        if ($setting?->limit_override_nominal !== null) {
            $nominal = (int) $setting->limit_override_nominal;
            $source = 'override_anggota';
        } else {
            $policy = $this->policyForCompany($company, $period);
            $nominal = $policy ? (int) $policy->limit_nominal : self::DEFAULT_LIMIT;
            $source = $policy?->perusahaan_id ? 'kebijakan_perusahaan' : 'limit_umum';
        }

        return [
            'nominal' => $nominal,
            'sumber' => $source,
            'kredit_waserba_aktif' => $setting?->kredit_waserba_aktif ?? true,
            'perusahaan_id' => $company?->id,
            'kode_perusahaan' => $company?->kode,
            'nama_perusahaan' => $company?->nama,
        ];
    }

    public function scheduleMemberSetting(
        Anggota $anggota,
        int|string|null $override,
        bool $creditEnabled,
        string $reason,
        int $userId,
        CarbonInterface|string|null $effectivePeriod = null
    ): PengaturanPayrollAnggota {
        $reason = trim($reason);
        if ($reason === '') {
            throw ValidationException::withMessages(['alasan' => 'Alasan perubahan kebijakan payroll wajib diisi.']);
        }

        $nominal = $override === null || $override === '' ? null : (int) $override;
        if ($nominal !== null && $nominal < 0) {
            throw ValidationException::withMessages(['limit_override_nominal' => 'Override limit tidak boleh negatif.']);
        }

        $effective = $effectivePeriod
            ? $this->period($effectivePeriod)
            : CarbonImmutable::now(config('app.timezone', 'Asia/Jakarta'))->addMonthNoOverflow()->startOfMonth();

        return DB::transaction(function () use ($anggota, $nominal, $creditEnabled, $reason, $userId, $effective): PengaturanPayrollAnggota {
            $locked = Anggota::query()->lockForUpdate()->findOrFail($anggota->id);
            if (PengaturanPayrollAnggota::query()->where('anggota_id', $locked->id)->whereDate('berlaku_mulai', $effective)->exists()) {
                throw ValidationException::withMessages(['berlaku_mulai' => 'Pengaturan Anggota untuk periode tersebut sudah tercatat.']);
            }

            return PengaturanPayrollAnggota::query()->create([
                'anggota_id' => $locked->id,
                'berlaku_mulai' => $effective->toDateString(),
                'limit_override_nominal' => $nominal,
                'kredit_waserba_aktif' => $creditEnabled,
                'alasan' => $reason,
                'created_by' => $userId,
                'idempotency_key' => 'payroll-setting:'.$locked->id.':'.$effective->format('Ym'),
            ]);
        });
    }

    public function scheduleResetToGeneral(Anggota $anggota, bool $creditEnabled, string $reason, int $userId): PengaturanPayrollAnggota
    {
        return $this->scheduleMemberSetting($anggota, null, $creditEnabled, $reason, $userId);
    }

    private function policyForCompany(?Perusahaan $company, CarbonImmutable $period): ?KebijakanLimitPotongGaji
    {
        $base = KebijakanLimitPotongGaji::query()
            ->where('aktif', true)
            ->whereDate('berlaku_mulai', '<=', $period)
            ->where(fn ($query) => $query->whereNull('berlaku_sampai')->orWhereDate('berlaku_sampai', '>=', $period));

        if ($company) {
            $companyPolicy = (clone $base)->where('perusahaan_id', $company->id)->latest('berlaku_mulai')->first();
            if ($companyPolicy) {
                return $companyPolicy;
            }
        }

        return (clone $base)->whereNull('perusahaan_id')->latest('berlaku_mulai')->first();
    }

    private function period(CarbonInterface|string $value): CarbonImmutable
    {
        return $value instanceof CarbonInterface
            ? CarbonImmutable::instance($value)->startOfMonth()
            : CarbonImmutable::parse($value, config('app.timezone', 'Asia/Jakarta'))->startOfMonth();
    }
}
