<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Mensaje extends Model
{
    protected $table = 'mensajes';
    protected $fillable = ['curso_id', 'remitente_id', 'asunto', 'mensaje', 'leido', 'padre_id'];
    protected $casts = ['leido' => 'boolean'];

    public function curso(): BelongsTo { return $this->belongsTo(Curso::class); }
    public function remitente(): BelongsTo { return $this->belongsTo(User::class, 'remitente_id'); }
    public function padre(): BelongsTo { return $this->belongsTo(self::class, 'padre_id'); }
    public function respuestas(): HasMany { return $this->hasMany(self::class, 'padre_id')->orderBy('created_at'); }
}
