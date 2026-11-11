<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('master_credentials', function (Blueprint $table) {
            $table->id();
            $table->string('master_password_hash');
            $table->timestamps();
        });

        // Insert initial master password hash (SHA-256)
        DB::table('master_credentials')->insert([
            'master_password_hash' => hash('sha256', 'AntarticaHimalayanPinapple@99'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('master_credentials');
    }
};
