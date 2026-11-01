<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\DuplicateReview;
use App\Models\Source;
use App\Models\Student;
use App\Models\User;
use App\Services\LeadIntakeService;

class AdminTest extends CrmTestCase
{
    public function test_add_counsellor_under_team_lead_four_and_move_team(): void
    {
        $this->actingAs($this->admin)->post(route('admin.users.store'), [
            'name' => 'New Counsellor', 'email' => 'NEW@x.test', 'role' => 'counsellor',
            'team_id' => $this->lead2->team_id, 'password' => 'Brand-new-2026x',
        ])->assertSessionHasNoErrors();
        $u = User::where('email', 'new@x.test')->firstOrFail();
        $this->assertSame($this->lead2->team_id, $u->team_id);

        $s = $this->student('9000000009', $u);
        $this->actingAs($this->admin)->post(route('admin.users.team', $u), ['team_id' => $this->lead1->team_id]);
        $this->assertSame($this->lead1->team_id, $u->fresh()->team_id);
        $this->assertSame($this->lead1->team_id, $s->fresh()->team_id);
        $this->assertTrue(AuditLog::where('action', 'user.moved_team')->exists());
    }

    public function test_handover_moves_all_leads(): void
    {
        $this->student('9000000001', $this->c1);
        $this->student('9000000002', $this->c1);
        $this->actingAs($this->admin)->post(route('admin.users.handover', $this->c1), ['to_user_id' => $this->c1b->id]);
        $this->assertSame(0, Student::where('assigned_user_id', $this->c1->id)->count());
        $this->assertSame(2, Student::where('assigned_user_id', $this->c1b->id)->count());
    }

    public function test_merge_duplicate_keeps_all_history(): void
    {
        $intake = app(LeadIntakeService::class);
        $src = Source::first();
        $a = $intake->intake(['name' => 'A', 'mobile' => '9876543210', 'email' => 'same@example.test'], $src, ['channel' => 'website'])['student'];
        $b = $intake->intake(['name' => 'A', 'mobile' => '9123456780', 'email' => 'same@example.test'], $src, ['channel' => 'website'])['student'];
        app(\App\Services\LeadWorkService::class)->logCall($b, $this->admin, ['outcome' => 'Spoke to B']);

        $review = DuplicateReview::firstOrFail();
        $this->actingAs($this->admin)->post(route('admin.duplicates.merge', $review))->assertRedirect();

        $this->assertSoftDeleted($b);
        $this->assertSame(2, $a->fresh()->enquiries()->count());
        $this->assertSame('Spoke to B', $a->fresh()->callEntries->first()->outcome);
        $this->assertSame('merged', $review->fresh()->status);
    }
}
