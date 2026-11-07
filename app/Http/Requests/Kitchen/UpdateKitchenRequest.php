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
            'lng' => ['nullable', 'required_with:lat', 'numeric', 'between:'.Bangladesh::MIN_LNG.','.Bangladesh::MAX_LNG],
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
        ];
    }

    protected function failedAuthorization(): void
    {
        abort(404, 'আপনি এখনো রাঁধুনি হিসেবে নিবন্ধন করেননি।');
    }
}
