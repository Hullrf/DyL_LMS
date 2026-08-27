<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Intentos extra que un instructor otorga a un estudiante puntual para una
 * actividad de tipo cuestionario, por encima del límite global de la
 * actividad (Actividad::$intentos_permitidos). Cada otorgamiento se suma
 * al anterior (nunca resta).
 */
class IntentoExtra extends Model
{
    use HasFactory;

    protected $table = 'intentos_extra';

    protected $fillable = ['user_id', 'actividad_id', 'cantidad', 'otorgado_por'];

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function actividad(): BelongsTo
    {
        return $this->belongsTo(Actividad::class);
    }

    public function otorgadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'otorgado_por');
    }
}
