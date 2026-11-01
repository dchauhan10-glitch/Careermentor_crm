<?php

namespace Tests\Feature;

use App\Models\Student;

class PermissionTest extends CrmTestCase
{
    public function test_counsellor_sees_only_own_leads(): void
    {
        $mine = $this->student('9000000001', $this->c1, 'Mine Student');
        $colleague = $this->student('9000000002', $this->c1b, 'Colleague Student');
        $other = $this->student('9000000003', $this->c2, 'Other Team');

        $this->actingAs($this->c1)->get(route('students.show', $mine))->assertOk();
        $this->actingAs($this->c1)->get(route('students.show', $colleague))->assertForbidden();
        $this->actingAs($this->c1)->get(route('students.show', $other))->assertForbidden();
        $this->actingAs($this->c1)->get(route('students.index'))->assertSee('Mine Student')
            ->assertDontSee('Colleague Student')->assertDontSee('Other Team');
        $this->actingAs($this->c1)->post(route('students.calls.store', $colleague), ['outcome' => 'x'])->assertForbidden();
    }

    public function test_team_lead_sees_own_team_only(): void
    {
        $team1 = $this->student('9000000001', $this->c1, 'Team One Lead');
        $own = $this->student('9000000004', $this->lead1, 'Leads Own');
        $team2 = $this->student('9000000003', $this->c2, 'Team Two Lead');

        $this->actingAs($this->lead1)->get(route('students.show', $team1))->assertOk();
        $this->actingAs($this->lead1)->get(route('students.show', $own))->assertOk();
        $this->actingAs($this->lead1)->get(route('students.show', $team2))->assertForbidden();
        $this->actingAs($this->lead1)->get(route('students.index'))->assertSee('Team One Lead')
            ->assertSee('Leads Own')->assertDontSee('Team Two Lead');
    }

    public function test_team_lead_assigns_only_within_team(): void
    {
        $s = $this->student('9000000001', $this->c1);
        $this->actingAs($this->lead1)->post(route('students.assign', $s), ['user_id' => $this->c1b->id])->assertRedirect();
        $this->assertSame($this->c1b->id, $s->fresh()->assigned_user_id);

        $this->actingAs($this->lead1)->post(route('students.assign', $s), ['user_id' => $this->c2->id])->assertForbidden();
        $this->assertSame($this->c1b->id, $s->fresh()->assigned_user_id);
    }

    public function test_counsellor_cannot_assign(): void
    {
        $s = $this->student('9000000001', $this->c1);
        $this->actingAs($this->c1)->post(route('students.assign', $s), ['user_id' => $this->c1b->id])->assertForbidden();
        $this->actingAs($this->c1)->post(route('students.bulk-assign'), ['student_ids' => [$s->id], 'user_id' => $this->c1b->id])->assertForbidden();
    }

    public function test_super_admin_assigns_across_teams_and_lead_follows_team(): void
    {
        $s = $this->student('9000000001', $this->c1);
        $this->actingAs($this->admin)->post(route('students.assign', $s), ['user_id' => $this->c2->id])->assertRedirect();
        $this->assertSame($this->c2->team_id, $s->fresh()->team_id);
        $this->assertSame(2, $s->assignmentHistory()->count());
    }

    public function test_only_super_admin_reaches_admin_pages_and_delete(): void
    {
        $s = $this->student('9000000001', $this->c1);
        foreach ([$this->lead1, $this->c1] as $u) {
            $this->actingAs($u)->get(route('admin.users'))->assertForbidden();
            $this->actingAs($u)->get(route('admin.duplicates'))->assertForbidden();
            $this->actingAs($u)->delete(route('students.destroy', $s))->assertForbidden();
            $this->actingAs($u)->get(route('students.create'))->assertForbidden();
        }
        $this->actingAs($this->admin)->get(route('admin.users'))->assertOk();
        $this->actingAs($this->admin)->delete(route('students.destroy', $s))->assertRedirect();
        $this->assertSoftDeleted($s);
    }

    public function test_all_main_pages_load_for_every_role(): void
    {
        $this->student('9000000001', $this->c1);
        foreach ([$this->admin, $this->lead1, $this->c1] as $u) {
            foreach (['dashboard', 'follow-ups', 'students.index'] as $r) {
                $this->actingAs($u)->get(route($r))->assertOk();
            }
            foreach (['today', 'overdue', 'upcoming', 'attention'] as $tab) {
                $this->actingAs($u)->get(route('follow-ups', ['tab' => $tab]))->assertOk();
            }
        }
        $this->actingAs($this->admin)->get(route('admin.duplicates'))->assertOk();
        $this->actingAs($this->admin)->get(route('students.create'))->assertOk();
    }

    public function test_search_by_mobile_in_any_format(): void
    {
        $this->student('9876543210', $this->c1, 'Findable Student');
        $this->actingAs($this->c1)->get(route('students.index', ['q' => '+91 98765 43210']))->assertSee('Findable Student');
        $this->actingAs($this->c1)->get(route('students.index', ['q' => '43210']))->assertSee('Findable Student');
        $this->actingAs($this->c1)->get(route('students.index', ['q' => 'Findable']))->assertSee('Findable Student');
    }
}
