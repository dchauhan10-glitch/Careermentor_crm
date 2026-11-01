<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Team;
use App\Models\User;
use App\Services\LeadWorkService;
use App\Support\Role;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/** Super Admin: staff accounts and teams, with no coding needed. */
class UserController extends Controller
{
    public function index()
    {
        return view('admin.users', [
            'teams' => Team::with(['lead', 'members' => fn ($q) => $q->withTrashed()->orderBy('name')])->orderBy('name')->get(),
            'users' => User::withTrashed()->with('team')->orderBy('role')->orderBy('name')->get(),
            'roles' => Role::cases(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:150', 'unique:users,email'],
            'role' => ['required', Rule::in(['team_lead', 'counsellor'])],
            'team_id' => ['nullable', 'required_if:role,counsellor', 'exists:teams,id'],
            'password' => ['required', Password::defaults()],
        ]);
        $data['email'] = mb_strtolower($data['email']);
        $user = User::create($data);

        if ($user->isTeamLead()) {
            $team = Team::create(['name' => $request->input('team_name') ?: 'Team of '.$user->name, 'lead_user_id' => $user->id]);
            $user->update(['team_id' => $team->id]);
        }
        AuditLog::record('user.created', $user, ['role' => $user->role->value]);

        return back()->with('status', "{$user->name} added. Share the password with them privately.");
    }

    /** Move a Counsellor to another Team Lead's team. */
    public function moveTeam(Request $request, User $user)
    {
        abort_unless($user->isCounsellor(), 422, 'Only Counsellors can be moved between teams.');
        $data = $request->validate(['team_id' => ['required', 'exists:teams,id']]);
        $from = $user->team_id;
        $user->update(['team_id' => $data['team_id']]);
        // The counsellor's leads follow them to the new team.
        $user->assignedStudents()->update(['team_id' => $data['team_id']]);
        AuditLog::record('user.moved_team', $user, ['from_team_id' => $from, 'to_team_id' => (int) $data['team_id']]);

        return back()->with('status', "{$user->name} moved.");
    }

    public function toggleActive(Request $request, User $user)
    {
        abort_if($user->id === $request->user()->id, 422, 'You cannot disable your own account.');
        $user->update(['is_active' => ! $user->is_active]);
        AuditLog::record($user->is_active ? 'user.enabled' : 'user.disabled', $user);

        return back()->with('status', $user->name.($user->is_active ? ' enabled.' : ' disabled and logged out.'));
    }

    public function resetPassword(Request $request, User $user)
    {
        $data = $request->validate(['password' => ['required', Password::defaults()]]);
        $user->update(['password' => $data['password']]);
        AuditLog::record('user.password_reset', $user);

        return back()->with('status', "Password changed for {$user->name}.");
    }

    public function handOver(Request $request, User $user, LeadWorkService $work)
    {
        $data = $request->validate(['to_user_id' => ['required', 'exists:users,id', Rule::notIn([$user->id])]]);
        $to = User::findOrFail($data['to_user_id']);
        abort_if(! $to->is_active || $to->isSuperAdmin(), 422, 'Choose an active Team Lead or Counsellor.');
        $n = $work->handOver($user, $to, $request->user());

        return back()->with('status', "$n lead(s) handed over from {$user->name} to {$to->name}.");
    }
}
