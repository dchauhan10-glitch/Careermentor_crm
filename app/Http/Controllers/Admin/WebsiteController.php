<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Enquiry;
use App\Models\Source;
use App\Models\WebsiteConnection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Super Admin: connect a website, create its key, switch it off. No code change needed. */
class WebsiteController extends Controller
{
    public function index()
    {
        $counts = Enquiry::whereNotNull('website_connection_id')->groupBy('website_connection_id')
            ->pluck(DB::raw('count(*)'), 'website_connection_id');
        $last30 = Enquiry::whereNotNull('website_connection_id')->where('created_at', '>=', now()->subDays(30))
            ->groupBy('website_connection_id')->pluck(DB::raw('count(*)'), 'website_connection_id');

        return view('admin.websites', [
            'connections' => WebsiteConnection::with('source')->orderBy('name')->get(),
            'counts' => $counts,
            'last30' => $last30,
            'websiteSources' => Source::where('type', 'website')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'source_name' => ['required', 'string', 'max:100'],
            'per_minute_limit' => ['required', 'integer', 'between:1,300'],
        ]);
        $source = Source::firstOrCreate(['name' => $data['source_name']], ['type' => 'website']);
        $conn = WebsiteConnection::make(['name' => $data['name'], 'per_minute_limit' => $data['per_minute_limit']]);
        $conn->source_id = $source->id;
        $key = $conn->issueKey();
        AuditLog::record('website.created', $conn, ['source' => $source->name]);

        return redirect()->route('admin.websites')->with('new_key', ['name' => $conn->name, 'key' => $key]);
    }

    public function toggle(WebsiteConnection $connection)
    {
        $connection->update(['is_active' => ! $connection->is_active]);
        AuditLog::record($connection->is_active ? 'website.switched_on' : 'website.switched_off', $connection);

        return back()->with('status', $connection->name.($connection->is_active ? ' switched on.' : ' switched off. Its sign-ups are now refused.'));
    }

    /** Replaces a leaked or lost key. The old key stops working at once. */
    public function newKey(WebsiteConnection $connection)
    {
        $key = $connection->issueKey();
        AuditLog::record('website.key_replaced', $connection);

        return redirect()->route('admin.websites')->with('new_key', ['name' => $connection->name, 'key' => $key]);
    }

    public function update(Request $request, WebsiteConnection $connection)
    {
        $data = $request->validate(['per_minute_limit' => ['required', 'integer', 'between:1,300']]);
        $connection->update($data);
        AuditLog::record('website.limit_changed', $connection, $data);

        return back()->with('status', 'Limit saved.');
    }
}
