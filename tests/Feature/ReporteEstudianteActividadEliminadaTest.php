<?php

namespace Tests\Feature;

use App\Models\Actividad;
use App\Models\Curso;
use App\Models\Leccion;
use App\Models\Modulo;
use App\Models\RespuestaEstudiante;
use App\Models\Rol;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReporteEstudianteActividadEliminadaTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Reproduce el bug reportado: un instructor borra (soft-delete) una actividad
     * que un estudiante ya había respondido. El reporte de estudiante del admin
     * no debe tronar con "Attempt to read property titulo on null".
     */
    public function test_reporte_estudiante_no_truena_si_actividad_fue_eliminada(): void
    {
        $rolAdmin = Rol::factory()->administrador()->create();
        $admin    = User::factory()->create(['estado' => 'activo']);
        $admin->roles()->attach($rolAdmin);

        $rolEstudiante = Rol::factory()->estudiante()->create();
        $estudiante    = User::factory()->create(['estado' => 'activo']);
        $estudiante->roles()->attach($rolEstudiante);

        $curso   = Curso::factory()->publicado()->create(['created_by' => $admin->id]);
        $modulo  = Modulo::factory()->create(['curso_id' => $curso->id]);
        $leccion = Leccion::factory()->create(['modulo_id' => $modulo->id]);

        $actividad = Actividad::factory()->create([
            'leccion_id'     => $leccion->id,
            'tipo'           => 'tarea',
            'titulo'         => 'Tarea que será eliminada',
            'puntaje_maximo' => 100,
        ]);

        $curso->inscripciones()->create([
            'user_id'      => $estudiante->id,
            'estado'       => 'en_progreso',
            'fecha_inicio' => now(),
        ]);

        RespuestaEstudiante::create([
            'user_id'      => $estudiante->id,
            'actividad_id' => $actividad->id,
            'respuesta'    => 'Mi entrega',
            'estado'       => 'calificada',
            'calificacion' => 80,
            'fecha_envio'  => now(),
            'fecha_calificacion' => now(),
        ]);

        // El instructor borra la actividad (soft-delete) después de la entrega.
        $actividad->delete();

        $response = $this->actingAs($admin)->get(route('reportes.estudiante', $estudiante));

        $response->assertStatus(200);
        $response->assertSee('Tarea que será eliminada');
    }
}
