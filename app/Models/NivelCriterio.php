<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NivelCriterio extends Model
{
    protected $table    = 'niveles_criterio';
    protected $fillable = ['criterio_id', 'descripcion', 'puntos', 'orden'];

    protected $casts = ['puntos' => 'decimal:2'];

    public function criterio(): BelongsTo
    {
        return $this->belongsTo(CriterioRubrica::class, 'criterio_id');
    }
}
