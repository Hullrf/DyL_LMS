<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('foros', function (Blueprint $table) {
            $table->id();
            $table->foreignId('curso_id')->constrained('cursos')->cascadeOnDelete();
            $table->foreignId('leccion_id')->nullable()->constrained('lecciones')->nullOnDelete();
            $table->string('titulo');
            $table->text('descripcion')->nullable();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
        });

        Schema::create('foro_comentarios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('foro_id')->constrained('foros')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->text('contenido');
            $table->foreignId('padre_id')->nullable()->constrained('foro_comentarios')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('foro_comentarios');
        Schema::dropIfExists('foros');
    }
};
