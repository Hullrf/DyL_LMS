<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Foro extends Model
{
    protected $table = 'foros';
    protected $fillable = ['curso_id', 'leccion_id', 'titulo', 'descripcion', 'created_by'];

    public function curso(): BelongsTo { return $this->belongsTo(Curso::class); }
    public function leccion(): BelongsTo { return $this->belongsTo(Leccion::class); }
    public function creador(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function comentarios(): HasMany { return $this->hasMany(ForoComentario::class)->whereNull('padre_id')->orderBy('created_at'); }
}

class ForoComentario extends Model
{
    protected $table = 'foro_comentarios';
    protected $fillable = ['foro_id', 'user_id', 'contenido', 'padre_id'];

    public function foro(): BelongsTo { return $this->belongsTo(Foro::class); }
    public function usuario(): BelongsTo { return $this->belongsTo(User::class, 'user_id'); }
    public function respuestas(): HasMany { return $this->hasMany(self::class, 'padre_id')->orderBy('created_at'); }
}
