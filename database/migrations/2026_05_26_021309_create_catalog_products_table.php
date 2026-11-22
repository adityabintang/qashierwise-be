<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('catalog_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('catalog_id');
            $table->string('meta_product_id')->nullable();
            $table->string('retailer_id');
            $table->string('name');
            $table->decimal('price', 15, 2)->default(0);
            $table->char('currency', 3)->default('IDR');
            $table->integer('stock_quantity')->default(0);
            $table->boolean('is_available')->default(true);
            $table->string('category')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'catalog_id', 'retailer_id']);
            $table->index(['user_id', 'catalog_id']);
            $table->index(['user_id', 'retailer_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('catalog_products');
    }
};
