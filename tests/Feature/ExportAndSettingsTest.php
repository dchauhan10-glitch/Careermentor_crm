<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\ExportController;
use App\Models\Course;
use App\Models\ExportLog;
use App\Models\LeadStatus;

class ExportAndSettingsTest extends CrmTestCase
{
    public function test_only_super_admin_exports_and_every_export_is_logged(): void
    {
        $this->student('9000000001', $this->c1, 'Export Me');
        $this->actingAs($this->lead1)->get(route('admin.export'))->assertForbidden();
        $this->actingAs($this->c1)->get(route('admin.export'))->assertForbidden();

        $res = $this->actingAs($this->admin)->get(route('admin.export'));
        $res->assertOk();
        $this->assertStringContainsString('Export Me', $res->streamedContent());
        $this->assertSame(1, ExportLog::count());
        $this->assertSame(1, ExportLog::first()->row_count);
    }

    public function test_export_neutralises_spreadsheet_formulas(): void
    {
        $this->assertSame("'=HYPERLINK(\"x\")", ExportController::safe('=HYPERLINK("x")'));
        $this->assertSame('Asha', ExportController::safe('Asha'));
    }

    public function test_settings_lists_can_be_extended_without_code(): void
    {
        $this->actingAs($this->admin)->post(route('admin.settings.add', 'courses'), ['name' => 'B.Arch'])->assertSessionHasNoErrors();
        $this->assertTrue(Course::where('name', 'B.Arch')->exists());
        $this->actingAs($this->admin)->post(route('admin.settings.add', 'statuses'), ['name' => 'Admission Done', 'is_closed' => 1]);
        $this->assertTrue(LeadStatus::where('name', 'Admission Done')->value('is_closed'));

        $new = LeadStatus::where('name', 'New')->first();
        $this->actingAs($this->admin)->post(route('admin.settings.toggle', ['statuses', $new->id]))->assertSessionHasErrors();
        $this->assertTrue($new->fresh()->is_active);

        $this->actingAs($this->lead1)->get(route('admin.settings'))->assertForbidden();
        $this->actingAs($this->admin)->get(route('admin.settings'))->assertOk();
    }
}
