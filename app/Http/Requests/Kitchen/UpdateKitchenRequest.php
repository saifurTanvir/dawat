<?php

namespace App\Http\Requests\Kitchen;

use App\Support\Geo\Bangladesh;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateKitchenRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->kitchen !== null;
    }

    public function rules(): array
    {
        return [
            'display_name' => ['sometimes', 'string', 'min:2', 'max:60'],
            'area_id' => ['sometimes', 'integer', Rule::exists('areas', 'id')->where('is_active', true)],
            'lat' => ['nullable', 'required_with:lng', 'numeric', 'between:'.Bangladesh::MIN_LAT.','.Bangladesh::MAX_LAT],
            // F5: which buttons buyers see, and whose number they reach
            'allow_call' => ['sometimes', 'boolean'],
            'allow_message' => ['sometimes', 'boolean'],
            'allow_comment' => ['sometimes', 'boolean'],
            // F29: "নিতে চাই" button on posts
            'allow_order' => ['sometimes', 'boolean'],
            // Another number must be confirmed first: POST /kitchen/contact.
            'contact_phone' => ['prohibited'],
            'contact_name' => ['prohibited'],
            'lng' => ['nullable', 'required_with:lat', 'numeric', 'between:'.Bangladesh::MIN_LNG.','.Bangladesh::MAX_LNG],
        ];
    }

    public function after(): array
    {
        return [
            function ($validator) {
                $kitchen = $this->user()->kitchen;
                $merged = [
                    'allow_call' => $this->boolean('allow_call', $kitchen->allow_call),
                    'allow_message' => $this->boolean('allow_message', $kitchen->allow_message),
                    'allow_comment' => $this->boolean('allow_comment', $kitchen->allow_comment),
                    'allow_order' => $this->boolean('allow_order', $kitchen->allow_order),
                ];
                if (! in_array(true, $merged, true)) {
                    $validator->errors()->add('allow_comment', 'অন্তত একটা উপায় চালু রাখুন, নইলে ক্রেতা যোগাযোগ করতে পারবেন না।');
                }
            },
        ];
    }

    public function messages(): array
    {
        return [
            'display_name.min' => 'নাম কমপক্ষে ২ অক্ষরের হতে হবে।',
            'area_id.exists' => 'এই এলাকাটি এখন চালু নেই।',
            'lat.between' => 'লোকেশনটি বাংলাদেশের ভেতরে নয়। ম্যাপে আবার পিন দিন।',
            'lng.between' => 'লোকেশনটি বাংলাদেশের ভেতরে নয়। ম্যাপে আবার পিন দিন।',
            'lat.required_with' => 'ম্যাপে বাসার জায়গায় পিন দিন।',
            'lng.required_with' => 'ম্যাপে বাসার জায়গায় পিন দিন।',
            'contact_phone.prohibited' => 'অন্য নম্বর যাচাই করে যোগ করতে হয়।',
            'contact_name.prohibited' => 'অন্য নম্বর যাচাই করে যোগ করতে হয়।',
        ];
    }

    protected function failedAuthorization(): void
    {
        abort(404, 'আপনি এখনো রাঁধুনি হিসেবে নিবন্ধন করেননি।');
    }
}
