<?php

namespace App\Http\Requests;

use App\Models\PembayaranSewaMobil;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PaySewaMobilRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'keuangan';
    }

    public function rules(): array
    {
        return [
            'metode_pembayaran' => ['required', Rule::in([
                PembayaranSewaMobil::METODE_TUNAI,
                PembayaranSewaMobil::METODE_TRANSFER_BANK,
            ])],
            'dompet_id' => ['required', 'exists:dompet_koperasi,id'],
            'jumlah_bayar' => ['required', 'integer', 'min:1'],
            'paid_at' => ['nullable', 'date'],
        ];
    }
}
