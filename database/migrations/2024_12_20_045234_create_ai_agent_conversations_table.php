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
        Schema::create('ai_agent_conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ai_agent_id')->constrained()->onDelete('cascade');
            $table->foreignId('whatsapp_contact_id')->constrained()->onDelete('cascade');
            $table->json('messages');
            $table->json('order_context')->nullable();
            $table->timestamp('expires_at');
            $table->timestamps();

            $table->unique(['ai_agent_id', 'whatsapp_contact_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_agent_conversations');
    }
};
