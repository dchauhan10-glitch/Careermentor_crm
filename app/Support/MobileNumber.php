<?php

namespace App\Support;

/**
 * Turns any way of writing an Indian mobile number into one standard
 * 10-digit form, so the same student is always found by the same number.
 */
final class MobileNumber
{
    /** Returns the 10-digit number, or null when it is not a valid Indian mobile. */
    public static function normalize(?string $raw): ?string
    {
        if ($raw === null) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $raw);

        if (strlen($digits) === 12 && str_starts_with($digits, '91')) {
            $digits = substr($digits, 2);
        } elseif (strlen($digits) === 11 && str_starts_with($digits, '0')) {
            $digits = substr($digits, 1);
        }

        return preg_match('/^[6-9]\d{9}$/', $digits) ? $digits : null;
    }

    /** For logs and audit entries: never show the full number. */
    public static function mask(?string $mobile): string
    {
        return $mobile ? str_repeat('x', max(strlen($mobile) - 4, 0)).substr($mobile, -4) : '';
    }
}
