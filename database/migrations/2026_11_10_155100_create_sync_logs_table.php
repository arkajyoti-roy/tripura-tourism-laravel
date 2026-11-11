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
        Schema::create('sync_logs', function (Blueprint $table) {
            $table->id();
            $table->string('sync_type', 50)->index(); // 'agents' or 'homestays' or 'all'
            $table->integer('service_code')->nullable(); // 75, 76
            $table->timestamp('last_synced_at')->nullable();
            $table->integer('records_received')->default(0);
            $table->integer('records_synced')->default(0);
            $table->string('status', 20)->default('success'); // 'success', 'failed'
            $table->text('message')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sync_logs');
    }
};

