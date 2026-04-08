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
        Schema::create('service_posts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('client_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('category_id')->constrained('service_categories')->onDelete('cascade');
            $table->string('title');
            $table->text('description');
            $table->string('city')->index();
            $table->string('neighborhood')->nullable()->index();
            $table->decimal('min_budget', 12, 2)->nullable();
            $table->decimal('max_budget', 12, 2)->nullable();
            $table->enum('urgency', ['low', 'medium', 'high'])->default('medium');
            $table->enum('status', ['open', 'assigned', 'closed', 'cancelled'])->default('open');
            $table->dateTime('preferred_date')->nullable();
            $table->json('images')->nullable();
            $table->timestamps();
        });

        Schema::create('service_post_responses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('post_id')->constrained('service_posts')->onDelete('cascade');
            $table->foreignId('provider_id')->constrained('users')->onDelete('cascade');
            $table->text('message');
            $table->decimal('proposed_price', 12, 2)->nullable();
            $table->enum('status', ['pending', 'accepted', 'rejected'])->default('pending');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('service_post_responses');
        Schema::dropIfExists('service_posts');
    }
};
