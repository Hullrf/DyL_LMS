<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('respuestas_estudiantes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('actividad_id')->constrained('actividades')->onDelete('cascade');
            $table->longText('respuesta');
            $table->integer('calificacion')->nullable();
            $table->enum('estado', ['sin_calificar', 'calificada', 'en_revision'])->default('sin_calificar');
            $table->longText('feedback')->nullable();
            $table->timestamp('fecha_envio');
            $table->timestamp('fecha_calificacion')->nullable();
            $table->timestamps();

            $table->index('user_id');
            $table->index('actividad_id');
            $table->index('estado');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('respuestas_estudiantes');
    }
};
