<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inscripciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('curso_id')->constrained('cursos')->onDelete('cascade');
            $table->date('fecha_inicio');
            $table->date('fecha_fin')->nullable();
            $table->enum('estado', ['en_progreso', 'completado', 'abandonado'])->default('en_progreso');
            $table->timestamps();

            $table->unique(['user_id', 'curso_id']);
            $table->index('user_id');
            $table->index('curso_id');
            $table->index('estado');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inscripciones');
    }
};
