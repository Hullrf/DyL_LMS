<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('selecciones_rubrica', function (Blueprint $table) {
            $table->id();
            $table->foreignId('respuesta_estudiante_id')
                  ->constrained('respuestas_estudiantes')->onDelete('cascade');
            $table->foreignId('criterio_id')
                  ->constrained('criterios_rubrica')->onDelete('cascade');
            $table->foreignId('nivel_criterio_id')
                  ->constrained('niveles_criterio')->onDelete('cascade');
            $table->timestamps();
            $table->unique(['respuesta_estudiante_id', 'criterio_id'], 'unique_seleccion');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('selecciones_rubrica');
    }
};
