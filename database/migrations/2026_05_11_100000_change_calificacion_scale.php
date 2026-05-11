<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. actividades: puntaje_maximo integer → decimal(5,2) + usa_rubrica
        Schema::table('actividades', function (Blueprint $table) {
            $table->decimal('puntaje_maximo_nuevo', 5, 2)->default(5.00)->after('orden');
            $table->boolean('usa_rubrica')->default(false)->after('puntaje_maximo_nuevo');
        });
        DB::statement('UPDATE actividades SET puntaje_maximo_nuevo = 5.00');
        Schema::table('actividades', function (Blueprint $table) {
            $table->dropColumn('puntaje_maximo');
        });
        Schema::table('actividades', function (Blueprint $table) {
            $table->renameColumn('puntaje_maximo_nuevo', 'puntaje_maximo');
        });

        // 2. preguntas: puntaje integer → decimal(5,2)
        Schema::table('preguntas', function (Blueprint $table) {
            $table->decimal('puntaje_nuevo', 5, 2)->default(1.00)->after('tipo');
        });
        DB::statement('UPDATE preguntas SET puntaje_nuevo = puntaje');
        Schema::table('preguntas', function (Blueprint $table) {
            $table->dropColumn('puntaje');
        });
        Schema::table('preguntas', function (Blueprint $table) {
            $table->renameColumn('puntaje_nuevo', 'puntaje');
        });

        // 3. respuestas_estudiantes: calificacion integer → decimal(5,2)
        //    Convertir proporcionalmente: old/100*5
        Schema::table('respuestas_estudiantes', function (Blueprint $table) {
            $table->decimal('calificacion_nueva', 5, 2)->nullable()->after('respuesta');
        });
        DB::statement('UPDATE respuestas_estudiantes SET calificacion_nueva = ROUND(calificacion / 100 * 5, 2) WHERE calificacion IS NOT NULL');
        Schema::table('respuestas_estudiantes', function (Blueprint $table) {
            $table->dropColumn('calificacion');
        });
        Schema::table('respuestas_estudiantes', function (Blueprint $table) {
            $table->renameColumn('calificacion_nueva', 'calificacion');
        });
    }

    public function down(): void
    {
        Schema::table('actividades', function (Blueprint $table) {
            $table->dropColumn('usa_rubrica');
            $table->integer('puntaje_maximo_old')->default(100)->after('orden');
        });
        DB::statement('UPDATE actividades SET puntaje_maximo_old = 100');
        Schema::table('actividades', function (Blueprint $table) {
            $table->dropColumn('puntaje_maximo');
        });
        Schema::table('actividades', function (Blueprint $table) {
            $table->renameColumn('puntaje_maximo_old', 'puntaje_maximo');
        });

        Schema::table('respuestas_estudiantes', function (Blueprint $table) {
            $table->integer('calificacion_old')->nullable()->after('respuesta');
        });
        DB::statement('UPDATE respuestas_estudiantes SET calificacion_old = ROUND(calificacion / 5 * 100) WHERE calificacion IS NOT NULL');
        Schema::table('respuestas_estudiantes', function (Blueprint $table) {
            $table->dropColumn('calificacion');
        });
        Schema::table('respuestas_estudiantes', function (Blueprint $table) {
            $table->renameColumn('calificacion_old', 'calificacion');
        });

        Schema::table('preguntas', function (Blueprint $table) {
            $table->integer('puntaje_old')->default(10)->after('tipo');
        });
        DB::statement('UPDATE preguntas SET puntaje_old = ROUND(puntaje)');
        Schema::table('preguntas', function (Blueprint $table) {
            $table->dropColumn('puntaje');
        });
        Schema::table('preguntas', function (Blueprint $table) {
            $table->renameColumn('puntaje_old', 'puntaje');
        });
    }
};
