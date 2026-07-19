<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreSewaMobilRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'admin';
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'nama_kegiatan' => $this->normalizeText($this->input('nama_kegiatan')),
            'lokasi_kegiatan' => $this->normalizeText($this->input('lokasi_kegiatan')),
            'keterangan' => $this->nullableText($this->input('keterangan')),
        ]);
    }

    public function rules(): array
    {
        return [
            'karyawan_id' => ['required', 'exists:karyawan,id'],
            'aset_koperasi_id' => ['required', 'exists:aset_koperasi,id'],
            'nama_kegiatan' => ['required', 'string', 'max:150'],
            'lokasi_kegiatan' => ['required', 'string', 'max:150'],
            'tanggal_mulai' => ['required', 'date'],
            'tanggal_selesai' => ['required', 'date', 'after_or_equal:tanggal_mulai'],
            'keterangan' => ['nullable', 'string', 'max:1000'],
            'idempotency_key' => ['nullable', 'string', 'max:191'],
            'kode_sewa' => ['prohibited'],
            'jumlah_hari' => ['prohibited'],
            'tarif_harian_snapshot' => ['prohibited'],
            'total_sewa' => ['prohibited'],
            'tarif_total' => ['prohibited'],
            'status' => ['prohibited'],
            'status_pembayaran' => ['prohibited'],
        ];
    }

    public function messages(): array
    {
        return [
            'aset_koperasi_id.required' => 'Mobil wajib dipilih.',
            'karyawan_id.required' => 'Karyawan penyewa wajib dipilih.',
            'nama_kegiatan.required' => 'Nama kegiatan wajib diisi.',
            'lokasi_kegiatan.required' => 'Lokasi kegiatan wajib diisi.',
            'tanggal_mulai.required' => 'Tanggal mulai wajib diisi.',
            'tanggal_selesai.required' => 'Tanggal selesai wajib diisi.',
            'tanggal_selesai.after_or_equal' => 'Tanggal selesai harus sama dengan atau setelah tanggal mulai.',
            '*.prohibited' => 'Kode, status, jumlah hari, tarif snapshot, dan total sewa dihitung oleh server.',
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
        $normalized = $this->normalizeText($value);

        return $normalized === '' ? null : $normalized;
    }
}
