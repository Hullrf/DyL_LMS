<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('eventos_personales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->string('titulo');
            $table->dateTime('inicia_en');
            $table->dateTime('termina_en')->nullable();
            $table->text('descripcion')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'inicia_en']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('eventos_personales');
    }
};
