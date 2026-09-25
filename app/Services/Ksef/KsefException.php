<?php

namespace App\Services\Ksef;

use RuntimeException;

class KsefException extends RuntimeException
{
    public function __construct(string $message, public readonly ?int $status = null)
    {
        parent::__construct($message);
    }
}
