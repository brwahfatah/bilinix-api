<?php

namespace App\Http\Requests\Domain;

use Illuminate\Foundation\Http\FormRequest;

class UpdateNameserversRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // auth:sanctum middleware enforces authentication
    }

    public function rules(): array
    {
        return [
            'nameservers'   => ['required', 'array', 'min:2', 'max:5'],
            'nameservers.*' => ['required', 'string', 'max:255'],
        ];
    }
}
