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
        Schema::table('accommodation', function (Blueprint $table) {
            if (!Schema::hasColumn('accommodation', 'is_active')) {
                $table->boolean('is_active')->nullable()->after('valid_upto');
            }

            if (!Schema::hasColumn('accommodation', 'updated_at')) {
                $table->timestamp('updated_at')->nullable()->after('created_at');
            }

            if (!Schema::hasColumn('accommodation', 'external_synced_at')) {
                $table->timestamp('external_synced_at')->nullable()->after('updated_at');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('accommodation', function (Blueprint $table) {
            if (Schema::hasColumn('accommodation', 'external_synced_at')) {
                $table->dropColumn('external_synced_at');
            }

            if (Schema::hasColumn('accommodation', 'updated_at')) {
                $table->dropColumn('updated_at');
            }

            if (Schema::hasColumn('accommodation', 'is_active')) {
                $table->dropColumn('is_active');
            }
        });
    }
};

