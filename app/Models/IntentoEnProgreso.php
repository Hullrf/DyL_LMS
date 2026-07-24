<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IntentoEnProgreso extends Model
{
    use HasFactory;

    protected $table = 'intentos_en_progreso';

    protected $fillable = ['user_id', 'actividad_id', 'fecha_inicio'];

    protected $casts = [
        'fecha_inicio' => 'datetime',
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
