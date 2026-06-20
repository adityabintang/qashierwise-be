<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropForeign(['product_id']);
            $table->foreignId('product_id')->nullable()->change()->constrained('products')->nullOnDelete();
            $table->string('product_name')->nullable()->after('product_id');
            $table->string('product_retailer_id')->nullable()->after('product_name');
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropForeign(['product_id']);
            $table->dropColumn(['product_name', 'product_retailer_id']);
            $table->foreignId('product_id')->nullable(false)->change()->constrained('products')->cascadeOnDelete();
        });
    }
};
