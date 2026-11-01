<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\College;
use App\Models\Course;
use App\Models\Exam;
use App\Models\LeadStatus;
use App\Models\Source;
use App\Models\State;
use App\Models\Student;
use App\Models\User;
use App\Services\LeadIntakeService;
use App\Services\LeadWorkService;
use App\Support\MobileNumber;
use App\Support\Priority;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

class StudentController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $f = $request->validate([
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
        ]);

        $q = Student::visibleTo($user)->with(['status', 'assignedUser', 'course', 'firstSource']);

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
            ->when($f['follow_to'] ?? null, fn ($q, $v) => $q->where('next_follow_up_at', '<', \Carbon\Carbon::parse($v)->addDay()));

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

        $students = $q->orderByRaw('next_follow_up_at is null')->orderBy('next_follow_up_at')->orderByDesc('id')
            ->paginate(config('crm.per_page'))->withQueryString();

        return view('students.index', [
            'students' => $students,
            'filters' => $f,
            'lists' => $this->lists($user),
        ]);
    }

    public function show(Request $request, Student $student)
    {
        $this->authorize('view', $student);
        $student->load(['status', 'assignedUser', 'team', 'course', 'exam', 'college', 'state', 'firstSource',
            'enquiries.source', 'callEntries.user', 'callEntries.status', 'statusHistory.changedBy',
            'statusHistory.toStatus', 'assignmentHistory.toUser', 'assignmentHistory.assignedBy']);

        return view('students.show', [
            'student' => $student,
            'lists' => $this->lists($request->user()),
        ]);
    }

    public function create(Request $request)
    {
        $this->authorize('create', Student::class);

        return view('students.create', ['lists' => $this->lists($request->user())]);
    }

    public function store(Request $request, LeadIntakeService $intake)
    {
        $this->authorize('create', Student::class);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'mobile' => ['required', 'string', 'max:20'],
            'parent_mobile' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:150'],
            'course' => ['nullable', 'string', 'max:120'],
            'exam' => ['nullable', 'string', 'max:120'],
            'city_state' => ['nullable', 'string', 'max:120'],
            'admission_year' => ['nullable', 'integer', 'between:2020,2040'],
            'source_id' => ['required', 'exists:sources,id'],
        ]);

        try {
            $res = $intake->intake($data, Source::findOrFail($data['source_id']), ['channel' => 'manual', 'created_by' => $request->user()->id]);
        } catch (InvalidArgumentException $e) {
            return back()->withInput()->withErrors(['mobile' => $e->getMessage()]);
        }

        $msg = $res['outcome'] === 'merged'
            ? 'This mobile number already existed, so the enquiry was added to the existing student.'
            : 'Student added.';

        return redirect()->route('students.show', $res['student'])->with('status', $msg);
    }

    public function updateDetails(Request $request, Student $student)
    {
        $this->authorize('editDetails', $student);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'parent_mobile' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:150'],
            'state_id' => ['nullable', 'exists:states,id'],
            'city_text' => ['nullable', 'string', 'max:120'],
            'course_id' => ['nullable', 'exists:courses,id'],
            'college_id' => ['nullable', 'exists:colleges,id'],
            'exam_id' => ['nullable', 'exists:exams,id'],
            'exam_rank' => ['nullable', 'integer', 'min:1'],
            'admission_year' => ['nullable', 'integer', 'between:2020,2040'],
            'remarks' => ['nullable', 'string', 'max:2000'],
        ]);
        if (! empty($data['parent_mobile'])) {
            $data['parent_mobile'] = MobileNumber::normalize($data['parent_mobile'])
                ?? throw \Illuminate\Validation\ValidationException::withMessages(['parent_mobile' => 'Parent mobile is not a valid 10-digit number.']);
        }
        $student->fill($data);
        $changed = array_keys($student->getDirty());
        $student->save();
        AuditLog::record('student.details_updated', $student, ['fields' => $changed]);

        return back()->with('status', 'Details saved.');
    }

    public function logCall(Request $request, Student $student, LeadWorkService $work)
    {
        $this->authorize('work', $student);
        $data = $request->validate([
            'outcome' => ['required', 'string', 'max:255'],
            'advice' => ['nullable', 'string', 'max:2000'],
            'remarks' => ['nullable', 'string', 'max:2000'],
            'lead_status_id' => ['nullable', 'exists:lead_statuses,id'],
            'priority' => ['nullable', Rule::enum(Priority::class)],
            'next_follow_up_at' => ['nullable', 'date', 'after:now'],
            'corrects_call_entry_id' => ['nullable', Rule::exists('call_entries', 'id')->where('student_id', $student->id)],
        ], [
            'next_follow_up_at.after' => 'The next follow-up must be in the future.',
        ]);

        $work->logCall($student, $request->user(), $data);

        return redirect()->route('students.show', $student)->with('status', 'Call saved.');
    }

    public function assign(Request $request, Student $student, LeadWorkService $work)
    {
        $this->authorize('assign', $student);
        $to = $this->assignee($request);
        $work->assign($student, $request->user(), $to);

        return back()->with('status', $to ? "Assigned to {$to->name}." : 'Moved to Unassigned.');
    }

    public function bulkAssign(Request $request, LeadWorkService $work)
    {
        $request->validate(['student_ids' => ['required', 'array', 'max:200'], 'student_ids.*' => ['integer']]);
        $to = $this->assignee($request);
        $n = 0;
        foreach (Student::visibleTo($request->user())->whereIn('id', $request->input('student_ids'))->get() as $s) {
            if ($request->user()->can('assign', $s)) {
                $work->assign($s, $request->user(), $to);
                $n++;
            }
        }

        return back()->with('status', "$n lead(s) assigned".($to ? " to {$to->name}." : ' to Unassigned.'));
    }

    public function destroy(Request $request, Student $student)
    {
        $this->authorize('delete', $student);
        $student->delete();
        AuditLog::record('student.deleted', $student);

        return redirect()->route('students.index')->with('status', 'Student moved to deleted records. It can be restored.');
    }

    private function assignee(Request $request): ?User
    {
        $request->validate(['user_id' => ['nullable', 'exists:users,id']]);
        $to = $request->filled('user_id') ? User::findOrFail($request->input('user_id')) : null;

        if ($to === null) {
            abort_unless($request->user()->isSuperAdmin(), 403, 'Only the Super Admin can move leads to Unassigned.');
        } else {
            abort_unless($request->user()->can('assignTo', [Student::class, $to]), 403, 'You cannot assign leads to that person.');
        }

        return $to;
    }

    /** Drop-down contents, with the assignee list limited by role. */
    private function lists(User $user): array
    {
        $assignable = User::where('is_active', true)->whereIn('role', ['team_lead', 'counsellor'])
            ->when(! $user->isSuperAdmin(), fn ($q) => $q->where(fn ($w) => $w->where('id', $user->id)
                ->orWhere('team_id', $user->ledTeam?->id ?? 0)))
            ->orderBy('name')->get();

        return [
            'statuses' => LeadStatus::where('is_active', true)->orderBy('sort_order')->get(),
            'sources' => Source::orderBy('name')->get(),
            'courses' => Course::where('is_active', true)->orderBy('name')->get(),
            'exams' => Exam::where('is_active', true)->orderBy('name')->get(),
            'colleges' => College::where('is_active', true)->orderBy('name')->get(),
            'states' => State::orderBy('name')->get(),
            'assignable' => $user->isCounsellor() ? collect() : $assignable,
            'priorities' => Priority::cases(),
        ];
    }
}
