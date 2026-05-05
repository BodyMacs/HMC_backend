<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketplace_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('buyer_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('product_id')->constrained('products')->onDelete('cascade');
            $table->integer('quantity')->default(1);
            $table->decimal('amount', 15, 2);
            $table->decimal('delivery_fee', 15, 2)->default(0);
            $table->string('status')->default('pending'); // pending, paid_escrow, shipped, delivered, completed, cancelled, refunded
            $table->string('transaction_reference')->nullable()->unique();
            $table->string('seller_disbursement_status')->default('pending'); // pending, disbursed, cancelled
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_orders');
    }
};
