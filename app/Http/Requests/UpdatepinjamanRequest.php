<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePinjamanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'admin';
    }

    public function rules(): array
    {
        return [
            'anggota_id' => ['required', 'integer', 'exists:anggota,id'],
            'tanggal_pengajuan' => ['required', 'date', 'before_or_equal:today'],
            'jumlah_pinjaman' => ['required', 'numeric', 'min:1', 'max:5000000'],
            'tenor_bulan' => ['required', 'integer', 'min:1', 'max:12'],
            'biaya_admin' => ['required', 'numeric', 'min:0'],
            'cara_bayar_admin' => ['required', 'in:tunai,potong_pinjaman'],
            'keterangan' => ['nullable', 'string', 'max:1000'],
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
