<?php
// database/migrations/2026_08_30_000001_add_nota_aprobatoria_to_cursos_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cursos', function (Blueprint $table) {
            $table->unsignedTinyInteger('nota_aprobatoria')
                ->default(80)
                ->after('tipo_certificado');
        });
    }

    public function down(): void
    {
        Schema::table('cursos', function (Blueprint $table) {
            $table->dropColumn('nota_aprobatoria');
        });
    }
};
