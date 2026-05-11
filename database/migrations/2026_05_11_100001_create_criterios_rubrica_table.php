<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('criterios_rubrica', function (Blueprint $table) {
            $table->id();
            $table->foreignId('actividad_id')->constrained('actividades')->onDelete('cascade');
            $table->string('nombre');
            $table->integer('orden')->default(0);
            $table->timestamps();
            $table->index('actividad_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('criterios_rubrica');
    }
};
