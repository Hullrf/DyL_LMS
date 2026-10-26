<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Certificado extends Model
{
    protected $table = 'certificados';
    protected $fillable = [
        'user_id', 'curso_id', 'fecha_emision',
        'numero_certificado', 'archivo_pdf', 'calificacion_final', 'aprobado_por_id',
    ];

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function curso(): BelongsTo
    {
        // withTrashed: un certificado emitido sigue siendo válido y visible aunque el curso se archive.
        return $this->belongsTo(Curso::class)->withTrashed();
    }

    public function aprobador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'aprobado_por_id');
    }
}
