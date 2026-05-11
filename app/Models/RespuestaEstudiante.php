<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RespuestaEstudiante extends Model
{
    use HasFactory;
    protected $table = 'respuestas_estudiantes';
    protected $fillable = [
        'user_id', 'actividad_id', 'respuesta', 'archivo_adjunto',
        'calificacion', 'estado', 'feedback', 'fecha_envio', 'fecha_calificacion',
    ];

    protected $casts = [
        'fecha_envio' => 'datetime',
        'fecha_calificacion' => 'datetime',
    ];

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function actividad(): BelongsTo
    {
        return $this->belongsTo(Actividad::class);
    }
}
