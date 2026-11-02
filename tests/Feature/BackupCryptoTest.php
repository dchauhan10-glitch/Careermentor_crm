<?php

namespace Tests\Feature;

use App\Backup\BackupCrypto;
use RuntimeException;
use Tests\TestCase;

class BackupCryptoTest extends TestCase
{
    private array $tmp = [];

    protected function tearDown(): void
    {
        foreach ($this->tmp as $f) {
            @unlink($f);
        }
        parent::tearDown();
    }

    private function file(string $contents = ''): string
    {
        $f = tempnam(sys_get_temp_dir(), 'bc');
        file_put_contents($f, $contents);

        return $this->tmp[] = $f;
    }

    private function roundTrip(string $data): string
    {
        $key = base64_decode(BackupCrypto::newKey());
        $in = $this->file($data);
        $enc = $this->file();
        $out = $this->file();
        BackupCrypto::encryptFile($in, $enc, $key);
        BackupCrypto::decryptFile($enc, $out, $key);

        return file_get_contents($out);
    }

    public function test_round_trip_empty_small_and_multi_chunk(): void
    {
        $this->assertSame('', $this->roundTrip(''));
        $this->assertSame('hello', $this->roundTrip('hello'));
        $big = random_bytes(1048576 * 2 + 123);
        $this->assertSame($big, $this->roundTrip($big));
        $exact = random_bytes(1048576);
        $this->assertSame($exact, $this->roundTrip($exact));
    }

    public function test_tampered_or_truncated_file_is_rejected(): void
    {
        $key = base64_decode(BackupCrypto::newKey());
        $in = $this->file(random_bytes(1048576 + 500));
        $enc = $this->file();
        BackupCrypto::encryptFile($in, $enc, $key);
        $bytes = file_get_contents($enc);

        $flipped = $bytes;
        $flipped[100] = chr(ord($flipped[100]) ^ 1);
        $cut = substr($bytes, 0, 7 + 8 + 5 + 1048576 + 16); // only the first chunk
        foreach (['flipped' => $flipped, 'cut' => $cut] as $label => $bad) {
            $f = $this->file($bad);
            try {
                BackupCrypto::decryptFile($f, $this->file(), $key);
                $this->fail("$label file was accepted");
            } catch (RuntimeException) {
                $this->addToAssertionCount(1);
            }
        }
    }
}
