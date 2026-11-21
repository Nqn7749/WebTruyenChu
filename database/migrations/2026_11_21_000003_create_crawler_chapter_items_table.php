<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('crawler_chapter_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('crawler_source_id')->constrained()->cascadeOnDelete();
            $table->foreignId('story_id')->constrained()->cascadeOnDelete();
            $table->string('external_key', 2048);
            $table->char('external_hash', 64);
            $table->foreignId('chapter_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['crawler_source_id', 'external_hash']);
            $table->unique('chapter_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crawler_chapter_items');
    }
};
