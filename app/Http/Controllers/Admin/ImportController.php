<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessImportBatch;
use App\Models\AuditLog;
use App\Models\ImportBatch;
use App\Models\Source;
use App\Services\ImportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use InvalidArgumentException;
use Throwable;

/** Super Admin only: upload Excel/CSV lists, including physical lists from colleges, schools and coaching centres. */
class ImportController extends Controller
{
    public function index()
    {
        return view('admin.imports.index', [
            'batches' => ImportBatch::with(['source', 'uploader'])->latest()->paginate(20),
            'sources' => Source::whereIn('type', ['physical_upload', 'sheet_import', 'other'])->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request, ImportService $imports)
    {
        $data = $request->validate([
            'file' => ['required', 'file', 'max:10240', 'mimes:xlsx,csv,txt'],
            'source_name' => ['required', 'string', 'max:100'],
            'source_type' => ['required', Rule::in(['physical_upload', 'sheet_import', 'other'])],
            'collected_on' => ['required', 'date', 'before_or_equal:today'],
        ], ['file.mimes' => 'Please upload an Excel (.xlsx) or CSV file.']);

        $ext = strtolower($request->file('file')->getClientOriginalExtension());
        if (! in_array($ext, ['xlsx', 'csv'], true)) {
            return back()->withErrors(['file' => 'Please upload an Excel (.xlsx) or CSV file.']);
        }

        $source = Source::firstOrCreate(['name' => trim($data['source_name'])], ['type' => $data['source_type']]);
        $batch = ImportBatch::create([
            'source_id' => $source->id,
            'uploaded_by' => $request->user()->id,
            'original_filename' => basename($request->file('file')->getClientOriginalName()),
            'stored_path' => '',
            'collected_on' => $data['collected_on'],
        ]);

        try {
            $imports->prepare($request->file('file'), $batch);
        } catch (Throwable $e) {
            Storage::disk('local')->deleteDirectory('imports/'.$batch->id);
            $batch->delete();
            report($e);

            return back()->withErrors(['file' => $e instanceof InvalidArgumentException ? $e->getMessage() : 'This file could not be read. Please save it again as .xlsx or .csv and retry.']);
        }
        AuditLog::record('import.uploaded', $batch, ['rows' => $batch->total_rows, 'source' => $source->name]);

        return redirect()->route('admin.imports.map', $batch);
    }

    public function map(Request $request, ImportBatch $batch, ImportService $imports)
    {
        abort_unless($batch->status === 'uploaded', 404);
        $map = $request->has('map') ? $this->cleanMap($request->input('map'), $batch) : $batch->column_map;

        return view('admin.imports.map', [
            'batch' => $batch,
            'map' => $map,
            'fields' => ImportService::FIELDS,
            'preview' => $imports->preview($batch, $map),
        ]);
    }

    public function start(Request $request, ImportBatch $batch)
    {
        abort_unless($batch->status === 'uploaded', 404);
        $map = $this->cleanMap($request->input('map', []), $batch);
        if ($map['name'] === null || $map['mobile'] === null) {
            return back()->withErrors(['map' => 'Please choose which columns hold the student name and the mobile number.']);
        }
        $batch->update(['column_map' => $map, 'status' => 'queued']);
        AuditLog::record('import.started', $batch);
        ProcessImportBatch::dispatch($batch->id);

        return redirect()->route('admin.imports.show', $batch)->with('status', 'Import started. It runs in small batches; this page shows progress.');
    }

    public function show(ImportBatch $batch)
    {
        return view('admin.imports.show', ['batch' => $batch->load('source', 'uploader'), 'errors_list' => $batch->errors()->limit(500)->get()]);
    }

    public function cancel(ImportBatch $batch)
    {
        abort_unless($batch->status === 'uploaded', 404);
        Storage::disk('local')->deleteDirectory('imports/'.$batch->id);
        $batch->delete();

        return redirect()->route('admin.imports.index')->with('status', 'Upload cancelled. Nothing was imported.');
    }

    private function cleanMap(array $input, ImportBatch $batch): array
    {
        $max = count($batch->headers) - 1;
        $map = [];
        foreach (array_keys(ImportService::FIELDS) as $f) {
            $v = $input[$f] ?? null;
            $map[$f] = is_numeric($v) && (int) $v >= 0 && (int) $v <= $max ? (int) $v : null;
        }

        return $map;
    }
}
