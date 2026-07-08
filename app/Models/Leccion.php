<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Leccion extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'lecciones';
    protected $fillable = ['modulo_id', 'titulo', 'contenido_html', 'video_url', 'orden', 'duracion_minutos', 'tipo', 'permitir_descarga_adjuntos'];

    protected $casts = [
        'permitir_descarga_adjuntos' => 'boolean',
    ];

    /** Convierte una URL de YouTube/Vimeo a URL embebible para el iframe. */
    public function embedUrl(): ?string
    {
        if (!$this->video_url) return null;

        // YouTube: watch?v=ID  o  youtu.be/ID
        if (preg_match('/(?:youtube\.com\/watch\?v=|youtu\.be\/)([a-zA-Z0-9_-]{11})/', $this->video_url, $m)) {
            return 'https://www.youtube.com/embed/' . $m[1];
        }

        // Vimeo: vimeo.com/ID
        if (preg_match('/vimeo\.com\/(\d+)/', $this->video_url, $m)) {
            return 'https://player.vimeo.com/video/' . $m[1];
        }

        return $this->video_url; // MP4 directo u otro
    }

    public function modulo(): BelongsTo
    {
        return $this->belongsTo(Modulo::class);
    }

    public function actividades(): HasMany
    {
        return $this->hasMany(Actividad::class)->orderBy('orden');
    }

    public function progresos(): HasMany
    {
        return $this->hasMany(ProgresoLeccion::class);
    }
}
