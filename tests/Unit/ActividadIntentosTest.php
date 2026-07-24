<?php

namespace Tests\Unit;

use App\Models\Actividad;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ActividadIntentosTest extends TestCase
{
    use RefreshDatabase;

    public function test_actividad_nueva_tiene_un_intento_permitido_por_defecto(): void
    {
        $actividad = Actividad::factory()->create(['tipo' => 'cuestionario']);

        $this->assertEquals(1, $actividad->intentos_permitidos);
        $this->assertEquals('mas_alto', $actividad->criterio_calificacion_intentos);
        $this->assertTrue($actividad->mostrar_historial_intentos);
    }

    public function test_permite_multiples_intentos_es_falso_con_un_intento(): void
    {
        $actividad = Actividad::factory()->create(['tipo' => 'cuestionario', 'intentos_permitidos' => 1]);

        $this->assertFalse($actividad->permiteMultiplesIntentos());
    }

    public function test_permite_multiples_intentos_es_verdadero_con_mas_de_uno(): void
    {
        $actividad = Actividad::factory()->create(['tipo' => 'cuestionario', 'intentos_permitidos' => 3]);

        $this->assertTrue($actividad->permiteMultiplesIntentos());
    }

    public function test_permite_multiples_intentos_es_falso_para_otros_tipos_aunque_el_campo_sea_mayor_a_uno(): void
    {
        $actividad = Actividad::factory()->create(['tipo' => 'tarea', 'intentos_permitidos' => 5]);

        $this->assertFalse($actividad->permiteMultiplesIntentos());
    }

    public function test_intentos_usados_por_cuenta_las_respuestas_del_usuario(): void
    {
        $actividad = Actividad::factory()->create(['tipo' => 'cuestionario']);
        $usuario   = \App\Models\User::factory()->create();
        $otro      = \App\Models\User::factory()->create();

        \App\Models\RespuestaEstudiante::factory()->create(['user_id' => $usuario->id, 'actividad_id' => $actividad->id]);
        \App\Models\RespuestaEstudiante::factory()->create(['user_id' => $usuario->id, 'actividad_id' => $actividad->id]);
        \App\Models\RespuestaEstudiante::factory()->create(['user_id' => $otro->id, 'actividad_id' => $actividad->id]);

        $this->assertEquals(2, $actividad->intentosUsadosPor($usuario->id));
        $this->assertEquals(1, $actividad->intentosUsadosPor($otro->id));
    }

    public function test_tiene_intento_en_revision_para_detecta_respuesta_en_revision_del_usuario(): void
    {
        $actividad = Actividad::factory()->create(['tipo' => 'cuestionario']);
        $usuario   = \App\Models\User::factory()->create();

        $this->assertFalse($actividad->tieneIntentoEnRevisionPara($usuario->id));

        \App\Models\RespuestaEstudiante::factory()->create([
            'user_id' => $usuario->id, 'actividad_id' => $actividad->id, 'estado' => 'en_revision',
        ]);

        $this->assertTrue($actividad->tieneIntentoEnRevisionPara($usuario->id));
    }
}
