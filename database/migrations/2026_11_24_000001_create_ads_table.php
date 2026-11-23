<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ads', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            // One of App\Models\Ad::PLACEMENTS, e.g. "home_left" (home page, left edge).
            $table->string('placement', 32);
            // "image": an uploaded banner with a link. "code": a snippet from an ad network (admin only).
            $table->string('type', 8)->default('image');
            $table->string('image_path')->nullable();
            $table->text('link_url')->nullable();
            $table->string('alt_text', 150)->nullable();
            $table->text('html_code')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->unsignedInteger('clicks')->default(0);
            $table->timestamps();

            $table->index(['placement', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ads');
    }
};
