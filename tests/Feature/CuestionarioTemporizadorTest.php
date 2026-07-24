<?php

namespace Tests\Feature;

use App\Models\Actividad;
use App\Models\Curso;
use App\Models\Inscripcion;
use App\Models\IntentoEnProgreso;
use App\Models\Leccion;
use App\Models\Modulo;
use App\Models\Opcion;
use App\Models\Pregunta;
use App\Models\Rol;
use App\Models\RespuestaEstudiante;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CuestionarioTemporizadorTest extends TestCase
{
    use RefreshDatabase;

    private User $instructor;
    private User $estudiante;
    private Curso $curso;
    private Leccion $leccion;

    protected function setUp(): void
    {
        parent::setUp();

        $rolInstructor  = Rol::create(['nombre' => 'Instructor']);
        $rolEstudiante  = Rol::create(['nombre' => 'Estudiante']);
        Rol::create(['nombre' => 'Administrador']);

        $this->instructor = User::factory()->create(['estado' => 'activo']);
        $this->instructor->roles()->attach($rolInstructor);

        $this->estudiante = User::factory()->create(['estado' => 'activo']);
        $this->estudiante->roles()->attach($rolEstudiante);

        $this->curso = Curso::factory()->create([
            'created_by' => $this->instructor->id,
            'estado'     => 'publicado',
        ]);

        $modulo        = Modulo::factory()->create(['curso_id' => $this->curso->id]);
        $this->leccion = Leccion::factory()->create(['modulo_id' => $modulo->id]);

        Inscripcion::create([
            'user_id'      => $this->estudiante->id,
            'curso_id'     => $this->curso->id,
            'fecha_inicio' => now(),
            'estado'       => 'en_progreso',
        ]);
    }

    private function crearCuestionario(?int $duracionMinutos, int $intentosPermitidos = 1): Actividad
    {
        $actividad = Actividad::factory()->create([
            'leccion_id'          => $this->leccion->id,
            'tipo'                => 'cuestionario',
            'puntaje_maximo'      => 100,
            'duracion_minutos'    => $duracionMinutos,
            'intentos_permitidos' => $intentosPermitidos,
        ]);

        $pregunta = Pregunta::factory()->create([
            'actividad_id' => $actividad->id,
            'tipo'         => 'opcion_multiple',
            'puntaje'      => 100,
        ]);
        Opcion::factory()->correcta()->create(['pregunta_id' => $pregunta->id]);
        Opcion::factory()->create(['pregunta_id' => $pregunta->id, 'es_correcta' => false]);

        return $actividad->fresh();
    }

    public function test_iniciar_intento_crea_la_fila_con_fecha_inicio_cercana_a_ahora(): void
    {
        $actividad = $this->crearCuestionario(20);

        $response = $this->actingAs($this->estudiante)->post(route('actividades.iniciarIntento', $actividad));

        $response->assertRedirect(route('actividades.show', $actividad));
        $this->assertDatabaseHas('intentos_en_progreso', [
            'user_id' => $this->estudiante->id, 'actividad_id' => $actividad->id,
        ]);
        $intento = IntentoEnProgreso::where('actividad_id', $actividad->id)->first();
        $this->assertTrue($intento->fecha_inicio->diffInSeconds(now()) < 5);
    }

    public function test_segundo_post_no_reinicia_fecha_inicio(): void
    {
        $actividad = $this->crearCuestionario(20);

        $this->actingAs($this->estudiante)->post(route('actividades.iniciarIntento', $actividad));
        $original = IntentoEnProgreso::where('actividad_id', $actividad->id)->first()->fecha_inicio;

        $this->travel(2)->minutes();
        $this->actingAs($this->estudiante)->post(route('actividades.iniciarIntento', $actividad));

        $actual = IntentoEnProgreso::where('actividad_id', $actividad->id)->first()->fecha_inicio;
        $this->assertTrue($original->equalTo($actual));
    }

    public function test_iniciar_intento_rechaza_actividades_que_no_son_cuestionario(): void
    {
        $actividad = Actividad::factory()->create([
            'leccion_id' => $this->leccion->id, 'tipo' => 'tarea', 'puntaje_maximo' => 5,
        ]);

        $response = $this->actingAs($this->estudiante)->post(route('actividades.iniciarIntento', $actividad));

        $response->assertForbidden();
    }

    public function test_iniciar_intento_rechaza_si_ya_se_agotaron_los_intentos(): void
    {
        $actividad = $this->crearCuestionario(20, 1);
        RespuestaEstudiante::factory()->create(['user_id' => $this->estudiante->id, 'actividad_id' => $actividad->id]);

        $response = $this->actingAs($this->estudiante)->post(route('actividades.iniciarIntento', $actividad));

        $response->assertRedirect(route('actividades.show', $actividad));
        $response->assertSessionHas('error', 'Ya usaste todos los intentos permitidos para este cuestionario.');
        $this->assertDatabaseMissing('intentos_en_progreso', ['actividad_id' => $actividad->id]);
    }

    public function test_iniciar_intento_rechaza_si_hay_intento_en_revision(): void
    {
        $actividad = $this->crearCuestionario(20, 3);
        RespuestaEstudiante::factory()->create([
            'user_id' => $this->estudiante->id, 'actividad_id' => $actividad->id, 'estado' => 'en_revision',
        ]);

        $response = $this->actingAs($this->estudiante)->post(route('actividades.iniciarIntento', $actividad));

        $response->assertSessionHas('error', 'Tienes un intento pendiente de revisión. Espera a que el instructor lo califique antes de reintentar.');
        $this->assertDatabaseMissing('intentos_en_progreso', ['actividad_id' => $actividad->id]);
    }
}
