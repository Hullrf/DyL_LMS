<?php

namespace Tests\Feature;

use App\Models\Actividad;
use App\Models\Curso;
use App\Models\Leccion;
use App\Models\Modulo;
use App\Models\Rol;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ActividadSinNotaTest extends TestCase
{
    use RefreshDatabase;

    private function crearInstructor(): User
    {
        $rol  = Rol::factory()->instructor()->create();
        $user = User::factory()->create(['estado' => 'activo']);
        $user->roles()->attach($rol);
        return $user;
    }

    private function crearEscenario(User $instructor): array
    {
        $curso   = Curso::factory()->publicado()->create(['created_by' => $instructor->id]);
        $modulo  = Modulo::factory()->create(['curso_id' => $curso->id]);
        $leccion = Leccion::factory()->create(['modulo_id' => $modulo->id]);
        return [$curso, $modulo, $leccion];
    }

    /** Test 1: tipo lectura sin puntaje_maximo → se guarda con NULL */
    public function test_instructor_puede_crear_actividad_lectura_sin_puntaje(): void
    {
        $instructor = $this->crearInstructor();
        [, , $leccion] = $this->crearEscenario($instructor);

        $response = $this->actingAs($instructor)->post(route('actividades.store', $leccion), [
            'titulo'        => 'Lectura ISO 9001',
            'tipo'          => 'lectura',
            'descripcion'   => 'Lee el capítulo 4.',
            'es_obligatoria' => '0',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('actividades', [
            'leccion_id'    => $leccion->id,
            'tipo'          => 'lectura',
            'puntaje_maximo' => null,
        ]);
    }

    /** Test 2: tipo cuestionario sin puntaje_maximo → error de validación */
    public function test_cuestionario_sin_puntaje_falla_validacion(): void
    {
        $instructor = $this->crearInstructor();
        [, , $leccion] = $this->crearEscenario($instructor);

        $response = $this->actingAs($instructor)->post(route('actividades.store', $leccion), [
            'titulo'        => 'Cuestionario sin nota',
            'tipo'          => 'cuestionario',
            'descripcion'   => 'Sin puntaje.',
            'es_obligatoria' => '1',
        ]);

        $response->assertSessionHasErrors('puntaje_maximo');
        $this->assertDatabaseMissing('actividades', ['titulo' => 'Cuestionario sin nota']);
    }

    /** Test 3: tieneCalificacion() devuelve false para tipos sin nota */
    public function test_metodo_tiene_calificacion_retorna_false_para_tipos_sin_nota(): void
    {
        foreach (['ejercicio', 'lectura', 'encuesta', 'reflexion'] as $tipo) {
            $actividad = new Actividad(['tipo' => $tipo]);
            $this->assertFalse(
                $actividad->tieneCalificacion(),
                "tieneCalificacion() debe ser false para tipo={$tipo}"
            );
        }

        foreach (['cuestionario', 'ensayo', 'tarea', 'practica'] as $tipo) {
            $actividad = new Actividad(['tipo' => $tipo]);
            $this->assertTrue(
                $actividad->tieneCalificacion(),
                "tieneCalificacion() debe ser true para tipo={$tipo}"
            );
        }
    }

    /** Test 4: vista show de actividad lectura no contiene formulario de respuesta */
    public function test_show_actividad_lectura_no_tiene_formulario_de_respuesta(): void
    {
        $instructor = $this->crearInstructor();
        [, , $leccion] = $this->crearEscenario($instructor);

        $actividad = Actividad::factory()->create([
            'leccion_id'    => $leccion->id,
            'tipo'          => 'lectura',
            'puntaje_maximo' => null,
        ]);

        $rolEstudiante = Rol::factory()->estudiante()->create();
        $estudiante    = User::factory()->create(['estado' => 'activo']);
        $estudiante->roles()->attach($rolEstudiante);
        $leccion->modulo->curso->inscripciones()->create([
            'user_id'     => $estudiante->id,
            'estado'      => 'en_progreso',
            'fecha_inicio' => now(),
        ]);

        $response = $this->actingAs($estudiante)->get(route('actividades.show', $actividad));

        $response->assertStatus(200);
        $response->assertDontSee('form-respuesta', false);
        $response->assertSee('Sin calificación');
    }
}
