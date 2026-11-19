<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chapters', function (Blueprint $table) {
            $table->timestamp('published_at')->nullable()->after('status');
        });

        // Existing published chapters predate this field. created_at is the
        // safest stable approximation because updated_at may include view increments.
        DB::table('chapters')
            ->where('status', true)
            ->update(['published_at' => DB::raw('created_at')]);
    }

    public function down(): void
    {
        Schema::table('chapters', function (Blueprint $table) {
            $table->dropColumn('published_at');
        });
    }
};
