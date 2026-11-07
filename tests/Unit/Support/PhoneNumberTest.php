<?php

namespace Tests\Unit\Support;

use App\Support\PhoneNumber;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class PhoneNumberTest extends TestCase
{
    public static function validNumbers(): array
    {
        return [
            'local' => ['01712345678', '01712345678'],
            'with spaces' => ['017 1234 5678', '01712345678'],
            'with +88' => ['+8801712345678', '01712345678'],
            'with 88' => ['8801912345678', '01912345678'],
            'bangla digits' => ['০১৮১২৩৪৫৬৭৮', '01812345678'],
            'dashes' => ['0131-234-5678', '01312345678'],
        ];
    }

    #[DataProvider('validNumbers')]
    public function test_it_normalises_valid_numbers(string $input, string $expected): void
    {
        $this->assertSame($expected, PhoneNumber::normalize($input));
    }

    public static function invalidNumbers(): array
    {
        return [['0171234567'], ['01212345678'], ['02123456789'], ['hello'], [''], ['017123456789']];
    }

    #[DataProvider('invalidNumbers')]
    public function test_it_rejects_invalid_numbers(string $input): void
    {
        $this->assertNull(PhoneNumber::normalize($input));
    }
}
