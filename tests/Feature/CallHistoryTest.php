<?php

namespace Tests\Feature;

use App\Models\CallEntry;
use App\Models\LeadStatus;
use LogicException;

class CallHistoryTest extends CrmTestCase
{
    public function test_counsellor_logs_calls_and_all_are_kept_in_order(): void
    {
        $s = $this->student('9876543210', $this->c1);
        $interested = LeadStatus::where('name', 'Interested')->first();

        foreach (range(1, 5) as $i) {
            $this->actingAs($this->c1)->post(route('students.calls.store', $s), [
                'outcome' => "Call $i", 'advice' => 'Apply early', 'lead_status_id' => $interested->id,
                'next_follow_up_at' => now()->addDays(2)->format('Y-m-d\TH:i'),
            ])->assertRedirect(route('students.show', $s));
        }

        $this->assertSame(5, CallEntry::where('student_id', $s->id)->count());
        $entries = $s->fresh()->callEntries;
        $this->assertSame($this->c1->id, $entries->first()->user_id); // caller filled automatically
        $this->assertSame('Interested', $s->fresh()->status->name);
        $this->assertNotNull($s->fresh()->next_follow_up_at);

        $this->actingAs($this->c1)->get(route('students.show', $s))->assertOk()
            ->assertSee('Call 1')->assertSee('Call 5')->assertSee('Apply early');
    }

    public function test_call_entries_cannot_be_edited_or_deleted(): void
    {
        $s = $this->student('9876543210', $this->c1);
        $this->actingAs($this->c1)->post(route('students.calls.store', $s), ['outcome' => 'First']);
        $entry = CallEntry::first();

        try {
            $entry->update(['outcome' => 'Changed']);
            $this->fail('Edit should be blocked');
        } catch (LogicException) {
        }
        try {
            $entry->delete();
            $this->fail('Delete should be blocked');
        } catch (LogicException) {
        }
        $this->assertSame('First', $entry->fresh()->outcome);
    }

    public function test_corrections_are_added_as_new_entries(): void
    {
        $s = $this->student('9876543210', $this->c1);
        $this->actingAs($this->c1)->post(route('students.calls.store', $s), ['outcome' => 'Wrong note']);
        $first = CallEntry::first();
        $this->actingAs($this->c1)->post(route('students.calls.store', $s), ['outcome' => 'Correct note', 'corrects_call_entry_id' => $first->id]);

        $this->assertSame(2, CallEntry::count());
        $this->assertSame('Wrong note', $first->fresh()->outcome);
        $this->assertSame($first->id, CallEntry::latest('id')->first()->corrects_call_entry_id);
    }

    public function test_follow_up_must_be_in_future(): void
    {
        $s = $this->student('9876543210', $this->c1);
        $this->actingAs($this->c1)->post(route('students.calls.store', $s), [
            'outcome' => 'x', 'next_follow_up_at' => now()->subDay()->format('Y-m-d\TH:i'),
        ])->assertSessionHasErrors('next_follow_up_at');
    }

    public function test_closing_a_lead_clears_its_follow_up(): void
    {
        $s = $this->student('9876543210', $this->c1);
        $this->actingAs($this->c1)->post(route('students.calls.store', $s), [
            'outcome' => 'Joined college', 'lead_status_id' => \App\Models\LeadStatus::where('name', 'Converted')->value('id'),
            'next_follow_up_at' => now()->addDay()->format('Y-m-d\TH:i'),
        ]);
        $this->assertNull($s->fresh()->next_follow_up_at);
    }
}
