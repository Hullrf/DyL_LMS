<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('actividades', function (Blueprint $table) {
            $table->boolean('permitir_descarga_adjuntos')->default(true)->after('usa_rubrica');
        });

        Schema::table('lecciones', function (Blueprint $table) {
            $table->dropColumn('permitir_descarga_adjuntos');
        });
    }

    public function down(): void
    {
        Schema::table('lecciones', function (Blueprint $table) {
            $table->boolean('permitir_descarga_adjuntos')->default(true)->after('tipo');
        });

        Schema::table('actividades', function (Blueprint $table) {
            $table->dropColumn('permitir_descarga_adjuntos');
        });
    }
};
