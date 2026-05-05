<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('marketplace_orders', function (Blueprint $table) {
            $table->dropUnique(['transaction_reference']);
        });
    }

    public function down(): void
    {
        Schema::table('marketplace_orders', function (Blueprint $table) {
            $table->unique('transaction_reference');
        });
    }
};
