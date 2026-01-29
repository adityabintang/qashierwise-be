<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('payment_provider_credentials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->enum('provider', ['doku', 'xendit', 'midtrans', 'duitku']);
            $table->text('credentials_encrypted');
            $table->boolean('is_active')->default(false);
            $table->enum('connection_status', ['pending', 'valid', 'invalid'])->default('pending');
            $table->text('validation_error')->nullable();
            $table->timestamp('last_validated_at')->nullable();
            $table->timestamps();

            // Unique constraint: one credential per user per provider
            $table->unique(['user_id', 'provider'], 'unique_user_provider');

            // Indexes for performance
            $table->index(['user_id', 'is_active'], 'idx_user_active');
            $table->index(['provider', 'connection_status'], 'idx_provider_status');
        });

        // Enable Row Level Security for PostgreSQL
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE payment_provider_credentials ENABLE ROW LEVEL SECURITY');

            // Policy: Users can only see their own credentials
            DB::statement("
                CREATE POLICY user_credentials_select ON payment_provider_credentials
                FOR SELECT
                USING (user_id = NULLIF(current_setting('app.current_user_id', true), '')::BIGINT)
            ");

            // Policy: Users can only insert their own credentials
            DB::statement("
                CREATE POLICY user_credentials_insert ON payment_provider_credentials
                FOR INSERT
                WITH CHECK (user_id = NULLIF(current_setting('app.current_user_id', true), '')::BIGINT)
            ");

            // Policy: Users can only update their own credentials
            DB::statement("
                CREATE POLICY user_credentials_update ON payment_provider_credentials
                FOR UPDATE
                USING (user_id = NULLIF(current_setting('app.current_user_id', true), '')::BIGINT)
            ");

            // Policy: Users can only delete their own credentials
            DB::statement("
                CREATE POLICY user_credentials_delete ON payment_provider_credentials
                FOR DELETE
                USING (user_id = NULLIF(current_setting('app.current_user_id', true), '')::BIGINT)
            ");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop RLS policies for PostgreSQL
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('DROP POLICY IF EXISTS user_credentials_delete ON payment_provider_credentials');
            DB::statement('DROP POLICY IF EXISTS user_credentials_update ON payment_provider_credentials');
            DB::statement('DROP POLICY IF EXISTS user_credentials_insert ON payment_provider_credentials');
            DB::statement('DROP POLICY IF EXISTS user_credentials_select ON payment_provider_credentials');
        }

        Schema::dropIfExists('payment_provider_credentials');
    }
};
