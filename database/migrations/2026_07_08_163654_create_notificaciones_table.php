<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notificaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('tipo');
            $table->string('titulo');
            $table->text('mensaje');
            $table->boolean('leido')->default(false);
            $table->json('data')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'leido']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notificaciones');
    }
};
