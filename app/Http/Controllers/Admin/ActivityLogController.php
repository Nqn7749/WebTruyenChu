<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminActivityLog;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ActivityLogController extends Controller
{
    public function index(Request $request): View
    {
        $logs = AdminActivityLog::query()
            ->with('admin')
            ->when($request->filled('action'), fn ($query) => $query->where('action', $request->string('action')))
            ->latest('id')
            ->paginate(30)
            ->withQueryString();

        return view('admin.activity-logs.index', compact('logs'));
    }
}
