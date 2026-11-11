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
        Schema::table('agents', function (Blueprint $table) {
            if (!Schema::hasColumn('agents', 'is_active')) {
                $table->boolean('is_active')->nullable()->after('validDate');
            }

            if (!Schema::hasColumn('agents', 'updated_at')) {
                $table->timestamp('updated_at')->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('agents', function (Blueprint $table) {
            if (Schema::hasColumn('agents', 'updated_at')) {
                $table->dropColumn('updated_at');
            }

            if (Schema::hasColumn('agents', 'is_active')) {
                $table->dropColumn('is_active');
            }
        });
    }
};
