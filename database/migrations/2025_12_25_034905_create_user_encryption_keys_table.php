<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('user_encryption_keys', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->onDelete('cascade');
            $table->text('encryption_key_encrypted');
            $table->integer('key_version')->default(1);
            $table->timestamps();
            
            // Index for performance
            $table->index('user_id', 'idx_user_key_lookup');
        });

        // Enable Row Level Security for PostgreSQL
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE user_encryption_keys ENABLE ROW LEVEL SECURITY');
            
            // Policy: Users can only access their own encryption key
            DB::statement("
                CREATE POLICY user_keys_access ON user_encryption_keys
                FOR ALL
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
            DB::statement('DROP POLICY IF EXISTS user_keys_access ON user_encryption_keys');
        }
        
        Schema::dropIfExists('user_encryption_keys');
    }
};
