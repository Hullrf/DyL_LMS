<?php

namespace Tests\Feature;

use App\Models\Actividad;
use App\Models\RespuestaEstudiante;
use App\Services\NotaCursoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ArmaActividades;
use Tests\Concerns\ArmaCursoCompleto;
use Tests\TestCase;

class NotaCursoServiceTest extends TestCase
{
    use RefreshDatabase, ArmaCursoCompleto, ArmaActividades;

    private function servicio(): NotaCursoService
    {
        return app(NotaCursoService::class);
    }

    public function test_modo_ponderado_cuando_todas_tienen_peso_y_suman_100(): void
    {
        $u = $this->estudiante();
        $curso = $this->cursoInscrito($u, ['nota_aprobatoria' => 70]);
        $a1 = $this->actividadEn($curso, ['puntaje_maximo' => 5, 'ponderacion' => 80]);
        $a2 = $this->actividadEn($curso, ['puntaje_maximo' => 5, 'ponderacion' => 20]);
        $this->calificar($u, $a1, 0);
        $this->calificar($u, $a2, 5);

        $r = $this->servicio()->para($u, $curso);

        $this->assertSame('ponderado', $r['modo']);
        $this->assertEquals(20.0, $r['acumulado']);
        $this->assertSame(20, $r['promedio_a_la_fecha']); // por puntos habria dado 50
        $this->assertEquals([80.0, 20.0], $r['filas']->pluck('peso')->all());
    }

    public function test_modo_por_puntos_si_alguna_ponderacion_falta_o_no_suma_100(): void
    {
        $u = $this->estudiante();
        $curso = $this->cursoInscrito($u);
        $a1 = $this->actividadEn($curso, ['puntaje_maximo' => 5, 'ponderacion' => 50]);
        $a2 = $this->actividadEn($curso, ['puntaje_maximo' => 15, 'ponderacion' => null]);
        $this->calificar($u, $a1, 5);
        $this->calificar($u, $a2, 0);

        $r = $this->servicio()->para($u, $curso);

        $this->assertSame('por_puntos', $r['modo']);
        $this->assertEquals([25.0, 75.0], $r['filas']->pluck('peso')->all());
        $this->assertSame(25, $r['promedio_a_la_fecha']);

        $a2->update(['ponderacion' => 40]); // suman 90
        $this->assertSame('por_puntos', $this->servicio()->para($u, $curso)['modo']);
    }

    public function test_pendientes_y_en_revision_no_cuentan_como_cero(): void
    {
        $u = $this->estudiante();
        $curso = $this->cursoInscrito($u);
        $a1 = $this->actividadEn($curso, ['puntaje_maximo' => 5]);
        $a2 = $this->actividadEn($curso, ['puntaje_maximo' => 5]);
        $a3 = $this->actividadEn($curso, ['puntaje_maximo' => 5]);
        $this->calificar($u, $a1, 5);
        RespuestaEstudiante::factory()->create(['user_id' => $u->id, 'actividad_id' => $a2->id, 'estado' => 'en_revision']);

        $r = $this->servicio()->para($u, $curso);
        $estados = $r['filas']->pluck('estado', 'actividad.id');

        $this->assertSame('calificada', $estados[$a1->id]);
        $this->assertSame('en_revision', $estados[$a2->id]);
        $this->assertSame('pendiente', $estados[$a3->id]);
        $this->assertNull($r['filas']->firstWhere('actividad.id', $a3->id)->nota);
        $this->assertSame(100, $r['promedio_a_la_fecha']);
    }

    public function test_proyeccion_de_nota_requerida(): void
    {
        $u = $this->estudiante();
        $curso = $this->cursoInscrito($u, ['nota_aprobatoria' => 70]);
        $acts = collect(range(1, 4))->map(fn() => $this->actividadEn($curso, ['puntaje_maximo' => 5, 'ponderacion' => 25]));
        $this->calificar($u, $acts[0], 5);
        $this->calificar($u, $acts[1], 3);

        $r = $this->servicio()->para($u, $curso);

        $this->assertEquals(40.0, $r['acumulado']);   // 25 + 15
        $this->assertEquals(60.0, $r['requerido']);   // faltan 30 sobre 50 de peso pendiente
        $this->assertTrue($r['alcanzable']);
    }

    public function test_proyeccion_ya_alcanzada_y_no_alcanzable(): void
    {
        $u = $this->estudiante();
        $curso = $this->cursoInscrito($u, ['nota_aprobatoria' => 50]);
        $a1 = $this->actividadEn($curso, ['puntaje_maximo' => 5, 'ponderacion' => 60]);
        $a2 = $this->actividadEn($curso, ['puntaje_maximo' => 5, 'ponderacion' => 40]);
        $this->calificar($u, $a1, 5); // aporta 60 >= 50

        $r = $this->servicio()->para($u, $curso);
        $this->assertEquals(0.0, $r['requerido']);
        $this->assertTrue($r['alcanzable']);

        // Caso no alcanzable: aprobatoria 100 y se sacó 0 en lo que pesa 60 -> faltan 100 sobre 40 de peso pendiente
        $u2 = $this->estudiante();
        $curso2 = $this->cursoInscrito($u2, ['nota_aprobatoria' => 100]);
        $b1 = $this->actividadEn($curso2, ['puntaje_maximo' => 5, 'ponderacion' => 60]);
        $this->actividadEn($curso2, ['puntaje_maximo' => 5, 'ponderacion' => 40]);
        $this->calificar($u2, $b1, 0);

        $r2 = $this->servicio()->para($u2, $curso2);
        $this->assertEquals(250.0, $r2['requerido']);
        $this->assertFalse($r2['alcanzable']);
    }

    public function test_curso_sin_actividades_calificables_no_divide_por_cero(): void
    {
        $u = $this->estudiante();
        $curso = $this->cursoInscrito($u);
        $this->actividadEn($curso, ['tipo' => 'lectura', 'puntaje_maximo' => null]);

        $r = $this->servicio()->para($u, $curso);

        $this->assertCount(0, $r['filas']);
        $this->assertNull($r['promedio_a_la_fecha']);
        $this->assertNull($r['requerido']);
    }

    public function test_actividad_eliminada_no_cuenta(): void
    {
        $u = $this->estudiante();
        $curso = $this->cursoInscrito($u);
        $a1 = $this->actividadEn($curso, ['puntaje_maximo' => 5]);
        $a2 = $this->actividadEn($curso, ['puntaje_maximo' => 5]);
        $a2->delete();

        $this->assertCount(1, $this->servicio()->para($u, $curso)['filas']);
    }
}
