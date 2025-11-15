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
        Schema::create('whatsapp_media', function (Blueprint $table) {
            $table->id();
            $table->foreignId('whatsapp_account_id')->constrained()->onDelete('cascade');
            $table->foreignId('message_id')->nullable()->constrained('whatsapp_messages')->onDelete('set null');
            $table->string('media_id')->unique();
            $table->string('mime_type');
            $table->string('filename')->nullable();
            $table->bigInteger('file_size')->nullable();
            $table->string('url')->nullable();
            $table->string('local_path')->nullable();
            $table->enum('type', ['image', 'document', 'audio', 'video', 'sticker']);
            $table->string('sha256')->nullable();
            $table->timestamp('downloaded_at')->nullable();
            $table->timestamps();
            $table->index(['whatsapp_account_id', 'type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('whatsapp_media');
    }
};
