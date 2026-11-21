<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Lượt đọc thật (phương án C): đếm khi người đọc ở lại trang chương
     * đủ 15 giây VÀ cuộn qua 25% nội dung (ghi qua endpoint recordRead).
     * Tách riêng khỏi views (đếm ngay khi mở trang) để bảng hot xếp hạng
     * theo mức độ đọc thật thay vì lượt mở trang.
     */
    public function up(): void
    {
        Schema::table('stories', function (Blueprint $table) {
            $table->unsignedBigInteger('reads_count')->default(0)->after('views');
        });

        Schema::table('chapters', function (Blueprint $table) {
            $table->unsignedBigInteger('reads_count')->default(0)->after('views');
        });
    }

    public function down(): void
    {
        Schema::table('stories', function (Blueprint $table) {
            $table->dropColumn('reads_count');
        });

        Schema::table('chapters', function (Blueprint $table) {
            $table->dropColumn('reads_count');
        });
    }
};
