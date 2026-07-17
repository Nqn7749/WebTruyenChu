<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('chapters', function (Blueprint $table) {
            
            $table->id();

            $table->foreignId('story_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->unsignedInteger('chapter_number');

            $table->string('title')
                ->nullable();

            $table->string('slug');

            $table->longText('content');

            $table->unsignedBigInteger('views')
                ->default(0);

            $table->unsignedInteger('comment_count')
                ->default(0);

            $table->boolean('status')
                ->default(true);

            $table->timestamps();

            $table->softDeletes();

            $table->unique([
                'story_id',
                'chapter_number'
            ]);

            $table->unique([
                'story_id',
                'slug'
            ]);

            $table->index([
                'story_id',
                'chapter_number'
            ]);
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('chapters');
    }
};
