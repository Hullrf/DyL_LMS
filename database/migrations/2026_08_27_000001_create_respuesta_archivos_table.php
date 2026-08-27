<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('respuesta_archivos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('respuesta_estudiante_id')->constrained('respuestas_estudiantes')->onDelete('cascade');
            $table->string('path');
            $table->string('nombre_original')->nullable();
            $table->unsignedBigInteger('tamano')->nullable();
            $table->unsignedTinyInteger('orden')->default(0);
            $table->timestamps();
        });

        // Backfill: cada respuesta_estudiante que ya tenía un único archivo_adjunto
        // pasa a tener una fila equivalente en respuesta_archivos, para que el
        // resto del código pueda leer siempre de la relación nueva sin perder
        // las entregas ya existentes. La columna archivo_adjunto se deja intacta
        // (no se borra) por seguridad, pero deja de leerse/escribirse en adelante.
        DB::table('respuestas_estudiantes')
            ->whereNotNull('archivo_adjunto')
            ->orderBy('id')
            ->select('id', 'archivo_adjunto')
            ->each(function ($respuesta) {
                DB::table('respuesta_archivos')->insert([
                    'respuesta_estudiante_id' => $respuesta->id,
                    'path'                    => $respuesta->archivo_adjunto,
                    'nombre_original'         => basename($respuesta->archivo_adjunto),
                    'orden'                   => 0,
                    'created_at'              => now(),
                    'updated_at'              => now(),
                ]);
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('respuesta_archivos');
    }
};
