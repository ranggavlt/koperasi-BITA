<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreKaryawanAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'keuangan';
    }

    public function rules(): array
    {
        return [
            'temporary_password' => ['required', 'string', 'min:8'],
        ];
    }

    public function messages(): array
    {
        return [
            'temporary_password.required' => 'Password sementara wajib diisi.',
            'temporary_password.min' => 'Password sementara minimal 8 karakter.',
        ];
    }
}
