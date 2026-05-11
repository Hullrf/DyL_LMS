<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('modulos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('curso_id')->constrained('cursos')->onDelete('cascade');
            $table->string('titulo');
            $table->text('descripcion')->nullable();
            $table->integer('orden')->default(0);
            $table->integer('duracion_horas')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index('curso_id');
            $table->index('orden');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('modulos');
    }
};
