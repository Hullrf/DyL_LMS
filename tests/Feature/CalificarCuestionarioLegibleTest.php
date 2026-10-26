<?php

namespace Tests\Feature;

use App\Models\Opcion;
use App\Models\Pregunta;
use App\Models\RespuestaEstudiante;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ArmaActividades;
use Tests\Concerns\ArmaCursoCompleto;
use Tests\TestCase;

/**
 * La pantalla de calificar mostraba la respuesta de un cuestionario como JSON crudo
 * ({"147":"598",...}); ahora muestra cada pregunta con lo que el estudiante eligió.
 */
class CalificarCuestionarioLegibleTest extends TestCase
{
    use RefreshDatabase, ArmaCursoCompleto, ArmaActividades;

    private function cuestionarioRespondido(): array
    {
        $instructor = $this->instructor();
        $estudiante = $this->estudiante();
        $curso = $this->cursoInscrito($estudiante, ['created_by' => $instructor->id]);
        $actividad = $this->actividadEn($curso, ['tipo' => 'cuestionario', 'titulo' => 'Quiz de prueba', 'puntaje_maximo' => 10]);

        $p1 = Pregunta::factory()->create(['actividad_id' => $actividad->id, 'tipo' => 'opcion_multiple', 'pregunta_texto' => '¿Cuántos principios hay?', 'orden' => 1]);
        $ocho = Opcion::factory()->correcta()->create(['pregunta_id' => $p1->id, 'texto' => 'Siete principios', 'orden' => 0]);
        $diez = Opcion::factory()->create(['pregunta_id' => $p1->id, 'texto' => 'Diez principios', 'es_correcta' => false, 'orden' => 1]);

        $p2 = Pregunta::factory()->create(['actividad_id' => $actividad->id, 'tipo' => 'opcion_multiple', 'seleccion_multiple' => true, 'pregunta_texto' => 'Selecciona los principios correctos', 'orden' => 2]);
        $a = Opcion::factory()->correcta()->create(['pregunta_id' => $p2->id, 'texto' => 'Enfoque al cliente', 'orden' => 0]);
        $b = Opcion::factory()->correcta()->create(['pregunta_id' => $p2->id, 'texto' => 'Liderazgo', 'orden' => 1]);
        $c = Opcion::factory()->create(['pregunta_id' => $p2->id, 'texto' => 'Maximizar ingresos', 'es_correcta' => false, 'orden' => 2]);

        $p3 = Pregunta::factory()->create(['actividad_id' => $actividad->id, 'tipo' => 'respuesta_corta', 'pregunta_texto' => 'Explica la mejora continua', 'orden' => 3]);

        $respuesta = RespuestaEstudiante::factory()->calificada()->create([
            'user_id'        => $estudiante->id,
            'actividad_id'   => $actividad->id,
            'calificacion'   => 4,
            'respuesta'      => json_encode([
                $p1->id => (string) $diez->id,
                $p2->id => [(string) $a->id, (string) $c->id],
                $p3->id => 'Es un ciclo permanente de planear y corregir.',
            ]),
        ]);

        return [$instructor, $respuesta, [$p1, $p2, $p3]];
    }

    public function test_la_respuesta_de_un_cuestionario_se_muestra_por_pregunta_y_no_como_json(): void
    {
        [$instructor, $respuesta, [$p1, $p2, $p3]] = $this->cuestionarioRespondido();

        $this->actingAs($instructor)->get(route('calificaciones.show', $respuesta))
            ->assertOk()
            // preguntas
            ->assertSee('¿Cuántos principios hay?')
            ->assertSee('Selecciona los principios correctos')
            ->assertSee('Explica la mejora continua')
            // lo que eligió el estudiante (por su texto, no por su id)
            ->assertSee('Diez principios')
            ->assertSee('Enfoque al cliente')
            ->assertSee('Maximizar ingresos')
            ->assertSee('Es un ciclo permanente de planear y corregir.')
            // la opción correcta que el estudiante no eligió también se ve
            ->assertSee('Siete principios')
            ->assertSee('Liderazgo')
            // y ya no aparece el JSON crudo
            ->assertDontSee('"' . $p1->id . '":', false)
            ->assertDontSee('&quot;' . $p1->id . '&quot;:', false);
    }

    public function test_una_respuesta_de_cuestionario_que_no_es_json_sigue_mostrandose_como_texto(): void
    {
        [$instructor, $respuesta] = $this->cuestionarioRespondido();
        $respuesta->update(['respuesta' => 'Texto antiguo sin formato']);

        $this->actingAs($instructor)->get(route('calificaciones.show', $respuesta))
            ->assertOk()
            ->assertSee('Texto antiguo sin formato');
    }
}
