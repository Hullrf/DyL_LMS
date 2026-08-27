<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RespuestaArchivo extends Model
{
    use HasFactory;

    protected $table = 'respuesta_archivos';

    protected $fillable = ['respuesta_estudiante_id', 'path', 'nombre_original', 'tamano', 'orden'];

    public function respuesta(): BelongsTo
    {
        return $this->belongsTo(RespuestaEstudiante::class, 'respuesta_estudiante_id');
    }
}
