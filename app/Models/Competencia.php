<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Competencia extends Model
{
    protected $table = 'competencias';
    protected $fillable = ['curso_id', 'descripcion', 'orden'];

    public function curso(): BelongsTo
    {
        return $this->belongsTo(Curso::class);
    }

    public function actividades(): BelongsToMany
    {
        return $this->belongsToMany(Actividad::class, 'actividad_competencia', 'competencia_id', 'actividad_id');
    }
}
