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
        // Add phone_number_id to whatsapp_templates if not exists
        if (! Schema::hasColumn('whatsapp_templates', 'phone_number_id')) {
            Schema::table('whatsapp_templates', function (Blueprint $table) {
                $table->string('phone_number_id')->nullable()->after('whatsapp_account_id');
                $table->index('phone_number_id');
            });
        }

        // Update existing templates with phone_number_id from their whatsapp_account
        $templates = DB::table('whatsapp_templates')
            ->whereNull('phone_number_id')
            ->get();

        foreach ($templates as $template) {
            $account = DB::table('whatsapp_accounts')
                ->where('id', $template->whatsapp_account_id)
                ->first();

            if ($account) {
                DB::table('whatsapp_templates')
                    ->where('id', $template->id)
                    ->update(['phone_number_id' => $account->phone_number_id]);
            }
        }

        // Delete orphaned templates (templates without valid account)
        DB::table('whatsapp_templates')
            ->whereNull('phone_number_id')
            ->delete();

        // Make phone_number_id required (only for non-SQLite)
        if (DB::connection()->getDriverName() !== 'sqlite') {
            $templatesWithoutPhoneNumberId = DB::table('whatsapp_templates')
                ->whereNull('phone_number_id')
                ->count();

            if ($templatesWithoutPhoneNumberId === 0) {
                Schema::table('whatsapp_templates', function (Blueprint $table) {
                    $table->string('phone_number_id')->nullable(false)->change();
                });
            }
        }

        // Update unique constraint to include phone_number_id
        // First drop old constraint
        try {
            Schema::table('whatsapp_templates', function (Blueprint $table) {
                $table->dropUnique(['whatsapp_account_id', 'name', 'language']);
            });
        } catch (\Exception $e) {
            // Constraint might not exist
        }

        // Add new unique constraint with phone_number_id
        try {
            Schema::table('whatsapp_templates', function (Blueprint $table) {
                $table->unique(['phone_number_id', 'name', 'language']);
            });
        } catch (\Exception $e) {
            // Constraint might already exist
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('whatsapp_templates', function (Blueprint $table) {
            // Restore old unique constraint
            try {
                $table->dropUnique(['phone_number_id', 'name', 'language']);
            } catch (\Exception $e) {
                // Constraint might not exist
            }

            try {
                $table->unique(['whatsapp_account_id', 'name', 'language']);
            } catch (\Exception $e) {
                // Constraint might already exist
            }

            // Drop phone_number_id column
            if (Schema::hasColumn('whatsapp_templates', 'phone_number_id')) {
                $table->dropIndex(['phone_number_id']);
                $table->dropColumn('phone_number_id');
            }
        });
    }
};
