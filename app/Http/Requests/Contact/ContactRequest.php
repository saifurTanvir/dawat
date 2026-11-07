<?php

namespace App\Http\Requests\Contact;

use Illuminate\Foundation\Http\FormRequest;

class ContactRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'type' => ['required', 'in:call,message,share'],
            'qr' => ['nullable', 'string', 'max:32'],
        ];
    }
}
