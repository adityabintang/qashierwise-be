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
            // Reminder settings
            $table->boolean('reminder_enabled')->default(false)->after('require_menu_selection');
            $table->string('reminder_template')->nullable()->after('reminder_enabled');
            $table->string('reminder_template_language')->default('id')->after('reminder_template');
            $table->json('reminder_param_mapping')->nullable()->after('reminder_template_language');
            $table->json('reminder_timing')->nullable()->after('reminder_param_mapping');

            // Qstash related - store scheduled job IDs for cancellation
            $table->json('scheduled_reminder_jobs')->nullable()->after('reminder_timing');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('reservation_configs', function (Blueprint $table) {
            $table->dropColumn([
                'reminder_enabled',
                'reminder_template',
                'reminder_template_language',
                'reminder_param_mapping',
                'reminder_timing',
                'scheduled_reminder_jobs',
            ]);
        });
    }
};
