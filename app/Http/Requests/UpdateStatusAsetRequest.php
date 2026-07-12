<?php

namespace App\Http\Requests;

use App\Models\AsetKoperasi;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateStatusAsetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'keuangan';
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in(AsetKoperasi::statuses())],
        ];
    }

    public function messages(): array
    {
        return [
            'status.required' => 'Status aset wajib dipilih.',
            'status.in' => 'Status aset tidak valid.',
        ];
    }
}
