<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreSewaPrinterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'keuangan';
    }

    protected function prepareForValidation(): void
    {
        $details = collect($this->input('details', []))
            ->filter(fn ($row) => is_array($row) && ($row['aset_koperasi_id'] ?? null) !== null && ($row['harga_dasar'] ?? null) !== null)
            ->values()
            ->all();

        $this->merge([
            'details' => $details,
            'keterangan' => $this->nullableText($this->input('keterangan')),
        ]);
    }

    public function rules(): array
    {
        return [
            'karyawan_pic_id' => ['required', 'exists:karyawan,id'],
            'mulai_tanggal' => ['required', 'date'],
            'selesai_tanggal' => ['required', 'date', 'after_or_equal:mulai_tanggal'],
            'details' => ['required', 'array', 'min:1'],
            'details.*.aset_koperasi_id' => ['required', 'exists:aset_koperasi,id'],
            'details.*.harga_dasar' => ['required', 'integer', 'min:1'],
            'keterangan' => ['nullable', 'string', 'max:1000'],
            'idempotency_key' => ['nullable', 'string', 'max:191'],
            'kode_sewa' => ['prohibited'],
            'status' => ['prohibited'],
            'status_pembayaran' => ['prohibited'],
            'total_harga_dasar' => ['prohibited'],
            'total_margin' => ['prohibited'],
            'grand_total' => ['prohibited'],
            'margin_persen_snapshot' => ['prohibited'],
            'margin_nominal' => ['prohibited'],
            'total_harga' => ['prohibited'],
        ];
    }

    public function messages(): array
    {
        return [
            'karyawan_pic_id.required' => 'PIC Karyawan wajib dipilih.',
            'mulai_tanggal.required' => 'Tanggal mulai wajib diisi.',
            'selesai_tanggal.after_or_equal' => 'Tanggal selesai harus sama dengan atau setelah tanggal mulai.',
            'details.required' => 'Minimal satu Printer wajib dipilih.',
            'details.min' => 'Minimal satu Printer wajib dipilih.',
            'details.*.harga_dasar.min' => 'Harga dasar setiap Printer wajib lebih besar dari nol.',
            '*.prohibited' => 'Field status, kode, margin, atau total tidak boleh dikirim dari browser.',
        ];
    }

    private function nullableText(mixed $value): ?string
    {
        $normalized = trim((string) preg_replace('/\s+/', ' ', (string) $value));

        return $normalized === '' ? null : $normalized;
    }
}
