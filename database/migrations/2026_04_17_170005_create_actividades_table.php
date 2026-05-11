<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('actividades', function (Blueprint $table) {
            $table->id();
            $table->foreignId('leccion_id')->constrained('lecciones')->onDelete('cascade');
            $table->enum('tipo', ['cuestionario', 'ensayo', 'tarea', 'practica']);
            $table->string('titulo');
            $table->longText('descripcion')->nullable();
            $table->integer('orden')->default(0);
            $table->integer('puntaje_maximo')->default(100);
            $table->integer('duracion_minutos')->nullable();
            $table->boolean('es_obligatoria')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index('leccion_id');
            $table->index('tipo');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('actividades');
    }
};
