<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contact_tags', function (Blueprint $table) {
            // System tags shared across all merchants (user_id = null)
            $table->boolean('is_system')->default(false)->after('color');

            // Drop existing FK and unique so we can make user_id nullable
            $table->dropForeign(['user_id']);
            $table->dropUnique('contact_tags_user_id_name_unique');

            $table->unsignedBigInteger('user_id')->nullable()->change();

            // Re-add FK (nullable → null on merchant delete won't cascade system tags)
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');

            // Unique per merchant; system tags (user_id=null) are unique by name at app level
            $table->unique(['user_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::table('contact_tags', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropUnique('contact_tags_user_id_name_unique');
            $table->dropColumn('is_system');
            $table->unsignedBigInteger('user_id')->nullable(false)->change();
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->unique(['user_id', 'name']);
        });
    }
};
