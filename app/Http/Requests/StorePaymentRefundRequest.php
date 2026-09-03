<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePaymentRefundRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasAnyRole(['Super Admin', 'Owner', 'Finance']) === true;
    }

    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'min:1'],
            'reason' => ['required', 'string', 'max:2000'],
            'provider_reference' => ['nullable', 'string', 'max:255'],
            'confirmed' => ['accepted'],
        ];
    }
}
