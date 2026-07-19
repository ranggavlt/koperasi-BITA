<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReverseBebanOperasionalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'admin';
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'alasan' => trim((string) preg_replace('/\s+/', ' ', (string) $this->input('alasan'))),
        ]);
    }

    public function rules(): array
    {
        return [
            'alasan' => ['required', 'string', 'min:5', 'max:1000'],
            'nominal' => ['prohibited'],
            'jumlah_refund' => ['prohibited'],
            'dompet_id' => ['prohibited'],
            'status' => ['prohibited'],
        ];
    }

    public function messages(): array
    {
        return [
            'alasan.required' => 'Alasan reversal wajib diisi.',
            'alasan.min' => 'Alasan reversal terlalu singkat.',
            '*.prohibited' => 'Nominal reversal penuh dihitung oleh server dan tidak boleh diubah dari browser.',
        ];
    }
}
