<?php

namespace Tests\Feature;

use App\Models\ProgresoActividad;
use App\Services\CompetenciasService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ArmaActividades;
use Tests\Concerns\ArmaCursoCompleto;
use Tests\TestCase;

class CompetenciasAvanceTest extends TestCase
{
    use RefreshDatabase, ArmaCursoCompleto, ArmaActividades;

    private function escenario(): array
    {
        $u = $this->estudiante();
        $curso = $this->cursoInscrito($u);
        $a1 = $this->actividadEn($curso, ['puntaje_maximo' => 5]);                                   // se califica 4/5
        $a2 = $this->actividadEn($curso, ['tipo' => 'lectura', 'puntaje_maximo' => null]);          // se marca hecha
        $a3 = $this->actividadEn($curso, ['puntaje_maximo' => 5]);                                   // pendiente
        $c = $curso->competencias()->create(['descripcion' => 'Aplicar fundamentos de seguridad']);
        $c->actividades()->sync([$a1->id, $a2->id, $a3->id]);

        return [$u, $curso, $c, $a1, $a2, $a3];
    }

    public function test_avance_cuenta_cumplidas_y_nota_media(): void
    {
        [$u, $curso, $c, $a1, $a2] = $this->escenario();
        $this->calificar($u, $a1, 4);
        ProgresoActividad::create(['user_id' => $u->id, 'actividad_id' => $a2->id, 'completado' => true, 'fecha_completado' => now()]);

        $fila = app(CompetenciasService::class)->avance($u, $curso)->first();

        $this->assertSame(3, $fila->total);
        $this->assertSame(2, $fila->cumplidas);
        $this->assertSame(80, $fila->nota_media);
    }

    public function test_sin_calificadas_la_nota_media_es_nula(): void
    {
        [$u, $curso] = $this->escenario();

        $fila = app(CompetenciasService::class)->avance($u, $curso)->first();

        $this->assertSame(0, $fila->cumplidas);
        $this->assertNull($fila->nota_media);
    }

    public function test_actividad_eliminada_no_cuenta(): void
    {
        [$u, $curso, $c, $a1, $a2, $a3] = $this->escenario();
        $a3->delete();

        $this->assertSame(2, app(CompetenciasService::class)->avance($u, $curso)->first()->total);
    }

    public function test_el_estudiante_ve_la_seccion_con_su_avance(): void
    {
        [$u, $curso, $c, $a1] = $this->escenario();
        $this->calificar($u, $a1, 4);

        $this->actingAs($u)->get(route('cursos.show', $curso))->assertOk()
            ->assertSee('Competencias del curso')->assertSee('Aplicar fundamentos de seguridad')
            ->assertSeeText('1 de 3 actividades cumplidas')->assertSeeText('Nota media: 80%');
    }

    public function test_curso_sin_competencias_no_muestra_nada_al_estudiante(): void
    {
        $u = $this->estudiante();
        $curso = $this->cursoInscrito($u);
        $this->actividadEn($curso);

        $this->actingAs($u)->get(route('cursos.show', $curso))->assertOk()->assertDontSee('Competencias del curso');
    }

    public function test_competencia_sin_actividades_enlazadas_no_rompe(): void
    {
        $u = $this->estudiante();
        $curso = $this->cursoInscrito($u);
        $curso->competencias()->create(['descripcion' => 'Competencia vacía']);

        $this->actingAs($u)->get(route('cursos.show', $curso))->assertOk()
            ->assertSee('Competencia vacía')->assertSeeText('Sin actividades enlazadas');
    }

    public function test_el_instructor_no_ve_la_seccion_de_estudiante(): void
    {
        [$u, $curso] = $this->escenario();

        $this->actingAs($curso->creador)->get(route('cursos.show', $curso))->assertOk()->assertDontSee('Competencias del curso');
    }
}
