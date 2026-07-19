<?php

namespace App\Http\Requests;

use App\Models\PembayaranSewaPrinter;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PaySewaPrinterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'admin';
    }

    public function rules(): array
    {
        return [
            'metode_pembayaran' => ['required', Rule::in([
                PembayaranSewaPrinter::METODE_TUNAI,
                PembayaranSewaPrinter::METODE_TRANSFER_BANK,
            ])],
            'dompet_id' => ['required', 'exists:dompet_koperasi,id'],
            'jumlah_bayar' => ['required', 'integer', 'min:1'],
            'paid_at' => ['nullable', 'date'],
            'status' => ['prohibited'],
            'status_pembayaran' => ['prohibited'],
            'jumlah_refund' => ['prohibited'],
        ];
    }
}
