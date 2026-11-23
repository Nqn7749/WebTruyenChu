<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One row per story per day with the real views and real reads of that day. It is what the
        // automatic "Hot" ranking looks at (a lifetime counter cannot tell what is hot *now*).
        Schema::create('story_daily_stats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('story_id')->constrained()->cascadeOnDelete();
            $table->date('stat_date');
            $table->unsignedInteger('views')->default(0);
            $table->unsignedInteger('reads')->default(0);

            $table->unique(['story_id', 'stat_date']);
            $table->index('stat_date');
        });

        // null = decided by the automatic ranking, 'on' / 'off' = an admin pinned the Hot flag.
        Schema::table('stories', function (Blueprint $table) {
            $table->string('hot_override', 3)->nullable()->after('is_hot');
        });

        // Until now Hot was only ever set by hand, so every story that is hot today was chosen by an
        // admin: pin those as "on" so the automatic ranking does not clear them.
        DB::table('stories')->where('is_hot', true)->update(['hot_override' => 'on']);
    }

    public function down(): void
    {
        Schema::table('stories', function (Blueprint $table) {
            $table->dropColumn('hot_override');
        });

        Schema::dropIfExists('story_daily_stats');
    }
};
