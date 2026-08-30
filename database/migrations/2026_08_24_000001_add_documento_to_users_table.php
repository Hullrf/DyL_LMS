<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('numero_documento', 30)->nullable()->after('empresa');
            $table->string('ciudad_expedicion', 100)->nullable()->after('numero_documento');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['numero_documento', 'ciudad_expedicion']);
        });
    }
};
