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
        Schema::table('products', function (Blueprint $table) {
            $table->string('condition')->default('Neuf')->after('category');
            $table->integer('stock')->default(1)->after('condition');
            $table->string('contact_phone')->nullable()->after('location');
            $table->string('contact_whatsapp')->nullable()->after('contact_phone');
            $table->boolean('delivery_available')->default(false)->after('contact_whatsapp');
            $table->decimal('delivery_fee', 15, 2)->nullable()->after('delivery_available');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn([
                'condition',
                'stock',
                'contact_phone',
                'contact_whatsapp',
                'delivery_available',
                'delivery_fee'
            ]);
        });
    }
};
