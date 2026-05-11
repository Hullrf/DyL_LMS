<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('preguntas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('actividad_id')->constrained('actividades')->onDelete('cascade');
            $table->longText('pregunta_texto');
            $table->enum('tipo', ['opcion_multiple', 'verdadero_falso', 'respuesta_corta']);
            $table->integer('puntaje')->default(10);
            $table->integer('orden')->default(0);
            $table->timestamps();

            $table->index('actividad_id');
            $table->index('tipo');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('preguntas');
    }
};
