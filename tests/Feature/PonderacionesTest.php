<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ArmaActividades;
use Tests\Concerns\ArmaCursoCompleto;
use Tests\TestCase;

class PonderacionesTest extends TestCase
{
    use RefreshDatabase, ArmaCursoCompleto, ArmaActividades;

    private function escenario(): array
    {
        $inst  = $this->instructor();
        $curso = \App\Models\Curso::factory()->publicado()->create(['created_by' => $inst->id]);
        $a1 = $this->actividadEn($curso, ['puntaje_maximo' => 5]);
        $a2 = $this->actividadEn($curso, ['puntaje_maximo' => 5]);

        return [$inst, $curso, $a1, $a2];
    }

    public function test_guarda_cuando_suman_100(): void
    {
        [$inst, $curso, $a1, $a2] = $this->escenario();

        $this->actingAs($inst)->put(route('cursos.ponderaciones.update', $curso), [
            'ponderaciones' => [$a1->id => '60', $a2->id => '40'],
        ])->assertRedirect(route('cursos.ponderaciones.edit', $curso))->assertSessionHas('success');

        $this->assertEquals(60, $a1->fresh()->ponderacion);
        $this->assertEquals(40, $a2->fresh()->ponderacion);
    }

    public function test_claves_de_actividades_de_otro_curso_se_ignoran(): void
    {
        [$inst, $curso, $a1, $a2] = $this->escenario();
        $ajena = $this->actividadEn(\App\Models\Curso::factory()->publicado()->create(), ['puntaje_maximo' => 5, 'ponderacion' => 33]);

        $this->actingAs($inst)->put(route('cursos.ponderaciones.update', $curso), [
            'ponderaciones' => [$a1->id => '60', $a2->id => '40', $ajena->id => '99'],
        ])->assertSessionHasNoErrors();

        $this->assertEquals(60, $a1->fresh()->ponderacion);
        $this->assertEquals(33, $ajena->fresh()->ponderacion);
    }

    public function test_rechaza_suma_distinta_de_100_o_pesos_incompletos(): void
    {
        [$inst, $curso, $a1, $a2] = $this->escenario();

        $this->actingAs($inst)->put(route('cursos.ponderaciones.update', $curso), [
            'ponderaciones' => [$a1->id => '50', $a2->id => '40'],
        ])->assertSessionHasErrors('ponderaciones');

        $this->actingAs($inst)->put(route('cursos.ponderaciones.update', $curso), [
            'ponderaciones' => [$a1->id => '100', $a2->id => ''],
        ])->assertSessionHasErrors('ponderaciones');

        $this->assertNull($a1->fresh()->ponderacion);
    }

    public function test_todo_vacio_limpia_las_ponderaciones(): void
    {
        [$inst, $curso, $a1, $a2] = $this->escenario();
        $a1->update(['ponderacion' => 70]);
        $a2->update(['ponderacion' => 30]);

        $this->actingAs($inst)->put(route('cursos.ponderaciones.update', $curso), [
            'ponderaciones' => [$a1->id => '', $a2->id => ''],
        ])->assertSessionHasNoErrors();

        $this->assertNull($a1->fresh()->ponderacion);
        $this->assertNull($a2->fresh()->ponderacion);
    }

    public function test_valores_fuera_de_rango_o_basura_se_rechazan_sin_500(): void
    {
        [$inst, $curso, $a1, $a2] = $this->escenario();

        $this->actingAs($inst)->put(route('cursos.ponderaciones.update', $curso), [
            'ponderaciones' => [$a1->id => '-5', $a2->id => 'abc'],
        ])->assertSessionHasErrors();

        $this->actingAs($inst)->put(route('cursos.ponderaciones.update', $curso), [
            'ponderaciones' => 'no-es-arreglo',
        ])->assertSessionHasErrors('ponderaciones');
    }

    public function test_otro_instructor_y_estudiante_no_pueden(): void
    {
        [$inst, $curso] = $this->escenario();

        $this->actingAs($this->instructor())->get(route('cursos.ponderaciones.edit', $curso))->assertForbidden();
        $this->actingAs($this->estudiante())->get(route('cursos.ponderaciones.edit', $curso))->assertForbidden();
    }

    public function test_la_pantalla_muestra_actividades_con_nota_y_ocultan_las_demas(): void
    {
        [$inst, $curso] = $this->escenario();
        $this->actividadEn($curso, ['tipo' => 'lectura', 'titulo' => 'Lectura sin nota', 'puntaje_maximo' => null]);

        $this->actingAs($inst)->get(route('cursos.ponderaciones.edit', $curso))
            ->assertOk()->assertSee('Ponderaciones')->assertDontSee('Lectura sin nota');
    }
}
