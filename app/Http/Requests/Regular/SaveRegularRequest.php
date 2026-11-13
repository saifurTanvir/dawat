<?php

namespace App\Http\Requests\Regular;

use App\Enums\MealSlot;
use App\Support\Geo\Bangladesh;
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
            // F30: highest price per plate, in taka; empty = no limit.
            'max_price_taka' => ['nullable', 'integer', 'between:20,2000'],
            'weekdays' => ['required', 'array', 'min:1', 'max:7'],
            'weekdays.*' => ['distinct', 'integer', 'between:0,6'],
            // F55: optional home pin, so the nearest free cook can be found. Admins only see it.
            'lat' => ['nullable', 'required_with:lng', 'numeric', 'between:'.Bangladesh::MIN_LAT.','.Bangladesh::MAX_LAT],
            'lng' => ['nullable', 'required_with:lat', 'numeric', 'between:'.Bangladesh::MIN_LNG.','.Bangladesh::MAX_LNG],
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
            'lat.between' => 'লোকেশনটি বাংলাদেশের ভেতরে নয়। ম্যাপে আবার পিন দিন।',
            'lng.between' => 'লোকেশনটি বাংলাদেশের ভেতরে নয়। ম্যাপে আবার পিন দিন।',
            'max_price_taka.between' => 'সর্বোচ্চ দাম ৳২০ থেকে ৳২০০০-এর মধ্যে দিন, অথবা খালি রাখুন।',
        ];
    }
}
