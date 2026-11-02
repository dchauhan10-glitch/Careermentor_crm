<?php

namespace Tests\Unit;

use App\Support\MobileNumber;
use PHPUnit\Framework\TestCase;

class MobileNumberTest extends TestCase
{
    public function test_common_formats_become_ten_digits(): void
    {
        foreach (['9876543210', '+91 98765 43210', '09876543210', '91-9876-543-210'] as $raw) {
            $this->assertSame('9876543210', MobileNumber::normalize($raw), $raw);
        }
    }

    public function test_invalid_numbers_are_rejected(): void
    {
        foreach ([null, '', '12345', '5876543210', '98765432101'] as $raw) {
            $this->assertNull(MobileNumber::normalize($raw));
        }
    }

    public function test_mask_shows_only_last_four_digits(): void
    {
        $this->assertSame('xxxxxx3210', MobileNumber::mask('9876543210'));
        $this->assertSame('', MobileNumber::mask(null));
    }
}
