-- Update whatsapp_messages type enum to include 'template'
ALTER TABLE whatsapp_messages DROP CONSTRAINT IF EXISTS whatsapp_messages_type_check;
ALTER TABLE whatsapp_messages ADD CONSTRAINT whatsapp_messages_type_check CHECK (type IN ('text', 'image', 'document', 'audio', 'video', 'location', 'contacts', 'button', 'interactive', 'template'));
