<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type');          // visit, message, alert, system, payment, application
            $table->string('title');
            $table->text('message');
            $table->text('detail')->nullable();
            $table->string('icon')->default('bell');
            $table->string('icon_bg')->default('bg-blue-100');
            $table->string('icon_color')->default('text-blue-600');
            $table->string('action_label')->nullable();
            $table->string('action_link')->nullable();
            $table->string('reference_type')->nullable(); // App\Models\Visit, App\Models\Rental, etc.
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->boolean('is_read')->default(false);
            $table->timestamps();

            $table->index(['user_id', 'is_read']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
