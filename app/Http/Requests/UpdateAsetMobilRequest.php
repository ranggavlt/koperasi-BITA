<?php

namespace App\Http\Requests;

use App\Models\AsetKoperasi;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAsetMobilRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'keuangan';
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'plat_nomor' => $this->normalizeIdentity($this->input('plat_nomor')),
            'merek' => $this->normalizeText($this->input('merek')),
            'model' => $this->normalizeText($this->input('model')),
            'warna' => $this->normalizeText($this->input('warna')),
            'keterangan' => $this->nullableText($this->input('keterangan')),
            'tarif_sewa_harian' => $this->normalizeMoney($this->input('tarif_sewa_harian')),
        ]);
    }

    public function rules(): array
    {
        /** @var AsetKoperasi|null $aset */
        $aset = $this->route('aset');
        $detailId = $aset?->mobil?->id;

        return [
            'plat_nomor' => ['required', 'string', 'max:30', Rule::unique('aset_mobil', 'plat_nomor')->ignore($detailId)],
            'merek' => ['required', 'string', 'max:100'],
            'model' => ['required', 'string', 'max:100'],
            'tahun' => ['required', 'integer', 'min:1980', 'max:' . (now(config('app.timezone', 'Asia/Jakarta'))->year + 1)],
            'warna' => ['required', 'string', 'max:50'],
            'tarif_sewa_harian' => ['required', 'integer', 'min:1'],
            'keterangan' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'plat_nomor.required' => 'Plat nomor mobil wajib diisi.',
            'plat_nomor.unique' => 'Plat nomor mobil sudah digunakan.',
            'merek.required' => 'Merek mobil wajib diisi.',
            'model.required' => 'Model mobil wajib diisi.',
            'tahun.required' => 'Tahun mobil wajib diisi.',
            'tahun.min' => 'Tahun mobil tidak masuk akal.',
            'tahun.max' => 'Tahun mobil tidak boleh melewati tahun depan.',
            'warna.required' => 'Warna mobil wajib diisi.',
            'tarif_sewa_harian.required' => 'Tarif Sewa Harian wajib diisi.',
            'tarif_sewa_harian.min' => 'Tarif Sewa Harian wajib lebih besar dari nol.',
        ];
    }

    private function normalizeText(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return trim((string) preg_replace('/\s+/', ' ', (string) $value));
    }

    private function nullableText(mixed $value): ?string
    {
        $normalized = $this->normalizeText($value);

        return $normalized === '' ? null : $normalized;
    }

    private function normalizeIdentity(mixed $value): ?string
    {
        $normalized = $this->normalizeText($value);

        return $normalized === null ? null : strtoupper($normalized);
    }

    private function normalizeMoney(mixed $value): int
    {
        return (int) preg_replace('/[^\d]/', '', (string) $value);
    }
}
