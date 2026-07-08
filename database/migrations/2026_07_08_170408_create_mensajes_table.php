<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mensajes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('curso_id')->constrained('cursos')->cascadeOnDelete();
            $table->foreignId('remitente_id')->constrained('users')->cascadeOnDelete();
            $table->string('asunto');
            $table->text('mensaje');
            $table->boolean('leido')->default(false);
            $table->foreignId('padre_id')->nullable()->constrained('mensajes')->nullOnDelete();
            $table->timestamps();

            $table->index(['remitente_id', 'leido']);
            $table->index(['curso_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mensajes');
    }
};
