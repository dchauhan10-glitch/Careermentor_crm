<?php

namespace App\Services;

use App\Models\DuplicateReview;
use App\Models\Enquiry;
use App\Models\LeadStatus;
use App\Models\Source;
use App\Models\Student;
use App\Support\MobileNumber;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * The single way a lead enters the CRM (website, import or manual entry).
 * Same mobile number = same student: a repeat becomes a new enquiry on the
 * existing record. Doubtful matches go to the Super Admin review list.
 */
class LeadIntakeService
{
    /**
     * @param  array{name:string, mobile:string, parent_mobile?:?string, email?:?string,
     *               course?:?string, exam?:?string, college?:?string, city_state?:?string,
     *               exam_rank?:?int, admission_year?:?int, source_page?:?string,
     *               collected_on?:?string}  $data
     * @param  array{channel:string, import_batch_id?:?int, website_connection_id?:?int, created_by?:?int}  $context
     * @return array{student: Student, enquiry: Enquiry, outcome: 'created'|'merged'}
     */
    public function intake(array $data, Source $source, array $context): array
    {
        $mobile = MobileNumber::normalize($data['mobile'] ?? null);
        if (! $mobile) {
            throw new InvalidArgumentException('Mobile number is not a valid 10-digit Indian mobile.');
        }
        $parent = MobileNumber::normalize($data['parent_mobile'] ?? null);
        $email = isset($data['email']) ? (mb_strtolower(trim($data['email'])) ?: null) : null;
        $name = trim(preg_replace('/\s+/', ' ', $data['name'] ?? ''));
        if ($name === '') {
            throw new InvalidArgumentException('Student name is required.');
        }

        return DB::transaction(function () use ($data, $source, $context, $mobile, $parent, $email, $name) {
            $student = Student::withTrashed()->where('mobile', $mobile)->lockForUpdate()->first();
            $outcome = $student ? 'merged' : 'created';

            if (! $student) {
                $student = Student::create([
                    'name' => $name,
                    'mobile' => $mobile,
                    'parent_mobile' => $parent,
                    'email' => $email,
                    'city_text' => $data['city_state'] ?? null,
                    'exam_rank' => $data['exam_rank'] ?? null,
                    'admission_year' => $data['admission_year'] ?? null,
                    'first_source_id' => $source->id,
                    'lead_status_id' => LeadStatus::newStatus()->id,
                    'priority' => 'warm',
                ]);
            } else {
                // Fill only what is missing; never overwrite what staff already have.
                $student->fill(array_filter([
                    'parent_mobile' => $student->parent_mobile ? null : $parent,
                    'email' => $student->email ? null : $email,
                    'city_text' => $student->city_text ? null : ($data['city_state'] ?? null),
                    'exam_rank' => $student->exam_rank ? null : ($data['exam_rank'] ?? null),
                    'admission_year' => $student->admission_year ? null : ($data['admission_year'] ?? null),
                ]));
                $student->enquiry_count++;
                $student->save();
            }

            $enquiry = Enquiry::create([
                'student_id' => $student->id,
                'source_id' => $source->id,
                'import_batch_id' => $context['import_batch_id'] ?? null,
                'website_connection_id' => $context['website_connection_id'] ?? null,
                'created_by' => $context['created_by'] ?? null,
                'channel' => $context['channel'],
                'source_page' => $data['source_page'] ?? null,
                'name' => $name,
                'email' => $email,
                'parent_mobile' => $parent,
                'course_text' => $data['course'] ?? null,
                'exam_text' => $data['exam'] ?? null,
                'college_text' => $data['college'] ?? null,
                'city_state_text' => $data['city_state'] ?? null,
                'exam_rank' => $data['exam_rank'] ?? null,
                'admission_year' => $data['admission_year'] ?? null,
                'consent_recorded' => true,
                'collected_on' => $data['collected_on'] ?? now()->toDateString(),
            ]);

            $this->flagDoubtfulCases($student, $enquiry, $outcome, $name, $parent, $email);

            return ['student' => $student, 'enquiry' => $enquiry, 'outcome' => $outcome];
        });
    }

    private function flagDoubtfulCases(Student $student, Enquiry $enquiry, string $outcome, string $name, ?string $parent, ?string $email): void
    {
        if ($outcome === 'merged') {
            if ($student->trashed()) {
                $this->review($student, null, $enquiry, 'New enquiry for a deleted student record');
            } elseif (! $this->namesLookAlike($student->name, $name)) {
                $this->review($student, null, $enquiry, 'Same mobile, different name: "'.$name.'"');
            }

            return;
        }

        // A new mobile, but the same email or parent mobile as someone else.
        $other = Student::where('id', '!=', $student->id)
            ->where(function ($q) use ($parent, $email) {
                $q->when($email, fn ($q) => $q->orWhere('email', $email))
                    ->when($parent, fn ($q) => $q->orWhere('mobile', $parent)->orWhere('parent_mobile', $parent));
            })
            ->when(! $email && ! $parent, fn ($q) => $q->whereRaw('1 = 0'))
            ->first();

        if ($other) {
            $this->review($other, $student, $enquiry, 'Different mobile, but same email or parent mobile');
        }
    }

    private function namesLookAlike(string $a, string $b): bool
    {
        $norm = fn ($s) => preg_replace('/[^a-z]/', '', mb_strtolower($s));
        $a = $norm($a);
        $b = $norm($b);
        if ($a === '' || $b === '' || str_contains($a, $b) || str_contains($b, $a)) {
            return true;
        }
        similar_text($a, $b, $pct);

        return $pct >= 70;
    }

    private function review(Student $existing, ?Student $candidate, Enquiry $enquiry, string $reason): void
    {
        DuplicateReview::create([
            'student_id' => $existing->id,
            'candidate_student_id' => $candidate?->id,
            'enquiry_id' => $enquiry->id,
            'reason' => $reason,
        ]);
    }
}
