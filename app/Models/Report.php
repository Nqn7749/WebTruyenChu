<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Report extends Model
{
    use HasFactory;

    /**
     * Chỉ các trường người báo cáo được phép nhập (đã qua validation).
     * reporter_id do hệ thống gán từ tài khoản đang đăng nhập. Các trường
     * xử lý (status, reviewed_by, reviewed_at, resolution_note) KHÔNG nằm
     * ở đây — chỉ gán qua forceFill() khi admin xử lý báo cáo.
     */
    protected $fillable = [
        'reporter_id',
        'reason',
        'details',
    ];

    protected function casts(): array
    {
        return ['reviewed_at' => 'datetime'];
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reporter_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function reportable(): MorphTo
    {
        return $this->morphTo();
    }
}
