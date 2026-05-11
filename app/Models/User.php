<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, SoftDeletes;

    protected $fillable = [
        'name', 'email', 'password', 'empresa', 'estado',
    ];

    protected $hidden = [
        'password', 'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Rol::class, 'role_user', 'user_id', 'role_id');
    }

    public function cursosCreados(): HasMany
    {
        return $this->hasMany(Curso::class, 'created_by');
    }

    public function cursos(): BelongsToMany
    {
        return $this->belongsToMany(Curso::class, 'inscripciones')
            ->withPivot('fecha_inicio', 'fecha_fin', 'estado')
            ->withTimestamps();
    }

    public function respuestas(): HasMany
    {
        return $this->hasMany(RespuestaEstudiante::class);
    }

    public function progresoLecciones(): HasMany
    {
        return $this->hasMany(ProgresoLeccion::class);
    }

    public function certificados(): HasMany
    {
        return $this->hasMany(Certificado::class);
    }

    public function tieneRol(string $nombreRol): bool
    {
        return $this->roles()->where('nombre', $nombreRol)->exists();
    }

    public function esAdmin(): bool
    {
        return $this->tieneRol('Administrador');
    }

    public function esInstructor(): bool
    {
        return $this->tieneRol('Instructor');
    }

    public function esEstudiante(): bool
    {
        return $this->tieneRol('Estudiante') || !$this->roles()->exists();
    }
}
