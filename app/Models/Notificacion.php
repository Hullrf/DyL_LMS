<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Notificacion extends Model
{
    protected $table = 'notificaciones';

    protected $fillable = ['user_id', 'tipo', 'titulo', 'mensaje', 'leido', 'data'];

    protected $casts = [
        'leido' => 'boolean',
        'data' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function crear(int $userId, string $tipo, string $titulo, string $mensaje, ?string $url = null): self
    {
        return self::create([
            'user_id' => $userId,
            'tipo' => $tipo,
            'titulo' => $titulo,
            'mensaje' => $mensaje,
            'data' => $url ? ['url' => $url] : null,
        ]);
    }
}
