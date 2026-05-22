<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('properties', function (Blueprint $table): void {
            $table->boolean('is_furnished')->default(false)->after('category');
            $table->dropColumn('etat');
        });
    }

    public function down(): void
    {
        Schema::table('properties', function (Blueprint $table): void {
            $table->string('etat')->nullable()->after('category');
            $table->dropColumn('is_furnished');
        });
    }
};
