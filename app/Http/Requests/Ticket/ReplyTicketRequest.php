<?php

namespace App\Http\Requests\Ticket;

use Illuminate\Foundation\Http\FormRequest;

class ReplyTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // auth:sanctum middleware enforces authentication
    }

    public function rules(): array
    {
        return [
            'message' => ['required', 'string', 'min:2'],
        ];
    }
}
