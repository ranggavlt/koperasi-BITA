<?php

namespace App\Http\Requests;

use App\Models\Simpanan;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSimpananRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'admin';
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'jumlah' => $this->normalizeMoney($this->input('jumlah')),
            'keterangan' => $this->nullableText($this->input('keterangan')),
            'nomor_referensi' => $this->nullableText($this->input('nomor_referensi')),
            'idempotency_key' => $this->nullableText($this->input('idempotency_key')),
        ]);
    }

    public function rules(): array
    {
        return [
            'anggota_id' => ['required', 'exists:anggota,id'],
            'jenis_simpanan_id' => [
                'nullable',
                Rule::exists('jenis_simpanan', 'id')->where(fn ($query) => $query->where('aktif', true)),
            ],
            'dompet_id' => ['required', 'exists:dompet_koperasi,id'],
            'jenis_transaksi' => ['required', Rule::in([Simpanan::JENIS_SETORAN, Simpanan::JENIS_PENARIKAN])],
            'metode_pembayaran' => ['required', Rule::in([Simpanan::METODE_TUNAI, Simpanan::METODE_TRANSFER_BANK])],
            'jumlah' => ['required', 'integer', 'min:1'],
            'tanggal' => ['required', 'date'],
            'nomor_referensi' => ['nullable', 'string', 'max:80'],
            'keterangan' => ['nullable', 'string', 'max:1000'],
            'idempotency_key' => ['nullable', 'string', 'max:191'],
        ];
    }

    public function messages(): array
    {
        return [
            'anggota_id.required' => 'Anggota wajib dipilih.',
            'jenis_transaksi.required' => 'Jenis transaksi wajib dipilih.',
            'metode_pembayaran.required' => 'Metode pembayaran wajib dipilih.',
            'dompet_id.required' => 'Dompet Kas/Bank wajib dipilih.',
            'jumlah.required' => 'Nominal Simpanan Sukarela wajib diisi.',
            'jumlah.integer' => 'Nominal Simpanan Sukarela harus berupa Rupiah bulat.',
            'jumlah.min' => 'Nominal Simpanan Sukarela wajib lebih besar dari nol.',
            'tanggal.required' => 'Tanggal simpanan wajib diisi.',
        ];
    }

    public function attributes(): array
    {
        return [
            'jenis_simpanan_id' => 'Jenis Simpanan',
            'jenis_transaksi' => 'Jenis Transaksi',
            'metode_pembayaran' => 'Metode Pembayaran',
        ];
    }

    private function nullableText(mixed $value): ?string
    {
        $normalized = trim((string) preg_replace('/\s+/', ' ', (string) $value));

        return $normalized === '' ? null : $normalized;
    }

    private function normalizeMoney(mixed $value): int|string
    {
        if (is_int($value)) {
            return $value;
        }

        $string = trim((string) $value);

        if ($string === '') {
            return 0;
        }

        if (preg_match('/^(\d+)(\.(\d{1,2}))?$/', $string, $matches) === 1) {
            $fraction = $matches[3] ?? '';

            if ($fraction !== '' && (int) $fraction !== 0) {
                return $string;
            }

            return (int) $matches[1];
        }

        return (int) preg_replace('/[^\d]/', '', $string);
    }
}
