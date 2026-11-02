<?php

namespace App\Backup;

use RuntimeException;

/**
 * Encrypts backup files with a key only the owner holds.
 *
 * Uses OpenSSL AES-256-GCM (available on Hostinger, where the sodium
 * extension is not), streamed in 1 MB chunks so memory stays low.
 * Each chunk carries its own authentication tag, and the chunk number and
 * "last chunk" flag are authenticated too, so a file that has been changed,
 * reordered or cut short is rejected.
 *
 * File layout: MAGIC | file-id (8 bytes) | chunks...
 * Chunk layout: flag (1 byte: 0 = more, 1 = last) | length (4 bytes) | ciphertext | tag (16 bytes)
 */
final class BackupCrypto
{
    private const MAGIC = "CRMBK2\n";
    private const CIPHER = 'aes-256-gcm';
    private const CHUNK = 1048576;
    private const KEY_BYTES = 32;
    private const FILE_ID_BYTES = 8;
    private const TAG_BYTES = 16;

    public static function key(): string
    {
        $b64 = (string) config('backup.encryption_key');
        $key = base64_decode($b64, true);
        if ($key === false || strlen($key) !== self::KEY_BYTES) {
            throw new RuntimeException('BACKUP_ENCRYPTION_KEY is missing or invalid. Create one with: php artisan crm:backup-key');
        }

        return $key;
    }

    public static function newKey(): string
    {
        return base64_encode(random_bytes(self::KEY_BYTES));
    }

    public static function encryptFile(string $in, string $out, ?string $key = null): void
    {
        $key ??= self::key();
        $fileId = random_bytes(self::FILE_ID_BYTES);
        $r = fopen($in, 'rb');
        $w = fopen($out, 'wb');
        fwrite($w, self::MAGIC.$fileId);

        $n = 0;
        $chunk = (string) fread($r, self::CHUNK);
        do {
            $next = (string) fread($r, self::CHUNK);
            $last = $next === '';
            $flag = $last ? "\x01" : "\x00";
            $tag = '';
            $cipher = openssl_encrypt(
                $chunk, self::CIPHER, $key, OPENSSL_RAW_DATA,
                self::iv($fileId, $n), $tag, self::aad($fileId, $n, $flag), self::TAG_BYTES
            );
            if ($cipher === false) {
                throw new RuntimeException('Backup encryption failed.');
            }
            fwrite($w, $flag.pack('N', strlen($cipher)).$cipher.$tag);
            $chunk = $next;
            $n++;
        } while (! $last);

        fclose($r);
        fclose($w);
    }

    public static function decryptFile(string $in, string $out, ?string $key = null): void
    {
        $key ??= self::key();
        $r = fopen($in, 'rb');
        if (fread($r, strlen(self::MAGIC)) !== self::MAGIC) {
            fclose($r);
            throw new RuntimeException('Not a CRM backup file.');
        }
        $fileId = (string) fread($r, self::FILE_ID_BYTES);
        $w = fopen($out, 'wb');

        try {
            $n = 0;
            $sawLast = false;
            while (($head = fread($r, 5)) !== '' && $head !== false) {
                if ($sawLast || strlen($head) !== 5) {
                    throw new RuntimeException('Backup file is damaged.');
                }
                $flag = $head[0];
                $len = unpack('N', substr($head, 1))[1];
                if ($len > self::CHUNK || ($flag !== "\x00" && $flag !== "\x01")) {
                    throw new RuntimeException('Backup file is damaged.');
                }
                $cipher = $len > 0 ? (string) fread($r, $len) : '';
                $tag = (string) fread($r, self::TAG_BYTES);
                if (strlen($cipher) !== $len || strlen($tag) !== self::TAG_BYTES) {
                    throw new RuntimeException('Backup file is incomplete.');
                }
                $plain = openssl_decrypt(
                    $cipher, self::CIPHER, $key, OPENSSL_RAW_DATA,
                    self::iv($fileId, $n), $tag, self::aad($fileId, $n, $flag)
                );
                if ($plain === false) {
                    throw new RuntimeException('Backup cannot be decrypted: wrong key or damaged file.');
                }
                fwrite($w, $plain);
                $sawLast = $flag === "\x01";
                $n++;
            }
            if (! $sawLast) {
                throw new RuntimeException('Backup file is incomplete.');
            }
        } finally {
            fclose($r);
            fclose($w);
        }
    }

    private static function iv(string $fileId, int $n): string
    {
        return $fileId.pack('N', $n); // 12 bytes, unique per chunk and per file
    }

    private static function aad(string $fileId, int $n, string $flag): string
    {
        return self::MAGIC.$fileId.pack('N', $n).$flag;
    }
}
