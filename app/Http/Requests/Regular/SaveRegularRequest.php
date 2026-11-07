<?php

namespace App\Http\Requests\Regular;

use App\Enums\MealSlot;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveRegularRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:60'],
            'area_id' => ['required', 'integer', Rule::exists('areas', 'id')->where('is_active', true)],
            'address' => ['required', 'string', 'min:5', 'max:255'],
            'note' => ['nullable', 'string', 'max:300'],
            'meal_slots' => ['required', 'array', 'min:1', 'max:4'],
            'meal_slots.*' => ['distinct', Rule::enum(MealSlot::class)],
            'plates' => ['required', 'integer', 'between:1,10'],
            'weekdays' => ['required', 'array', 'min:1', 'max:7'],
            'weekdays.*' => ['distinct', 'integer', 'between:0,6'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'নাম লিখুন।',
            'area_id.required' => 'এলাকা বেছে নিন।',
            'address.required' => 'ডেলিভারির ঠিকানা লিখুন।',
            'address.min' => 'ঠিকানাটা আরেকটু বিস্তারিত লিখুন।',
            'meal_slots.required' => 'অন্তত একটা বেলা বেছে নিন।',
            'weekdays.required' => 'অন্তত একটা দিন বেছে নিন।',
            'plates.between' => 'প্লেট ১ থেকে ১০টা।',
        ];
    }
}
