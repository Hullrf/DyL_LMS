<?php

namespace Tests\Feature;

use App\Models\Actividad;
use App\Models\Curso;
use App\Models\Leccion;
use App\Models\Modulo;
use App\Models\Pregunta;
use App\Models\Rol;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PreguntaControllerTest extends TestCase
{
    use RefreshDatabase;

    private function crearInstructorConActividad(float $puntajeMaximo = 100): Actividad
    {
        $rol = Rol::firstOrCreate(['nombre' => 'Instructor'], ['descripcion' => 'Instructor role']);
        $instructor = User::factory()->create(['estado' => 'activo']);
        $instructor->roles()->attach($rol);

        $curso   = Curso::factory()->create(['created_by' => $instructor->id]);
        $modulo  = Modulo::factory()->create(['curso_id' => $curso->id]);
        $leccion = Leccion::factory()->create(['modulo_id' => $modulo->id]);

        $actividad = Actividad::factory()->create([
            'leccion_id'     => $leccion->id,
            'tipo'           => 'cuestionario',
            'puntaje_maximo' => $puntajeMaximo,
        ]);

        $this->actingAs($instructor);

        return $actividad;
    }

    public function test_agregar_pregunta_redistribuye_el_puntaje_entre_todas(): void
    {
        $actividad = $this->crearInstructorConActividad(90);
        Pregunta::factory()->create(['actividad_id' => $actividad->id, 'tipo' => 'respuesta_corta', 'puntaje' => 999]);

        $this->post(route('preguntas.store', $actividad), [
            'pregunta_texto' => '¿Segunda pregunta?',
            'tipo'           => 'respuesta_corta',
        ]);

        $this->assertEquals(2, $actividad->preguntas()->count());
        $this->assertEquals(90, $actividad->preguntas()->sum('puntaje'));
        $this->assertEquals(45, $actividad->preguntas()->orderBy('orden')->first()->puntaje);
    }

    public function test_eliminar_pregunta_redistribuye_el_puntaje_entre_las_restantes(): void
    {
        $actividad = $this->crearInstructorConActividad(90);
        $p1 = Pregunta::factory()->create(['actividad_id' => $actividad->id, 'tipo' => 'respuesta_corta', 'orden' => 1]);
        $p2 = Pregunta::factory()->create(['actividad_id' => $actividad->id, 'tipo' => 'respuesta_corta', 'orden' => 2]);
        $p3 = Pregunta::factory()->create(['actividad_id' => $actividad->id, 'tipo' => 'respuesta_corta', 'orden' => 3]);

        $this->delete(route('preguntas.destroy', $p2));

        $this->assertEquals(2, $actividad->preguntas()->count());
        $this->assertEquals(90, $actividad->preguntas()->sum('puntaje'));
    }

    public function test_marcar_correcta_en_pregunta_de_opcion_unica_desmarca_las_demas(): void
    {
        $actividad = $this->crearInstructorConActividad(10);
        $pregunta  = Pregunta::factory()->create([
            'actividad_id' => $actividad->id, 'tipo' => 'opcion_multiple', 'seleccion_multiple' => false,
        ]);
        $a = $pregunta->opciones()->create(['texto' => 'A', 'es_correcta' => true, 'orden' => 1]);
        $b = $pregunta->opciones()->create(['texto' => 'B', 'es_correcta' => false, 'orden' => 2]);

        $this->put(route('opciones.marcarCorrecta', $b));

        $this->assertFalse($a->fresh()->es_correcta);
        $this->assertTrue($b->fresh()->es_correcta);
    }

    public function test_marcar_correcta_en_pregunta_de_seleccion_multiple_solo_alterna_esa_opcion(): void
    {
        $actividad = $this->crearInstructorConActividad(10);
        $pregunta  = Pregunta::factory()->create([
            'actividad_id' => $actividad->id, 'tipo' => 'opcion_multiple', 'seleccion_multiple' => true,
        ]);
        $a = $pregunta->opciones()->create(['texto' => 'A', 'es_correcta' => true, 'orden' => 1]);
        $b = $pregunta->opciones()->create(['texto' => 'B', 'es_correcta' => false, 'orden' => 2]);

        $this->put(route('opciones.marcarCorrecta', $b));

        $this->assertTrue($a->fresh()->es_correcta);
        $this->assertTrue($b->fresh()->es_correcta);

        $this->put(route('opciones.marcarCorrecta', $b));
        $this->assertFalse($b->fresh()->es_correcta);
    }

    public function test_estudiante_no_puede_marcar_correcta(): void
    {
        $actividad = $this->crearInstructorConActividad(10);
        $pregunta  = Pregunta::factory()->create(['actividad_id' => $actividad->id, 'tipo' => 'opcion_multiple']);
        $opcion    = $pregunta->opciones()->create(['texto' => 'A', 'es_correcta' => false, 'orden' => 1]);

        $rolEstudiante = Rol::firstOrCreate(['nombre' => 'Estudiante'], ['descripcion' => 'Estudiante role']);
        $estudiante = User::factory()->create(['estado' => 'activo']);
        $estudiante->roles()->attach($rolEstudiante);

        $response = $this->actingAs($estudiante)->put(route('opciones.marcarCorrecta', $opcion));

        $response->assertForbidden();
        $this->assertFalse($opcion->fresh()->es_correcta);
    }

    public function test_editor_muestra_boton_marcar_correcta_y_badge_pendiente(): void
    {
        $actividad = $this->crearInstructorConActividad(10);
        $pregunta  = Pregunta::factory()->create(['actividad_id' => $actividad->id, 'tipo' => 'opcion_multiple']);
        $opcion    = $pregunta->opciones()->create(['texto' => 'A', 'es_correcta' => false, 'orden' => 1]);

        $response = $this->get(route('actividades.edit', $actividad));

        $response->assertOk();
        $response->assertSee('Falta marcar la correcta');
        $response->assertSee('Importar desde Google Forms');
        $response->assertSee(route('opciones.marcarCorrecta', $opcion), false);
    }
}
