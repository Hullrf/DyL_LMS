<?php

namespace Tests\Feature;

use App\Models\Competencia;
use App\Models\Curso;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ArmaActividades;
use Tests\Concerns\ArmaCursoCompleto;
use Tests\TestCase;

class CompetenciasInstructorTest extends TestCase
{
    use RefreshDatabase, ArmaCursoCompleto, ArmaActividades;

    private function escenario(): array
    {
        $inst  = $this->instructor();
        $curso = Curso::factory()->publicado()->create(['created_by' => $inst->id]);
        $a1 = $this->actividadEn($curso, ['titulo' => 'Actividad uno']);
        $a2 = $this->actividadEn($curso, ['titulo' => 'Actividad dos']);

        return [$inst, $curso, $a1, $a2];
    }

    public function test_estado_vacio_guia_al_instructor(): void
    {
        [$inst, $curso] = $this->escenario();

        $this->actingAs($inst)->get(route('cursos.competencias.index', $curso))->assertOk()
            ->assertSee('Aún no hay competencias')->assertSee('Agregar competencia');
    }

    public function test_crear_competencia_con_actividades(): void
    {
        [$inst, $curso, $a1, $a2] = $this->escenario();

        $this->actingAs($inst)->post(route('cursos.competencias.store', $curso), [
            'descripcion' => 'Aplicar fundamentos de seguridad', 'actividad_ids' => [$a1->id, $a2->id],
        ])->assertRedirect(route('cursos.competencias.index', $curso));

        $c = Competencia::firstOrFail();
        $this->assertSame($curso->id, $c->curso_id);
        $this->assertEqualsCanonicalizing([$a1->id, $a2->id], $c->actividades()->pluck('actividades.id')->all());
    }

    public function test_actividades_de_otro_curso_se_ignoran(): void
    {
        [$inst, $curso, $a1] = $this->escenario();
        $otroCurso = Curso::factory()->publicado()->create(['created_by' => $inst->id]);
        $ajena = $this->actividadEn($otroCurso);

        $this->actingAs($inst)->post(route('cursos.competencias.store', $curso), [
            'descripcion' => 'X', 'actividad_ids' => [$a1->id, $ajena->id],
        ]);

        $this->assertSame([$a1->id], Competencia::firstOrFail()->actividades()->pluck('actividades.id')->all());
    }

    public function test_actualizar_reemplaza_enlaces_y_eliminar_borra(): void
    {
        [$inst, $curso, $a1, $a2] = $this->escenario();
        $c = $curso->competencias()->create(['descripcion' => 'Vieja']);
        $c->actividades()->sync([$a1->id]);

        $this->actingAs($inst)->put(route('cursos.competencias.update', [$curso, $c]), [
            'descripcion' => 'Nueva', 'actividad_ids' => [$a2->id],
        ])->assertRedirect(route('cursos.competencias.index', $curso));

        $this->assertSame('Nueva', $c->fresh()->descripcion);
        $this->assertSame([$a2->id], $c->actividades()->pluck('actividades.id')->all());

        $this->actingAs($inst)->delete(route('cursos.competencias.destroy', [$curso, $c]))->assertRedirect();
        $this->assertDatabaseCount('competencias', 0);
        $this->assertDatabaseCount('actividad_competencia', 0);
    }

    public function test_validacion_de_la_descripcion(): void
    {
        [$inst, $curso] = $this->escenario();

        $this->actingAs($inst)->post(route('cursos.competencias.store', $curso), ['descripcion' => ''])
            ->assertSessionHasErrors('descripcion');
        $this->actingAs($inst)->post(route('cursos.competencias.store', $curso), ['descripcion' => str_repeat('a', 501)])
            ->assertSessionHasErrors('descripcion');
        $this->actingAs($inst)->post(route('cursos.competencias.store', $curso), ['descripcion' => 'ok', 'actividad_ids' => 'basura'])
            ->assertSessionHasErrors('actividad_ids');
    }

    public function test_otro_instructor_estudiante_y_competencia_de_otro_curso(): void
    {
        [$inst, $curso] = $this->escenario();
        $otroCurso = Curso::factory()->publicado()->create(['created_by' => $inst->id]);
        $ajena = $otroCurso->competencias()->create(['descripcion' => 'De otro curso']);

        $this->actingAs($this->instructor())->get(route('cursos.competencias.index', $curso))->assertForbidden();
        $this->actingAs($this->estudiante())->get(route('cursos.competencias.index', $curso))->assertForbidden();
        $this->actingAs($inst)->put(route('cursos.competencias.update', [$curso, $ajena]), ['descripcion' => 'hack'])->assertNotFound();
        $this->assertSame('De otro curso', $ajena->fresh()->descripcion);
    }

    public function test_actividad_eliminada_no_aparece_en_la_pantalla(): void
    {
        [$inst, $curso, $a1, $a2] = $this->escenario();
        $a2->delete();

        $this->actingAs($inst)->get(route('cursos.competencias.index', $curso))->assertOk()
            ->assertSee('Actividad uno')->assertDontSee('Actividad dos');
    }
}
