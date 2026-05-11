<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CriterioRubrica extends Model
{
    protected $table    = 'criterios_rubrica';
    protected $fillable = ['actividad_id', 'nombre', 'orden'];

    public function actividad(): BelongsTo
    {
        return $this->belongsTo(Actividad::class);
    }

    public function niveles(): HasMany
    {
        return $this->hasMany(NivelCriterio::class, 'criterio_id')->orderBy('orden');
    }

    public function puntajeMaximo(): float
    {
        return (float) $this->niveles()->max('puntos');
    }
}
