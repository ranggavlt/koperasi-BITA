<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreKaryawanAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'admin';
    }

    public function rules(): array
    {
        return [
            'temporary_password' => ['required', 'string', 'min:8'],
            'role' => ['required', 'string', 'in:admin,kasir,karyawan'],
        ];
    }

    public function messages(): array
    {
        return [
            'temporary_password.required' => 'Password sementara wajib diisi.',
            'temporary_password.min' => 'Password sementara minimal 8 karakter.',
            'role.required' => 'Role/Peran wajib dipilih.',
            'role.in' => 'Role tidak valid.',
        ];
    }
}
