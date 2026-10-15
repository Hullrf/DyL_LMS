<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\NotaCursoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\ArmaActividades;
use Tests\TestCase;

class CertificadoNotaPonderadaTest extends TestCase
{
    use RefreshDatabase, ArmaActividades;

    /** @return array<string, array{0: array<int, array{0: float, 1: float}>}> [max, nota] por actividad */
    public static function escenariosPorPuntos(): array
    {
        return [
            'tercios 5/5/5 con 5, 2.5, 0' => [[[5, 5], [5, 2.5], [5, 0]]],
            'tercios 5/5/5 con 4, 4, 4'   => [[[5, 4], [5, 4], [5, 4]]],
            'puntajes distintos 5 y 15'   => [[[5, 4], [15, 9]]],
            'frontera .5 con tercios'     => [[[100, 100], [100, 50], [100, 1.49]]],
        ];
    }

    #[DataProvider('escenariosPorPuntos')]
    public function test_paridad_por_puntos_con_formula_original(array $escenario): void
    {
        $u = User::factory()->create();
        $curso = $this->cursoInscrito($u);
        foreach ($escenario as [$max, $nota]) {
            $this->calificar($u, $this->actividadEn($curso, ['puntaje_maximo' => $max]), $nota);
        }

        // Formula original de CertificadoService::calcularCalificacionFinal
        $esperado = (int) round(array_sum(array_column($escenario, 1)) / array_sum(array_column($escenario, 0)) * 100);

        $this->assertSame($esperado, app(NotaCursoService::class)->para($u, $curso)['promedio_a_la_fecha']);
    }
}
