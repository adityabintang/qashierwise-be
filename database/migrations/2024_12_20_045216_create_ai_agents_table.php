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
        Schema::create('ai_agents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('whatsapp_account_id')->constrained()->onDelete('cascade');
            $table->foreignId('default_store_id')->nullable()->constrained('stores')->onDelete('set null');
            $table->string('bot_name');
            $table->text('system_prompt');
            $table->json('business_info')->nullable();
            $table->boolean('order_enabled')->default(false);
            $table->boolean('is_active')->default(false);
            $table->json('settings')->nullable();
            $table->timestamps();

            $table->unique('whatsapp_account_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_agents');
    }
};
