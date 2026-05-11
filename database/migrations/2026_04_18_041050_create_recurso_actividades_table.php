<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('recurso_actividades', function (Blueprint $table) {
            $table->id();
            $table->foreignId('actividad_id')->constrained('actividades')->onDelete('cascade');
            $table->enum('tipo', ['documento', 'video', 'texto', 'enlace']);
            $table->string('titulo');
            $table->text('descripcion')->nullable();
            $table->longText('contenido')->nullable();   // texto HTML
            $table->string('archivo_path')->nullable();  // documentos subidos
            $table->string('url', 2048)->nullable();     // videos / enlaces externos
            $table->integer('orden')->default(0);
            $table->timestamps();

            $table->index(['actividad_id', 'orden']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recurso_actividades');
    }
};
