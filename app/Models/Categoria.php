<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Categoria extends Model
{
    protected $table = 'categorias';
    protected $fillable = ['nombre', 'slug', 'color'];

    public function cursos(): HasMany
    {
        return $this->hasMany(Curso::class);
    }
}
