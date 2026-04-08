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
        Schema::create('property_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('visit_id')->nullable()->constrained()->nullOnDelete();
            
            $table->tinyInteger('rating')->unsigned(); // 1 to 5
            $table->string('title')->nullable();
            $table->text('comment')->nullable();
            
            $table->string('status')->default('approved'); // 'pending', 'approved', 'rejected'
            $table->boolean('is_verified_tenant')->default(false);
            
            $table->timestamps();

            // Un utilisateur ne peut laisser qu'un seul avis par bien
            $table->unique(['property_id', 'user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('property_reviews');
    }
};
