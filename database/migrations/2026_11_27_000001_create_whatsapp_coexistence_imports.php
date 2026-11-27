<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_coexistence_imports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('whatsapp_account_id')->constrained()->cascadeOnDelete();
            $table->string('waba_id');
            $table->string('phone_number_id');
            $table->string('signup_hash', 64);
            $table->timestamp('onboarded_at');
            $table->timestamp('subscribed_at')->nullable();
            $table->timestamp('contacts_requested_at')->nullable();
            $table->timestamp('history_requested_at')->nullable();
            $table->string('contacts_request_id')->nullable();
            $table->string('history_request_id')->nullable();
            $table->string('contacts_status')->default('waiting');
            $table->string('history_status')->default('waiting');
            $table->unsignedTinyInteger('history_progress')->nullable();
            $table->unsignedInteger('contacts_without_phone')->default(0);
            $table->timestamps();
            $table->unique(['whatsapp_account_id', 'signup_hash'], 'wa_import_signup_unique');
        });

        Schema::table('whatsapp_accounts', function (Blueprint $table) {
            $table->foreignId('coexistence_import_id')->nullable()->constrained('whatsapp_coexistence_imports')->nullOnDelete();
        });

        Schema::create('whatsapp_coexistence_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('import_id')->constrained('whatsapp_coexistence_imports')->cascadeOnDelete();
            $table->string('field');
            $table->string('digest', 64);
            $table->longText('payload')->nullable();
            $table->json('attachment_jobs')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
            $table->unique(['import_id', 'digest']);
        });

        Schema::create('whatsapp_coexistence_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('import_id')->constrained('whatsapp_coexistence_imports')->cascadeOnDelete();
            $table->string('meta_message_id');
            $table->text('payload')->nullable();
            $table->boolean('processed')->default(false);
            $table->unique(['import_id', 'meta_message_id']);
        });

        Schema::table('whatsapp_contacts', function (Blueprint $table) {
            $table->unsignedBigInteger('coexistence_contact_timestamp')->nullable();
            $table->string('coexistence_contact_name')->nullable();
            $table->unsignedBigInteger('coexistence_contact_import_id')->nullable();
        });

        Schema::create('whatsapp_coexistence_skipped_contacts', function (Blueprint $table) {
            $table->foreignId('import_id')->constrained('whatsapp_coexistence_imports')->cascadeOnDelete();
            $table->string('identity_hash', 64);
            $table->primary(['import_id', 'identity_hash'], 'wa_import_skipped_contacts_pk');
        });

        Schema::table('whatsapp_messages', function (Blueprint $table) {
            $table->boolean('is_history_import')->default(false);
            $table->index(['contact_id', 'sent_at', 'id'], 'wa_messages_inbox_order');
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_messages', function (Blueprint $table) {
            $table->dropIndex('wa_messages_inbox_order');
            $table->dropColumn('is_history_import');
        });
        Schema::table('whatsapp_contacts', fn (Blueprint $table) => $table->dropColumn([
            'coexistence_contact_timestamp', 'coexistence_contact_name', 'coexistence_contact_import_id',
        ]));
        Schema::dropIfExists('whatsapp_coexistence_attachments');
        Schema::dropIfExists('whatsapp_coexistence_skipped_contacts');
        Schema::dropIfExists('whatsapp_coexistence_batches');
        Schema::table('whatsapp_accounts', fn (Blueprint $table) => $table->dropConstrainedForeignId('coexistence_import_id'));
        Schema::dropIfExists('whatsapp_coexistence_imports');
    }
};
