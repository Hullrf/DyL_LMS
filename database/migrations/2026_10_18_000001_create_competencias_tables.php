<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('competencias', function (Blueprint $table) {
            $table->id();
            $table->foreignId('curso_id')->constrained('cursos')->onDelete('cascade');
            $table->string('descripcion', 500);
            $table->unsignedSmallInteger('orden')->default(0);
            $table->timestamps();

            $table->index('curso_id');
        });

        Schema::create('actividad_competencia', function (Blueprint $table) {
            $table->foreignId('competencia_id')->constrained('competencias')->onDelete('cascade');
            $table->foreignId('actividad_id')->constrained('actividades')->onDelete('cascade');
            $table->primary(['competencia_id', 'actividad_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('actividad_competencia');
        Schema::dropIfExists('competencias');
    }
};
