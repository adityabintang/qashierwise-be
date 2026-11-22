<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('delivery_configs', function (Blueprint $table) {
            // Require the driver to input the recipient location/address on the
            // proof page before confirming delivery.
            $table->boolean('address_required')->default(true)->after('proof_required');
        });
    }

    public function down(): void
    {
        Schema::table('delivery_configs', function (Blueprint $table) {
            $table->dropColumn('address_required');
        });
    }
};
