<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdatepinjamanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'admin';
    }

    public function rules(): array
    {
        return [
            'anggota_id' => ['required', 'exists:anggota,id'],
            'jumlah_pinjaman' => ['required', 'integer', 'min:1', 'max:5000000'],
            'tenor_bulan' => ['required', 'integer', 'min:1', 'max:12'],
            'tanggal_pengajuan' => ['required', 'date'],
            'keterangan' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'anggota_id.required' => 'Anggota wajib dipilih.',
            'jumlah_pinjaman.required' => 'Jumlah pinjaman wajib diisi.',
            'jumlah_pinjaman.integer' => 'Jumlah pinjaman harus berupa Rupiah bulat.',
            'jumlah_pinjaman.max' => 'Jumlah pinjaman maksimal Rp5.000.000.',
            'tenor_bulan.required' => 'Tenor pinjaman wajib diisi.',
            'tenor_bulan.max' => 'Tenor pinjaman maksimal 12 bulan.',
            'tanggal_pengajuan.required' => 'Tanggal pengajuan wajib diisi.',
        ];
    }
}
