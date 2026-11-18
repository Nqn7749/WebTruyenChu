<?php

namespace App\Services;

use App\Models\AdminActivityLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class AdminActivityLogger
{
    public function record(
        Request $request,
        string $action,
        ?Model $subject,
        string $description
    ): void {
        $log = new AdminActivityLog([
            'admin_id' => $request->user()?->id,
            'action' => $action,
            'description' => $description,
            'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 500),
        ]);

        if ($subject) {
            $log->subject()->associate($subject);
        }

        $log->save();
    }
}
