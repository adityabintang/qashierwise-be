<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_agent_drip_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained('ai_agent_conversations')->cascadeOnDelete();
            $table->string('sequence');           // abandoned_cart | abandoned_payment | re_engagement
            $table->unsignedTinyInteger('step');  // 1..3
            $table->timestamp('fire_at');
            $table->string('status')->default('pending'); // pending|sent|cancelled|skipped
            $table->string('template_name')->nullable(); // Meta template (out-of-session)
            $table->json('payload')->nullable();         // snapshot for renderer
            $table->timestamp('fired_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'fire_at'], 'drip_schedule_scan_idx');
            $table->index(['conversation_id', 'sequence', 'status'], 'drip_conv_seq_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_agent_drip_schedules');
    }
};
