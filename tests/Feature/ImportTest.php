<?php

namespace Tests\Feature;

use App\Models\Enquiry;
use App\Models\ImportBatch;
use App\Models\Student;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;

class ImportTest extends CrmTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    private function upload(UploadedFile $file, string $source = 'Demo Public School, Kota'): ImportBatch
    {
        $this->actingAs($this->admin)->post(route('admin.imports.store'), [
            'file' => $file, 'source_name' => $source, 'source_type' => 'physical_upload', 'collected_on' => '2026-10-15',
        ])->assertRedirect();

        return ImportBatch::latest('id')->firstOrFail();
    }

    public function test_csv_import_with_mapping_preview_duplicates_and_errors(): void
    {
        $this->student('9876543210', $this->c1, 'Asha Rao'); // already in CRM from a website

        $csv = "Student Name,Phone,Parent Phone,Course,City\n"
            ."Asha Rao,+91 98765 43210,,MBBS,Kota\n"     // existing -> new enquiry
            ."Rohan Mehta,9123456780,9988776655,B.Tech,Kota\n"
            ."Rohan Mehta,09123456780,,B.Tech,Kota\n"   // repeat within the file
            .",9000000001,,MBA,Kota\n"                   // no name -> rejected
            ."Bad Number,12345,,MBA,Kota\n";             // bad mobile -> rejected
        $batch = $this->upload(UploadedFile::fake()->createWithContent('kota.csv', $csv));

        $this->assertSame(5, $batch->total_rows);
        $this->assertSame(0, $batch->column_map['name']);
        $this->assertSame(1, $batch->column_map['mobile']);
        $this->assertSame(2, $batch->column_map['parent_mobile']);

        $this->actingAs($this->admin)->get(route('admin.imports.map', $batch))->assertOk()
            ->assertSee('Rohan Mehta')->assertSee('Name is missing');

        $this->actingAs($this->admin)->post(route('admin.imports.start', $batch), ['map' => $batch->column_map])
            ->assertRedirect(route('admin.imports.show', $batch));

        $batch->refresh();
        $this->assertSame('completed', $batch->status);
        $this->assertSame([1, 2, 2, 5], [$batch->created_count, $batch->merged_count, $batch->error_count, $batch->processed_rows]);
        $this->assertSame([5, 6], $batch->errors()->pluck('row_number')->all()); // row 1 is the header
        $this->assertSame(2, Student::count());

        $asha = Student::where('mobile', '9876543210')->first();
        $this->assertSame($this->c1->id, $asha->assigned_user_id); // existing assignment untouched
        $this->assertSame('Asha Rao', $asha->name);
        $imported = Enquiry::where('import_batch_id', $batch->id)->first();
        $this->assertSame('import', $imported->channel);
        $this->assertSame('Demo Public School, Kota', $imported->source->name);
        $this->assertSame('2026-10-15', $imported->collected_on->toDateString());

        $this->actingAs($this->admin)->get(route('admin.imports.show', $batch))->assertOk()->assertSee('Row 5');
    }

    public function test_xlsx_import_and_numbers_stored_as_numbers(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'x').'.xlsx';
        $w = new Writer;
        $w->openToFile($path);
        $w->addRow(Row::fromValues(['Name', 'Mobile Number', 'Admission Year']));
        $w->addRow(Row::fromValues(['Kavya Iyer', 9876501234, 2027]));   // Excel number, not text
        $w->addRow(Row::fromValues(['Arjun Das', '98765 01235', '2026']));
        $w->close();

        $batch = $this->upload(new UploadedFile($path, 'list.xlsx', null, null, true));
        $this->actingAs($this->admin)->post(route('admin.imports.start', $batch), ['map' => $batch->column_map]);

        $this->assertSame(2, Student::count());
        $this->assertSame(2027, Student::where('mobile', '9876501234')->value('admission_year'));
    }

    public function test_large_file_is_processed_in_batches(): void
    {
        $rows = "Name,Mobile\n";
        for ($i = 0; $i < 700; $i++) {
            $rows .= "Student $i,9".str_pad((string) (200000000 + $i), 9, '0', STR_PAD_LEFT)."\n";
        }
        $batch = $this->upload(UploadedFile::fake()->createWithContent('big.csv', $rows));
        $imports = app(\App\Services\ImportService::class);
        $intake = app(\App\Services\LeadIntakeService::class);
        $batch->update(['status' => 'queued']);

        $this->assertFalse($imports->processNext($batch, $intake));
        $this->assertSame(300, $batch->fresh()->processed_rows);
        $this->assertSame(300, Student::count());
        $this->assertFalse($imports->processNext($batch->fresh(), $intake));
        $this->assertTrue($imports->processNext($batch->fresh(), $intake));
        $this->assertSame(700, Student::count());
        $this->assertSame('completed', $batch->fresh()->status);
    }

    public function test_only_super_admin_can_import(): void
    {
        foreach ([$this->lead1, $this->c1] as $u) {
            $this->actingAs($u)->get(route('admin.imports.index'))->assertForbidden();
            $this->actingAs($u)->post(route('admin.imports.store'), [])->assertForbidden();
        }
    }

    public function test_rejects_other_file_types(): void
    {
        $this->actingAs($this->admin)->post(route('admin.imports.store'), [
            'file' => UploadedFile::fake()->create('evil.php', 1, 'text/x-php'),
            'source_name' => 'X', 'source_type' => 'physical_upload', 'collected_on' => '2026-10-15',
        ])->assertSessionHasErrors('file');
        $this->assertSame(0, ImportBatch::count());
    }
}
