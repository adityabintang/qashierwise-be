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
        Schema::create('ai_prompt_analytics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ai_agent_id')->constrained()->onDelete('cascade');
            $table->integer('tokens_used');
            $table->string('prompt_type'); // 'full', 'optimized', 'cached'
            $table->timestamp('created_at');

            $table->index(['ai_agent_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_prompt_analytics');
    }
};
