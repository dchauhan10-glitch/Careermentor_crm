<?php

namespace App\Http\Controllers;

use App\Models\CallEntry;
use App\Models\DuplicateReview;
use App\Models\Student;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function __invoke(Request $request)
    {
        $user = $request->user();
        $base = fn () => Student::visibleTo($user);

        // One grouped query for the status counts, rather than one per tile.
        $byStatus = $base()->join('lead_statuses', 'lead_statuses.id', '=', 'students.lead_status_id')
            ->groupBy('lead_statuses.name', 'lead_statuses.sort_order')->orderBy('lead_statuses.sort_order')->pluck(DB::raw('count(*)'), 'lead_statuses.name');

        $tiles = [
            'Total leads' => $byStatus->sum(),
            'New' => $byStatus['New'] ?? 0,
            'Interested' => $byStatus['Interested'] ?? 0,
            'Not Interested' => $byStatus['Not Interested'] ?? 0,
            'Not called yet' => collect(\App\Support\StatusTone::NOT_CALLED)->sum(fn ($n) => $byStatus[$n] ?? 0),
            'Calls today' => CallEntry::whereIn('student_id', $base()->select('id'))->where('called_at', '>=', today())
                ->when($user->isCounsellor(), fn ($q) => $q->where('user_id', $user->id))->count(),
            'Converted' => $byStatus['Converted'] ?? 0,
            'Follow-ups today' => $base()->whereBetween('next_follow_up_at', [today(), today()->endOfDay()])->count(),
            'Overdue follow-ups' => $base()->where('next_follow_up_at', '<', today())->count(),
        ];

        $data = ['tiles' => $tiles, 'byStatus' => $byStatus];

        if ($user->isSuperAdmin()) {
            $data['unassigned'] = Student::whereNull('assigned_user_id')->count();
            $data['openDuplicates'] = DuplicateReview::where('status', 'open')->count();
            $data['bySource'] = DB::table('enquiries')->join('sources', 'sources.id', '=', 'enquiries.source_id')
                ->whereNull('enquiries.deleted_at')->groupBy('sources.name')
                ->orderByDesc(DB::raw('count(*)'))->pluck(DB::raw('count(*)'), 'sources.name');
        }

        if (! $user->isCounsellor()) {
            $people = User::where('is_active', true)->whereIn('role', ['team_lead', 'counsellor'])
                ->when($user->isTeamLead(), fn ($q) => $q->where(fn ($w) => $w->where('id', $user->id)->orWhere('team_id', $user->ledTeam?->id ?? 0)))
                ->with('team')->orderBy('name')->get();
            $ids = $people->pluck('id');
            $calls7 = CallEntry::whereIn('user_id', $ids)->where('called_at', '>=', now()->subDays(7))
                ->groupBy('user_id')->pluck(DB::raw('count(*)'), 'user_id');
            $assigned = Student::whereIn('assigned_user_id', $ids)->groupBy('assigned_user_id')
                ->pluck(DB::raw('count(*)'), 'assigned_user_id');
            $overdue = Student::whereIn('assigned_user_id', $ids)->where('next_follow_up_at', '<', today())
                ->groupBy('assigned_user_id')->pluck(DB::raw('count(*)'), 'assigned_user_id');
            $data['activity'] = $people->map(fn ($p) => [
                'user' => $p, 'assigned' => $assigned[$p->id] ?? 0,
                'calls7' => $calls7[$p->id] ?? 0, 'overdue' => $overdue[$p->id] ?? 0,
            ]);
        }

        $data['recentCalls'] = CallEntry::with(['student', 'user'])
            ->whereIn('student_id', $base()->select('id'))
            ->when($user->isCounsellor(), fn ($q) => $q->where('user_id', $user->id))
            ->orderByDesc('called_at')->limit(8)->get();

        $data['todayList'] = $base()->with('status')
            ->where('next_follow_up_at', '<', today()->endOfDay())
            ->orderBy('next_follow_up_at')->limit(10)->get();

        return view('dashboard', $data);
    }
}
