<?php

namespace Tests\Feature;

use App\Models\Actividad;
use App\Models\CriterioRubrica;
use App\Models\Curso;
use App\Models\Inscripcion;
use App\Models\Leccion;
use App\Models\Modulo;
use App\Models\NivelCriterio;
use App\Models\Rol;
use App\Models\RespuestaEstudiante;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RubricaTest extends TestCase
{
    use RefreshDatabase;

    private User $instructor;
    private User $estudiante;
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

        $curso = Curso::factory()->create([
            'created_by' => $this->instructor->id,
            'estado'     => 'publicado',
        ]);

        $modulo  = Modulo::factory()->create(['curso_id' => $curso->id]);
        $leccion = Leccion::factory()->create(['modulo_id' => $modulo->id]);

        $this->actividad = Actividad::factory()->create([
            'leccion_id'     => $leccion->id,
            'tipo'           => 'tarea',
            'puntaje_maximo' => 5.00,
            'usa_rubrica'    => false,
        ]);

        Inscripcion::create([
            'user_id'      => $this->estudiante->id,
            'curso_id'     => $curso->id,
            'fecha_inicio' => now(),
            'estado'       => 'en_progreso',
        ]);
    }

    public function test_instructor_puede_guardar_rubrica(): void
    {
        $criteriosJson = json_encode([
            [
                'nombre'  => 'Planteamiento',
                'niveles' => [
                    ['descripcion' => 'Deficiente',   'puntos' => '0'],
                    ['descripcion' => 'Aceptable',    'puntos' => '0.5'],
                    ['descripcion' => 'Sobresaliente','puntos' => '1.0'],
                ],
            ],
            [
                'nombre'  => 'Metodología',
                'niveles' => [
                    ['descripcion' => 'Deficiente', 'puntos' => '0'],
                    ['descripcion' => 'Excelente',  'puntos' => '1.5'],
                ],
            ],
        ]);

        $response = $this->actingAs($this->instructor)->post(
            route('rubrica.store', $this->actividad),
            ['usa_rubrica' => '1', 'criterios_json' => $criteriosJson]
        );

        $response->assertRedirect(route('actividades.edit', $this->actividad));
        $this->assertDatabaseHas('criterios_rubrica', [
            'actividad_id' => $this->actividad->id,
            'nombre'       => 'Planteamiento',
        ]);
        $this->assertDatabaseHas('niveles_criterio', ['descripcion' => 'Sobresaliente', 'puntos' => 1.0]);

        $this->actividad->refresh();
        $this->assertTrue($this->actividad->usa_rubrica);
        $this->assertEquals(2.5, (float) $this->actividad->puntaje_maximo); // 1.0 + 1.5
    }

    public function test_rubrica_visible_para_estudiante(): void
    {
        $this->actividad->update(['usa_rubrica' => true]);

        $criterio = CriterioRubrica::create([
            'actividad_id' => $this->actividad->id,
            'nombre'       => 'Criterio de Prueba',
            'orden'        => 0,
        ]);
        NivelCriterio::create(['criterio_id' => $criterio->id, 'descripcion' => 'Bajo',  'puntos' => 0.0, 'orden' => 0]);
        NivelCriterio::create(['criterio_id' => $criterio->id, 'descripcion' => 'Alto',  'puntos' => 1.0, 'orden' => 1]);

        $response = $this->actingAs($this->estudiante)
            ->get(route('actividades.show', $this->actividad));

        $response->assertOk();
        $response->assertSee('Criterio de Prueba');
        $response->assertSee('Bajo');
        $response->assertSee('1.00');
    }

    public function test_instructor_puede_calificar_con_rubrica(): void
    {
        $this->actividad->update(['usa_rubrica' => true, 'puntaje_maximo' => 2.5]);

        $criterio1 = CriterioRubrica::create(['actividad_id' => $this->actividad->id, 'nombre' => 'C1', 'orden' => 0]);
        $nivel1a   = NivelCriterio::create(['criterio_id' => $criterio1->id, 'descripcion' => 'Bajo',  'puntos' => 0,   'orden' => 0]);
        $nivel1b   = NivelCriterio::create(['criterio_id' => $criterio1->id, 'descripcion' => 'Alto',  'puntos' => 1.0, 'orden' => 1]);

        $criterio2 = CriterioRubrica::create(['actividad_id' => $this->actividad->id, 'nombre' => 'C2', 'orden' => 1]);
        $nivel2a   = NivelCriterio::create(['criterio_id' => $criterio2->id, 'descripcion' => 'Bajo',  'puntos' => 0,   'orden' => 0]);
        $nivel2b   = NivelCriterio::create(['criterio_id' => $criterio2->id, 'descripcion' => 'Alto',  'puntos' => 1.5, 'orden' => 1]);

        $respuesta = RespuestaEstudiante::create([
            'user_id'      => $this->estudiante->id,
            'actividad_id' => $this->actividad->id,
            'respuesta'    => 'Mi entrega de prueba',
            'estado'       => 'sin_calificar',
            'fecha_envio'  => now(),
        ]);

        $response = $this->actingAs($this->instructor)->post(
            route('calificaciones.rubrica', $respuesta),
            [
                'selecciones' => [
                    $criterio1->id => $nivel1b->id,  // 1.0
                    $criterio2->id => $nivel2b->id,  // 1.5
                ],
                'feedback' => 'Buen trabajo',
            ]
        );

        $response->assertRedirect(route('calificaciones.index'));
        $respuesta->refresh();
        $this->assertEquals(2.5, (float) $respuesta->calificacion);
        $this->assertEquals('calificada', $respuesta->estado);
        $this->assertDatabaseHas('selecciones_rubrica', [
            'respuesta_estudiante_id' => $respuesta->id,
            'criterio_id'             => $criterio1->id,
            'nivel_criterio_id'       => $nivel1b->id,
        ]);
    }

    public function test_no_puede_calificar_sin_todos_los_criterios(): void
    {
        $this->actividad->update(['usa_rubrica' => true]);

        $criterio1 = CriterioRubrica::create(['actividad_id' => $this->actividad->id, 'nombre' => 'C1', 'orden' => 0]);
        $nivel1b   = NivelCriterio::create(['criterio_id' => $criterio1->id, 'descripcion' => 'Alto', 'puntos' => 1.0, 'orden' => 0]);
        $criterio2 = CriterioRubrica::create(['actividad_id' => $this->actividad->id, 'nombre' => 'C2', 'orden' => 1]);
        NivelCriterio::create(['criterio_id' => $criterio2->id, 'descripcion' => 'Alto', 'puntos' => 1.5, 'orden' => 0]);

        $respuesta = RespuestaEstudiante::create([
            'user_id'      => $this->estudiante->id,
            'actividad_id' => $this->actividad->id,
            'respuesta'    => 'Entrega',
            'estado'       => 'sin_calificar',
            'fecha_envio'  => now(),
        ]);

        // Solo selecciona 1 de 2 criterios
        $response = $this->actingAs($this->instructor)->post(
            route('calificaciones.rubrica', $respuesta),
            ['selecciones' => [$criterio1->id => $nivel1b->id]]
        );

        $response->assertRedirect();
        $respuesta->refresh();
        $this->assertNull($respuesta->calificacion);
    }

    public function test_calificacion_manual_acepta_decimales(): void
    {
        $this->actividad->update(['puntaje_maximo' => 5.00, 'usa_rubrica' => false]);

        $respuesta = RespuestaEstudiante::create([
            'user_id'      => $this->estudiante->id,
            'actividad_id' => $this->actividad->id,
            'respuesta'    => 'Texto de respuesta',
            'estado'       => 'sin_calificar',
            'fecha_envio'  => now(),
        ]);

        $response = $this->actingAs($this->instructor)->put(
            route('calificaciones.update', $respuesta),
            ['calificacion' => '3.5', 'feedback' => 'Bien']
        );

        $response->assertRedirect(route('calificaciones.index'));
        $respuesta->refresh();
        $this->assertEquals(3.5, (float) $respuesta->calificacion);
        $this->assertEquals('calificada', $respuesta->estado);
    }
}
