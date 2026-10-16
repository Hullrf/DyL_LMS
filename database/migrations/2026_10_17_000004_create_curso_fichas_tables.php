<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('curso_fichas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('curso_id')->unique()->constrained('cursos')->onDelete('cascade');
            $table->text('justificacion')->nullable();
            $table->text('resultado_aprendizaje')->nullable();
            $table->json('resultados_especificos')->nullable();
            $table->unsignedTinyInteger('creditos')->nullable();
            $table->unsignedTinyInteger('semestre')->nullable();
            $table->text('prerrequisitos')->nullable();
            $table->string('programa')->nullable();
            $table->string('facultad')->nullable();
            $table->timestamps();
        });

        Schema::create('curso_bibliografias', function (Blueprint $table) {
            $table->id();
            $table->foreignId('curso_id')->constrained('cursos')->onDelete('cascade');
            $table->string('autor');
            $table->unsignedSmallInteger('anio')->nullable();
            $table->string('titulo');
            $table->string('editorial')->nullable();
            $table->unsignedSmallInteger('orden')->default(0);
            $table->timestamps();

            $table->index('curso_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('curso_bibliografias');
        Schema::dropIfExists('curso_fichas');
    }
};
