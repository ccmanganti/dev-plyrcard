<?php

namespace App\Support;

class PhoneFormatter
{
    public static function normalize(?string $phone): ?string
    {
        if (blank($phone)) return null;
        $phone = trim($phone);
        $digits = preg_replace('/\D+/', '', $phone);
        if (strlen($digits) === 10) return sprintf('(%s) %s-%s', substr($digits, 0, 3), substr($digits, 3, 3), substr($digits, 6));
        if (strlen($digits) === 11 && str_starts_with($digits, '1')) return sprintf('+1 (%s) %s-%s', substr($digits, 1, 3), substr($digits, 4, 3), substr($digits, 7));
        return $phone;
    }
}