<?php

namespace App\Http\Requests;

use App\Models\PembayaranSewaMobil;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PaySewaMobilRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'admin';
    }

    public function rules(): array
    {
        return [
            'metode_penerimaan' => ['required', Rule::in([
                PembayaranSewaMobil::METODE_TUNAI,
                PembayaranSewaMobil::METODE_TRANSFER_BANK,
            ])],
            'dompet_penerimaan_id' => ['required', 'exists:dompet_koperasi,id'],
            'jumlah_diterima' => ['required', 'integer', 'min:1'],
            'metode_pembayaran_vendor' => ['required', Rule::in([
                PembayaranSewaMobil::METODE_TUNAI,
                PembayaranSewaMobil::METODE_TRANSFER_BANK,
            ])],
            'dompet_vendor_id' => ['required', 'exists:dompet_koperasi,id'],
            'jumlah_bayar_vendor' => ['required', 'integer', 'min:1'],
            'paid_at' => ['nullable', 'date'],
        ];
    }
}
