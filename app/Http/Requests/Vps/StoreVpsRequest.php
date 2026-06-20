<?php

namespace App\Http\Requests\Vps;

use Illuminate\Foundation\Http\FormRequest;

class StoreVpsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // auth:sanctum middleware enforces authentication
    }

    public function rules(): array
    {
        return [
            'product_id'     => ['required', 'integer', 'min:1'],
            'billing_cycle'  => ['required', 'string', 'in:monthly,quarterly,semiannually,annually'],
            'payment_method' => ['required', 'string'],
            'config_options' => ['nullable', 'array'],
        ];
    }
}
