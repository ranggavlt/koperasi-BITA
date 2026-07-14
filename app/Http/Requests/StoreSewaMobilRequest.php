<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreSewaMobilRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'karyawan';
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
            'aset_koperasi_id' => ['required', 'exists:aset_koperasi,id'],
            'nama_kegiatan' => ['required', 'string', 'max:150'],
            'lokasi_kegiatan' => ['required', 'string', 'max:150'],
            'mulai_at' => ['required', 'date'],
            'selesai_at' => ['required', 'date', 'after:mulai_at'],
            'keterangan' => ['nullable', 'string', 'max:1000'],
            'idempotency_key' => ['nullable', 'string', 'max:191'],
        ];
    }

    public function messages(): array
    {
        return [
            'aset_koperasi_id.required' => 'Mobil wajib dipilih.',
            'nama_kegiatan.required' => 'Nama kegiatan wajib diisi.',
            'lokasi_kegiatan.required' => 'Lokasi kegiatan wajib diisi.',
            'mulai_at.required' => 'Waktu mulai wajib diisi.',
            'selesai_at.after' => 'Waktu selesai harus setelah waktu mulai.',
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
