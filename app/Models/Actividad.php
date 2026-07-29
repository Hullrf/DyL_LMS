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
        'intentos_permitidos', 'criterio_calificacion_intentos', 'mostrar_historial_intentos',
    ];

    protected $casts = [
        'fecha_apertura'              => 'datetime',
        'fecha_cierre'                => 'datetime',
        'es_obligatoria'              => 'boolean',
        'usa_rubrica'                 => 'boolean',
        'permitir_descarga_adjuntos'  => 'boolean',
        'puntaje_maximo'              => 'decimal:2',
        'mostrar_historial_intentos'  => 'boolean',
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

    /** Resuelve si se permite descarga: actividad (si está definido) o hereda de la lección. */
    public function descargaPermitida(): bool
    {
        $raw = $this->getRawOriginal('permitir_descarga_adjuntos');
        if ($raw !== null) return (bool) $raw;
        return (bool) ($this->leccion->permitir_descarga_adjuntos ?? true);
    }

    public function leccion(): BelongsTo
    {
        return $this->belongsTo(Leccion::class);
    }

    /** Marca la actividad como completada para un usuario y verifica si la lección queda completa. */
    public function completarPara(int $userId): void
    {
        ProgresoActividad::updateOrCreate(
            ['user_id' => $userId, 'actividad_id' => $this->id],
            ['completado' => true, 'fecha_completado' => now()]
        );

        $leccion = $this->leccion;
        $total = $leccion->actividades()->count();
        if ($total === 0) return;

        $completadas = ProgresoActividad::where('user_id', $userId)
            ->whereIn('actividad_id', $leccion->actividades()->pluck('id'))
            ->where('completado', true)
            ->count();

        if ($completadas >= $total) {
            ProgresoLeccion::updateOrCreate(
                ['user_id' => $userId, 'leccion_id' => $leccion->id],
                ['completado' => true, 'fecha_completado' => now()]
            );

            $curso = $leccion->modulo->curso;
            $totalLecciones = $curso->lecciones()->count();
            $leccionesCompletadas = ProgresoLeccion::where('user_id', $userId)
                ->whereIn('leccion_id', $curso->lecciones()->pluck('lecciones.id'))
                ->where('completado', true)
                ->count();

            if ($totalLecciones > 0 && $leccionesCompletadas >= $totalLecciones) {
                Inscripcion::where('user_id', $userId)
                    ->where('curso_id', $curso->id)
                    ->update(['estado' => 'completado', 'fecha_fin' => now()->toDateString()]);
            }
        }
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

    public function permiteMultiplesIntentos(): bool
    {
        return $this->tipo === 'cuestionario' && $this->intentos_permitidos > 1;
    }

    public function intentosUsadosPor(int $userId): int
    {
        return $this->respuestas()->where('user_id', $userId)->count();
    }

    public function tieneIntentoEnRevisionPara(int $userId): bool
    {
        return $this->respuestas()
            ->where('user_id', $userId)
            ->where('estado', 'en_revision')
            ->exists();
    }

    /**
     * Reparte el puntaje_maximo de la actividad equitativamente entre
     * todas sus preguntas. La última pregunta absorbe el residuo del
     * redondeo para que la suma sea exactamente puntaje_maximo.
     */
    public function redistribuirPuntajesPreguntas(): void
    {
        $preguntas = $this->preguntas()->orderBy('orden')->get();
        $total     = $preguntas->count();

        if ($total === 0) return;

        $base    = (int) floor($this->puntaje_maximo / $total);
        $residuo = $this->puntaje_maximo - ($base * $total);

        foreach ($preguntas as $i => $pregunta) {
            $puntaje = $base + ($i === $total - 1 ? $residuo : 0);
            $pregunta->update(['puntaje' => max(1, $puntaje)]);
        }
    }
}
