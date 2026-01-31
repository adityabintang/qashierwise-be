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
        if (Schema::hasTable('otp_codes')) {
            return;
        }

        Schema::create('otp_codes', function (Blueprint $table) {
            $table->id();
            $table->string('email');
            $table->string('code', 6);
            $table->enum('type', ['email_verification', 'password_reset']);
            $table->timestamp('expires_at');
            $table->unsignedInteger('attempts')->default(0);
            $table->timestamp('used_at')->nullable();
            $table->timestamps();

            $table->index(['email', 'type', 'expires_at']);
            $table->index('code');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('otp_codes');
    }
};
