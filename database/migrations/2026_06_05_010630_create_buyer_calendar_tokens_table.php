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
        Schema::create('buyer_calendar_tokens', function (Blueprint $table) {
            $table->id();
            // Keyed by the buyer's email — used to look up their stored token
            // when a new reservation is confirmed and needs to be added to their calendar.
            $table->string('email')->unique();
            // Full Google token JSON (access_token + refresh_token + expiry).
            $table->text('access_token');
            // The Google Calendar ID the buyer authorised us to write to.
            // Defaults to 'primary' (their main calendar).
            $table->string('calendar_id')->default('primary');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('buyer_calendar_tokens');
    }
};
