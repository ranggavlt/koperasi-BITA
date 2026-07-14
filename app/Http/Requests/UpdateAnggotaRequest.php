<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAnggotaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'keuangan';
    }

    public function rules(): array
    {
        return [
            'tanggal_bergabung' => ['required', 'date', 'before_or_equal:today'],
            'alamat' => ['required', 'string', 'max:2000'],
            'plafon_pinjaman' => ['required', 'numeric', 'min:0', 'max:5000000'],
        ];
    }
}
