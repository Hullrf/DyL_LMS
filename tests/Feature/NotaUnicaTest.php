<?php

namespace Tests\Feature;

use App\Models\Curso;
use App\Models\Inscripcion;
use App\Services\NotaCursoService;
use App\Services\ReporteService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ArmaActividades;
use Tests\Concerns\ArmaCursoCompleto;
use Tests\TestCase;

/** La nota del curso es una sola: matriz, reportes, certificado y vistas del estudiante coinciden. */
class NotaUnicaTest extends TestCase
{
    use RefreshDatabase, ArmaCursoCompleto, ArmaActividades;

    /** A1 max 100 peso 20, A2 max 10 peso 80; estudiante 100/100 y 0/10 => 20 (por puntaje daría 91). */
    private function escenario(): array
    {
        $inst = $this->instructor();
        $u    = $this->estudiante();
        $curso = $this->cursoInscrito($u, ['created_by' => $inst->id, 'nota_aprobatoria' => 80]);
        Inscripcion::where('user_id', $u->id)->update(['estado' => 'completado']);
        $a1 = $this->actividadEn($curso, ['puntaje_maximo' => 100, 'ponderacion' => 20]);
        $a2 = $this->actividadEn($curso, ['puntaje_maximo' => 10, 'ponderacion' => 80]);
        $this->calificar($u, $a1, 100);
        $this->calificar($u, $a2, 0);

        return [$inst, $u, $curso];
    }

    public function test_la_matriz_coincide_con_la_nota_del_certificado_y_gobierna_bajo_minimo(): void
    {
        [$inst, $u, $curso] = $this->escenario();

        $final = app(NotaCursoService::class)->para($u, $curso)['promedio_a_la_fecha'];
        $this->assertSame(20, $final);

        $resp = $this->actingAs($inst)->get(route('calificaciones.curso', $curso))->assertOk();
        $fila = $resp->viewData('filas')->first();
        $this->assertSame($final, $fila->promedio_total);
        $this->assertSame($final, $fila->promedio);
        $resp->assertSee('Aprobar de todas formas');
    }

    public function test_el_filtro_por_modulo_usa_los_pesos_del_curso_completo(): void
    {
        [$inst, $u, $curso] = $this->escenario();
        $modulo = $curso->modulos()->orderBy('orden')->first(); // contiene A1 (100/100)

        $fila = $this->actingAs($inst)->get(route('calificaciones.curso', [$curso, 'modulo' => $modulo->id]))
            ->viewData('filas')->first();

        $this->assertSame(100, $fila->promedio);
        $this->assertSame(20, $fila->promedio_total);
    }

    public function test_reporte_coincide_con_nota_curso_service_en_curso_ponderado(): void
    {
        [, $u, $curso] = $this->escenario();
        $esperado = app(NotaCursoService::class)->para($u, $curso)['promedio_a_la_fecha'];

        $porCurso = app(ReporteService::class)->reportePorCurso($curso)['estudiantes']->firstWhere('usuario.id', $u->id);
        $porEst   = app(ReporteService::class)->reportePorEstudiante($u)['cursos']->firstWhere('curso.id', $curso->id);

        $this->assertSame($esperado, $porCurso['promedio']);
        $this->assertSame($esperado, $porEst['promedio']);
    }

    public function test_aviso_cuando_agregar_una_actividad_rompe_la_ponderacion(): void
    {
        [$inst, $u, $curso] = $this->escenario();
        $aviso = 'hoy la nota se calcula por puntaje';

        $this->assertFalse($curso->ponderacionIncompleta());
        $this->assertSame('ponderado', app(NotaCursoService::class)->para($u, $curso)['modo']);
        foreach (['cursos.ponderaciones.edit', 'cursos.edit', 'calificaciones.curso'] as $ruta) {
            $this->actingAs($inst)->get(route($ruta, $curso))->assertOk()->assertDontSee($aviso);
        }

        $this->actividadEn($curso, ['puntaje_maximo' => 10]); // sin ponderación

        $this->assertTrue($curso->ponderacionIncompleta());
        $this->assertSame('por_puntos', app(NotaCursoService::class)->para($u, $curso)['modo']);
        foreach (['cursos.ponderaciones.edit', 'cursos.edit', 'calificaciones.curso'] as $ruta) {
            $this->actingAs($inst)->get(route($ruta, $curso))->assertOk()->assertSee($aviso);
        }
        $this->actingAs($inst)->get(route('cursos.ponderaciones.edit', $curso))->assertSee('Modo actual:', false)->assertSee('por puntaje');
    }

    public function test_sin_ponderaciones_no_hay_aviso(): void
    {
        $inst  = $this->instructor();
        $curso = Curso::factory()->publicado()->create(['created_by' => $inst->id]);
        $this->actividadEn($curso, ['puntaje_maximo' => 10]);

        $this->assertFalse($curso->ponderacionIncompleta());
        $this->actingAs($inst)->get(route('cursos.ponderaciones.edit', $curso))->assertOk()->assertDontSee('hoy la nota se calcula por puntaje');
    }
}
