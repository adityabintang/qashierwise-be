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
        Schema::table('reservation_configs', function (Blueprint $table) {
            $table->unsignedInteger('capacity_per_slot')->default(12)->after('available_slots');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('reservation_configs', function (Blueprint $table) {
            $table->dropColumn('capacity_per_slot');
        });
    }
};
