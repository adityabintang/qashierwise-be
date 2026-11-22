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
        // Drop the existing CHECK constraint and recreate it with 'reservasi' included.
        DB::statement('ALTER TABLE orders DROP CONSTRAINT IF EXISTS orders_delivery_type_check');
        DB::statement("ALTER TABLE orders ADD CONSTRAINT orders_delivery_type_check CHECK (delivery_type IN ('pickup', 'delivery', 'reservasi'))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE orders DROP CONSTRAINT IF EXISTS orders_delivery_type_check');
        DB::statement("ALTER TABLE orders ADD CONSTRAINT orders_delivery_type_check CHECK (delivery_type IN ('pickup', 'delivery'))");
    }
};
