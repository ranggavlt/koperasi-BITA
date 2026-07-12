<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreBebanOperasionalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'keuangan';
    }

    protected function prepareForValidation(): void
    {
        $details = collect($this->input('details', []))
            ->filter(fn ($row) => is_array($row)
                && ($row['akun_id'] ?? null) !== null
                && ($row['nominal'] ?? null) !== null
                && trim((string) ($row['keterangan'] ?? '')) !== '')
            ->map(fn (array $row): array => [
                'akun_id' => $row['akun_id'] ?? null,
                'aset_koperasi_id' => filled($row['aset_koperasi_id'] ?? null) ? $row['aset_koperasi_id'] : null,
                'keterangan' => $this->nullableText($row['keterangan'] ?? null),
                'nominal' => $row['nominal'] ?? null,
            ])
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
            'tanggal_beban' => ['required', 'date'],
            'keterangan' => ['nullable', 'string', 'max:1000'],
            'details' => ['required', 'array', 'min:1'],
            'details.*.akun_id' => ['required', 'exists:akun,id'],
            'details.*.aset_koperasi_id' => ['nullable', 'exists:aset_koperasi,id'],
            'details.*.keterangan' => ['required', 'string', 'max:1000'],
            'details.*.nominal' => ['required', 'integer', 'min:1'],
            'idempotency_key' => ['nullable', 'string', 'max:191'],
            'kode_beban' => ['prohibited'],
            'dompet_id' => ['prohibited'],
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
            'details.required' => 'Minimal satu detail Beban Operasional wajib diisi.',
            'details.min' => 'Minimal satu detail Beban Operasional wajib diisi.',
            'details.*.akun_id.required' => 'Akun Beban wajib dipilih pada setiap detail.',
            'details.*.keterangan.required' => 'Keterangan detail Beban wajib diisi.',
            'details.*.nominal.min' => 'Nominal detail Beban wajib lebih besar dari nol.',
            '*.prohibited' => 'Kode, status, dompet, total, dan data posting tidak boleh dikirim dari browser.',
        ];
    }

    private function nullableText(mixed $value): ?string
    {
        $normalized = trim((string) preg_replace('/\s+/', ' ', (string) $value));

        return $normalized === '' ? null : $normalized;
    }
}
