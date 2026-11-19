<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chapters', function (Blueprint $table) {
            // Index thường (story_id, chapter_number) đã có từ migration gốc
            // nên vẫn phục vụ được query và FK.
            $table->dropUnique(['story_id', 'chapter_number']);
        });
    }

    public function down(): void
    {
        Schema::table('chapters', function (Blueprint $table) {
            $table->unique(['story_id', 'chapter_number']);
        });
    }
};
