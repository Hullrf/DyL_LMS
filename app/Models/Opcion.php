<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Opcion extends Model
{
    use HasFactory;
    protected $table = 'opciones';
    protected $fillable = ['pregunta_id', 'texto', 'es_correcta', 'explicacion', 'orden'];

    protected $casts = ['es_correcta' => 'boolean'];

    public function pregunta(): BelongsTo
    {
        return $this->belongsTo(Pregunta::class);
    }
}
