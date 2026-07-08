<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;
use OwenIt\Auditing\Auditable as AuditableTrait;

class Actividad extends Model implements Auditable
{
    use HasFactory, SoftDeletes, AuditableTrait;

    const TIPOS_SIN_NOTA = ['ejercicio', 'lectura', 'encuesta', 'reflexion'];

    protected $auditExclude = ['updated_at'];

    protected $table = 'actividades';
    protected $fillable = [
        'leccion_id', 'tipo', 'titulo', 'descripcion',
        'orden', 'puntaje_maximo', 'duracion_minutos', 'es_obligatoria',
        'fecha_apertura', 'fecha_cierre', 'usa_rubrica',
        'permitir_descarga_adjuntos',
    ];

    protected $casts = [
        'fecha_apertura'              => 'datetime',
        'fecha_cierre'                => 'datetime',
        'es_obligatoria'              => 'boolean',
        'usa_rubrica'                 => 'boolean',
        'permitir_descarga_adjuntos'  => 'boolean',
        'puntaje_maximo'              => 'decimal:2',
    ];

    /**
     * 'sin_plazo' | 'pendiente' | 'abierta' | 'cerrada'
     */
    public function estadoPlazo(): string
    {
        $now = now();
        if (!$this->fecha_apertura && !$this->fecha_cierre) return 'sin_plazo';
        if ($this->fecha_apertura && $now->lt($this->fecha_apertura))  return 'pendiente';
        if ($this->fecha_cierre   && $now->gt($this->fecha_cierre))    return 'cerrada';
        return 'abierta';
    }

    public function estaAbierta(): bool
    {
        return in_array($this->estadoPlazo(), ['sin_plazo', 'abierta']);
    }

    public function leccion(): BelongsTo
    {
        return $this->belongsTo(Leccion::class);
    }

    public function preguntas(): HasMany
    {
        return $this->hasMany(Pregunta::class)->orderBy('orden');
    }

    public function respuestas(): HasMany
    {
        return $this->hasMany(RespuestaEstudiante::class);
    }

    public function recursos(): HasMany
    {
        return $this->hasMany(RecursoActividad::class)->orderBy('orden');
    }

    public function criteriosRubrica(): HasMany
    {
        return $this->hasMany(CriterioRubrica::class)->orderBy('orden');
    }

    public function puntajeRubrica(): float
    {
        return (float) $this->criteriosRubrica()
            ->with('niveles')
            ->get()
            ->sum(fn($c) => $c->niveles->max('puntos') ?? 0);
    }

    public function tieneCalificacion(): bool
    {
        return !in_array($this->tipo, self::TIPOS_SIN_NOTA);
    }
}
