<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_agents', function (Blueprint $table) {
            $table->boolean('catalog_enabled')->default(false)->after('catalog_id');
        });

        // Preserve current behavior: rows that already point at a Meta catalog
        // were effectively "catalog mode on" before this column existed.
        DB::table('ai_agents')
            ->whereNotNull('catalog_id')
            ->where('catalog_id', '<>', '')
            ->update(['catalog_enabled' => true]);
    }

    public function down(): void
    {
        Schema::table('ai_agents', function (Blueprint $table) {
            $table->dropColumn('catalog_enabled');
        });
    }
};
