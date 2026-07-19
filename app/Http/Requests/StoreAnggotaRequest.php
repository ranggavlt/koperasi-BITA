<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAnggotaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'admin';
    }

    public function rules(): array
    {
        return [
            'karyawan_id' => [
                'required',
                Rule::exists('karyawan', 'id')->where(fn ($query) => $query->where('status_kerja', 'aktif')),
                Rule::unique('anggota', 'karyawan_id'),
            ],
            'tanggal_bergabung' => ['required', 'date', 'before_or_equal:today'],
            'alamat' => ['required', 'string', 'max:2000'],
            'plafon_pinjaman' => ['required', 'numeric', 'min:0', 'max:5000000'],
        ];
    }
}
