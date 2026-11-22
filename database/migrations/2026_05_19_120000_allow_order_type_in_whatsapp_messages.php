<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add 'order', 'sticker', 'reaction', 'unsupported' to the type CHECK
     * constraint on whatsapp_messages. Without 'order' the webhook handler
     * crashes when storing a Catalog Order webhook (interactive cart submission).
     *
     * Postgres-specific — uses raw ALTER TABLE since Laravel's Schema builder
     * doesn't expose CHECK constraint updates portably.
     */
    public function up(): void
    {
        if (! Schema::hasTable('whatsapp_messages')) {
            return;
        }
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE whatsapp_messages DROP CONSTRAINT IF EXISTS whatsapp_messages_type_check');
        DB::statement(<<<'SQL'
            ALTER TABLE whatsapp_messages ADD CONSTRAINT whatsapp_messages_type_check
            CHECK (type::text = ANY (ARRAY[
                'text','image','document','audio','video','location',
                'contacts','button','interactive','template',
                'order','sticker','reaction','unsupported','system'
            ]::text[]))
        SQL);
    }

    public function down(): void
    {
        if (! Schema::hasTable('whatsapp_messages')) {
            return;
        }
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE whatsapp_messages DROP CONSTRAINT IF EXISTS whatsapp_messages_type_check');
        DB::statement(<<<'SQL'
            ALTER TABLE whatsapp_messages ADD CONSTRAINT whatsapp_messages_type_check
            CHECK (type::text = ANY (ARRAY[
                'text','image','document','audio','video','location',
                'contacts','button','interactive','template'
            ]::text[]))
        SQL);
    }
};
