<?php

namespace Tests\Feature;

use App\Models\Actividad;
use App\Models\Curso;
use App\Models\Inscripcion;
use App\Models\Leccion;
use App\Models\Modulo;
use App\Models\Opcion;
use App\Models\Pregunta;
use App\Models\Rol;
use App\Models\RespuestaEstudiante;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CuestionarioIntentosTest extends TestCase
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

    private function crearCuestionarioOpcionMultiple(int $intentosPermitidos): Actividad
    {
        $actividad = Actividad::factory()->create([
            'leccion_id'          => $this->leccion->id,
            'tipo'                => 'cuestionario',
            'puntaje_maximo'      => 100,
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

    private function enviarRespuesta(Actividad $actividad): \Illuminate\Testing\TestResponse
    {
        $pregunta = $actividad->preguntas()->with('opciones')->first();
        $correcta = $pregunta->opciones->firstWhere('es_correcta', true);

        return $this->actingAs($this->estudiante)->post(
            route('respuestas.store', $actividad),
            ['respuesta' => json_encode([$pregunta->id => $correcta->id])]
        );
    }

    public function test_cuestionario_de_un_intento_bloquea_el_segundo_envio(): void
    {
        $actividad = $this->crearCuestionarioOpcionMultiple(1);

        $this->enviarRespuesta($actividad)->assertRedirect(route('actividades.show', $actividad));
        $response = $this->enviarRespuesta($actividad);

        $response->assertRedirect(route('actividades.show', $actividad));
        $response->assertSessionHas('error', 'Ya usaste todos los intentos permitidos para este cuestionario.');
        $this->assertEquals(1, RespuestaEstudiante::count());
    }

    public function test_cuestionario_con_varios_intentos_permite_reintentar_hasta_el_limite(): void
    {
        $actividad = $this->crearCuestionarioOpcionMultiple(3);

        $this->enviarRespuesta($actividad)->assertRedirect(route('actividades.show', $actividad));
        $this->enviarRespuesta($actividad)->assertRedirect(route('actividades.show', $actividad));
        $this->enviarRespuesta($actividad)->assertRedirect(route('actividades.show', $actividad));

        $this->assertEquals(3, RespuestaEstudiante::count());

        $response = $this->enviarRespuesta($actividad);
        $response->assertSessionHas('error', 'Ya usaste todos los intentos permitidos para este cuestionario.');
        $this->assertEquals(3, RespuestaEstudiante::count());
    }

    public function test_intento_en_revision_bloquea_un_nuevo_intento(): void
    {
        $actividad = Actividad::factory()->create([
            'leccion_id'          => $this->leccion->id,
            'tipo'                => 'cuestionario',
            'puntaje_maximo'      => 100,
            'intentos_permitidos' => 3,
        ]);
        $pregunta = Pregunta::factory()->create([
            'actividad_id' => $actividad->id,
            'tipo'         => 'respuesta_corta',
            'puntaje'      => 100,
        ]);

        $response = $this->actingAs($this->estudiante)->post(
            route('respuestas.store', $actividad),
            ['respuesta' => json_encode([$pregunta->id => 'mi respuesta libre'])]
        );
        $response->assertRedirect(route('actividades.show', $actividad));
        $this->assertDatabaseHas('respuestas_estudiantes', ['actividad_id' => $actividad->id, 'estado' => 'en_revision']);

        $segundoIntento = $this->actingAs($this->estudiante)->post(
            route('respuestas.store', $actividad),
            ['respuesta' => json_encode([$pregunta->id => 'otra respuesta'])]
        );
        $segundoIntento->assertSessionHas('error', 'Tienes un intento pendiente de revisión. Espera a que el instructor lo califique antes de reintentar.');
        $this->assertEquals(1, RespuestaEstudiante::count());
    }

    public function test_otros_tipos_de_actividad_siguen_limitados_a_un_solo_envio(): void
    {
        $actividad = Actividad::factory()->create([
            'leccion_id'          => $this->leccion->id,
            'tipo'                => 'tarea',
            'puntaje_maximo'      => 5,
            'intentos_permitidos' => 5,
        ]);

        $primero = $this->actingAs($this->estudiante)->post(
            route('respuestas.store', $actividad),
            ['respuesta' => 'Mi entrega']
        );
        $primero->assertRedirect(route('actividades.show', $actividad));

        $segundo = $this->actingAs($this->estudiante)->post(
            route('respuestas.store', $actividad),
            ['respuesta' => 'Otro intento']
        );
        $segundo->assertSessionHas('error', 'Ya has respondido esta actividad.');
        $this->assertEquals(1, RespuestaEstudiante::count());
    }

    public function test_instructor_puede_configurar_intentos_al_crear_cuestionario(): void
    {
        $response = $this->actingAs($this->instructor)->post(
            route('actividades.store', $this->leccion),
            [
                'titulo'                         => 'Quiz con reintentos',
                'tipo'                            => 'cuestionario',
                'puntaje_maximo'                  => 100,
                'intentos_permitidos'             => 3,
                'criterio_calificacion_intentos'  => 'ultimo',
                'mostrar_historial_intentos'      => '0',
            ]
        );

        $response->assertRedirect();
        $this->assertDatabaseHas('actividades', [
            'titulo'                         => 'Quiz con reintentos',
            'intentos_permitidos'            => 3,
            'criterio_calificacion_intentos' => 'ultimo',
            'mostrar_historial_intentos'     => 0,
        ]);
    }

    public function test_actividad_creada_sin_especificar_intentos_usa_defaults(): void
    {
        $response = $this->actingAs($this->instructor)->post(
            route('actividades.store', $this->leccion),
            ['titulo' => 'Quiz simple', 'tipo' => 'cuestionario', 'puntaje_maximo' => 100]
        );

        $response->assertRedirect();
        $this->assertDatabaseHas('actividades', [
            'titulo'                         => 'Quiz simple',
            'intentos_permitidos'            => 1,
            'criterio_calificacion_intentos' => 'mas_alto',
            'mostrar_historial_intentos'     => 1,
        ]);
    }

    public function test_instructor_puede_actualizar_intentos_permitidos(): void
    {
        $actividad = $this->crearCuestionarioOpcionMultiple(1);

        $response = $this->actingAs($this->instructor)->put(
            route('actividades.update', $actividad),
            [
                'titulo'                         => $actividad->titulo,
                'puntaje_maximo'                 => 100,
                'intentos_permitidos'            => 2,
                'criterio_calificacion_intentos' => 'ultimo',
                'mostrar_historial_intentos'     => '1',
            ]
        );

        $response->assertRedirect(route('actividades.edit', $actividad));
        $actividad->refresh();
        $this->assertEquals(2, $actividad->intentos_permitidos);
        $this->assertEquals('ultimo', $actividad->criterio_calificacion_intentos);
        $this->assertTrue($actividad->mostrar_historial_intentos);
    }

    public function test_update_preserva_valores_de_intentos_cuando_son_omitidos(): void
    {
        // Crear cuestionario con valores no-default
        $actividad = $this->crearCuestionarioOpcionMultiple(3);
        $actividad->update([
            'criterio_calificacion_intentos' => 'ultimo',
            'mostrar_historial_intentos'     => false,
        ]);
        $actividad->refresh();

        // Verificar valores iniciales
        $this->assertEquals(3, $actividad->intentos_permitidos);
        $this->assertEquals('ultimo', $actividad->criterio_calificacion_intentos);
        $this->assertFalse($actividad->mostrar_historial_intentos);

        // UPDATE omitiendo los 3 campos - solo cambiar titulo y puntaje
        $response = $this->actingAs($this->instructor)->put(
            route('actividades.update', $actividad),
            [
                'titulo'         => 'Nuevo titulo',
                'puntaje_maximo' => 50,
                // intentos_permitidos, criterio_calificacion_intentos, mostrar_historial_intentos OMITIDOS
            ]
        );

        $response->assertRedirect(route('actividades.edit', $actividad));
        $actividad->refresh();

        // Verificar que los 3 campos mantienen sus valores originales
        $this->assertEquals(3, $actividad->intentos_permitidos);
        $this->assertEquals('ultimo', $actividad->criterio_calificacion_intentos);
        $this->assertFalse($actividad->mostrar_historial_intentos);

        // Verificar que el titulo si cambio
        $this->assertEquals('Nuevo titulo', $actividad->titulo);
        $this->assertEquals(50, $actividad->puntaje_maximo);
    }

    public function test_muestra_intento_x_de_y_cuando_hay_multiples_intentos(): void
    {
        $actividad = $this->crearCuestionarioOpcionMultiple(3);
        $this->enviarRespuesta($actividad);

        $response = $this->actingAs($this->estudiante)->get(route('actividades.show', $actividad));

        $response->assertOk();
        $response->assertSee('Intento 1 de 3');
        $response->assertSee('Reintentar', false);
    }

    public function test_no_muestra_reintentar_cuando_se_agotan_los_intentos_multiples(): void
    {
        $actividad = $this->crearCuestionarioOpcionMultiple(2);
        $this->enviarRespuesta($actividad);
        $this->enviarRespuesta($actividad);

        $response = $this->actingAs($this->estudiante)->get(route('actividades.show', $actividad));

        $response->assertOk();
        $response->assertSee('Intento 2 de 2');
        $response->assertDontSee('Reintentar');
    }

    public function test_muestra_bloqueo_por_revision_pendiente(): void
    {
        $actividad = Actividad::factory()->create([
            'leccion_id' => $this->leccion->id, 'tipo' => 'cuestionario',
            'puntaje_maximo' => 100, 'intentos_permitidos' => 3,
        ]);
        Pregunta::factory()->create(['actividad_id' => $actividad->id, 'tipo' => 'respuesta_corta', 'puntaje' => 100]);
        $pregunta = $actividad->preguntas()->first();

        $this->actingAs($this->estudiante)->post(
            route('respuestas.store', $actividad),
            ['respuesta' => json_encode([$pregunta->id => 'texto libre'])]
        );

        $response = $this->actingAs($this->estudiante)->get(route('actividades.show', $actividad));

        $response->assertOk();
        $response->assertSee('Intento pendiente de revisión');
        $response->assertDontSee('Reintentar');
    }

    public function test_historial_de_intentos_se_oculta_si_la_configuracion_lo_indica(): void
    {
        $actividad = $this->crearCuestionarioOpcionMultiple(3);
        $actividad->update(['mostrar_historial_intentos' => false]);
        $this->enviarRespuesta($actividad);

        $response = $this->actingAs($this->estudiante)->get(route('actividades.show', $actividad));

        $response->assertOk();
        $response->assertDontSee('Historial de intentos');
    }

    public function test_cuestionario_de_un_intento_mantiene_el_mensaje_actual(): void
    {
        $actividad = $this->crearCuestionarioOpcionMultiple(1);
        $this->enviarRespuesta($actividad);

        $response = $this->actingAs($this->estudiante)->get(route('actividades.show', $actividad));

        $response->assertOk();
        $response->assertSee('Ya respondiste esta actividad');
    }
}
