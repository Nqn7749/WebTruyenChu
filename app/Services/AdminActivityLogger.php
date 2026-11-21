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
            // Cột description là varchar(1000): tiêu đề truyện (tối đa 255)
            // + lý do xóa / ghi chú xử lý báo cáo (tối đa 1000) có thể vượt
            // và gây 500 — chỗ báo cáo còn nằm trong DB::transaction nên
            // toàn bộ xử lý bị rollback. Cắt ngắn còn đúng 1000 ký tự.
            // Lưu ý: Str::limit($description, 1000) KHÔNG dùng được ở đây
            // vì nó trả về 1000 ký tự + hậu tố '...' = 1003 ký tự, vẫn
            // tràn cột (và trên MySQL strict sẽ lại 500).
            'description' => mb_substr($description, 0, 1000),
            'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 500),
        ]);

        if ($subject) {
            $log->subject()->associate($subject);
        }

        $log->save();
    }
}
