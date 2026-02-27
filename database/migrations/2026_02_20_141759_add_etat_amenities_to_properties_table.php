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
            $table->string('etat')->nullable()->after('category'); // Neuf, Bon état, Rénové, Meublé
            $table->json('amenities')->nullable()->after('etat');   // ['Climatisation', 'Parking', ...]
        });
    }

    public function down(): void
    {
        Schema::table('properties', function (Blueprint $table): void {
            $table->dropColumn(['etat', 'amenities']);
        });
    }
};
