<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ApproveSewaMobilRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'keuangan';
    }

    public function rules(): array
    {
        return [
            'pengurus_penyetuju_id' => ['required', 'exists:pengurus_koperasi,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'pengurus_penyetuju_id.required' => 'Pengurus penyetuju wajib dipilih.',
        ];
    }
}
