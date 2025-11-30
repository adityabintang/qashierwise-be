<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class FixMessageTypeConstraint extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'whatsapp:fix-type-constraint';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Add template type to whatsapp_messages type constraint';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Updating whatsapp_messages type constraint...');

        try {
            // Drop old constraint
            DB::statement('ALTER TABLE whatsapp_messages DROP CONSTRAINT IF EXISTS whatsapp_messages_type_check');
            $this->info('✓ Dropped old constraint');

            // Add new constraint with template type
            DB::statement("ALTER TABLE whatsapp_messages ADD CONSTRAINT whatsapp_messages_type_check CHECK (type::text = ANY (ARRAY['text'::character varying, 'image'::character varying, 'document'::character varying, 'audio'::character varying, 'video'::character varying, 'location'::character varying, 'contacts'::character varying, 'button'::character varying, 'interactive'::character varying, 'template'::character varying]::text[]))");
            $this->info('✓ Added new constraint with template type');

            $this->info('');
            $this->info('✅ Successfully updated! Template messages now allowed.');

            return 0;
        } catch (\Exception $e) {
            $this->error('Failed to update constraint: ' . $e->getMessage());
            return 1;
        }
    }
}
