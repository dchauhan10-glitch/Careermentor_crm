<?php

namespace App\Policies;

use App\Models\Student;
use App\Models\User;

class StudentPolicy
{
    public function view(User $user, Student $student): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }
        if ($student->assigned_user_id === $user->id) {
            return true;
        }

        return $user->isTeamLead() && $user->ledTeam && $student->team_id === $user->ledTeam->id;
    }

    /** Status, priority, remarks, follow-ups and call entries. */
    public function work(User $user, Student $student): bool
    {
        return $this->view($user, $student);
    }

    public function create(User $user): bool
    {
        return $user->isSuperAdmin();
    }

    public function editDetails(User $user, Student $student): bool
    {
        return $user->isSuperAdmin() || ($user->isTeamLead() && $this->view($user, $student));
    }

    public function assign(User $user, Student $student): bool
    {
        return $user->isSuperAdmin() || ($user->isTeamLead() && $this->view($user, $student));
    }

    /** May this user hand a lead to $target? */
    public function assignTo(User $user, User $target): bool
    {
        if (! $target->is_active || $target->isSuperAdmin() && ! $user->isSuperAdmin()) {
            return false;
        }
        if ($user->isSuperAdmin()) {
            return true;
        }
        if (! $user->isTeamLead() || ! $user->ledTeam) {
            return false;
        }

        return $target->id === $user->id || $target->team_id === $user->ledTeam->id;
    }

    public function delete(User $user, Student $student): bool
    {
        return $user->isSuperAdmin();
    }
}
