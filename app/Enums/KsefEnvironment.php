<?php

namespace App\Enums;

/**
 * Środowiska KSeF 2.0. Adresy z dokumentacji Ministerstwa Finansów
 * (https://github.com/CIRFMF/ksef-api/blob/main/srodowiska.md).
 */
enum KsefEnvironment: string
{
    case Test = 'test';
    case Demo = 'demo';
    case Prod = 'prod';

    public function label(): string
    {
        return match ($this) {
            self::Test => 'Testowe (integracja)',
            self::Demo => 'Przedprodukcyjne (demo)',
            self::Prod => 'Produkcyjne — faktury o mocy prawnej',
        };
    }

    public function baseUrl(): string
    {
        return match ($this) {
            self::Test => 'https://api-test.ksef.mf.gov.pl/v2',
            self::Demo => 'https://api-demo.ksef.mf.gov.pl/v2',
            self::Prod => 'https://api.ksef.mf.gov.pl/v2',
        };
    }

    public function isProduction(): bool
    {
        return $this === self::Prod;
    }
}
