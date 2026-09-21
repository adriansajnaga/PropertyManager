<?php

namespace Tests\Unit;

use App\Support\PhoneNumber;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class PhoneNumberTest extends TestCase
{
    /**
     * @return array<string, array{0: string, 1: ?string}>
     */
    public static function numbers(): array
    {
        return [
            'dziewięć cyfr ze spacjami' => ['600 100 200', '48600100200'],
            'z plusem i myślnikami' => ['+48 600-100-200', '48600100200'],
            'z zerami zamiast plusa' => ['0048600100200', '48600100200'],
            'numer niemiecki' => ['+49 151 23456789', '4915123456789'],
            'za krótki' => ['123', null],
            'pusty' => ['', null],
        ];
    }

    #[DataProvider('numbers')]
    public function test_it_normalizes_phone_numbers(string $input, ?string $expected): void
    {
        $this->assertSame($expected, PhoneNumber::normalize($input));
    }

    public function test_it_formats_polish_numbers_for_people(): void
    {
        $this->assertSame('+48 600 100 200', PhoneNumber::format('600100200'));
    }
}
