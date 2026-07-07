<?php

use App\Models\Curso;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $cursos = Curso::with('modulos')->get();

        foreach ($cursos as $curso) {
            $modulos = $curso->modulos()->orderBy('orden')->get();
            foreach ($modulos as $index => $modulo) {
                if ($modulo->orden !== $index) {
                    $modulo->update(['orden' => $index]);
                }
            }
        }
    }

    public function down(): void
    {
        // No podemos revertir cambios de orden arbitrarios
    }
};
