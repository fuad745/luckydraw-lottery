<?php

declare(strict_types=1);

namespace App\Support;

final class Phone
{
    /** E.164-ish normalisation: keep digits, prefix '+'. Returns null if implausible. */
    public static function normalize(?string $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone);

        // A real MSISDN is ~8–15 digits; reject anything obviously bogus.
        if ($digits === null || strlen($digits) < 8 || strlen($digits) > 15) {
            return null;
        }

        return '+'.$digits;
    }
}
