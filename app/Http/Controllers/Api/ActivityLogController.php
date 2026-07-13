<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ActivityLogController extends Controller
{
public function index(Request $request)
{
    // Pagination size (default 15)
    $perPage = $request->get('perPage', 15);

    // Start building query
    $query = ActivityLog::with('user')->latest();

    // Apply filters if provided
    if ($request->filled('user_id')) {
        $query->where('user_id', $request->user_id);
    }

    if ($request->filled('start_date')) {
        $query->whereDate('created_at', '>=', $request->start_date);
    }

    if ($request->filled('end_date')) {
        $query->whereDate('created_at', '<=', $request->end_date);
    }

    // If a specific route is given, fetch only that route
    if ($request->filled('route')) {
        $query->where('route', $request->route);
    }
    if (!$request->filled('route')) {
        $query->where('route', '!=', 'api/flight-providers');
    }

    // Debug log
    Log::info('ActivityLog index called', [
        'query' => $request->query(),
    ]);

    // Return paginated results
    return $query->paginate($perPage);
}



public function delete()
{
    // Delete all activity logs
    ActivityLog::truncate();

    return response()->json([
        'message' => 'All activity logs have been deleted successfully.'
    ]);
}




}
