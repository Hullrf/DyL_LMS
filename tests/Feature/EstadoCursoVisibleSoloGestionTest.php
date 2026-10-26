<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ArmaActividades;
use Tests\Concerns\ArmaCursoCompleto;
use Tests\TestCase;

/** La etiqueta "Borrador" del detalle del curso es para quien lo gestiona, no para el estudiante inscrito. */
class EstadoCursoVisibleSoloGestionTest extends TestCase
{
    use RefreshDatabase, ArmaCursoCompleto, ArmaActividades;

    public function test_el_estudiante_inscrito_no_ve_la_etiqueta_borrador_pero_el_admin_si(): void
    {
        $estudiante = $this->estudiante();
        $curso = $this->cursoInscrito($estudiante, ['estado' => 'borrador', 'titulo' => 'Curso en preparacion']);

        $this->actingAs($estudiante)->get(route('cursos.show', $curso))
            ->assertOk()->assertSee('Curso en preparacion')->assertDontSee('Borrador');

        $this->actingAs($this->admin())->get(route('cursos.show', $curso))
            ->assertOk()->assertSee('Borrador');
    }
}
