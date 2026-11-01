<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\CallEntry;
use App\Models\DuplicateReview;
use App\Models\Enquiry;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Super Admin review list for doubtful duplicate students. */
class DuplicateController extends Controller
{
    public function index()
    {
        return view('admin.duplicates', [
            'reviews' => DuplicateReview::with(['student' => fn ($q) => $q->withTrashed(), 'candidate'])
                ->where('status', 'open')->latest()->paginate(config('crm.per_page')),
        ]);
    }

    public function keep(DuplicateReview $review)
    {
        $review->update(['status' => 'kept_separate', 'resolved_by' => auth()->id(), 'resolved_at' => now()]);
        AuditLog::record('duplicate.kept', $review);

        return back()->with('status', 'Marked as not a duplicate.');
    }

    /**
     * Moves the candidate's enquiries and call entries onto the existing
     * student, then soft-deletes the candidate. Call entries keep their text;
     * only the student they belong to changes, so this is done in the database
     * directly and recorded in the audit log.
     */
    public function merge(DuplicateReview $review)
    {
        abort_unless($review->candidate_student_id, 422, 'Nothing to merge for this item.');

        DB::transaction(function () use ($review) {
            $keep = Student::findOrFail($review->student_id);
            $drop = Student::findOrFail($review->candidate_student_id);
            $moved = Enquiry::where('student_id', $drop->id)->update(['student_id' => $keep->id]);
            $calls = DB::table('call_entries')->where('student_id', $drop->id)->update(['student_id' => $keep->id]);
            DB::table('status_history')->where('student_id', $drop->id)->update(['student_id' => $keep->id]);
            DB::table('assignment_history')->where('student_id', $drop->id)->update(['student_id' => $keep->id]);
            $keep->increment('enquiry_count', $moved);
            $drop->delete();
            $review->update(['status' => 'merged', 'resolved_by' => auth()->id(), 'resolved_at' => now()]);
            AuditLog::record('duplicate.merged', $keep, ['merged_student_id' => $drop->id, 'enquiries' => $moved, 'calls' => $calls]);
        });

        return back()->with('status', 'Records merged.');
    }
}
