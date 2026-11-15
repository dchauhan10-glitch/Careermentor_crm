<?php

namespace App\Http\Controllers;

use App\Models\CallEntry;
use App\Models\Student;
use App\Models\User;
use App\Support\MobileNumber;
use Illuminate\Http\Request;

/** Read-only list of saved calls, limited to the students each person may see. */
class CallHistoryController extends Controller
{
    public function __invoke(Request $request)
    {
        $user = $request->user();
        $f = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'period' => ['nullable', 'in:today,7,30,all'],
            'by' => ['nullable', 'integer'],
        ]);
        $period = $f['period'] ?? '7';

        $q = CallEntry::query()
            ->whereIn('student_id', Student::visibleTo($user)->select('id'))
            ->with(['student:id,name,mobile,student_code', 'user:id,name', 'status:id,name'])
            ->when($period === 'today', fn ($q) => $q->where('called_at', '>=', today()))
            ->when(in_array($period, ['7', '30'], true), fn ($q) => $q->where('called_at', '>=', today()->subDays((int) $period - 1)))
            ->when($f['by'] ?? null, fn ($q, $by) => $q->where('user_id', $by))
            ->when(trim($f['q'] ?? ''), function ($q, $term) {
                $isNumber = preg_match('/^[\d\s+\-]+$/', $term) === 1;
                $digits = MobileNumber::normalize($term) ?? preg_replace('/\D+/', '', $term);
                $q->whereHas('student', fn ($s) => $isNumber
                    ? $s->where('mobile', 'like', '%'.$digits.'%')
                    : $s->where('name', 'like', '%'.addcslashes($term, '%_').'%'));
            })
            ->orderByDesc('called_at')->orderByDesc('id');

        $callers = $user->isCounsellor() ? collect() : User::whereIn('role', ['team_lead', 'counsellor', 'super_admin'])
            ->when($user->isTeamLead(), fn ($w) => $w->where(fn ($w) => $w->where('id', $user->id)->orWhere('team_id', $user->ledTeam?->id ?? 0)))
            ->orderBy('name')->get(['id', 'name']);

        return view('calls.index', [
            'calls' => $q->paginate(config('crm.per_page'))->withQueryString(),
            'filters' => $f + ['period' => $period],
            'callers' => $callers,
        ]);
    }
}
