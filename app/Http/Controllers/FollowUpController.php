<?php

namespace App\Http\Controllers;

use App\Models\Student;
use Illuminate\Http\Request;

class FollowUpController extends Controller
{
    public function __invoke(Request $request)
    {
        $user = $request->user();
        $tab = in_array($request->query('tab'), ['today', 'overdue', 'upcoming', 'attention'], true) ? $request->query('tab') : 'today';

        $q = Student::visibleTo($user)->with(['status', 'assignedUser']);
        match ($tab) {
            'today' => $q->whereBetween('next_follow_up_at', [today(), today()->endOfDay()])->orderBy('next_follow_up_at'),
            'overdue' => $q->where('next_follow_up_at', '<', today())->orderBy('next_follow_up_at'),
            'upcoming' => $q->whereBetween('next_follow_up_at', [today()->addDay(), today()->addDays(7)->endOfDay()])->orderBy('next_follow_up_at'),
            // Open leads with no follow-up date, or untouched for 7+ days.
            'attention' => $q->whereHas('status', fn ($s) => $s->where('is_closed', false))
                ->where(fn ($w) => $w->whereNull('next_follow_up_at')
                    ->orWhere(fn ($w) => $w->whereNull('last_contacted_at')->where('created_at', '<', now()->subDays(2)))
                    ->orWhere('last_contacted_at', '<', now()->subDays(7)))
                ->orderBy('last_contacted_at'),
        };

        $counts = [
            'today' => Student::visibleTo($user)->whereBetween('next_follow_up_at', [today(), today()->endOfDay()])->count(),
            'overdue' => Student::visibleTo($user)->where('next_follow_up_at', '<', today())->count(),
            'upcoming' => Student::visibleTo($user)->whereBetween('next_follow_up_at', [today()->addDay(), today()->addDays(7)->endOfDay()])->count(),
        ];

        return view('follow-ups', [
            'tab' => $tab,
            'counts' => $counts,
            'students' => $q->paginate(config('crm.per_page'))->withQueryString(),
        ]);
    }
}
