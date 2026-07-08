<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Anuncio extends Model
{
    protected $table = 'anuncios';
    protected $fillable = ['curso_id', 'titulo', 'contenido', 'created_by'];

    public function curso(): BelongsTo { return $this->belongsTo(Curso::class); }
    public function creador(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
}
