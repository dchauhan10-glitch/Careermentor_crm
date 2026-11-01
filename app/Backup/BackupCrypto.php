<?php

namespace App\Backup;

use RuntimeException;

/**
 * Encrypts backup files with a key only the owner holds (libsodium
 * XChaCha20-Poly1305, streamed in 1 MB chunks so memory stays low).
 * Without the key the file cannot be read or changed unnoticed.
 */
final class BackupCrypto
{
    private const MAGIC = "CRMBK1\n";
    private const CHUNK = 1048576;

    public static function key(): string
    {
        $b64 = (string) config('backup.encryption_key');
        $key = base64_decode($b64, true);
        if ($key === false || strlen($key) !== SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_KEYBYTES) {
            throw new RuntimeException('BACKUP_ENCRYPTION_KEY is missing or invalid. Create one with: php artisan crm:backup-key');
        }

        return $key;
    }

    public static function newKey(): string
    {
        return base64_encode(sodium_crypto_secretstream_xchacha20poly1305_keygen());
    }

    public static function encryptFile(string $in, string $out, ?string $key = null): void
    {
        $key ??= self::key();
        [$state, $header] = sodium_crypto_secretstream_xchacha20poly1305_init_push($key);
        $r = fopen($in, 'rb');
        $w = fopen($out, 'wb');
        fwrite($w, self::MAGIC.$header);
        $chunk = fread($r, self::CHUNK);
        do {
            $next = fread($r, self::CHUNK);
            $tag = ($next === '' || $next === false) ? SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_FINAL : SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_MESSAGE;
            fwrite($w, sodium_crypto_secretstream_xchacha20poly1305_push($state, (string) $chunk, '', $tag));
            $chunk = $next;
        } while ($tag !== SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_FINAL);
        fclose($r);
        fclose($w);
    }

    public static function decryptFile(string $in, string $out, ?string $key = null): void
    {
        $key ??= self::key();
        $r = fopen($in, 'rb');
        if (fread($r, strlen(self::MAGIC)) !== self::MAGIC) {
            throw new RuntimeException('Not a CRM backup file.');
        }
        $header = fread($r, SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_HEADERBYTES);
        $state = sodium_crypto_secretstream_xchacha20poly1305_init_pull($header, $key);
        $w = fopen($out, 'wb');
        $abytes = SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_ABYTES;
        $tag = null;
        while (! feof($r)) {
            $c = fread($r, self::CHUNK + $abytes);
            if ($c === '' || $c === false) {
                break;
            }
            $res = sodium_crypto_secretstream_xchacha20poly1305_pull($state, $c);
            if ($res === false) {
                throw new RuntimeException('Backup cannot be decrypted: wrong key or damaged file.');
            }
            [$plain, $tag] = $res;
            fwrite($w, $plain);
        }
        fclose($r);
        fclose($w);
        if ($tag !== SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_FINAL) {
            throw new RuntimeException('Backup file is incomplete.');
        }
    }
}
