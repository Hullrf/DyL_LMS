<?php

namespace Tests\Concerns;

use App\Models\Actividad;
use App\Models\Curso;
use App\Models\Inscripcion;
use App\Models\Leccion;
use App\Models\Modulo;
use App\Models\RespuestaEstudiante;
use App\Models\User;

trait ArmaActividades
{
    protected function cursoInscrito(User $estudiante, array $cursoAttrs = []): Curso
    {
        $curso = Curso::factory()->publicado()->create($cursoAttrs);
        Inscripcion::factory()->create(['user_id' => $estudiante->id, 'curso_id' => $curso->id]);

        return $curso;
    }

    protected function actividadEn(Curso $curso, array $attrs = []): Actividad
    {
        // orden creciente: la factory lo sortea al azar y los tests dependen del orden de creación
        $modulo  = Modulo::factory()->create(['curso_id' => $curso->id, 'orden' => $curso->modulos()->count() + 1]);
        $leccion = Leccion::factory()->create(['modulo_id' => $modulo->id]);

        return Actividad::factory()->tarea()->create($attrs + ['leccion_id' => $leccion->id]);
    }

    protected function calificar(User $u, Actividad $a, float $nota): RespuestaEstudiante
    {
        return RespuestaEstudiante::factory()->calificada()->create([
            'user_id' => $u->id, 'actividad_id' => $a->id, 'calificacion' => $nota,
        ]);
    }
}
