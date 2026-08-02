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
            'vendor_nama' => $this->normalizeText($this->input('vendor_nama')),
            'vendor_kontak' => $this->normalizeText($this->input('vendor_kontak')),
            'vendor_alamat' => $this->nullableText($this->input('vendor_alamat')),
            'jenis_kendaraan' => $this->normalizeText($this->input('jenis_kendaraan')),
            'merek_kendaraan' => $this->normalizeText($this->input('merek_kendaraan')),
            'model_kendaraan' => $this->normalizeText($this->input('model_kendaraan')),
            'plat_nomor_snapshot' => $this->nullableText($this->input('plat_nomor_snapshot')),
            'warna_kendaraan' => $this->normalizeText($this->input('warna_kendaraan')),
            'keterangan_kendaraan' => $this->nullableText($this->input('keterangan_kendaraan')),
            'total_harga_vendor' => $this->normalizeMoney($this->input('total_harga_vendor')),
            'total_markup' => $this->normalizeMoney($this->input('total_markup')),
            'keterangan' => $this->nullableText($this->input('keterangan')),
        ]);
    }

    public function rules(): array
    {
        return [
            'karyawan_id' => ['required', 'exists:karyawan,id'],
            'nama_kegiatan' => ['required', 'string', 'max:150'],
            'lokasi_kegiatan' => ['required', 'string', 'max:150'],
            'tanggal_mulai' => ['required', 'date'],
            'tanggal_selesai' => ['required', 'date', 'after_or_equal:tanggal_mulai'],
            'vendor_nama' => ['required', 'string', 'max:150'],
            'vendor_kontak' => ['required', 'string', 'max:80'],
            'vendor_alamat' => ['required', 'string', 'max:1000'],
            'jenis_kendaraan' => ['required', 'string', 'max:80'],
            'merek_kendaraan' => ['required', 'string', 'max:100'],
            'model_kendaraan' => ['required', 'string', 'max:120'],
            'plat_nomor_snapshot' => ['nullable', 'string', 'max:30'],
            'tahun_kendaraan' => ['required', 'integer', 'min:1900', 'max:2100'],
            'warna_kendaraan' => ['required', 'string', 'max:80'],
            'keterangan_kendaraan' => ['nullable', 'string', 'max:1000'],
            'total_harga_vendor' => ['required', 'integer', 'min:1'],
            'total_markup' => ['required', 'integer', 'min:1'],
            'keterangan' => ['nullable', 'string', 'max:1000'],
            'idempotency_key' => ['nullable', 'string', 'max:191'],
            'aset_koperasi_id' => ['prohibited'],
            'kode_sewa' => ['prohibited'],
            'jumlah_hari' => ['prohibited'],
            'tarif_harian_snapshot' => ['prohibited'],
            'total_sewa' => ['prohibited'],
            'total_tagihan_perusahaan' => ['prohibited'],
            'tarif_total' => ['prohibited'],
            'status' => ['prohibited'],
            'status_pembayaran' => ['prohibited'],
        ];
    }

    public function messages(): array
    {
        return [
            'karyawan_id.required' => 'Karyawan penyewa wajib dipilih.',
            'nama_kegiatan.required' => 'Nama kegiatan wajib diisi.',
            'lokasi_kegiatan.required' => 'Lokasi kegiatan wajib diisi.',
            'tanggal_mulai.required' => 'Tanggal mulai wajib diisi.',
            'tanggal_selesai.required' => 'Tanggal selesai wajib diisi.',
            'tanggal_selesai.after_or_equal' => 'Tanggal selesai harus sama dengan atau setelah tanggal mulai.',
            'vendor_nama.required' => 'Nama vendor wajib diisi.',
            'vendor_kontak.required' => 'Kontak vendor wajib diisi.',
            'vendor_alamat.required' => 'Alamat vendor wajib diisi.',
            'jenis_kendaraan.required' => 'Jenis kendaraan wajib diisi.',
            'merek_kendaraan.required' => 'Merek kendaraan wajib diisi.',
            'model_kendaraan.required' => 'Model/tipe kendaraan wajib diisi.',
            'tahun_kendaraan.required' => 'Tahun kendaraan wajib diisi.',
            'warna_kendaraan.required' => 'Warna kendaraan wajib diisi.',
            'total_harga_vendor.required' => 'Total Biaya Vendor wajib diisi.',
            'total_harga_vendor.min' => 'Total Biaya Vendor wajib lebih dari nol.',
            'total_markup.required' => 'Margin Koperasi wajib diisi.',
            'total_markup.min' => 'Margin Koperasi wajib lebih dari nol.',
            '*.prohibited' => 'Kode, status, jumlah hari, total tagihan, dan field legacy dihitung oleh server.',
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

    private function normalizeMoney(mixed $value): int
    {
        if (is_int($value)) {
            return $value;
        }

        return (int) preg_replace('/[^\d]/', '', (string) $value);
    }
}
