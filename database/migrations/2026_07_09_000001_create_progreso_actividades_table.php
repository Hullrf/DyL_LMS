<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('progreso_actividades', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('actividad_id')->constrained('actividades')->onDelete('cascade');
            $table->boolean('completado')->default(false);
            $table->timestamp('fecha_completado')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'actividad_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('progreso_actividades');
    }
};
