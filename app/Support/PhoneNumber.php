<?php

namespace App\Support;

/**
 * Bangladeshi mobile numbers, normalised to the local 11-digit form 01XXXXXXXXX.
 */
final class PhoneNumber
{
    public const PATTERN = '/^01[3-9]\d{8}$/';

    /** Returns the normalised number, or null if it is not a valid BD mobile number. */
    public static function normalize(?string $input): ?string
    {
        if ($input === null) {
            return null;
        }

        // Accept Bangla digits too: ০১৭… is how many people type.
        $digits = strtr($input, ['০' => '0', '১' => '1', '২' => '2', '৩' => '3', '৪' => '4', '৫' => '5', '৬' => '6', '৭' => '7', '৮' => '8', '৯' => '9']);
        $digits = preg_replace('/\D+/', '', $digits);

        // +8801XXXXXXXXX / 8801XXXXXXXXX → 01XXXXXXXXX
        if (strlen($digits) === 13 && str_starts_with($digits, '88')) {
            $digits = substr($digits, 2);
        }

        return preg_match(self::PATTERN, $digits) ? $digits : null;
    }

    /** International form for SMS gateways and WhatsApp links: 8801XXXXXXXXX. */
    public static function international(string $normalized): string
    {
        return '88'.$normalized;
    }
}
