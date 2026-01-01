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
        Schema::table('ai_prompt_analytics', function (Blueprint $table) {
            $table->boolean('cache_hit')->default(false)->after('prompt_type');
            $table->integer('response_time_ms')->nullable()->after('cache_hit');
            $table->integer('prompt_tokens')->nullable()->after('response_time_ms');
            $table->integer('completion_tokens')->nullable()->after('prompt_tokens');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ai_prompt_analytics', function (Blueprint $table) {
            $table->dropColumn(['cache_hit', 'response_time_ms', 'prompt_tokens', 'completion_tokens']);
        });
    }
};
