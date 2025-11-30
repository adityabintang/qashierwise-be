<?php

use Illuminate\Support\Facades\DB;

// Drop old constraint
DB::statement('ALTER TABLE whatsapp_messages DROP CONSTRAINT IF EXISTS whatsapp_messages_type_check');

// Add new constraint with template type
DB::statement("ALTER TABLE whatsapp_messages ADD CONSTRAINT whatsapp_messages_type_check CHECK (type::text = ANY (ARRAY['text'::character varying, 'image'::character varying, 'document'::character varying, 'audio'::character varying, 'video'::character varying, 'location'::character varying, 'contacts'::character varying, 'button'::character varying, 'interactive'::character varying, 'template'::character varying]::text[]))");

echo "✅ Type constraint updated successfully!\n";
echo "Template type now allowed in whatsapp_messages table.\n";
