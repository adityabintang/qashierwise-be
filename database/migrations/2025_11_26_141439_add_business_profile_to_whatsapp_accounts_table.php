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
        Schema::table('whatsapp_accounts', function (Blueprint $table) {
            $table->string('about', 139)->nullable()->after('verified_name');
            $table->string('address', 256)->nullable()->after('about');
            $table->text('description')->nullable()->after('address');
            $table->string('email', 128)->nullable()->after('description');
            $table->string('vertical', 50)->nullable()->after('email');
            $table->json('websites')->nullable()->after('vertical');
            $table->string('profile_picture_url')->nullable()->after('websites');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('whatsapp_accounts', function (Blueprint $table) {
            $table->dropColumn([
                'about',
                'address',
                'description',
                'email',
                'vertical',
                'websites',
                'profile_picture_url',
            ]);
        });
    }
};
