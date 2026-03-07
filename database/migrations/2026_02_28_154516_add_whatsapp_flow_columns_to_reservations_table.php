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
        Schema::table('reservations', function (Blueprint $table) {
            // WhatsApp Flow identity
            $table->string('flow_token')->nullable()->index()->after('order_id');
            $table->string('flow_id')->nullable()->after('flow_token');

            // WhatsApp contact link
            $table->foreignId('whatsapp_contact_id')->nullable()->constrained('whatsapp_contacts')->nullOnDelete()->after('flow_id');

            // Extended booking fields
            $table->string('event_type')->nullable()->after('whatsapp_contact_id');
            $table->text('special_notes')->nullable()->after('event_type');
            $table->json('preferences')->nullable()->after('special_notes');

            // Pricing breakdown
            $table->decimal('table_fee', 12, 2)->default(0)->after('preferences');
            $table->decimal('menu_total', 12, 2)->default(0)->after('table_fee');
            $table->decimal('deposit', 12, 2)->nullable()->after('menu_total');
            $table->boolean('deposit_paid')->default(false)->after('deposit');

            // Payment metadata
            $table->string('payment_label')->nullable()->after('payment_method');

            // Confirmation timestamp
            $table->timestamp('confirmed_at')->nullable()->after('notified_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->dropForeign(['whatsapp_contact_id']);
            $table->dropIndex(['flow_token']);
            $table->dropColumn([
                'flow_token',
                'flow_id',
                'whatsapp_contact_id',
                'event_type',
                'special_notes',
                'preferences',
                'table_fee',
                'menu_total',
                'deposit',
                'deposit_paid',
                'payment_label',
                'confirmed_at',
            ]);
        });
    }
};
