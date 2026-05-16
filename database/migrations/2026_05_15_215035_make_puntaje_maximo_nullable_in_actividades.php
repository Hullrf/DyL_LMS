<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('actividades', function (Blueprint $table) {
            $table->decimal('puntaje_maximo', 8, 2)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('actividades', function (Blueprint $table) {
            $table->decimal('puntaje_maximo', 8, 2)->nullable(false)->default(5.00)->change();
        });
    }
};
