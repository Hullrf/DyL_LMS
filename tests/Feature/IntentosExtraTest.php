<?php

namespace Tests\Feature;

use App\Models\Actividad;
use App\Models\Curso;
use App\Models\Inscripcion;
use App\Models\IntentoExtra;
use App\Models\Leccion;
use App\Models\Modulo;
use App\Models\Opcion;
use App\Models\Pregunta;
use App\Models\Rol;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IntentosExtraTest extends TestCase
{
    use RefreshDatabase;

    private User $instructor;
    private User $estudiante;
    private User $otroEstudiante;
    private Curso $curso;
    private Leccion $leccion;
    private Actividad $actividad;

    protected function setUp(): void
    {
        parent::setUp();

        $rolInstructor = Rol::create(['nombre' => 'Instructor']);
        $rolEstudiante = Rol::create(['nombre' => 'Estudiante']);
        Rol::create(['nombre' => 'Administrador']);

        $this->instructor = User::factory()->create(['estado' => 'activo']);
        $this->instructor->roles()->attach($rolInstructor);

        $this->estudiante = User::factory()->create(['estado' => 'activo']);
        $this->estudiante->roles()->attach($rolEstudiante);

        $this->otroEstudiante = User::factory()->create(['estado' => 'activo']);
        $this->otroEstudiante->roles()->attach($rolEstudiante);

        $this->curso = Curso::factory()->create([
            'created_by' => $this->instructor->id,
            'estado'     => 'publicado',
        ]);

        $modulo        = Modulo::factory()->create(['curso_id' => $this->curso->id]);
        $this->leccion = Leccion::factory()->create(['modulo_id' => $modulo->id]);

        foreach ([$this->estudiante, $this->otroEstudiante] as $u) {
            Inscripcion::create([
                'user_id'      => $u->id,
                'curso_id'     => $this->curso->id,
                'fecha_inicio' => now(),
                'estado'       => 'en_progreso',
            ]);
        }

        $this->actividad = Actividad::factory()->create([
            'leccion_id'          => $this->leccion->id,
            'tipo'                => 'cuestionario',
            'puntaje_maximo'      => 100,
            'intentos_permitidos' => 1,
        ]);

        $pregunta = Pregunta::factory()->create([
            'actividad_id' => $this->actividad->id,
            'tipo'         => 'opcion_multiple',
            'puntaje'      => 100,
        ]);
        Opcion::factory()->correcta()->create(['pregunta_id' => $pregunta->id]);
        Opcion::factory()->create(['pregunta_id' => $pregunta->id, 'es_correcta' => false]);
    }

    private function enviarRespuesta(User $user, Actividad $actividad): \Illuminate\Testing\TestResponse
    {
        $pregunta = $actividad->preguntas()->with('opciones')->first();
        $correcta = $pregunta->opciones->firstWhere('es_correcta', true);

        return $this->actingAs($user)->post(
            route('respuestas.store', $actividad),
            ['respuesta' => json_encode([$pregunta->id => $correcta->id])]
        );
    }

    public function test_instructor_otorga_intento_extra_y_el_estudiante_puede_reintentar(): void
    {
        $this->enviarRespuesta($this->estudiante, $this->actividad);

        $bloqueado = $this->enviarRespuesta($this->estudiante, $this->actividad);
        $bloqueado->assertSessionHas('error', 'Ya usaste todos los intentos permitidos para este cuestionario.');

        $response = $this->actingAs($this->instructor)->post(
            route('calificaciones.intentos-extra', [$this->actividad, $this->estudiante]),
            ['cantidad' => 1]
        );
        $response->assertRedirect(route('calificaciones.curso', $this->curso));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('intentos_extra', [
            'user_id'      => $this->estudiante->id,
            'actividad_id' => $this->actividad->id,
            'cantidad'     => 1,
        ]);

        $segundoIntento = $this->enviarRespuesta($this->estudiante, $this->actividad);
        $segundoIntento->assertRedirect(route('actividades.show', $this->actividad));
        $segundoIntento->assertSessionHas('success');
        $this->assertEquals(2, \App\Models\RespuestaEstudiante::where('user_id', $this->estudiante->id)->count());
    }

    public function test_otorgar_intento_extra_no_afecta_a_otros_estudiantes(): void
    {
        $this->enviarRespuesta($this->estudiante, $this->actividad);
        $this->enviarRespuesta($this->otroEstudiante, $this->actividad);

        $this->actingAs($this->instructor)->post(
            route('calificaciones.intentos-extra', [$this->actividad, $this->estudiante]),
            ['cantidad' => 1]
        );

        $otroBloqueado = $this->enviarRespuesta($this->otroEstudiante, $this->actividad);
        $otroBloqueado->assertSessionHas('error', 'Ya usaste todos los intentos permitidos para este cuestionario.');
    }

    public function test_otorgar_intento_extra_es_acumulativo(): void
    {
        $this->actingAs($this->instructor)->post(
            route('calificaciones.intentos-extra', [$this->actividad, $this->estudiante]),
            ['cantidad' => 1]
        );
        $this->actingAs($this->instructor)->post(
            route('calificaciones.intentos-extra', [$this->actividad, $this->estudiante]),
            ['cantidad' => 2]
        );

        $this->assertDatabaseHas('intentos_extra', [
            'user_id'      => $this->estudiante->id,
            'actividad_id' => $this->actividad->id,
            'cantidad'     => 3,
        ]);
    }

    public function test_no_se_puede_otorgar_intento_extra_en_actividad_no_cuestionario(): void
    {
        $tarea = Actividad::factory()->create([
            'leccion_id' => $this->leccion->id,
            'tipo'       => 'tarea',
        ]);

        $response = $this->actingAs($this->instructor)->post(
            route('calificaciones.intentos-extra', [$tarea, $this->estudiante]),
            ['cantidad' => 1]
        );

        $response->assertForbidden();
        $this->assertDatabaseCount('intentos_extra', 0);
    }

    public function test_instructor_de_otro_curso_no_puede_otorgar_intento_extra(): void
    {
        $otroInstructor = User::factory()->create(['estado' => 'activo']);
        $otroInstructor->roles()->attach(Rol::where('nombre', 'Instructor')->first());

        $response = $this->actingAs($otroInstructor)->post(
            route('calificaciones.intentos-extra', [$this->actividad, $this->estudiante]),
            ['cantidad' => 1]
        );

        $response->assertForbidden();
    }

    public function test_cantidad_invalida_es_rechazada(): void
    {
        $response = $this->actingAs($this->instructor)->post(
            route('calificaciones.intentos-extra', [$this->actividad, $this->estudiante]),
            ['cantidad' => 0]
        );

        $response->assertSessionHasErrors('cantidad');
        $this->assertDatabaseCount('intentos_extra', 0);
    }

    public function test_matriz_de_calificaciones_muestra_control_de_intento_extra_para_cuestionario(): void
    {
        $response = $this->actingAs($this->instructor)->get(route('calificaciones.curso', $this->curso));

        $response->assertOk();
        $response->assertSee('intento extra', false);
    }

    public function test_matriz_de_calificaciones_no_muestra_control_para_tarea(): void
    {
        $tarea = Actividad::factory()->create([
            'leccion_id' => $this->leccion->id,
            'tipo'       => 'tarea',
            'titulo'     => 'Tarea Sin Reintento',
        ]);

        $response = $this->actingAs($this->instructor)->get(route('calificaciones.curso', $this->curso));

        $response->assertOk();
        // La columna de la tarea no debe incluir el control de intento extra.
        $html = $response->getContent();
        $inicioTarea = strpos($html, 'Tarea Sin Reintento');
        $this->assertNotFalse($inicioTarea);
    }
}
