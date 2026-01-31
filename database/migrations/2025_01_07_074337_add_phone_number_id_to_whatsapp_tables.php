<?php

use App\Models\WhatsAppAccount;
use App\Models\WhatsAppContact;
use App\Models\WhatsAppMessage;
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
        // Add phone_number_id to whatsapp_contacts if not exists
        if (! Schema::hasColumn('whatsapp_contacts', 'phone_number_id')) {
            Schema::table('whatsapp_contacts', function (Blueprint $table) {
                $table->string('phone_number_id')->nullable()->after('user_id');
                $table->index('phone_number_id');
            });
        }

        // Add phone_number_id to whatsapp_messages if not exists
        if (! Schema::hasColumn('whatsapp_messages', 'phone_number_id')) {
            Schema::table('whatsapp_messages', function (Blueprint $table) {
                $table->string('phone_number_id')->nullable()->after('user_id');
                $table->index('phone_number_id');
            });
        }

        // Update existing data using Eloquent (database-agnostic)
        // Get all users with active WhatsApp accounts
        $activeAccounts = WhatsAppAccount::withoutGlobalScopes()
            ->where('is_active', true)
            ->get();

        foreach ($activeAccounts as $account) {
            // Update contacts for this user
            WhatsAppContact::withoutGlobalScopes()
                ->where('user_id', $account->user_id)
                ->whereNull('phone_number_id')
                ->update(['phone_number_id' => $account->phone_number_id]);

            // Update messages for this user
            WhatsAppMessage::withoutGlobalScopes()
                ->where('user_id', $account->user_id)
                ->whereNull('phone_number_id')
                ->update(['phone_number_id' => $account->phone_number_id]);
        }

        // For contacts/messages still without phone_number_id, use ANY account from that user
        $allAccounts = WhatsAppAccount::withoutGlobalScopes()->get()->groupBy('user_id');

        foreach ($allAccounts as $userId => $accounts) {
            $firstAccount = $accounts->first();

            WhatsAppContact::withoutGlobalScopes()
                ->where('user_id', $userId)
                ->whereNull('phone_number_id')
                ->update(['phone_number_id' => $firstAccount->phone_number_id]);

            WhatsAppMessage::withoutGlobalScopes()
                ->where('user_id', $userId)
                ->whereNull('phone_number_id')
                ->update(['phone_number_id' => $firstAccount->phone_number_id]);
        }

        // Delete orphaned data (contacts/messages without any account)
        WhatsAppContact::withoutGlobalScopes()
            ->whereNull('phone_number_id')
            ->delete();

        WhatsAppMessage::withoutGlobalScopes()
            ->whereNull('phone_number_id')
            ->delete();

        // Make it required after data migration (only if there's no data without phone_number_id)
        $contactsWithoutPhoneNumberId = WhatsAppContact::withoutGlobalScopes()->whereNull('phone_number_id')->count();
        $messagesWithoutPhoneNumberId = WhatsAppMessage::withoutGlobalScopes()->whereNull('phone_number_id')->count();

        if ($contactsWithoutPhoneNumberId === 0 && $messagesWithoutPhoneNumberId === 0) {
            // Only change to non-nullable if all data has phone_number_id
            // This is skipped for SQLite as it doesn't support changing columns
            if (DB::connection()->getDriverName() !== 'sqlite') {
                Schema::table('whatsapp_contacts', function (Blueprint $table) {
                    $table->string('phone_number_id')->nullable(false)->change();
                });

                Schema::table('whatsapp_messages', function (Blueprint $table) {
                    $table->string('phone_number_id')->nullable(false)->change();
                });
            }
        }

        // Update unique constraint to include phone_number_id (handle if already exists)
        try {
            Schema::table('whatsapp_contacts', function (Blueprint $table) {
                $table->dropUnique(['user_id', 'wa_id']);
            });
        } catch (\Exception $e) {
            // Constraint might not exist
        }

        try {
            Schema::table('whatsapp_contacts', function (Blueprint $table) {
                $table->unique(['user_id', 'phone_number_id', 'wa_id']);
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
        Schema::table('whatsapp_contacts', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'phone_number_id', 'wa_id']);
            $table->unique(['user_id', 'wa_id']);
            $table->dropIndex(['phone_number_id']);
            $table->dropColumn('phone_number_id');
        });

        Schema::table('whatsapp_messages', function (Blueprint $table) {
            $table->dropIndex(['phone_number_id']);
            $table->dropColumn('phone_number_id');
        });
    }
};
