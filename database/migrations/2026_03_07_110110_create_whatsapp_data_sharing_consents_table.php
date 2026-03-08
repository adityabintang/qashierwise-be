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
        Schema::create('whatsapp_data_sharing_consents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('whatsapp_account_id')->constrained('whatsapp_accounts')->onDelete('cascade');

            $table->boolean('business_profile_shared')->default(true);
            $table->boolean('contacts_shared')->default(true);
            $table->boolean('conversation_history_shared')->default(true);

            $table->unsignedTinyInteger('conversation_months')->default(6);
            $table->string('sharing_mode')->default('all'); // all|selected
            $table->json('selected_contact_ids')->nullable();

            $table->string('status')->default('pending'); // pending|active|revoked
            $table->timestamp('consent_given_at')->nullable();
            $table->timestamp('consent_updated_at')->nullable();
            $table->timestamp('synced_at')->nullable();

            $table->unsignedInteger('synced_contact_count')->default(0);
            $table->unsignedInteger('synced_message_count')->default(0);
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'whatsapp_account_id']);
            $table->index('status');
            $table->unique(['user_id', 'whatsapp_account_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('whatsapp_data_sharing_consents');
    }
};
