<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('reservations')) {
            return;
        }
        
        Schema::create('reservations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('whatsapp_contact_id')->nullable()->constrained('whatsapp_contacts')->onDelete('set null');
            $table->string('customer_name');
            $table->string('phone')->nullable();
            $table->date('reservation_date');
            $table->string('reservation_time');
            $table->integer('guest_count')->default(1);
            $table->string('email')->nullable();
            $table->string('event_type')->nullable();
            $table->text('special_notes')->nullable();
            $table->json('preferences')->nullable();
            $table->decimal('deposit', 10, 2)->default(0);
            $table->boolean('deposit_paid')->default(false);
            $table->json('pre_order_items')->nullable();
            $table->string('flow_token')->nullable();
            $table->string('flow_id')->nullable();
            $table->string('status')->default('pending');
            $table->text('cancellation_reason')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->boolean('reminder_sent')->default(false);
            $table->timestamp('reminder_sent_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reservations');
    }
};
