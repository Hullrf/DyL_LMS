<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SeleccionRubrica extends Model
{
    protected $table    = 'selecciones_rubrica';
    protected $fillable = ['respuesta_estudiante_id', 'criterio_id', 'nivel_criterio_id'];

    public function respuesta(): BelongsTo
    {
        return $this->belongsTo(RespuestaEstudiante::class, 'respuesta_estudiante_id');
    }

    public function criterio(): BelongsTo
    {
        return $this->belongsTo(CriterioRubrica::class, 'criterio_id');
    }

    public function nivel(): BelongsTo
    {
        return $this->belongsTo(NivelCriterio::class, 'nivel_criterio_id');
    }
}
