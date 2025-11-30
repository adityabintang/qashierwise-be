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
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('wa_id')->index();
            $table->string('name')->nullable();
            $table->string('profile_pic_url')->nullable();
            $table->timestamp('last_message_at')->nullable()->index();
            $table->text('last_message_text')->nullable();
            $table->integer('unread_count')->default(0);
            $table->timestamps();
            $table->unique(['user_id', 'wa_id']);
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
