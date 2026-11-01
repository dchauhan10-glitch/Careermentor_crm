<?php

namespace Tests\Feature;

use App\Models\DuplicateReview;
use App\Models\Source;
use App\Models\Student;
use App\Services\LeadIntakeService;
use App\Support\MobileNumber;
use InvalidArgumentException;

class LeadIntakeTest extends CrmTestCase
{
    public function test_mobile_numbers_are_standardized(): void
    {
        foreach (['+91 98765 43210', '09876543210', '919876543210', '98765-43210'] as $raw) {
            $this->assertSame('9876543210', MobileNumber::normalize($raw), $raw);
        }
        $this->assertNull(MobileNumber::normalize('12345'));
        $this->assertNull(MobileNumber::normalize('5876543210')); // Indian mobiles start 6-9
    }

    public function test_repeat_enquiry_joins_existing_student_and_keeps_both_sources(): void
    {
        $intake = app(LeadIntakeService::class);
        $a = $intake->intake(['name' => 'Asha Rao', 'mobile' => '9876543210'], Source::where('name', 'Collegioz website')->first(), ['channel' => 'website']);
        $b = $intake->intake(['name' => 'Asha Rao', 'mobile' => '+91 98765 43210', 'email' => 'asha@example.test'], Source::where('name', 'ugpgcolleges.com')->first(), ['channel' => 'website']);

        $this->assertSame('created', $a['outcome']);
        $this->assertSame('merged', $b['outcome']);
        $this->assertSame(1, Student::count());
        $s = Student::first();
        $this->assertSame(2, $s->enquiry_count);
        $this->assertSame('asha@example.test', $s->email); // missing field filled in
        $this->assertEqualsCanonicalizing(['Collegioz website', 'ugpgcolleges.com'], $s->enquiries->map->source->pluck('name')->all());
        $this->assertSame(0, DuplicateReview::count());
    }

    public function test_same_mobile_different_name_goes_to_review(): void
    {
        $this->student('9876543210', null, 'Asha Rao');
        $this->student('9876543210', null, 'Vikram Singh');
        $this->assertSame(1, Student::count());
        $this->assertSame(1, DuplicateReview::where('status', 'open')->count());
    }

    public function test_new_mobile_with_same_email_goes_to_review(): void
    {
        $intake = app(LeadIntakeService::class);
        $src = Source::first();
        $intake->intake(['name' => 'A', 'mobile' => '9876543210', 'email' => 'same@example.test'], $src, ['channel' => 'website']);
        $intake->intake(['name' => 'A', 'mobile' => '9123456780', 'email' => 'same@example.test'], $src, ['channel' => 'website']);
        $this->assertSame(2, Student::count());
        $this->assertSame(1, DuplicateReview::count());
    }

    public function test_invalid_mobile_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        app(LeadIntakeService::class)->intake(['name' => 'A', 'mobile' => '123'], Source::first(), ['channel' => 'website']);
    }

    public function test_students_get_a_readable_id(): void
    {
        $this->assertMatchesRegularExpression('/^CM-\d{6}$/', $this->student()->student_code);
    }
}
