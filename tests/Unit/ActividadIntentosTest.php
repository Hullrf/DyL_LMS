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
}
