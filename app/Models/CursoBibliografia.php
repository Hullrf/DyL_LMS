<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CursoBibliografia extends Model
{
    protected $table = 'curso_bibliografias';
    protected $fillable = ['curso_id', 'autor', 'anio', 'titulo', 'editorial', 'orden'];

    public function curso(): BelongsTo
    {
        return $this->belongsTo(Curso::class);
    }
}
