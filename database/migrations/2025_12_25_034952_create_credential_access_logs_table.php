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
        Schema::create('credential_access_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('credential_id')->nullable()->constrained('payment_provider_credentials')->onDelete('cascade');
            $table->enum('action', ['create', 'read', 'update', 'delete', 'decrypt', 'validate', 'rls_violation']);
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->boolean('success')->default(true);
            $table->text('error_message')->nullable();
            $table->timestamp('created_at')->useCurrent();

            // Indexes for audit queries
            $table->index(['user_id', 'action', 'created_at'], 'idx_user_action');
            $table->index(['credential_id', 'created_at'], 'idx_credential_access');
            $table->index(['action', 'success', 'created_at'], 'idx_action_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('credential_access_logs');
    }
};
