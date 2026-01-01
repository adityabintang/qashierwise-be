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
        Schema::create('reservations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('whatsapp_contact_id')->nullable()->constrained('whatsapp_contacts')->onDelete('set null');

            // Required fields
            $table->string('customer_name');
            $table->string('phone');
            $table->date('reservation_date');
            $table->time('reservation_time');
            $table->unsignedInteger('guest_count')->default(1);

            // Optional fields
            $table->string('email')->nullable();
            $table->string('event_type')->nullable(); // regular, birthday, meeting, anniversary, other
            $table->text('special_notes')->nullable();
            $table->json('preferences')->nullable(); // window, quiet, smoking, baby_chair
            $table->decimal('deposit', 12, 2)->default(0);
            $table->boolean('deposit_paid')->default(false);

            // Pre-order menu (JSON array of product IDs and quantities)
            $table->json('pre_order_items')->nullable();

            // WhatsApp Flow tracking
            $table->string('flow_token')->nullable()->unique();
            $table->string('flow_id')->nullable();

            // Status management
            $table->enum('status', [
                'pending',
                'confirmed',
                'cancelled',
                'completed',
                'no_show',
            ])->default('pending');
            $table->text('cancellation_reason')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('completed_at')->nullable();

            // Reminder tracking
            $table->boolean('reminder_sent')->default(false);
            $table->timestamp('reminder_sent_at')->nullable();

            $table->timestamps();

            // Indexes for common queries
            $table->index(['user_id', 'reservation_date']);
            $table->index(['status', 'reservation_date']);
            $table->index('phone');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reservations');
    }
};
