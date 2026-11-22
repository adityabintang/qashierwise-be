<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Per-merchant delivery configuration surfaced on /dashboard/delivery.
     */
    public function up(): void
    {
        Schema::create('delivery_configs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->boolean('is_active')->default(true);
            $table->decimal('default_ongkir', 12, 2)->default(0);
            // Require driver to upload a photo before the "received?" question fires.
            $table->boolean('proof_required')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_configs');
    }
};
