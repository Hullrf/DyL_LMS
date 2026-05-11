<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProgresoLeccion extends Model
{
    use HasFactory;
    protected $table = 'progreso_lecciones';
    protected $fillable = ['user_id', 'leccion_id', 'completado', 'fecha_completado', 'tiempo_dedicado_minutos'];

    protected $casts = [
        'completado' => 'boolean',
        'fecha_completado' => 'datetime',
    ];

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function leccion(): BelongsTo
    {
        return $this->belongsTo(Leccion::class);
    }
}
