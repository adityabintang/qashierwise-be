<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Delivery fulfillment lifecycle — kept SEPARATE from `status` (which tracks
     * payment: pending/paid/cancelled) so existing payment logic & UI badges
     * stay untouched.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // null = order hasn't entered the fulfillment lifecycle yet.
            // awaiting_confirmation | confirmed | out_for_delivery | delivered | complaint
            $table->string('fulfillment_status')->nullable()->after('status');

            // Driver/courier assigned at merchant-confirm time.
            $table->foreignId('delivery_driver_id')->nullable()->after('fulfillment_status')
                ->constrained('delivery_drivers')->nullOnDelete();
            $table->string('courier_name')->nullable()->after('delivery_driver_id');
            $table->string('courier_phone')->nullable()->after('courier_name');

            // Secret token for the public driver proof-upload page.
            $table->string('delivery_token', 80)->nullable()->unique()->after('courier_phone');

            // Proof of delivery + customer geo (optional; address text is baseline).
            $table->string('proof_image_url')->nullable()->after('delivery_token');
            $table->decimal('customer_lat', 10, 7)->nullable()->after('proof_image_url');
            $table->decimal('customer_lng', 10, 7)->nullable()->after('customer_lat');

            // Stage timestamps.
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('out_for_delivery_at')->nullable();
            $table->timestamp('delivered_at')->nullable();

            // Complaint escalation note (handled by human PIC).
            $table->text('complaint_note')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('delivery_driver_id');
            $table->dropColumn([
                'fulfillment_status',
                'courier_name',
                'courier_phone',
                'delivery_token',
                'proof_image_url',
                'customer_lat',
                'customer_lng',
                'confirmed_at',
                'out_for_delivery_at',
                'delivered_at',
                'complaint_note',
            ]);
        });
    }
};
