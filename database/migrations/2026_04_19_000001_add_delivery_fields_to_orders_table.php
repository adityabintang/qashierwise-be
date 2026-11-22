<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->enum('delivery_type', ['pickup', 'delivery'])->default('pickup')->after('source');
            $table->text('alamat')->nullable()->after('delivery_type');
            $table->decimal('ongkir', 12, 2)->default(0)->after('alamat');
            $table->text('catatan')->nullable()->after('ongkir');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['delivery_type', 'alamat', 'ongkir', 'catatan']);
        });
    }
};
