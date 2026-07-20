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
            'metode_penerimaan' => ['required', Rule::in([
                PembayaranSewaPrinter::METODE_TUNAI,
                PembayaranSewaPrinter::METODE_TRANSFER_BANK,
            ])],
            'dompet_penerimaan_id' => ['required', 'exists:dompet_koperasi,id'],
            'metode_pembayaran_vendor' => ['required', Rule::in([
                PembayaranSewaPrinter::METODE_TUNAI,
                PembayaranSewaPrinter::METODE_TRANSFER_BANK,
            ])],
            'dompet_vendor_id' => ['required', 'exists:dompet_koperasi,id'],
            'jumlah_diterima' => ['required', 'integer', 'min:1'],
            'jumlah_bayar_vendor' => ['required', 'integer', 'min:1'],
            'paid_at' => ['nullable', 'date'],
            'metode_pembayaran' => ['prohibited'],
            'dompet_id' => ['prohibited'],
            'jumlah_bayar' => ['prohibited'],
            'status' => ['prohibited'],
            'status_pembayaran' => ['prohibited'],
            'jumlah_refund' => ['prohibited'],
        ];
    }
}
