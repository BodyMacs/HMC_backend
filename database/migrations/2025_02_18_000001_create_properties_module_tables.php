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
        Schema::create('properties', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade'); // Owner (Bailleur/Agent)
            $table->string('title');
            $table->string('slug')->unique();
            $table->enum('type', ['rent', 'sale']);
            $table->enum('status', ['active', 'pending', 'rejected', 'rented', 'sold'])->default('pending');
            $table->decimal('price', 12, 2);
            $table->string('currency')->default('XAF');
            $table->text('description')->nullable();
            $table->string('location');
            $table->string('city');
            $table->string('region')->nullable();
            $table->json('features')->nullable(); // JSON Features
            $table->integer('bedrooms')->nullable();
            $table->integer('bathrooms')->nullable();
            $table->decimal('area', 8, 0)->nullable(); // m2
            $table->integer('construction_year')->nullable();
            $table->integer('views_count')->default(0);
            $table->timestamps();
        });

        Schema::create('property_images', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('property_id')->constrained()->onDelete('cascade');
            $table->string('path');
            $table->boolean('is_primary')->default(false);
            $table->integer('order')->default(0);
            $table->timestamps();
        });

        Schema::create('favorites', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('property_id')->constrained()->onDelete('cascade');
            $table->timestamps();
            $table->unique(['user_id', 'property_id']);
        });

        Schema::create('visits', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('property_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade'); // Visitor
            $table->dateTime('scheduled_at');
            $table->enum('status', ['pending', 'confirmed', 'completed', 'cancelled'])->default('pending');
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('rentals', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('property_id')->constrained()->onDelete('cascade');
            $table->foreignId('tenant_id')->constrained('users')->onDelete('cascade'); // Locataire
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->decimal('price', 12, 2);
            $table->decimal('monthly_rent', 12, 2)->nullable();
            $table->string('payment_status')->default('unpaid');
            $table->enum('status', ['pending', 'active', 'finished', 'cancelled'])->default('pending');
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rentals');
        Schema::dropIfExists('visits');
        Schema::dropIfExists('favorites');
        Schema::dropIfExists('property_images');
        Schema::dropIfExists('properties');
    }
};
