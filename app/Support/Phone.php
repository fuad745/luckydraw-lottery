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

        // Normalise Ethiopian local forms to international so a payout number is
        // never stored as e.g. "+0912…". 09…/07… (10 digits) and bare 9…/7…
        // (9 digits) become 251….
        if (strlen($digits) === 10 && $digits[0] === '0') {
            $digits = '251'.substr($digits, 1);
        } elseif (strlen($digits) === 9 && in_array($digits[0], ['9', '7'], true)) {
            $digits = '251'.$digits;
        }

        return '+'.$digits;
    }
}
