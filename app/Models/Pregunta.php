<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Pregunta extends Model
{
    use HasFactory;
    protected $table = 'preguntas';
    protected $fillable = ['actividad_id', 'pregunta_texto', 'imagen_path', 'tipo', 'seleccion_multiple', 'puntaje', 'orden'];

    protected $casts = ['seleccion_multiple' => 'boolean'];

    public function imagenUrl(): ?string
    {
        return $this->imagen_path
            ? asset('storage/' . $this->imagen_path)
            : null;
    }

    public function actividad(): BelongsTo
    {
        return $this->belongsTo(Actividad::class);
    }

    public function opciones(): HasMany
    {
        return $this->hasMany(Opcion::class)->orderBy('orden');
    }
}
