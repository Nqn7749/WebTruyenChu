<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('crawler_sources', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('base_url', 2048);
            $table->string('listing_url', 2048);
            $table->json('selectors');
            $table->boolean('permission_confirmed')->default(false);
            $table->boolean('enabled')->default(false);
            $table->timestamp('last_crawled_at')->nullable();
            $table->timestamps();
        });

        Schema::create('crawler_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('crawler_source_id')->constrained()->cascadeOnDelete();
            $table->foreignId('started_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status')->default('queued');
            $table->unsignedInteger('stories_found')->default(0);
            $table->unsignedInteger('stories_imported')->default(0);
            $table->unsignedInteger('chapters_imported')->default(0);
            $table->text('message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
            $table->index(['crawler_source_id', 'created_at']);
        });

        Schema::create('crawler_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('crawler_source_id')->constrained()->cascadeOnDelete();
            $table->string('external_key', 2048);
            $table->foreignId('story_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['crawler_source_id', 'external_key']);
            $table->unique(['crawler_source_id', 'story_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crawler_items');
        Schema::dropIfExists('crawler_runs');
        Schema::dropIfExists('crawler_sources');
    }
};
