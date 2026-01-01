<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Enable prompt caching for all existing AI agents
        DB::table('ai_agents')->update([
            'enable_prompt_caching' => true,
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Disable prompt caching for all AI agents
        DB::table('ai_agents')->update([
            'enable_prompt_caching' => false,
        ]);
    }
};
