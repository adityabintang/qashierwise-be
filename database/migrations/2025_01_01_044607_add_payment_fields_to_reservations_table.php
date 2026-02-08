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
            // Table relationship
            $table->foreignId('table_id')->nullable()->after('pre_order_items')
                ->constrained('tables')->nullOnDelete();

            // Payment fields
            $table->string('payment_type')->nullable()->after('table_id')
                ->comment('dp or lunas');
            $table->string('payment_method')->nullable()->after('payment_type')
                ->comment('qris or cash');
            $table->string('payment_label')->nullable()->after('payment_method')
                ->comment('DP or LUNAS - for display');

            // QRIS transaction relationship
            $table->foreignId('qris_transaction_id')->nullable()->after('payment_label')
                ->constrained('qris_transactions')->nullOnDelete();

            // Pricing breakdown
            $table->decimal('table_fee', 10, 2)->default(100000)->after('qris_transaction_id')
                ->comment('Fee for table reservation');
            $table->decimal('menu_total', 10, 2)->default(0)->after('table_fee')
                ->comment('Total of pre-ordered menu items');
            $table->decimal('total_amount', 10, 2)->default(0)->after('menu_total')
                ->comment('Grand total = menu_total + table_fee');
            $table->decimal('paid_amount', 10, 2)->default(0)->after('total_amount')
                ->comment('Amount already paid (DP or full)');

            // Order relationship (for pre-ordered items)
            $table->foreignId('order_id')->nullable()->after('paid_amount')
                ->constrained('orders')->nullOnDelete();

            // Index for queries
            $table->index(['payment_type', 'payment_method']);
            $table->index('payment_label');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            // Drop indexes
            $table->dropIndex(['payment_type', 'payment_method']);
            $table->dropIndex(['payment_label']);

            // Drop foreign keys and columns
            $table->dropForeign(['table_id']);
            $table->dropForeign(['qris_transaction_id']);
            $table->dropForeign(['order_id']);

            $table->dropColumn([
                'table_id',
                'payment_type',
                'payment_method',
                'payment_label',
                'qris_transaction_id',
                'table_fee',
                'menu_total',
                'total_amount',
                'paid_amount',
                'order_id',
            ]);
        });
    }
};
