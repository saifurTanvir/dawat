<?php

namespace App\Support;

/**
 * F53: the choices on the shahidar form, value => Bangla label.
 * The app shows the same labels (resources/js/lib/shahidarApply.js).
 */
final class ShahidarApplicationOptions
{
    public const GENDER = ['male' => 'পুরুষ', 'female' => 'মহিলা', 'other' => 'বলতে চাই না'];

    public const JOB = [
        'none' => 'এখন কোনো কাজ নেই',
        'part_time' => 'খণ্ডকালীন কাজ করি',
        'full_time' => 'পূর্ণকালীন চাকরি করি',
        'student' => 'ছাত্র/ছাত্রী',
        'business' => 'নিজের ব্যবসা',
    ];

    public const VEHICLE = [
        'none' => 'কিছু নেই, হেঁটে করব',
        'bicycle' => 'সাইকেল',
        'ebike' => 'ইলেকট্রিক সাইকেল/বাইক',
        'motorbike' => 'মোটরসাইকেল',
        'scooter' => 'স্কুটি',
    ];

    /** Vehicles that need a driving licence. */
    public const NEEDS_LICENSE = ['motorbike', 'scooter'];

    public const HOURS = ['part_time' => 'খণ্ডকালীন (দিনে কয়েক ঘণ্টা)', 'full_time' => 'পূর্ণকালীন'];

    public const SLOTS = [
        'morning' => 'সকাল (৭টা–১১টা)',
        'lunch' => 'দুপুর (১১টা–৩টা)',
        'evening' => 'বিকেল (৩টা–৭টা)',
        'night' => 'রাত (৭টা–১১টা)',
    ];

    public const HEARD_FROM = [
        'facebook' => 'ফেসবুক',
        'friend' => 'পরিচিত কেউ',
        'cook' => 'দাওয়াতের গৃহিণী',
        'website' => 'ওয়েবসাইট',
        'other' => 'অন্যভাবে',
    ];

    public static function label(array $options, ?string $value): ?string
    {
        return $value === null ? null : ($options[$value] ?? $value);
    }

    public static function labels(array $options, ?array $values): string
    {
        return collect($values ?? [])->map(fn ($v) => $options[$v] ?? $v)->implode(', ');
    }
}
