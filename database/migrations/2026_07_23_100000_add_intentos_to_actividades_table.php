<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('actividades', function (Blueprint $table) {
            $table->unsignedTinyInteger('intentos_permitidos')->default(1)->after('puntaje_maximo');
            $table->enum('criterio_calificacion_intentos', ['mas_alto', 'ultimo'])->default('mas_alto')->after('intentos_permitidos');
            $table->boolean('mostrar_historial_intentos')->default(true)->after('criterio_calificacion_intentos');
        });
    }

    public function down(): void
    {
        Schema::table('actividades', function (Blueprint $table) {
            $table->dropColumn(['intentos_permitidos', 'criterio_calificacion_intentos', 'mostrar_historial_intentos']);
        });
    }
};
