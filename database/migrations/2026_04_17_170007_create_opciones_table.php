<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('opciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pregunta_id')->constrained('preguntas')->onDelete('cascade');
            $table->string('texto');
            $table->boolean('es_correcta')->default(false);
            $table->text('explicacion')->nullable();
            $table->integer('orden')->default(0);
            $table->timestamps();

            $table->index('pregunta_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('opciones');
    }
};
