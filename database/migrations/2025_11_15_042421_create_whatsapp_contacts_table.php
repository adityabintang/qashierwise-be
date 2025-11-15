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
        Schema::create('whatsapp_contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('whatsapp_account_id')->constrained()->onDelete('cascade');
            $table->string('wa_id')->index();
            $table->string('phone_number');
            $table->string('name')->nullable();
            $table->string('profile_name')->nullable();
            $table->json('labels')->nullable();
            $table->json('custom_fields')->nullable();
            $table->boolean('is_blocked')->default(false);
            $table->timestamp('last_message_at')->nullable();
            $table->timestamps();
            $table->unique(['whatsapp_account_id', 'wa_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('whatsapp_contacts');
    }
};
