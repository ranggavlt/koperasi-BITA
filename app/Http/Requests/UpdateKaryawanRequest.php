<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateKaryawanRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->role === 'admin';
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'nama' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('karyawan', 'email')->ignore($this->route('karyawan')),
            ],
            'telepon' => ['nullable', 'string', 'max:50'],
            'jabatan' => ['required', 'string', 'max:255'],
            'status_kerja' => ['required', Rule::in(['aktif', 'berhenti'])],
            'tanggal_berhenti' => ['nullable', 'required_if:status_kerja,berhenti', 'date'],
        ];
    }
}
