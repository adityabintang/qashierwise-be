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
        Schema::create('whatsapp_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('whatsapp_account_id')->constrained()->onDelete('cascade');
            $table->string('template_id')->nullable();
            $table->string('name')->index();
            $table->string('language')->default('en');
            $table->enum('category', ['AUTHENTICATION', 'MARKETING', 'UTILITY'])->default('UTILITY');
            $table->enum('status', ['APPROVED', 'PENDING', 'REJECTED', 'DISABLED'])->default('PENDING');
            $table->json('components')->nullable();
            $table->text('body')->nullable();
            $table->text('header')->nullable();
            $table->text('footer')->nullable();
            $table->json('buttons')->nullable();
            $table->integer('usage_count')->default(0);
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            $table->unique(['whatsapp_account_id', 'name', 'language']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('whatsapp_templates');
    }
};
