<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoresimpananRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'keuangan';
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'jumlah' => $this->normalizeMoney($this->input('jumlah')),
            'keterangan' => $this->nullableText($this->input('keterangan')),
            'idempotency_key' => $this->nullableText($this->input('idempotency_key')),
        ]);
    }

    public function rules(): array
    {
        return [
            'anggota_id' => ['required', 'exists:anggota,id'],
            'jenis_simpanan_id' => [
                'required',
                Rule::exists('jenis_simpanan', 'id')->where(fn ($query) => $query->where('aktif', true)),
            ],
            'dompet_id' => ['required', 'exists:dompet_koperasi,id'],
            'jumlah' => ['required', 'integer', 'min:1'],
            'tanggal' => ['required', 'date'],
            'keterangan' => ['nullable', 'string', 'max:1000'],
            'idempotency_key' => ['nullable', 'string', 'max:191'],
        ];
    }

    public function messages(): array
    {
        return [
            'anggota_id.required' => 'Anggota wajib dipilih.',
            'jenis_simpanan_id.required' => 'Jenis simpanan wajib dipilih.',
            'dompet_id.required' => 'Dompet penerimaan wajib dipilih.',
            'jumlah.required' => 'Jumlah simpanan wajib diisi.',
            'jumlah.min' => 'Jumlah simpanan wajib lebih besar dari nol.',
            'tanggal.required' => 'Tanggal simpanan wajib diisi.',
        ];
    }

    public function attributes(): array
    {
        return [
            'jenis_simpanan_id' => 'Jenis Simpanan',
        ];
    }

    private function nullableText(mixed $value): ?string
    {
        $normalized = trim((string) preg_replace('/\s+/', ' ', (string) $value));

        return $normalized === '' ? null : $normalized;
    }

    private function normalizeMoney(mixed $value): int
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
}
