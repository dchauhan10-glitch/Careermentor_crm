<?php

namespace App\Support;

/**
 * Six-digit codes from an authenticator app (RFC 6238, 30-second steps),
 * used for the Super Admin's two-step login.
 */
final class Totp
{
    private const ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    public static function newSecret(): string
    {
        $secret = '';
        for ($i = 0; $i < 32; $i++) {
            $secret .= self::ALPHABET[random_int(0, 31)];
        }

        return $secret;
    }

    public static function code(string $secret, int $timeStep): string
    {
        $key = self::base32Decode($secret);
        $hash = hash_hmac('sha1', pack('J', $timeStep), $key, true);
        $offset = ord($hash[19]) & 0x0F;
        $value = ((ord($hash[$offset]) & 0x7F) << 24) | (ord($hash[$offset + 1]) << 16)
            | (ord($hash[$offset + 2]) << 8) | ord($hash[$offset + 3]);

        return str_pad((string) ($value % 1_000_000), 6, '0', STR_PAD_LEFT);
    }

    /** Accepts the current code and one step either side, to allow for clock drift. */
    public static function verify(string $secret, string $code, ?int $now = null): bool
    {
        $code = preg_replace('/\D/', '', $code);
        if (strlen($code) !== 6) {
            return false;
        }
        $step = intdiv($now ?? time(), 30);
        foreach ([-1, 0, 1] as $d) {
            if (hash_equals(self::code($secret, $step + $d), $code)) {
                return true;
            }
        }

        return false;
    }

    public static function uri(string $secret, string $account, string $issuer = 'CareerMentors CRM'): string
    {
        return 'otpauth://totp/'.rawurlencode($issuer.':'.$account)
            .'?secret='.$secret.'&issuer='.rawurlencode($issuer).'&digits=6&period=30';
    }

    private static function base32Decode(string $s): string
    {
        $bits = '';
        foreach (str_split(strtoupper(rtrim($s, '='))) as $ch) {
            $pos = strpos(self::ALPHABET, $ch);
            if ($pos !== false) {
                $bits .= str_pad(decbin($pos), 5, '0', STR_PAD_LEFT);
            }
        }
        $out = '';
        foreach (str_split($bits, 8) as $byte) {
            if (strlen($byte) === 8) {
                $out .= chr(bindec($byte));
            }
        }

        return $out;
    }
}
