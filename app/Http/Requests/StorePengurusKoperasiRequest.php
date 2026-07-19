<?php

namespace App\Http\Requests;

use App\Models\PengurusKoperasi;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePengurusKoperasiRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'admin';
    }

    public function rules(): array
    {
        return [
            'anggota_id' => ['required', 'exists:anggota,id'],
            'jabatan' => ['required', Rule::in(PengurusKoperasi::JABATAN)],
        ];
    }
}
