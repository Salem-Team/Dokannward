<?php

namespace App\Support;

/**
 * Light phone normalisation for guest-checkout identity matching.
 * Egyptian mobiles are stored as the shopper typed them; matching
 * compares the 10-digit local form (1XXXXXXXXX).
 */
class Phone
{
    public static function digits(?string $phone): string
    {
        return preg_replace('/\D+/', '', (string) $phone) ?? '';
    }

    /** 10-digit local mobile without leading zero, e.g. 1034212422. */
    public static function egyptianLocal(?string $phone): ?string
    {
        $digits = self::digits($phone);

        if (str_starts_with($digits, '20')) {
            $digits = substr($digits, 2);
        }

        if (str_starts_with($digits, '0')) {
            $digits = substr($digits, 1);
        }

        if (strlen($digits) === 10 && str_starts_with($digits, '1')) {
            return $digits;
        }

        return null;
    }
}
