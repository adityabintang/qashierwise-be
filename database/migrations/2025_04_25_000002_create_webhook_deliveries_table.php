<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('webhook_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('webhook_id')->constrained()->cascadeOnDelete();
            $table->string('event_type')->comment('Type of event: message.incoming, message.status_updated, template.status_changed');
            $table->json('payload');
            $table->enum('status', ['pending', 'delivered', 'failed'])->default('pending');
            $table->integer('attempt_count')->default(0);
            $table->text('last_error')->nullable();
            $table->dateTime('next_retry_at')->nullable();
            $table->timestamps();

            $table->index('webhook_id');
            $table->index(['status', 'next_retry_at']);
            $table->index(['webhook_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_deliveries');
    }
};
