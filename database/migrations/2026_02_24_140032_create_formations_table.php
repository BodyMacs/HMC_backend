<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('formations', function (Blueprint $blueprint) {
            $blueprint->id();
            $blueprint->string('title');
            $blueprint->text('description');
            $blueprint->string('badge')->nullable(); // Certificat, Diplôme d'état
            $blueprint->decimal('price', 15, 2)->default(0);
            $blueprint->json('modules')->nullable(); // Modules and lessons stored as JSON for simplicity
            $blueprint->string('status')->default('active');
            $blueprint->timestamps();
        });

        Schema::create('user_formations', function (Blueprint $blueprint) {
            $blueprint->id();
            $blueprint->foreignId('user_id')->constrained()->onDelete('cascade');
            $blueprint->foreignId('formation_id')->constrained()->onDelete('cascade');
            $blueprint->string('status')->default('purchased'); // purchased, in_progress, completed
            $blueprint->integer('progress')->default(0); // 0-100
            $blueprint->timestamp('completed_at')->nullable();
            $blueprint->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_formations');
        Schema::dropIfExists('formations');
    }
};
