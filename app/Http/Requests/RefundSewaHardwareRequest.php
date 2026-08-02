<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RefundSewaHardwareRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'admin';
    }

    public function rules(): array
    {
        return [
            'alasan' => ['required', 'string', 'max:1000'],
            'jumlah_refund' => ['prohibited'],
            'jumlah_diterima' => ['prohibited'],
            'jumlah_bayar_vendor' => ['prohibited'],
            'status' => ['prohibited'],
            'status_pembayaran' => ['prohibited'],
        ];
    }

    public function messages(): array
    {
        return [
            'alasan.required' => 'Alasan refund wajib diisi.',
        ];
    }
}
