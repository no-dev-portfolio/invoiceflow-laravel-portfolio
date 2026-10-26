<?php

namespace App\Support;

class FormValue
{
    public static function text(mixed $value): string
    {
        return is_string($value) || is_int($value) ? (string) $value : '';
    }

    public static function old(string $key, mixed $default = ''): string
    {
        return self::text(old($key, $default));
    }
}
