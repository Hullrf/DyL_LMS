<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class RecursoActividad extends Model
{
    use HasFactory;

    protected $table = 'recurso_actividades';

    protected $fillable = [
        'actividad_id', 'tipo', 'titulo', 'descripcion',
        'contenido', 'archivo_path', 'url', 'orden',
    ];

    public function actividad(): BelongsTo
    {
        return $this->belongsTo(Actividad::class);
    }

    /** URL pública del archivo (solo tipo documento). */
    public function archivoUrl(): ?string
    {
        return $this->archivo_path ? Storage::url($this->archivo_path) : null;
    }

    /** Nombre del archivo sin ruta. */
    public function archivoNombre(): ?string
    {
        return $this->archivo_path ? basename($this->archivo_path) : null;
    }

    /** Convierte una URL de YouTube/Vimeo a URL embebible. */
    public function embedUrl(): ?string
    {
        if (!$this->url) return null;

        // YouTube: watch?v=ID  o  youtu.be/ID
        if (preg_match('/(?:youtube\.com\/watch\?v=|youtu\.be\/)([a-zA-Z0-9_-]{11})/', $this->url, $m)) {
            return 'https://www.youtube.com/embed/' . $m[1];
        }

        // Vimeo: vimeo.com/ID
        if (preg_match('/vimeo\.com\/(\d+)/', $this->url, $m)) {
            return 'https://player.vimeo.com/video/' . $m[1];
        }

        return $this->url; // mp4 directo u otro
    }

    /** Icono SVG path según tipo. */
    public function iconoTipo(): string
    {
        return match($this->tipo) {
            'documento' => 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z',
            'video'     => 'M15 10l4.553-2.069A1 1 0 0121 8.82v6.36a1 1 0 01-1.447.89L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z',
            'texto'     => 'M4 6h16M4 12h16M4 18h7',
            'enlace'    => 'M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1',
        };
    }
}
