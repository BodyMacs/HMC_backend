<?php

declare(strict_types=1);

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
        Schema::create('service_categories', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('icon')->nullable(); // URL or icon name
            $table->timestamps();
        });

        Schema::create('services', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('provider_id')->constrained('users')->onDelete('cascade'); // Prestataire
            $table->foreignId('category_id')->constrained('service_categories')->onDelete('cascade');
            $table->string('title');
            $table->text('description')->nullable();
            $table->decimal('base_price', 12, 2)->nullable();
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamps();
        });

        Schema::create('interventions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('service_id')->constrained('services')->onDelete('cascade');
            $table->foreignId('requester_id')->constrained('users')->onDelete('cascade'); // Client
            $table->dateTime('scheduled_at');
            $table->enum('status', ['pending', 'accepted', 'completed', 'rejected'])->default('pending');
            $table->text('notes')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->integer('rating')->nullable()->comment('1-5');
            $table->text('review')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('interventions');
        Schema::dropIfExists('services');
        Schema::dropIfExists('service_categories');
    }
};
