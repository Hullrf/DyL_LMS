<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('actividades', function (Blueprint $table) {
            $table->unsignedTinyInteger('ronda')->nullable()->after('titulo');
            $table->unsignedTinyInteger('numero_actividad')->nullable()->after('ronda');
            $table->unsignedTinyInteger('semana')->nullable()->after('numero_actividad');
        });
    }

    public function down(): void
    {
        Schema::table('actividades', function (Blueprint $table) {
            $table->dropColumn(['ronda', 'numero_actividad', 'semana']);
        });
    }
};
