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
        if (!Schema::hasColumn('users', 'roles')) {
            Schema::table('users', function (Blueprint $table) {
                $table->json('roles')->nullable()->after('role');
            });
        }

        // Initialize roles for existing users if any
        try {
            \Illuminate\Support\Facades\DB::table('users')->get()->each(function ($user) {
                if (empty($user->roles)) {
                    \Illuminate\Support\Facades\DB::table('users')
                        ->where('id', $user->id)
                        ->update(['roles' => json_encode([$user->role])]);
                }
            });
        } catch (\Throwable $e) {
            // Silently fail if table doesn't exist yet or other issues
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('roles');
        });
    }
};
