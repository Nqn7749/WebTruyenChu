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
        Schema::create('stories', function (Blueprint $table) {
            
            $table->id();

            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->string('title');

            $table->string('slug')
                ->unique();

            $table->string('author_name')
                ->nullable();

            $table->string('cover_image')
                ->nullable();

            $table->longText('description')
                ->nullable();

            $table->enum('status', [
                'ongoing',
                'completed',
                'paused'
            ])->default('ongoing');

            // SEO
            $table->string('meta_title')
                ->nullable();

            $table->text('meta_description')
                ->nullable();

            $table->string('meta_keywords')
                ->nullable();

            // Cache Counters
            $table->unsignedBigInteger('views')
                ->default(0);

            $table->unsignedInteger('chapter_count')
                ->default(0);

            $table->unsignedInteger('comment_count')
                ->default(0);

            $table->unsignedInteger('favorite_count')
                ->default(0);

            $table->unsignedInteger('rating_count')
                ->default(0);

            $table->decimal('average_rating', 3, 2)
                ->default(0);

            $table->boolean('is_hot')
                ->default(false);

            $table->boolean('is_featured')
                ->default(false);

            $table->boolean('is_recommended')
                ->default(false);

            $table->boolean('status_publish')
                ->default(true);

            $table->timestamp('last_chapter_at')
                ->nullable();

            $table->timestamp('last_view_at')
                ->nullable();

            $table->timestamps();

            $table->softDeletes();

            $table->index('status');
            $table->index('views');
            $table->index('is_hot');
            $table->index('is_featured');
            $table->index('last_chapter_at');

            $table->fullText([
                'title',
                'description'
            ]);
        });

        
        Schema::create('story_categories', function (Blueprint $table) {

            $table->id();

            $table->foreignId('story_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('category_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->timestamps();

            $table->unique([
                'story_id',
                'category_id'
            ]);
        });


        Schema::create('story_tags', function (Blueprint $table) {

            $table->id();

            $table->foreignId('story_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('tag_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->timestamps();

            $table->unique([
                'story_id',
                'tag_id'
            ]);
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stories');
    }
};
