<?php

namespace App\Http\Requests\Auth;

use App\Support\PhoneNumber;
use Illuminate\Foundation\Http\FormRequest;

class VerifyOtpRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $code = strtr((string) $this->input('code'), ['০' => '0', '১' => '1', '২' => '2', '৩' => '3', '৪' => '4', '৫' => '5', '৬' => '6', '৭' => '7', '৮' => '8', '৯' => '9']);

        $this->merge([
            'phone' => PhoneNumber::normalize($this->input('phone')) ?? $this->input('phone'),
            'code' => preg_replace('/\D+/', '', $code),
        ]);
    }

    public function rules(): array
    {
        $length = (int) config('services.otp.length', 6);

        return [
            'phone' => ['required', 'string', 'regex:'.PhoneNumber::PATTERN],
            'code' => ['required', 'string', "size:{$length}"],
            'device_name' => ['nullable', 'string', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'phone.required' => 'মোবাইল নম্বর দিন।',
            'phone.regex' => 'সঠিক মোবাইল নম্বর দিন, যেমন 017XXXXXXXX।',
            'code.required' => 'SMS-এ পাওয়া কোডটি দিন।',
            'code.size' => 'কোডটি :size অঙ্কের।',
        ];
    }
}
