<?php

namespace App\Http\Requests;

use App\Models\KonfigurasiManasukaRutin;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAnggotaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'admin';
    }

    public function rules(): array
    {
        return [
            'tanggal_bergabung' => ['required', 'date', 'before_or_equal:today'],
            'alamat' => ['required', 'string', 'max:2000'],
            'plafon_pinjaman' => ['required', 'numeric', 'min:0', 'max:5000000'],
            'manasuka_rutin_status' => ['sometimes', 'nullable', Rule::in(KonfigurasiManasukaRutin::statuses())],
            'manasuka_rutin_nominal' => ['required_with:manasuka_rutin_status', 'nullable', 'numeric', 'min:0'],
            'manasuka_rutin_alasan' => ['required_with:manasuka_rutin_status', 'nullable', 'string', 'min:5', 'max:1000'],
            'manasuka_rutin_idempotency_key' => ['required_with:manasuka_rutin_status', 'nullable', 'string', 'max:191'],
        ];
    }
}
