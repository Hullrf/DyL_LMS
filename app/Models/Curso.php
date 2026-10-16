<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;
use OwenIt\Auditing\Auditable as AuditableTrait;

class Curso extends Model implements Auditable
{
    use HasFactory, SoftDeletes, AuditableTrait;

    protected $auditExclude = ['updated_at'];

    protected $table = 'cursos';
    protected $fillable = [
        'titulo', 'codigo', 'grupo', 'descripcion', 'duracion_horas',
        'imagen_portada', 'estado', 'created_by', 'orden', 'categoria_id',
        'tipo_certificado', 'nota_aprobatoria',
        'seguimiento_dias_sin_acceso', 'seguimiento_max_atrasadas',
    ];

    /** "CAD612021730 · Grupo 703M" con las partes presentes (FE-1: metadatos fuera del título). */
    public function subtitulo(): ?string
    {
        $partes = array_filter([
            $this->codigo,
            $this->grupo ? "Grupo {$this->grupo}" : null,
        ]);

        return $partes ? implode(' · ', $partes) : null;
    }

    public function creador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function ficha(): HasOne
    {
        return $this->hasOne(CursoFicha::class);
    }

    public function bibliografias(): HasMany
    {
        return $this->hasMany(CursoBibliografia::class)->orderBy('orden');
    }

    /** Etiquetas de lo que falta para considerar completa la ficha académica. */
    public function fichaFaltantes(): array
    {
        $f = $this->ficha;
        $faltan = [];
        if (!$f || blank($f->justificacion))           $faltan[] = 'Justificación';
        if (!$f || blank($f->resultado_aprendizaje))   $faltan[] = 'Resultado de aprendizaje';
        if (!$f || $f->creditos === null)              $faltan[] = 'Créditos';
        if (!$f || blank($f->programa))                $faltan[] = 'Programa';
        if (!$this->bibliografias()->exists())         $faltan[] = 'Bibliografía';

        return $faltan;
    }

    public function categoria(): BelongsTo
    {
        return $this->belongsTo(Categoria::class);
    }

    public function modulos(): HasMany
    {
        return $this->hasMany(Modulo::class)->orderBy('orden');
    }

    public function lecciones(): HasManyThrough
    {
        return $this->hasManyThrough(Leccion::class, Modulo::class);
    }

    public function inscripciones(): HasMany
    {
        return $this->hasMany(Inscripcion::class);
    }

    public function estudiantes(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'inscripciones')
            ->withPivot('fecha_inicio', 'fecha_fin', 'estado')
            ->withTimestamps();
    }

    public function certificados(): HasMany
    {
        return $this->hasMany(Certificado::class);
    }

    public function estaPublicado(): bool
    {
        return $this->estado === 'publicado';
    }

    public function esBorrador(): bool
    {
        return $this->estado === 'borrador';
    }

    public function estaArchivado(): bool
    {
        return $this->estado === 'archivado';
    }

    /** Todas las actividades vivas del curso, ordenadas por módulo, lección y actividad. */
    public function todasLasActividades(): \Illuminate\Support\Collection
    {
        return Actividad::whereHas('leccion.modulo', fn($q) => $q->where('curso_id', $this->id))
            ->with('leccion.modulo')
            ->get()
            ->sortBy(fn($a) => [$a->leccion->modulo->orden, $a->leccion->orden, $a->orden])
            ->values();
    }

    /** Actividades con nota (sin las eliminadas), ordenadas por módulo, lección y actividad. */
    public function actividadesCalificables(): \Illuminate\Support\Collection
    {
        return $this->todasLasActividades()->filter(fn($a) => $a->tieneCalificacion())->values();
    }

    public function competencias(): HasMany
    {
        return $this->hasMany(Competencia::class)->orderBy('orden')->orderBy('id');
    }

    /** Hay ponderaciones configuradas pero no forman un esquema válido (faltan o no suman 100): la nota cae a por puntaje. */
    public function ponderacionIncompleta(): bool
    {
        $a = $this->actividadesCalificables();
        if ($a->whereNotNull('ponderacion')->isEmpty()) {
            return false;
        }

        return $a->contains(fn($x) => $x->ponderacion === null)
            || abs((float) $a->sum(fn($x) => (float) $x->ponderacion) - 100) >= 0.01;
    }
}
