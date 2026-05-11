<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cursos', function (Blueprint $table) {
            $table->id();
            $table->string('titulo')->unique();
            $table->longText('descripcion');
            $table->integer('duracion_horas');
            $table->string('imagen_portada')->nullable();
            $table->enum('estado', ['borrador', 'publicado', 'archivado'])->default('borrador');
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
            $table->integer('orden')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index('estado');
            $table->index('created_at');
            $table->index('created_by');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cursos');
    }
};
