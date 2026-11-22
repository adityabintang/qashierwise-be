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
        Schema::create('contact_tag_pivot', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contact_tag_id')->constrained('contact_tags')->onDelete('cascade');
            $table->foreignId('whatsapp_contact_id')->constrained('whatsapp_contacts')->onDelete('cascade');
            $table->timestamps();

            $table->unique(['contact_tag_id', 'whatsapp_contact_id']);
            $table->index('whatsapp_contact_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contact_tag_pivot');
    }
};
