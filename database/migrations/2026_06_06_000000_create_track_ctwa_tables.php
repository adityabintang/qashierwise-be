<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    private const PERMISSIONS = ['manage_track_ctwa', 'process_track_ctwa_payment', 'view_track_ctwa'];

    public function up(): void
    {
        Schema::create('ctwa_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('whatsapp_account_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('waba_id', 32);
            $table->string('dataset_id', 32);
            $table->text('events_token')->nullable();
            $table->timestamp('enabled_at')->nullable();
            $table->timestamp('first_enabled_at');
            $table->timestamps();
        });

        Schema::create('ctwa_ad_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('account_id', 32);
            $table->string('name');
            $table->string('timezone', 64);
            $table->string('currency', 3);
            $table->text('access_token');
            $table->timestamps();
            $table->unique(['user_id', 'account_id']);
        });

        Schema::create('ctwa_clicks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ctwa_setting_id')->constrained()->cascadeOnDelete();
            $table->string('contact', 80);
            $table->string('message_id');
            $table->string('source_id', 32)->nullable();
            $table->text('click_id')->nullable();
            $table->char('click_hash', 64)->nullable();
            $table->timestamp('clicked_at');
            $table->unique(['ctwa_setting_id', 'message_id']);
            $table->unique(['ctwa_setting_id', 'click_hash']);
            $table->index(['ctwa_setting_id', 'contact', 'clicked_at']);
            $table->index(['ctwa_setting_id', 'clicked_at', 'source_id']);
        });

        Schema::create('ctwa_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ctwa_setting_id')->constrained()->cascadeOnDelete();
            $table->foreignId('pos_order_id')->nullable()->unique()->constrained('orders')->nullOnDelete();
            $table->foreignId('ctwa_click_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('order_number', 80);
            $table->string('contact', 80);
            $table->decimal('recap_total', 12, 2);
            $table->decimal('paid_total', 12, 2)->nullable();
            $table->timestamp('first_recap_at');
            $table->timestamp('paid_at')->nullable()->index();
            $table->timestamps();
            $table->unique(['ctwa_setting_id', 'order_number']);
        });

        Schema::create('ctwa_recaps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ctwa_setting_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ctwa_order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('message_id');
            $table->string('contact', 80);
            $table->string('channel', 20);
            $table->text('body');
            $table->string('order_number', 80)->nullable();
            $table->decimal('total', 12, 2)->nullable();
            $table->string('review_reason')->nullable();
            $table->timestamp('sent_at');
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
            $table->unique(['ctwa_setting_id', 'message_id']);
            $table->index(['ctwa_setting_id', 'order_number', 'contact'], 'ctwa_recap_reference_index');
        });

        Schema::create('ctwa_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ctwa_order_id')->unique()->constrained()->cascadeOnDelete();
            $table->uuid('event_id')->unique();
            $table->string('dataset_id', 32);
            $table->string('waba_id', 32);
            $table->string('status', 20);
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->timestamp('next_attempt_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->string('error')->nullable();
            $table->timestamps();
            $table->index(['status', 'next_attempt_at']);
        });

        $now = now();
        foreach (self::PERMISSIONS as $name) {
            DB::table('permissions')->insertOrIgnore(['name' => $name, 'guard_name' => 'sanctum', 'created_at' => $now, 'updated_at' => $now]);
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        DB::table('permissions')->where('guard_name', 'sanctum')->whereIn('name', self::PERMISSIONS)->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Schema::dropIfExists('ctwa_deliveries');
        Schema::dropIfExists('ctwa_recaps');
        Schema::dropIfExists('ctwa_orders');
        Schema::dropIfExists('ctwa_clicks');
        Schema::dropIfExists('ctwa_ad_accounts');
        Schema::dropIfExists('ctwa_settings');
    }
};
