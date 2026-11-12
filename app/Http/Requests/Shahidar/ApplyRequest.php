<?php

namespace App\Http\Requests\Shahidar;

use App\Support\PhoneNumber;
use App\Support\ShahidarApplicationOptions as Options;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** F53: the public "become a shahidar" form. No sign-in needed. */
class ApplyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['phone' => PhoneNumber::normalize($this->input('phone')) ?? $this->input('phone')]);
        if (! in_array($this->input('vehicle'), Options::NEEDS_LICENSE, true)) {
            $this->merge(['has_license' => null]);
        }
    }

    public function rules(): array
    {
        $in = fn (array $options) => Rule::in(array_keys($options));

        return [
            'name' => ['required', 'string', 'min:2', 'max:60'],
            'phone' => ['required', 'string', 'regex:'.PhoneNumber::PATTERN],
            'gender' => ['required', $in(Options::GENDER)],
            'age' => ['nullable', 'integer', 'between:18,70'],
            'lives_in' => ['required', 'string', 'min:3', 'max:160'],
            'area_ids' => ['required', 'array', 'min:1', 'max:6'],
            'area_ids.*' => ['distinct', 'integer', Rule::exists('areas', 'id')->where('is_active', true)],
            'job' => ['required', $in(Options::JOB)],
            'job_note' => ['nullable', 'string', 'max:120'],
            'vehicle' => ['required', $in(Options::VEHICLE)],
            'has_license' => ['nullable', 'boolean', Rule::requiredIf(fn () => in_array($this->input('vehicle'), Options::NEEDS_LICENSE, true))],
            'has_smartphone' => ['required', 'boolean'],
            'has_nid' => ['required', 'boolean'],
            'hours' => ['required', $in(Options::HOURS)],
            'slots' => ['required', 'array', 'min:1', 'max:4'],
            'slots.*' => ['distinct', $in(Options::SLOTS)],
            'days_per_week' => ['required', 'integer', 'between:1,7'],
            'experience' => ['nullable', 'string', 'max:160'],
            'heard_from' => ['nullable', $in(Options::HEARD_FROM)],
            'note' => ['nullable', 'string', 'max:500'],
            'agree' => ['accepted'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'নাম লিখুন।',
            'name.min' => 'পুরো নাম লিখুন।',
            'phone.required' => 'মোবাইল নম্বর দিন।',
            'phone.regex' => 'সঠিক মোবাইল নম্বর দিন, যেমন ০১৭১২৩৪৫৬৭৮।',
            'gender.required' => 'একটা বেছে নিন।',
            'age.between' => 'বয়স ১৮ থেকে ৭০-এর মধ্যে হতে হবে।',
            'lives_in.required' => 'কোথায় থাকেন লিখুন।',
            'lives_in.min' => 'এলাকার নাম লিখুন, যেমন মধ্য বাড্ডা।',
            'area_ids.required' => 'কোন এলাকায় কাজ করতে চান, অন্তত একটা বেছে নিন।',
            'area_ids.max' => 'সর্বোচ্চ ৬টা এলাকা বেছে নিন।',
            'job.required' => 'এখন কী করেন বেছে নিন।',
            'vehicle.required' => 'যানবাহন বেছে নিন।',
            'has_license.required' => 'ড্রাইভিং লাইসেন্স আছে কি না বলুন।',
            'has_smartphone.required' => 'স্মার্টফোন আছে কি না বলুন।',
            'has_nid.required' => 'জাতীয় পরিচয়পত্র আছে কি না বলুন।',
            'hours.required' => 'কতক্ষণ কাজ করতে চান বেছে নিন।',
            'slots.required' => 'কোন সময়ে কাজ করতে পারবেন, অন্তত একটা বেছে নিন।',
            'days_per_week.required' => 'সপ্তাহে কত দিন বেছে নিন।',
            'agree.accepted' => 'দাওয়াত টিম আপনাকে ফোন করতে পারবে — এতে সম্মতি দিন।',
        ];
    }
}
