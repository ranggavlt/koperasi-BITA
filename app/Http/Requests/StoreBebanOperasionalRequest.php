<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreBebanOperasionalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'admin';
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'keterangan' => $this->nullableText($this->input('keterangan')),
            'nomor_referensi' => $this->nullableText($this->input('nomor_referensi')),
            'nominal' => $this->normalizeMoney($this->input('nominal')),
        ]);
    }

    public function rules(): array
    {
        return [
            'tanggal_beban' => ['required', 'date'],
            'akun_id' => ['required', 'exists:akun,id'],
            'dompet_id' => ['required', 'exists:dompet_koperasi,id'],
            'nominal' => ['required', 'integer', 'min:1'],
            'nomor_referensi' => ['nullable', 'string', 'max:50'],
            'keterangan' => ['required', 'string', 'max:1000'],
            'idempotency_key' => ['nullable', 'string', 'max:191'],
            'kode_beban' => ['prohibited'],
            'metode_pembayaran' => ['prohibited'],
            'total_beban' => ['prohibited'],
            'status' => ['prohibited'],
            'posted_at' => ['prohibited'],
            'reversed_at' => ['prohibited'],
            'alasan_reversal' => ['prohibited'],
            'reversal_transaksi_id' => ['prohibited'],
        ];
    }

    public function messages(): array
    {
        return [
            'tanggal_beban.required' => 'Tanggal Beban Operasional wajib diisi.',
            'akun_id.required' => 'Akun Beban wajib dipilih.',
            'dompet_id.required' => 'Dompet Kas/Bank wajib dipilih.',
            'nominal.min' => 'Nominal Beban wajib lebih besar dari nol.',
            'keterangan.required' => 'Keterangan/Memo Beban wajib diisi.',
            'nomor_referensi.max' => 'Nomor referensi maksimal 50 karakter.',
            '*.prohibited' => 'Kode, status, metode pembayaran, total, dan data posting tidak boleh dikirim dari browser.',
        ];
    }

    private function nullableText(mixed $value): ?string
    {
        $normalized = trim((string) preg_replace('/\s+/', ' ', (string) $value));

        return $normalized === '' ? null : $normalized;
    }

    private function normalizeMoney(mixed $value): int
    {
        return (int) preg_replace('/[^\d]/', '', (string) $value);
    }
}
