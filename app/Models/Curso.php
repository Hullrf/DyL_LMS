<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
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
        'titulo', 'descripcion', 'duracion_horas',
        'imagen_portada', 'estado', 'created_by', 'orden', 'categoria_id',
        'tipo_certificado', 'nota_aprobatoria',
    ];

    public function creador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
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
}
