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
        Schema::table('whatsapp_templates', function (Blueprint $table) {
            if (!Schema::hasColumn('whatsapp_templates', 'header_type')) {
                $table->string('header_type')->nullable()->after('header');
            }
            if (!Schema::hasColumn('whatsapp_templates', 'quality_score')) {
                $table->string('quality_score')->nullable()->after('buttons');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('whatsapp_templates', function (Blueprint $table) {
            if (Schema::hasColumn('whatsapp_templates', 'header_type')) {
                $table->dropColumn('header_type');
            }
            if (Schema::hasColumn('whatsapp_templates', 'quality_score')) {
                $table->dropColumn('quality_score');
            }
        });
    }
};
