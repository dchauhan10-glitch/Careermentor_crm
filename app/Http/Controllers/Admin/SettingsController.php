<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\College;
use App\Models\Course;
use App\Models\Exam;
use App\Models\LeadStatus;
use App\Models\Source;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Super Admin: lead statuses, sources and pick-lists, without coding. */
class SettingsController extends Controller
{
    private const LISTS = [
        'statuses' => LeadStatus::class,
        'sources' => Source::class,
        'courses' => Course::class,
        'exams' => Exam::class,
        'colleges' => College::class,
    ];

    public function index()
    {
        return view('admin.settings', [
            'statuses' => LeadStatus::orderBy('sort_order')->get(),
            'sources' => Source::orderBy('type')->orderBy('name')->get(),
            'courses' => Course::orderBy('name')->get(),
            'exams' => Exam::orderBy('name')->get(),
            'colleges' => College::orderBy('name')->get(),
            'recentAudit' => AuditLog::with('user')->latest('created_at')->limit(50)->get(),
        ]);
    }

    public function add(Request $request, string $list)
    {
        $model = self::LISTS[$list] ?? abort(404);
        $table = (new $model)->getTable();
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120', Rule::unique($table, 'name')],
            'is_closed' => ['nullable', 'boolean'],
            'type' => [$list === 'sources' ? 'required' : 'nullable', Rule::in(array_keys(Source::TYPES))],
        ]);
        $attrs = ['name' => trim($data['name'])];
        if ($list === 'statuses') {
            $attrs += ['is_closed' => (bool) ($data['is_closed'] ?? false), 'sort_order' => (LeadStatus::max('sort_order') ?? 0) + 1];
        }
        if ($list === 'sources') {
            $attrs['type'] = $data['type'];
        }
        $item = $model::create($attrs);
        AuditLog::record('settings.added', $item, ['list' => $list, 'name' => $item->name]);

        return back()->with('status', '"'.$item->name.'" added.');
    }

    public function toggle(string $list, int $id)
    {
        $model = self::LISTS[$list] ?? abort(404);
        $item = $model::findOrFail($id);
        if ($list === 'statuses' && $item->name === 'New') {
            return back()->withErrors(['status' => '"New" is used for every incoming lead and cannot be switched off.']);
        }
        $item->update(['is_active' => ! $item->is_active]);
        AuditLog::record('settings.toggled', $item, ['list' => $list, 'active' => $item->is_active]);

        return back()->with('status', '"'.$item->name.'" '.($item->is_active ? 'switched on.' : 'hidden from drop-downs. Existing records keep it.'));
    }
}
