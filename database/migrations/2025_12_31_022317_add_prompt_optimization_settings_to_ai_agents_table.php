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
        Schema::table('ai_agents', function (Blueprint $table) {
            $table->boolean('use_optimized_prompt')->default(true)->after('settings');
            $table->boolean('enable_prompt_caching')->default(true)->after('use_optimized_prompt');
            $table->integer('product_sample_limit')->default(10)->after('enable_prompt_caching');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ai_agents', function (Blueprint $table) {
            $table->dropColumn(['use_optimized_prompt', 'enable_prompt_caching', 'product_sample_limit']);
        });
    }
};
