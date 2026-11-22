<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_agent_conversations', function (Blueprint $table) {
            $table->timestamp('last_activity_at')->nullable()->after('updated_at');
            $table->integer('followup_count')->default(0)->after('last_activity_at');
            $table->timestamp('followup_state_started_at')->nullable()->after('followup_count');
            $table->string('pending_followup_job_id')->nullable()->after('followup_state_started_at');
            $table->timestamp('last_drip_at')->nullable()->after('pending_followup_job_id');

            // Scheduler will scan by (flow_state, last_activity_at) to pick
            // conversations due for a follow-up — keep it cheap from day one.
            $table->index(['last_activity_at', 'followup_count'], 'ai_conv_followup_scan_idx');
        });
    }

    public function down(): void
    {
        Schema::table('ai_agent_conversations', function (Blueprint $table) {
            $table->dropIndex('ai_conv_followup_scan_idx');
            $table->dropColumn([
                'last_activity_at',
                'followup_count',
                'followup_state_started_at',
                'pending_followup_job_id',
                'last_drip_at',
            ]);
        });
    }
};
