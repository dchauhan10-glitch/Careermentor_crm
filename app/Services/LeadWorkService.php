<?php

namespace App\Services;

use App\Models\AssignmentHistory;
use App\Models\AuditLog;
use App\Models\CallEntry;
use App\Models\LeadStatus;
use App\Models\StatusHistory;
use App\Models\Student;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/** Day-to-day changes staff make to a lead: calls, status, assignment. */
class LeadWorkService
{
    /** Saves a call entry and moves the lead's status and next follow-up along with it. */
    public function logCall(Student $student, User $by, array $data): CallEntry
    {
        return DB::transaction(function () use ($student, $by, $data) {
            $entry = CallEntry::create([
                'student_id' => $student->id,
                'user_id' => $by->id,
                'called_at' => $data['called_at'] ?? now(),
                'lead_status_id' => $data['lead_status_id'] ?? null,
                'outcome' => $data['outcome'],
                'advice' => $data['advice'] ?? null,
                'remarks' => $data['remarks'] ?? null,
                'next_follow_up_at' => $data['next_follow_up_at'] ?? null,
                'corrects_call_entry_id' => $data['corrects_call_entry_id'] ?? null,
            ]);

            if (! empty($data['lead_status_id']) || array_key_exists('priority', $data)) {
                $this->changeStatus($student, $by, $data['lead_status_id'] ?? null, $data['priority'] ?? null);
            }

            // A closed lead (Converted, Closed, Not Interested) needs no further follow-up.
            $student->next_follow_up_at = $student->status()->value('is_closed') ? null : ($data['next_follow_up_at'] ?? null);
            $student->last_contacted_at = $entry->called_at;
            $student->save();

            return $entry;
        });
    }

    public function changeStatus(Student $student, User $by, ?int $statusId, ?string $priority): void
    {
        $fromStatus = $student->lead_status_id;
        $fromPriority = $student->priority?->value;
        $toStatus = $statusId ?: $fromStatus;
        $toPriority = $priority ?: $fromPriority;

        if ($toStatus == $fromStatus && $toPriority === $fromPriority) {
            return;
        }

        $student->lead_status_id = $toStatus;
        $student->priority = $toPriority;
        if (LeadStatus::find($toStatus)?->is_closed) {
            $student->next_follow_up_at = null;
        }
        $student->save();

        StatusHistory::create([
            'student_id' => $student->id,
            'changed_by' => $by->id,
            'from_status_id' => $fromStatus,
            'to_status_id' => $toStatus,
            'from_priority' => $fromPriority,
            'to_priority' => $toPriority,
        ]);
    }

    /** Assigns a lead to a person; the lead joins that person's team. */
    public function assign(Student $student, User $by, ?User $to): void
    {
        if ($student->assigned_user_id === $to?->id) {
            return;
        }

        $teamId = $to ? ($to->isTeamLead() ? $to->ledTeam?->id : $to->team_id) : null;

        AssignmentHistory::create([
            'student_id' => $student->id,
            'assigned_by' => $by->id,
            'from_user_id' => $student->assigned_user_id,
            'to_user_id' => $to?->id,
            'to_team_id' => $teamId,
        ]);

        $student->assigned_user_id = $to?->id;
        $student->team_id = $teamId;
        $student->save();
    }

    /** Hands every open lead of a leaving staff member to someone else. */
    public function handOver(User $from, User $to, User $by): int
    {
        $count = 0;
        Student::where('assigned_user_id', $from->id)->chunkById(200, function ($students) use ($to, $by, &$count) {
            foreach ($students as $s) {
                $this->assign($s, $by, $to);
                $count++;
            }
        });
        AuditLog::record('leads.handover', $from, ['to_user_id' => $to->id, 'count' => $count]);

        return $count;
    }
}
