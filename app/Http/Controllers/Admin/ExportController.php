<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\ExportLog;
use App\Services\StudentSearch;
use Illuminate\Http\Request;

/** Super Admin only. Exports the current filtered list as CSV; every export is logged. */
class ExportController extends Controller
{
    public function __invoke(Request $request)
    {
        $f = $request->validate(StudentSearch::rules());
        $q = StudentSearch::query($request->user(), $f)
            ->with(['status', 'assignedUser', 'course', 'exam', 'college', 'state', 'firstSource'])->orderBy('id');
        $count = (clone $q)->count();

        ExportLog::create(['user_id' => $request->user()->id, 'filters' => array_filter($f), 'row_count' => $count, 'ip_address' => $request->ip()]);
        AuditLog::record('students.exported', null, ['rows' => $count]);

        $name = 'students-'.now()->format('Y-m-d-His').'.csv';

        return response()->streamDownload(function () use ($q) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // so Excel shows Indian names correctly
            fputcsv($out, ['Student ID', 'Name', 'Mobile', 'Parent mobile', 'Email', 'City', 'State', 'Course', 'College', 'Exam', 'Rank',
                'Admission year', 'First source', 'Status', 'Priority', 'Assigned to', 'Next follow-up', 'Last contacted', 'Enquiries', 'Added on'], ',', '"', '');
            $q->chunk(500, function ($rows) use ($out) {
                foreach ($rows as $s) {
                    fputcsv($out, array_map([self::class, 'safe'], [
                        $s->student_code, $s->name, $s->mobile, $s->parent_mobile, $s->email, $s->city_text, $s->state?->name,
                        $s->course?->name, $s->college?->name, $s->exam?->name, $s->exam_rank, $s->admission_year,
                        $s->firstSource?->name, $s->status?->name, $s->priority?->label(), $s->assignedUser?->name,
                        $s->next_follow_up_at?->format('Y-m-d H:i'), $s->last_contacted_at?->format('Y-m-d H:i'),
                        $s->enquiry_count, $s->created_at->format('Y-m-d'),
                    ]), ',', '"', '');
                }
            });
            fclose($out);
        }, $name, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** Stops a cell from being run as a formula when the file is opened in Excel. */
    public static function safe(mixed $v): string
    {
        $v = (string) $v;

        return $v !== '' && in_array($v[0], ['=', '+', '-', '@', "\t", "\r"], true) ? "'".$v : $v;
    }
}
