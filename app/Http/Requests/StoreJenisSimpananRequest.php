<?php

namespace App\Http\Requests;

use App\Models\JenisSimpanan;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreJenisSimpananRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'admin';
    }

    protected function prepareForValidation(): void
    {
        $kategori = (string) $this->input('kategori');

        $this->merge([
            'nama_jenis' => $this->nullableText($this->input('nama_jenis')),
            'kategori' => $kategori,
            'kode' => JenisSimpanan::kodeUntukKategori($kategori),
            'interval_bulan' => $this->input('interval_bulan'),
            'nominal_default' => $this->normalizeMoney($this->input('nominal_default')),
            'aktif' => (string) $this->input('aktif', '1'),
            'keterangan' => $this->nullableText($this->input('keterangan')),
            'alasan_perubahan' => $this->nullableText($this->input('alasan_perubahan')),
        ]);
    }

    public function rules(): array
    {
        return [
            'akun_id' => ['required', 'exists:akun,id'],
            'nama_jenis' => ['required', 'string', 'max:100'],
            'kategori' => ['required', Rule::in(array_keys(JenisSimpanan::KATEGORI))],
            'kode' => ['required', Rule::in(array_values(JenisSimpanan::KODE_BY_KATEGORI))],
            'interval_bulan' => ['nullable', 'integer', 'min:1', 'max:12'],
            'berlaku_mulai' => ['required', 'date'],
            'aktif' => ['required', Rule::in(['0', '1', 0, 1])],
            'nominal_default' => ['required', 'integer', 'min:0'],
            'keterangan' => ['nullable', 'string', 'max:1000'],
            'alasan_perubahan' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'akun_id.required' => 'Akun COA wajib dipilih.',
            'nama_jenis.required' => 'Nama Jenis Simpanan wajib diisi.',
            'kategori.required' => 'Kategori Jenis Simpanan wajib dipilih.',
            'kategori.in' => 'Kategori Jenis Simpanan tidak dikenal.',
            'interval_bulan.min' => 'Interval Simpanan Wajib wajib 1-12 bulan.',
            'interval_bulan.max' => 'Interval Simpanan Wajib wajib 1-12 bulan.',
            'berlaku_mulai.required' => 'Tanggal Berlaku Mulai wajib diisi.',
            'nominal_default.required' => 'Nominal default wajib diisi.',
        ];
    }

    private function nullableText(mixed $value): ?string
    {
        $normalized = trim((string) preg_replace('/\s+/', ' ', (string) $value));

        return $normalized === '' ? null : $normalized;
    }

    private function normalizeMoney(mixed $value): int
    {
        if (is_int($value)) {
            return $value;
        }

        $string = trim((string) $value);

        if ($string === '') {
            return 0;
        }

        if (preg_match('/^\d+(\.\d{1,2})?$/', $string) === 1) {
            return (int) explode('.', $string)[0];
        }

        return (int) preg_replace('/[^\d]/', '', $string);
    }
}
