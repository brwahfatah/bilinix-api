<?php

namespace App\Http\Requests\Ticket;

use Illuminate\Foundation\Http\FormRequest;

class CreateTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // auth:sanctum middleware enforces authentication
    }

    public function rules(): array
    {
        return [
            'subject'       => ['required', 'string', 'max:255'],
            'department_id' => ['required', 'integer'],
            'priority'      => ['required', 'string', 'in:low,medium,high'],
            'message'       => ['required', 'string', 'min:10'],
        ];
    }
}
