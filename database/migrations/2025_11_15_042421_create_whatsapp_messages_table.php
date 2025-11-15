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
        Schema::create('whatsapp_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('whatsapp_account_id')->constrained('whatsapp_accounts')->onDelete('cascade');
            $table->foreignId('whatsapp_contact_id')->nullable()->constrained('whatsapp_contacts')->onDelete('set null');
            $table->string('message_id')->unique();
            $table->string('wam_id')->nullable()->index();
            $table->enum('direction', ['inbound', 'outbound']);
            $table->enum('status', ['sent', 'delivered', 'read', 'failed', 'pending'])->default('pending');
            $table->string('from_number')->nullable();
            $table->string('to_number')->nullable();
            $table->enum('type', ['text', 'image', 'document', 'audio', 'video', 'location', 'contacts', 'template', 'button', 'list', 'interactive'])->default('text');
            $table->text('content')->nullable();
            $table->json('media')->nullable();
            $table->json('metadata')->nullable();
            $table->string('context_message_id')->nullable();
            $table->string('template_name')->nullable();
            $table->string('template_language')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();
            $table->index(['whatsapp_account_id', 'created_at']);
            $table->index(['whatsapp_contact_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('whatsapp_messages');
    }
};
