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
        Schema::dropIfExists('reservations');
        Schema::dropIfExists('reservation_flow_configs');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('reservations')) {
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
                $table->foreignId('table_id')->nullable()->constrained('tables')->nullOnDelete();
                $table->string('payment_type')->nullable();
                $table->string('payment_method')->nullable();
                $table->string('payment_label')->nullable();
                $table->foreignId('qris_transaction_id')->nullable()->constrained('qris_transactions')->nullOnDelete();
                $table->decimal('table_fee', 10, 2)->default(100000);
                $table->decimal('menu_total', 10, 2)->default(0);
                $table->decimal('total_amount', 10, 2)->default(0);
                $table->decimal('paid_amount', 10, 2)->default(0);
                $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
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

                $table->index(['payment_type', 'payment_method']);
                $table->index('payment_label');
            });
        }

        if (! Schema::hasTable('reservation_flow_configs')) {
            Schema::create('reservation_flow_configs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->onDelete('cascade');
                $table->string('flow_id')->nullable();
                $table->string('flow_name')->default('Reservasi');
                $table->enum('flow_status', ['draft', 'published', 'deprecated'])->default('draft');
                $table->time('opening_time')->default('10:00');
                $table->time('closing_time')->default('21:00');
                $table->integer('time_interval')->default(60);
                $table->json('blocked_times')->nullable();
                $table->json('operating_days')->nullable();
                $table->integer('max_advance_days')->default(30);
                $table->integer('min_advance_hours')->default(2);
                $table->integer('max_guests')->default(20);
                $table->integer('min_guests')->default(1);
                $table->boolean('enable_table_selection')->default(true);
                $table->json('available_table_ids')->nullable();
                $table->boolean('enable_menu_selection')->default(true);
                $table->json('available_product_ids')->nullable();
                $table->boolean('require_menu_selection')->default(false);
                $table->boolean('enable_payment')->default(true);
                $table->decimal('table_fee', 12, 2)->default(100000);
                $table->decimal('dp_percentage', 5, 2)->default(50.00);
                $table->boolean('allow_full_payment')->default(true);
                $table->boolean('allow_dp_payment')->default(true);
                $table->boolean('enable_qris')->default(true);
                $table->boolean('enable_cash')->default(true);
                $table->boolean('require_email')->default(false);
                $table->boolean('require_event_type')->default(false);
                $table->json('enabled_event_types')->nullable();
                $table->string('header_text')->default('📅 Buat Reservasi');
                $table->text('body_text')->nullable();
                $table->string('footer_text')->default('Powered by QashierWise');
                $table->string('cta_text')->default('Buat Reservasi');
                $table->timestamps();

                $table->unique('user_id');
            });
        }
    }
};
