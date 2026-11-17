<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stories', function (Blueprint $table) {
            $table->text('deletion_reason')->nullable()->after('deleted_at');
            $table->foreignId('deleted_by')
                ->nullable()
                ->after('deletion_reason')
                ->constrained('users')
                ->nullOnDelete();
        });

        // Existing member submissions were held for approval in the previous workflow.
        // The new workflow publishes member stories without admin approval.
        DB::table('stories')
            ->where('status_publish', false)
            ->whereIn('user_id', function ($query) {
                $query->select('users.id')
                    ->from('users')
                    ->join('roles', 'users.role_id', '=', 'roles.id')
                    ->where('roles.slug', 'user');
            })
            ->update(['status_publish' => true]);
    }

    public function down(): void
    {
        Schema::table('stories', function (Blueprint $table) {
            $table->dropForeign(['deleted_by']);
            $table->dropColumn(['deletion_reason', 'deleted_by']);
        });
    }
};
