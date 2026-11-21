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
        // Chỉ lọc theo action khi giá trị là chuỗi: ?action[]=x làm
        // $request->string('action') ném lỗi "Array to string
        // conversion" → 500 (V2). Array thì bỏ qua filter.
        $action = $request->query('action');

        $logs = AdminActivityLog::query()
            ->with('admin')
            ->when(is_string($action) && $action !== '', fn ($query) => $query->where('action', $action))
            ->latest('id')
            ->paginate(30)
            ->withQueryString();

        return view('admin.activity-logs.index', compact('logs'));
    }
}
