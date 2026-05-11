<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Contracts\Auditable;
use OwenIt\Auditing\Auditable as AuditableTrait;

class Inscripcion extends Model implements Auditable
{
    use HasFactory, AuditableTrait;

    protected $auditExclude = ['updated_at'];
    protected $table = 'inscripciones';
    protected $fillable = ['user_id', 'curso_id', 'fecha_inicio', 'fecha_fin', 'estado'];

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function curso(): BelongsTo
    {
        return $this->belongsTo(Curso::class);
    }
}
