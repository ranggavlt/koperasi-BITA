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
            ->filter(fn ($row) => is_array($row) && trim((string) ($row['jenis_model_printer'] ?? '')) !== '')
            ->map(fn (array $row): array => [
                'jenis_model_printer' => $this->normalizeText($row['jenis_model_printer'] ?? null),
                'spesifikasi_kebutuhan' => $this->nullableText($row['spesifikasi_kebutuhan'] ?? null),
                'kuantitas' => (int) ($row['kuantitas'] ?? 0),
                'harga_vendor_per_unit' => $this->normalizeMoney($row['harga_vendor_per_unit'] ?? 0),
            ])
            ->values()
            ->all();

        $this->merge([
            'details' => $details,
            'kebutuhan' => $this->nullableText($this->input('kebutuhan')),
            'vendor_nama' => $this->normalizeText($this->input('vendor_nama')),
            'vendor_kontak' => $this->normalizeText($this->input('vendor_kontak')),
            'vendor_alamat' => $this->nullableText($this->input('vendor_alamat')),
            'keterangan' => $this->nullableText($this->input('keterangan')),
        ]);
    }

    public function rules(): array
    {
        return [
            'karyawan_id' => ['required', 'exists:karyawan,id'],
            'mulai_tanggal' => ['required', 'date'],
            'selesai_tanggal' => ['required', 'date', 'after_or_equal:mulai_tanggal'],
            'kebutuhan' => ['nullable', 'string', 'max:1000'],
            'vendor_nama' => ['required', 'string', 'max:150'],
            'vendor_kontak' => ['required', 'string', 'max:80'],
            'vendor_alamat' => ['required', 'string', 'max:1000'],
            'details' => ['required', 'array', 'min:1'],
            'details.*.jenis_model_printer' => ['required', 'string', 'max:150'],
            'details.*.spesifikasi_kebutuhan' => ['nullable', 'string', 'max:1000'],
            'details.*.kuantitas' => ['required', 'integer', 'min:1'],
            'details.*.harga_vendor_per_unit' => ['required', 'integer', 'min:1'],
            'keterangan' => ['nullable', 'string', 'max:1000'],
            'idempotency_key' => ['nullable', 'string', 'max:191'],
            'kode_sewa' => ['prohibited'],
            'status' => ['prohibited'],
            'status_pembayaran' => ['prohibited'],
            'karyawan_pic_id' => ['prohibited'],
            'aset_koperasi_id' => ['prohibited'],
            'total_harga_vendor' => ['prohibited'],
            'total_harga_dasar' => ['prohibited'],
            'total_margin' => ['prohibited'],
            'total_tagihan_perusahaan' => ['prohibited'],
            'grand_total' => ['prohibited'],
            'margin_persen_snapshot' => ['prohibited'],
            'margin_nominal' => ['prohibited'],
            'total_harga' => ['prohibited'],
        ];
    }

    public function messages(): array
    {
        return [
            'karyawan_id.required' => 'Karyawan pemohon wajib dipilih.',
            'mulai_tanggal.required' => 'Tanggal mulai wajib diisi.',
            'selesai_tanggal.after_or_equal' => 'Tanggal selesai harus sama dengan atau setelah tanggal mulai.',
            'vendor_nama.required' => 'Nama vendor wajib diisi.',
            'vendor_kontak.required' => 'Kontak vendor wajib diisi.',
            'vendor_alamat.required' => 'Alamat vendor wajib diisi.',
            'details.required' => 'Minimal satu detail kebutuhan printer wajib diisi.',
            'details.min' => 'Minimal satu detail kebutuhan printer wajib diisi.',
            'details.*.jenis_model_printer.required' => 'Jenis/model printer wajib diisi.',
            'details.*.kuantitas.min' => 'Kuantitas printer wajib minimal 1.',
            'details.*.harga_vendor_per_unit.min' => 'Harga vendor per unit wajib lebih besar dari nol.',
            '*.prohibited' => 'Field status, kode, margin, total, atau aset printer lama tidak boleh dikirim dari browser.',
        ];
    }

    private function normalizeText(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return trim((string) preg_replace('/\s+/', ' ', (string) $value));
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
