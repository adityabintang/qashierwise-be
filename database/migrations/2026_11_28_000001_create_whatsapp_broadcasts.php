<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_messages', function (Blueprint $table) {
            $table->index(['phone_number_id', 'direction', 'type', 'sent_at'], 'wa_messages_quota_outgoing');
            $table->index(['contact_id', 'direction', 'sent_at'], 'wa_messages_quota_incoming');
        });

        Schema::create('whatsapp_broadcast_portfolios', function (Blueprint $table) {
            $table->string('id', 32)->primary();
        });

        Schema::create('whatsapp_broadcast_wabas', function (Blueprint $table) {
            $table->string('id', 32)->primary();
            $table->string('portfolio_id', 32)->index();
        });

        Schema::create('whatsapp_broadcasts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('whatsapp_account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('phone_number_id');
            $table->string('portfolio_id', 32)->index();
            $table->uuid('idempotency_key');
            $table->string('request_hash', 64);
            $table->string('template_id', 32);
            $table->string('template_name');
            $table->string('language', 16);
            $table->json('template_payload');
            $table->json('preview');
            $table->string('template_hash', 64);
            $table->string('stop_reason')->nullable();
            $table->timestamps();
            $table->unique(['whatsapp_account_id', 'idempotency_key']);
        });

        Schema::create('whatsapp_broadcast_recipients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('whatsapp_broadcast_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contact_id')->nullable()->constrained('whatsapp_contacts')->nullOnDelete();
            $table->string('wa_id', 15);
            $table->string('status', 20);
            $table->string('reason')->nullable();
            $table->uuid('callback_id')->unique();
            $table->string('message_id')->nullable()->unique();
            $table->timestamp('attempted_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamps();
            $table->unique(['whatsapp_broadcast_id', 'wa_id']);
            $table->index(['whatsapp_broadcast_id', 'status']);
            $table->index(['status', 'attempted_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_broadcast_recipients');
        Schema::dropIfExists('whatsapp_broadcasts');
        Schema::dropIfExists('whatsapp_broadcast_wabas');
        Schema::dropIfExists('whatsapp_broadcast_portfolios');
        Schema::table('whatsapp_messages', function (Blueprint $table) {
            $table->dropIndex('wa_messages_quota_outgoing');
            $table->dropIndex('wa_messages_quota_incoming');
        });
    }
};
