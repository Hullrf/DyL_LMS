<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('progreso_lecciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('leccion_id')->constrained('lecciones')->onDelete('cascade');
            $table->boolean('completado')->default(false);
            $table->timestamp('fecha_completado')->nullable();
            $table->integer('tiempo_dedicado_minutos')->default(0);
            $table->timestamps();

            $table->unique(['user_id', 'leccion_id']);
            $table->index('user_id');
            $table->index('leccion_id');
            $table->index('completado');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('progreso_lecciones');
    }
};
