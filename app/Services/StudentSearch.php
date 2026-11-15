<?php

namespace App\Services;

use App\Models\Student;
use App\Models\User;
use App\Support\MobileNumber;
use App\Support\Priority;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;

/** Search and filters shared by the student list and the export. */
class StudentSearch
{
    /** Quick filter buttons on the Leads list: key => label. */
    public const QUICK = [
        'interested' => 'Interested',
        'not_interested' => 'Not Interested',
        'not_called' => 'Not Called Yet',
        'follow_up_due' => 'Follow-up Due',
        'converted' => 'Converted',
    ];

    public static function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'integer'],
            'priority' => ['nullable', Rule::enum(Priority::class)],
            'source' => ['nullable', 'integer'],
            'course' => ['nullable', 'integer'],
            'exam' => ['nullable', 'integer'],
            'college' => ['nullable', 'integer'],
            'state' => ['nullable', 'integer'],
            'city' => ['nullable', 'string', 'max:60'],
            'year' => ['nullable', 'integer'],
            'assigned' => ['nullable', 'string', 'max:20'], // user id | unassigned
            'follow_up' => ['nullable', Rule::in(['today', 'overdue', 'upcoming', 'none'])],
            'follow_from' => ['nullable', 'date'],
            'follow_to' => ['nullable', 'date'],
            'quick' => ['nullable', Rule::in(array_keys(self::QUICK))],
            'sort' => ['nullable', Rule::in(['name', 'status', 'follow', 'added', 'assigned'])],
            'dir' => ['nullable', Rule::in(['asc', 'desc'])],
        ];
    }

    public static function query(User $user, array $f): Builder
    {
        $q = Student::visibleTo($user);

        if ($term = trim($f['q'] ?? '')) {
            $mobile = MobileNumber::normalize($term);
            $digits = preg_replace('/\D/', '', $term);
            $q->where(function ($w) use ($term, $mobile, $digits) {
                if ($mobile) {
                    $w->where('mobile', $mobile)->orWhere('parent_mobile', $mobile);
                } elseif (strlen($digits) >= 4 && $digits === preg_replace('/\s/', '', $term)) {
                    $w->where('mobile', 'like', '%'.$digits.'%');
                } else {
                    $w->where('name', 'like', $term.'%')
                        ->orWhere('student_code', $term)
                        ->orWhere('email', mb_strtolower($term));
                }
            });
        }

        $q->when($f['status'] ?? null, fn ($q, $v) => $q->where('lead_status_id', $v))
            ->when($f['priority'] ?? null, fn ($q, $v) => $q->where('priority', $v))
            ->when($f['source'] ?? null, fn ($q, $v) => $q->whereHas('enquiries', fn ($e) => $e->where('source_id', $v)))
            ->when($f['course'] ?? null, fn ($q, $v) => $q->where('course_id', $v))
            ->when($f['exam'] ?? null, fn ($q, $v) => $q->where('exam_id', $v))
            ->when($f['college'] ?? null, fn ($q, $v) => $q->where('college_id', $v))
            ->when($f['state'] ?? null, fn ($q, $v) => $q->where('state_id', $v))
            ->when($f['city'] ?? null, fn ($q, $v) => $q->where('city_text', 'like', $v.'%'))
            ->when($f['year'] ?? null, fn ($q, $v) => $q->where('admission_year', $v))
            ->when($f['follow_from'] ?? null, fn ($q, $v) => $q->where('next_follow_up_at', '>=', $v))
            ->when($f['follow_to'] ?? null, fn ($q, $v) => $q->where('next_follow_up_at', '<', Carbon::parse($v)->addDay()));

        if (($f['assigned'] ?? null) === 'unassigned') {
            $q->whereNull('assigned_user_id');
        } elseif (ctype_digit($f['assigned'] ?? '')) {
            $q->where('assigned_user_id', (int) $f['assigned']);
        }

        match ($f['follow_up'] ?? null) {
            'today' => $q->whereBetween('next_follow_up_at', [today(), today()->endOfDay()]),
            'overdue' => $q->where('next_follow_up_at', '<', today()),
            'upcoming' => $q->where('next_follow_up_at', '>', today()->endOfDay()),
            'none' => $q->whereNull('next_follow_up_at'),
            default => null,
        };

        $byStatus = fn (array $names) => $q->whereHas('status', fn ($s) => $s->whereIn('name', $names));
        match ($f['quick'] ?? null) {
            'interested' => $byStatus(['Interested']),
            'not_interested' => $byStatus(['Not Interested']),
            'not_called' => $byStatus(\App\Support\StatusTone::NOT_CALLED),
            'converted' => $byStatus(['Converted']),
            // Follow-up date is today or already past.
            'follow_up_due' => $q->where('next_follow_up_at', '<=', today()->endOfDay()),
            default => null,
        };

        return $q;
    }
}
