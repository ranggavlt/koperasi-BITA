<?php

namespace App\Http\Requests;

use App\Models\DompetKoperasi;
use App\Models\Simpanan;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAnggotaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'admin';
    }

    public function rules(): array
    {
        return [
            'karyawan_id' => [
                'required',
                Rule::exists('karyawan', 'id')->where(fn ($query) => $query->where('status_kerja', 'aktif')),
                Rule::unique('anggota', 'karyawan_id'),
            ],
            'tanggal_bergabung' => ['required', 'date', 'before_or_equal:today'],
            'alamat' => ['required', 'string', 'max:2000'],
            'plafon_pinjaman' => ['required', 'numeric', 'min:0', 'max:5000000'],
            'simpanan_wajib_metode_pembayaran' => ['nullable', Rule::in([
                Simpanan::METODE_POTONG_GAJI,
                Simpanan::METODE_TUNAI,
                Simpanan::METODE_TRANSFER_BANK,
            ])],
            'simpanan_wajib_dompet_id' => [
                'nullable',
                'required_if:simpanan_wajib_metode_pembayaran,' . Simpanan::METODE_TUNAI . ',' . Simpanan::METODE_TRANSFER_BANK,
                Rule::exists('dompet_koperasi', 'id')->where(function ($query): void {
                    $metode = (string) $this->input('simpanan_wajib_metode_pembayaran', Simpanan::METODE_POTONG_GAJI);

                    if ($metode === Simpanan::METODE_TUNAI) {
                        $query->where('jenis_dompet', DompetKoperasi::JENIS_KAS);
                    }

                    if ($metode === Simpanan::METODE_TRANSFER_BANK) {
                        $query->where('jenis_dompet', DompetKoperasi::JENIS_BANK);
                    }
                }),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'simpanan_wajib_dompet_id.required_if' => 'Dompet wajib dipilih untuk pembayaran Simpanan Wajib Tunai/Transfer Bank.',
            'simpanan_wajib_dompet_id.exists' => 'Dompet Simpanan Wajib tidak sesuai metode pembayaran yang dipilih.',
        ];
    }
}
