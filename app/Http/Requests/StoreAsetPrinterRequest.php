<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAsetPrinterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'admin';
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'nomor_seri' => $this->normalizeIdentity($this->input('nomor_seri')),
            'merek' => $this->normalizeText($this->input('merek')),
            'model' => $this->normalizeText($this->input('model')),
            'lokasi' => $this->normalizeText($this->input('lokasi')),
            'keterangan' => $this->nullableText($this->input('keterangan')),
        ]);
    }

    public function rules(): array
    {
        return [
            'nomor_seri' => ['required', 'string', 'max:100', Rule::unique('aset_printer', 'nomor_seri')],
            'merek' => ['required', 'string', 'max:100'],
            'model' => ['required', 'string', 'max:100'],
            'lokasi' => ['required', 'string', 'max:150'],
            'keterangan' => ['nullable', 'string', 'max:1000'],
            'vendor_id' => ['nullable', 'exists:vendor,id'],
            'harga_dasar_vendor' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'nomor_seri.required' => 'Nomor seri printer wajib diisi.',
            'nomor_seri.unique' => 'Nomor seri printer sudah digunakan.',
            'merek.required' => 'Merek printer wajib diisi.',
            'model.required' => 'Model printer wajib diisi.',
            'lokasi.required' => 'Lokasi printer wajib diisi.',
            'vendor_id.exists' => 'Vendor tidak ditemukan.',
            'harga_dasar_vendor.numeric' => 'Harga dasar harus berupa angka.',
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
}
