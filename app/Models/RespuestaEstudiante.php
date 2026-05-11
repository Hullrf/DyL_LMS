<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use OwenIt\Auditing\Contracts\Auditable;
use OwenIt\Auditing\Auditable as AuditableTrait;

class RespuestaEstudiante extends Model implements Auditable
{
    use HasFactory, AuditableTrait;

    protected $auditExclude = ['updated_at'];
    protected $table = 'respuestas_estudiantes';
    protected $fillable = [
        'user_id', 'actividad_id', 'respuesta', 'archivo_adjunto',
        'calificacion', 'estado', 'feedback', 'fecha_envio', 'fecha_calificacion',
    ];

    protected $casts = [
        'fecha_envio'       => 'datetime',
        'fecha_calificacion'=> 'datetime',
        'calificacion'      => 'decimal:2',
    ];

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function actividad(): BelongsTo
    {
        return $this->belongsTo(Actividad::class);
    }

    public function seleccionesRubrica(): HasMany
    {
        return $this->hasMany(SeleccionRubrica::class, 'respuesta_estudiante_id');
    }
}
