<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('certificados', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('curso_id')->constrained('cursos')->onDelete('cascade');
            $table->date('fecha_emision');
            $table->string('numero_certificado')->unique();
            $table->string('archivo_pdf')->nullable();
            $table->integer('calificacion_final')->nullable();
            $table->timestamps();

            $table->index('user_id');
            $table->index('curso_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('certificados');
    }
};
