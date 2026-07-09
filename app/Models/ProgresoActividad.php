<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProgresoActividad extends Model
{
    use HasFactory;

    protected $table = 'progreso_actividades';

    protected $fillable = ['user_id', 'actividad_id', 'completado', 'fecha_completado'];

    protected $casts = [
        'completado'       => 'boolean',
        'fecha_completado' => 'datetime',
    ];

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function actividad(): BelongsTo
    {
        return $this->belongsTo(Actividad::class);
    }
}
