<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventoPersonal extends Model
{
    protected $table = 'eventos_personales';
    protected $fillable = ['user_id', 'titulo', 'inicia_en', 'termina_en', 'descripcion'];
    protected $casts = ['inicia_en' => 'datetime', 'termina_en' => 'datetime'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
