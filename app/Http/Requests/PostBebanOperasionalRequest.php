<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PostBebanOperasionalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'keuangan';
    }

    public function rules(): array
    {
        return [
            'dompet_id' => ['nullable', 'exists:dompet_koperasi,id'],
            'kode_beban' => ['prohibited'],
            'metode_pembayaran' => ['prohibited'],
            'total_beban' => ['prohibited'],
            'status' => ['prohibited'],
            'posted_at' => ['prohibited'],
            'reversed_at' => ['prohibited'],
        ];
    }

    public function messages(): array
    {
        return [
            'dompet_id.exists' => 'Dompet pembayaran tidak valid.',
            '*.prohibited' => 'Data posting dihitung oleh server dan tidak boleh dikirim dari browser.',
        ];
    }
}
