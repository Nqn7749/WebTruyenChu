<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stories', function (Blueprint $table) {
            $table->text('source_url')->nullable();
            $table->char('source_hash', 64)->nullable()->unique();
        });

        Schema::table('chapters', function (Blueprint $table) {
            $table->text('source_url')->nullable();
            $table->char('source_hash', 64)->nullable()->unique();
        });
    }

    public function down(): void
    {
        Schema::table('chapters', function (Blueprint $table) {
            $table->dropUnique(['source_hash']);
            $table->dropColumn(['source_url', 'source_hash']);
        });

        Schema::table('stories', function (Blueprint $table) {
            $table->dropUnique(['source_hash']);
            $table->dropColumn(['source_url', 'source_hash']);
        });
    }
};
