<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('niveles_criterio', function (Blueprint $table) {
            $table->id();
            $table->foreignId('criterio_id')->constrained('criterios_rubrica')->onDelete('cascade');
            $table->longText('descripcion');
            $table->decimal('puntos', 5, 2);
            $table->integer('orden')->default(0);
            $table->timestamps();
            $table->index('criterio_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('niveles_criterio');
    }
};
