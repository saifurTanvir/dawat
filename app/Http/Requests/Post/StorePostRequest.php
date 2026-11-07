<?php

namespace App\Http\Requests\Post;

use App\Enums\MealSlot;
use App\Enums\TagType;
use App\Models\Tag;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StorePostRequest extends FormRequest
{
    public const MAX_IMAGES = 4;

    public function authorize(): bool
    {
        return $this->user()?->can('publish-posts') ?? false;
    }

    public function rules(): array
    {
        return [
            // Step 1: photos
            'images' => ['required', 'array', 'min:1', 'max:'.self::MAX_IMAGES],
            'images.*' => ['file', 'mimes:jpg,jpeg,png,webp', 'max:12288'],
            // Step 2: food, price, plates, time
            'title' => ['required', 'string', 'min:2', 'max:100'],
            'price_taka' => ['required', 'integer', 'min:20', 'max:2000'],
            'plates_total' => ['required', 'integer', 'min:1', 'max:20'],
            'meal_slot' => ['required', Rule::enum(MealSlot::class)],
            'available_hours' => ['required', 'integer', 'min:1', 'max:6'],
            // Step 3: constraints
            'tag_ids' => ['nullable', 'array', 'max:8'],
            'tag_ids.*' => ['integer', 'distinct', Rule::exists('tags', 'id')->where('is_active', true)],
            // Step 4: the cook's own note
            'note' => ['nullable', 'string', 'max:300'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                $ids = (array) $this->input('tag_ids', []);
                if (! $ids || $validator->errors()->has('tag_ids.*')) {
                    return;
                }
                $types = Tag::whereIn('id', $ids)->pluck('type')->map(fn (TagType $t) => $t->value)->countBy();
                if (($types['spice'] ?? 0) > 1) {
                    $validator->errors()->add('tag_ids', 'ঝালের মাত্রা একটাই বেছে নিন।');
                }
                if (($types['oil'] ?? 0) > 1) {
                    $validator->errors()->add('tag_ids', 'তেলের মাত্রা একটাই বেছে নিন।');
                }
            },
        ];
    }

    public function messages(): array
    {
        return [
            'images.required' => 'অন্তত একটা ছবি দিন।',
            'images.min' => 'অন্তত একটা ছবি দিন।',
            'images.max' => 'সর্বোচ্চ :max টা ছবি দেওয়া যাবে।',
            'images.*.mimes' => 'শুধু JPG, PNG বা WebP ছবি দেওয়া যাবে।',
            'images.*.max' => 'একটা ছবি ১২ MB-এর বেশি হতে পারবে না।',
            'title.required' => 'খাবারের নাম লিখুন, যেমন "ভাত, মুরগির ঝোল, ডাল"।',
            'title.min' => 'খাবারের নাম লিখুন।',
            'price_taka.required' => 'এক প্লেটের দাম দিন।',
            'price_taka.min' => 'দাম কমপক্ষে ২০ টাকা।',
            'price_taka.max' => 'দাম ২০০০ টাকার বেশি হতে পারবে না।',
            'plates_total.required' => 'কয় প্লেট আছে দিন।',
            'plates_total.max' => 'একবারে সর্বোচ্চ ২০ প্লেট।',
            'meal_slot.required' => 'কোন বেলার খাবার বেছে নিন।',
            'available_hours.required' => 'কতক্ষণ পাওয়া যাবে বেছে নিন।',
            'available_hours.max' => 'সর্বোচ্চ ৬ ঘণ্টা পর্যন্ত পোস্ট চালু থাকে।',
            'tag_ids.*.exists' => 'একটা ট্যাগ আর চালু নেই। আবার বেছে নিন।',
            'note.max' => 'নোট ৩০০ অক্ষরের মধ্যে রাখুন।',
        ];
    }

    protected function failedAuthorization(): void
    {
        abort(403, 'অনুমোদন আর রান্নাঘর সেটআপ শেষ হলে তবেই পোস্ট করা যাবে।');
    }
}
