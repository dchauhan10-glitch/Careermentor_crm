<?php

namespace Tests\Feature;

use App\Models\LeadStatus;

class RedesignTest extends CrmTestCase
{
    private function logCallFor($user, $student, string $outcome, ?string $status = null): void
    {
        $this->actingAs($user)->post(route('students.calls.store', $student), array_filter([
            'outcome' => $outcome,
            'lead_status_id' => $status ? LeadStatus::where('name', $status)->value('id') : null,
            'priority' => 'warm',
        ]))->assertRedirect();
    }

    public function test_call_history_page_is_scoped_by_role(): void
    {
        $mine = $this->student('9000000001', $this->c1, 'Mine Student');
        $other = $this->student('9000000003', $this->c2, 'Other Team');
        $this->logCallFor($this->c1, $mine, 'Mine call note');
        $this->logCallFor($this->c2, $other, 'Other call note');

        $this->actingAs($this->c1)->get(route('calls.index'))->assertOk()
            ->assertSee('Mine call note')->assertDontSee('Other call note');
        $this->actingAs($this->lead1)->get(route('calls.index'))->assertOk()
            ->assertSee('Mine call note')->assertDontSee('Other call note');
        $this->actingAs($this->admin)->get(route('calls.index', ['period' => 'all']))->assertOk()
            ->assertSee('Mine call note')->assertSee('Other call note');
        $this->actingAs($this->admin)->get(route('calls.index', ['q' => 'Other']))->assertOk()
            ->assertSee('Other call note')->assertDontSee('Mine call note');
        $this->actingAs($this->admin)->get(route('calls.index', ['q' => '9000000001']))->assertOk()
            ->assertSee('Mine call note')->assertDontSee('Other call note');
    }

    public function test_quick_filters_and_sorting(): void
    {
        $a = $this->student('9000000001', $this->c1, 'Alpha Interested');
        $b = $this->student('9000000002', $this->c1, 'Bravo Not Interested');
        $this->student('9000000004', $this->c1, 'Charlie New');
        $this->logCallFor($this->c1, $a, 'Keen', 'Interested');
        $this->logCallFor($this->c1, $b, 'No', 'Not Interested');

        $this->actingAs($this->c1)->get(route('students.index', ['quick' => 'interested']))->assertOk()
            ->assertSee('Alpha Interested')->assertDontSee('Bravo Not Interested')->assertDontSee('Charlie New');
        $this->actingAs($this->c1)->get(route('students.index', ['quick' => 'not_interested']))
            ->assertSee('Bravo Not Interested')->assertDontSee('Alpha Interested');
        $this->actingAs($this->c1)->get(route('students.index', ['quick' => 'not_called']))
            ->assertSee('Charlie New')->assertDontSee('Alpha Interested');
        $this->actingAs($this->c1)->get(route('students.index', ['sort' => 'name', 'dir' => 'desc']))->assertOk()
            ->assertSeeInOrder(['Charlie New', 'Bravo Not Interested', 'Alpha Interested']);
        $this->actingAs($this->c1)->get(route('students.index', ['sort' => 'bogus']))->assertSessionHasErrors('sort');
    }

    public function test_dashboard_cards_and_menu_by_role(): void
    {
        $s = $this->student('9000000001', $this->c1);
        $this->logCallFor($this->c1, $s, 'Called today', 'Interested');

        $this->actingAs($this->c1)->get(route('dashboard'))->assertOk()
            ->assertSee('Calls made today')->assertSee('Not Interested')->assertSee('Follow-ups due today')
            ->assertSee('Call History')->assertDontSee('Import lists');
        $this->actingAs($this->admin)->get(route('dashboard'))->assertOk()->assertSee('Import lists');
    }

    public function test_student_page_has_tap_to_call_and_quick_form(): void
    {
        $s = $this->student('9000000001', $this->c1);
        $this->actingAs($this->c1)->get(route('students.show', $s))->assertOk()
            ->assertSee('href="tel:+919000000001"', false)->assertSee('After the call')->assertSee('Save call');
    }

    public function test_installable_app_files_exist(): void
    {
        $this->assertFileExists(public_path('manifest.webmanifest'));
        $this->assertFileExists(public_path('sw.js'));
        $this->assertJson(file_get_contents(public_path('manifest.webmanifest')));
    }
}
