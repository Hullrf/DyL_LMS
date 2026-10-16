<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cursos', function (Blueprint $table) {
            $table->unsignedTinyInteger('seguimiento_dias_sin_acceso')->default(7)->after('nota_aprobatoria');
            $table->unsignedTinyInteger('seguimiento_max_atrasadas')->default(2)->after('seguimiento_dias_sin_acceso');
        });
    }

    public function down(): void
    {
        Schema::table('cursos', function (Blueprint $table) {
            $table->dropColumn(['seguimiento_dias_sin_acceso', 'seguimiento_max_atrasadas']);
        });
    }
};
