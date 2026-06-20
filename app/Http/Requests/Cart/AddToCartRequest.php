<?php

namespace App\Http\Requests\Cart;

use Illuminate\Foundation\Http\FormRequest;

class AddToCartRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Cart is available to guests and authenticated users
    }

    public function rules(): array
    {
        return [
            'product_id'    => ['required', 'string', 'max:50'],
            'name'          => ['required', 'string', 'max:255'],
            'type'          => ['required', 'string', 'max:50'],
            'billing_cycle' => ['required', 'string', 'in:monthly,quarterly,semiannually,annually,onetime'],
            'quantity'      => ['required', 'integer', 'min:1', 'max:10'],
            'unit_price'    => ['required', 'numeric', 'min:0'],
        ];
    }
}
