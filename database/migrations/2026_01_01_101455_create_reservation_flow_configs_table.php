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
        Schema::create('reservation_flow_configs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');

            // Flow Identity
            $table->string('flow_id')->nullable();
            $table->string('flow_name')->default('Reservasi');
            $table->enum('flow_status', ['draft', 'published', 'deprecated'])->default('draft');

            // Time Configuration
            $table->time('opening_time')->default('10:00');
            $table->time('closing_time')->default('21:00');
            $table->integer('time_interval')->default(60); // in minutes
            $table->json('blocked_times')->nullable();
            $table->json('operating_days')->nullable(); // [1,2,3,4,5,6,7] Mon-Sun

            // Booking Configuration
            $table->integer('max_advance_days')->default(30);
            $table->integer('min_advance_hours')->default(2);
            $table->integer('max_guests')->default(20);
            $table->integer('min_guests')->default(1);

            // Table Configuration
            $table->boolean('enable_table_selection')->default(true);
            $table->json('available_table_ids')->nullable();

            // Menu Configuration
            $table->boolean('enable_menu_selection')->default(true);
            $table->json('available_product_ids')->nullable();
            $table->boolean('require_menu_selection')->default(false);

            // Payment Configuration
            $table->boolean('enable_payment')->default(true);
            $table->decimal('table_fee', 12, 2)->default(100000);
            $table->decimal('dp_percentage', 5, 2)->default(50.00);
            $table->boolean('allow_full_payment')->default(true);
            $table->boolean('allow_dp_payment')->default(true);
            $table->boolean('enable_qris')->default(true);
            $table->boolean('enable_cash')->default(true);

            // Customer Data Configuration
            $table->boolean('require_email')->default(false);
            $table->boolean('require_event_type')->default(false);
            $table->json('enabled_event_types')->nullable();

            // Messages
            $table->string('header_text')->default('📅 Buat Reservasi');
            $table->text('body_text')->nullable();
            $table->string('footer_text')->default('Powered by QashierWise');
            $table->string('cta_text')->default('Buat Reservasi');

            $table->timestamps();

            $table->unique('user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reservation_flow_configs');
    }
};
