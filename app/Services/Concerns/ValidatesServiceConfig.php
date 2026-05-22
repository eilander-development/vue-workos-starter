<?php

namespace App\Services\Concerns;

use RuntimeException;

trait ValidatesServiceConfig
{
    protected function requiredConfigString(string $key, string $errorMessage): string
    {
        $value = config($key);

        if (! is_string($value) || trim($value) === '') {
            throw new RuntimeException($errorMessage);
        }

        return trim($value);
    }
}

