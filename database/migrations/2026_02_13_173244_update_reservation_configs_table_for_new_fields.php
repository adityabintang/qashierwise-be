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
        Schema::table('reservation_configs', function (Blueprint $table) {
            // Rename available_days to available_slots
            $table->renameColumn('available_days', 'available_slots');

            // Drop notification_phone column
            $table->dropColumn('notification_phone');

            // Add new columns
            $table->integer('reservation_fee')->nullable()->comment('Biaya reservasi yang harus dibayar');
            $table->json('available_tables')->nullable()->comment('Array of table IDs available for reservation');
            $table->json('available_products')->nullable()->comment('Array of product IDs for menu selection');
            $table->boolean('enable_menu_selection')->default(false)->comment('Enable menu pre-selection feature');
            $table->boolean('require_menu_selection')->default(false)->comment('Make menu selection required');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('reservation_configs', function (Blueprint $table) {
            // Rename back
            $table->renameColumn('available_slots', 'available_days');

            // Restore notification_phone
            $table->string('notification_phone')->nullable()->comment('Merchant phone for reservation alerts');

            // Drop new columns
            $table->dropColumn([
                'reservation_fee',
                'available_tables',
                'available_products',
                'enable_menu_selection',
                'require_menu_selection',
            ]);
        });
    }
};
