<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorepinjamanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'keuangan';
    }

    public function rules(): array
    {
        return [
            'anggota_id' => ['required', 'exists:anggota,id'],
            'dompet_id' => ['required', 'exists:dompet_koperasi,id'],
            'jumlah_pinjaman' => ['required', 'integer', 'min:1', 'max:5000000'],
            'tenor_bulan' => ['required', 'integer', 'min:1', 'max:12'],
            'tanggal_pinjaman' => ['required', 'date'],
            'bunga_persen' => ['nullable', Rule::in(['0', '0.0', '0.00'])],
            'keterangan' => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'anggota_id.required' => 'Anggota wajib dipilih.',
            'dompet_id.required' => 'Dompet sumber pencairan wajib dipilih.',
            'jumlah_pinjaman.required' => 'Jumlah pinjaman wajib diisi.',
            'jumlah_pinjaman.integer' => 'Jumlah pinjaman harus berupa Rupiah bulat.',
            'jumlah_pinjaman.max' => 'Jumlah pinjaman maksimal Rp5.000.000.',
            'tenor_bulan.required' => 'Tenor pinjaman wajib diisi.',
            'tenor_bulan.max' => 'Tenor pinjaman maksimal 12 bulan.',
            'tanggal_pinjaman.required' => 'Tanggal pinjaman wajib diisi.',
            'bunga_persen.in' => 'Bunga pinjaman KBSM selalu 0%.',
        ];
    }
}
