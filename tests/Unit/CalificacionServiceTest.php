<?php

namespace Tests\Unit;

use App\Models\Actividad;
use App\Models\Opcion;
use App\Models\Pregunta;
use App\Models\RespuestaEstudiante;
use App\Services\CalificacionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CalificacionServiceTest extends TestCase
{
    use RefreshDatabase;

    private CalificacionService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new CalificacionService();
    }

    public function test_calcular_cuestionario_devuelve_puntaje_maximo_con_todas_correctas(): void
    {
        $actividad = Actividad::factory()->create([
            'tipo'          => 'cuestionario',
            'puntaje_maximo' => 100,
        ]);
        $pregunta = Pregunta::factory()->create([
            'actividad_id' => $actividad->id,
            'puntaje'      => 10,
        ]);
        $correcta = Opcion::factory()->create([
            'pregunta_id' => $pregunta->id,
            'es_correcta' => true,
        ]);
        Opcion::factory()->create([
            'pregunta_id' => $pregunta->id,
            'es_correcta' => false,
        ]);

        $json = json_encode([$pregunta->id => $correcta->id]);
        $resultado = $this->service->calcularCuestionario($actividad, $json);

        $this->assertEquals(100, $resultado);
    }

    public function test_calcular_cuestionario_devuelve_cero_con_todas_incorrectas(): void
    {
        $actividad = Actividad::factory()->create([
            'tipo'          => 'cuestionario',
            'puntaje_maximo' => 100,
        ]);
        $pregunta = Pregunta::factory()->create([
            'actividad_id' => $actividad->id,
            'puntaje'      => 10,
        ]);
        Opcion::factory()->create(['pregunta_id' => $pregunta->id, 'es_correcta' => true]);
        $incorrecta = Opcion::factory()->create(['pregunta_id' => $pregunta->id, 'es_correcta' => false]);

        $json = json_encode([$pregunta->id => $incorrecta->id]);
        $resultado = $this->service->calcularCuestionario($actividad, $json);

        $this->assertEquals(0, $resultado);
    }

    public function test_calcular_cuestionario_devuelve_cero_con_json_invalido(): void
    {
        $actividad = Actividad::factory()->create(['tipo' => 'cuestionario', 'puntaje_maximo' => 100]);

        $resultado = $this->service->calcularCuestionario($actividad, 'no-es-json');

        $this->assertEquals(0, $resultado);
    }

    public function test_calcular_cuestionario_proporcional(): void
    {
        $actividad = Actividad::factory()->create([
            'tipo'          => 'cuestionario',
            'puntaje_maximo' => 100,
        ]);
        $p1 = Pregunta::factory()->create(['actividad_id' => $actividad->id, 'puntaje' => 5]);
        $p2 = Pregunta::factory()->create(['actividad_id' => $actividad->id, 'puntaje' => 5]);

        $c1 = Opcion::factory()->create(['pregunta_id' => $p1->id, 'es_correcta' => true]);
        Opcion::factory()->create(['pregunta_id' => $p1->id, 'es_correcta' => false]);
        Opcion::factory()->create(['pregunta_id' => $p2->id, 'es_correcta' => true]);
        $i2 = Opcion::factory()->create(['pregunta_id' => $p2->id, 'es_correcta' => false]);

        // Responde correctamente sólo la primera (50%)
        $json = json_encode([$p1->id => $c1->id, $p2->id => $i2->id]);
        $resultado = $this->service->calcularCuestionario($actividad, $json);

        $this->assertEquals(50, $resultado);
    }

    public function test_calificar_manual_actualiza_estado_a_calificada(): void
    {
        $respuesta = RespuestaEstudiante::factory()->create([
            'estado'       => 'sin_calificar',
            'calificacion' => null,
        ]);

        $this->service->calificarManual($respuesta, 85, 'Buen trabajo');

        $this->assertEquals(85, $respuesta->fresh()->calificacion);
        $this->assertEquals('calificada', $respuesta->fresh()->estado);
        $this->assertEquals('Buen trabajo', $respuesta->fresh()->feedback);
        $this->assertNotNull($respuesta->fresh()->fecha_calificacion);
    }

    public function test_calificar_manual_acepta_feedback_nulo(): void
    {
        $respuesta = RespuestaEstudiante::factory()->create(['estado' => 'sin_calificar']);

        $this->service->calificarManual($respuesta, 70, null);

        $this->assertEquals('calificada', $respuesta->fresh()->estado);
        $this->assertNull($respuesta->fresh()->feedback);
    }
}
