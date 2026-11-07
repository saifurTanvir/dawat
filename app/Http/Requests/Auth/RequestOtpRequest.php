<?php

namespace App\Http\Requests\Auth;

use App\Support\PhoneNumber;
use Illuminate\Foundation\Http\FormRequest;

class RequestOtpRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge(['phone' => PhoneNumber::normalize($this->input('phone')) ?? $this->input('phone')]);
    }

    public function rules(): array
    {
        return [
            'phone' => ['required', 'string', 'regex:'.PhoneNumber::PATTERN],
        ];
    }

    public function messages(): array
    {
        return [
            'phone.required' => 'মোবাইল নম্বর দিন।',
            'phone.regex' => 'সঠিক মোবাইল নম্বর দিন, যেমন 017XXXXXXXX।',
        ];
    }
}
