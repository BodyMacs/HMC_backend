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
        Schema::create('property_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade'); // The Bailleur
            $table->foreignId('agent_id')->nullable()->constrained('users')->nullOnDelete(); // Assigned agent HMC

            $table->string('title');
            $table->enum('type', ['rent', 'sale']);
            $table->string('category'); // villa, appart, studio, etc
            $table->decimal('price_estimate', 12, 2)->nullable();

            $table->string('city');
            $table->string('location');
            $table->text('description')->nullable();

            // Stats basics
            $table->integer('bedrooms')->nullable();
            $table->integer('bathrooms')->nullable();
            $table->decimal('area', 8, 2)->nullable();

            // Audit Documents (identities, title deeds, etc)
            $table->json('documents')->nullable(); // JSON array of paths: ['doc1.pdf', 'id_card.png']

            $table->enum('status', ['pending', 'assigned', 'visited', 'published', 'rejected'])->default('pending');
            $table->text('rejection_reason')->nullable();
            $table->timestamp('visited_at')->nullable();
            $table->timestamp('published_at')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('property_requests');
    }
};
