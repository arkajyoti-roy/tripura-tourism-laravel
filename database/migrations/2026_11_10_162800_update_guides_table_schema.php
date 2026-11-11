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
        Schema::table('guides', function (Blueprint $table) {
            if (!Schema::hasColumn('guides', 'email')) {
                $table->string('email')->nullable()->after('phone');
            }

            if (!Schema::hasColumn('guides', 'is_active')) {
                $table->boolean('is_active')->nullable()->after('validDate');
            }

            if (!Schema::hasColumn('guides', 'updated_at')) {
                $table->timestamp('updated_at')->nullable()->after('spot');
            }

            if (!Schema::hasColumn('guides', 'external_synced_at')) {
                $table->timestamp('external_synced_at')->nullable()->after('updated_at');
            }

            if (Schema::hasColumn('guides', 'updateDate')) {
                $table->dropColumn('updateDate');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('guides', function (Blueprint $table) {
            if (!Schema::hasColumn('guides', 'updateDate')) {
                $table->string('updateDate')->nullable();
            }

            if (Schema::hasColumn('guides', 'external_synced_at')) {
                $table->dropColumn('external_synced_at');
            }

            if (Schema::hasColumn('guides', 'updated_at')) {
                $table->dropColumn('updated_at');
            }

            if (Schema::hasColumn('guides', 'is_active')) {
                $table->dropColumn('is_active');
            }

            if (Schema::hasColumn('guides', 'email')) {
                $table->dropColumn('email');
            }
        });
    }
};

