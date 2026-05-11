<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lecciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('modulo_id')->constrained('modulos')->onDelete('cascade');
            $table->string('titulo');
            $table->longText('contenido_html')->nullable();
            $table->integer('orden')->default(0);
            $table->integer('duracion_minutos')->default(30);
            $table->enum('tipo', ['video', 'texto', 'mixto'])->default('texto');
            $table->timestamps();
            $table->softDeletes();

            $table->index('modulo_id');
            $table->index('orden');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lecciones');
    }
};
