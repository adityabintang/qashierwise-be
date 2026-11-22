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
            $table->boolean('auto_cleanup_enabled')->default(false)->after('scheduled_reminder_jobs');
            $table->date('auto_cleanup_reference_date')->nullable()->after('auto_cleanup_enabled');
            $table->json('available_slots_metadata')->nullable()->after('available_slots');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('reservation_configs', function (Blueprint $table) {
            $table->dropColumn(['auto_cleanup_enabled', 'auto_cleanup_reference_date', 'available_slots_metadata']);
        });
    }
};
