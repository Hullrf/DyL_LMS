<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CursoFicha extends Model
{
    protected $table = 'curso_fichas';
    protected $fillable = [
        'curso_id', 'justificacion', 'resultado_aprendizaje', 'resultados_especificos',
        'creditos', 'semestre', 'prerrequisitos', 'programa', 'facultad',
    ];
    protected $casts = ['resultados_especificos' => 'array'];

    public function curso(): BelongsTo
    {
        return $this->belongsTo(Curso::class);
    }
}
