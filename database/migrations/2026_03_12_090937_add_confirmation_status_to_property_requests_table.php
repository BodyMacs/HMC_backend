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
        Schema::table('property_requests', function (Blueprint $table) {
            $table->timestamp('bailleur_confirmed_at')->nullable()->after('scheduled_at');
            $table->timestamp('bailleur_declined_at')->nullable()->after('bailleur_confirmed_at');
            $table->string('bailleur_notes')->nullable()->after('bailleur_declined_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('property_requests', function (Blueprint $table) {
            $table->dropColumn(['bailleur_confirmed_at', 'bailleur_declined_at', 'bailleur_notes']);
        });
    }
};
