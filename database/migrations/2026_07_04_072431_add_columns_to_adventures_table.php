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
        Schema::table('adventures', function (Blueprint $table) {
            $table->string('location')->nullable();
            $table->string('image')->nullable();
            $table->string('price')->nullable();
            $table->string('contact_phone')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('adventures', function (Blueprint $table) {
            $table->dropColumn(['location', 'image', 'price', 'contact_phone']);
        });
    }
};
