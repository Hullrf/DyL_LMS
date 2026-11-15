<?php

namespace App\Models;

use App\Services\AvisosPorCorreoService;
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

    protected static function booted(): void
    {
        // Cada aviso de las categorías incluidas también sale por correo (ver AvisosPorCorreoService).
        static::created(fn (self $aviso) => app(AvisosPorCorreoService::class)->enviarSiCorresponde($aviso));
    }

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
