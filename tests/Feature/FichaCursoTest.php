<?php

namespace Tests\Feature;

use App\Models\Curso;
use App\Models\CursoFicha;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ArmaActividades;
use Tests\Concerns\ArmaCursoCompleto;
use Tests\TestCase;

class FichaCursoTest extends TestCase
{
    use RefreshDatabase, ArmaCursoCompleto, ArmaActividades;

    private function datos(array $extra = []): array
    {
        return $extra + [
            'justificacion'          => 'Porque la seguridad importa.',
            'resultado_aprendizaje'  => 'Aplicar fundamentos de seguridad.',
            'resultados_especificos' => "Contrastar principios\n\n  \nImplementar politicas\n",
            'creditos' => 2, 'semestre' => 7, 'prerrequisitos' => 'Redes',
            'programa' => 'Ingeniería de Sistemas', 'facultad' => 'Ingeniería',
            'bibliografia' => [
                ['autor' => 'Carvajal', 'anio' => 2011, 'titulo' => 'Seguridad en bases de datos', 'editorial' => 'Revista'],
                ['autor' => 'Basta', 'anio' => '', 'titulo' => 'Database security', 'editorial' => ''],
            ],
        ];
    }

    public function test_instructor_guarda_ficha_y_bibliografia_y_el_estudiante_la_ve(): void
    {
        $inst = $this->instructor();
        $curso = Curso::factory()->publicado()->create(['created_by' => $inst->id]);

        $this->actingAs($inst)->put(route('cursos.ficha.update', $curso), $this->datos())
            ->assertRedirect(route('cursos.ficha.edit', $curso));

        $ficha = $curso->fresh()->ficha;
        $this->assertSame(['Contrastar principios', 'Implementar politicas'], $ficha->resultados_especificos);
        $this->assertCount(2, $curso->fresh()->bibliografias);

        $est = $this->estudiante();
        \App\Models\Inscripcion::factory()->create(['user_id' => $est->id, 'curso_id' => $curso->id]);
        $this->actingAs($est)->get(route('cursos.show', $curso))->assertOk()
            ->assertSee('Información del curso')->assertSee('Porque la seguridad importa.')
            ->assertSee('Carvajal')->assertSee('Ingeniería de Sistemas')->assertSee('mailto:' . $inst->email, false);
    }

    public function test_actualizar_reemplaza_la_bibliografia(): void
    {
        $inst = $this->instructor();
        $curso = Curso::factory()->publicado()->create(['created_by' => $inst->id]);
        $this->actingAs($inst)->put(route('cursos.ficha.update', $curso), $this->datos());
        $this->actingAs($inst)->put(route('cursos.ficha.update', $curso), $this->datos(['bibliografia' => [
            ['autor' => 'Solo uno', 'anio' => 2020, 'titulo' => 'Unico', 'editorial' => 'E'],
        ]]));

        $this->assertSame(['Solo uno'], $curso->fresh()->bibliografias->pluck('autor')->all());
    }

    public function test_ficha_vacia_no_muestra_seccion_ni_variables_literales(): void
    {
        $inst = $this->instructor();
        $curso = Curso::factory()->publicado()->create(['created_by' => $inst->id]);
        $est = $this->estudiante();
        \App\Models\Inscripcion::factory()->create(['user_id' => $est->id, 'curso_id' => $curso->id]);

        $html = $this->actingAs($est)->get(route('cursos.show', $curso))->assertOk()->getContent();

        $this->assertStringNotContainsString('Información del curso', $html);
        $this->assertStringNotContainsString('{{', $html);
        $this->assertStringNotContainsString('}}', $html);
    }

    public function test_ficha_parcial_oculta_bloques_vacios(): void
    {
        $inst = $this->instructor();
        $curso = Curso::factory()->publicado()->create(['created_by' => $inst->id]);
        CursoFicha::create(['curso_id' => $curso->id, 'justificacion' => 'Solo esto']);
        $est = $this->estudiante();
        \App\Models\Inscripcion::factory()->create(['user_id' => $est->id, 'curso_id' => $curso->id]);

        $this->actingAs($est)->get(route('cursos.show', $curso))->assertOk()
            ->assertSee('Solo esto')->assertDontSee('Bibliografía')->assertDontSee('Prerrequisitos');
    }

    public function test_validacion_y_permisos(): void
    {
        $inst = $this->instructor();
        $curso = Curso::factory()->publicado()->create(['created_by' => $inst->id]);

        $this->actingAs($inst)->put(route('cursos.ficha.update', $curso), $this->datos([
            'creditos' => 99, 'bibliografia' => [['autor' => '', 'titulo' => '']],
        ]))->assertSessionHasErrors(['creditos', 'bibliografia.0.autor', 'bibliografia.0.titulo']);

        $this->actingAs($this->instructor())->get(route('cursos.ficha.edit', $curso))->assertForbidden();
        $this->actingAs($this->estudiante())->get(route('cursos.ficha.edit', $curso))->assertForbidden();
    }

    public function test_aviso_de_ficha_incompleta_en_la_edicion_del_curso(): void
    {
        $inst = $this->instructor();
        $curso = Curso::factory()->publicado()->create(['created_by' => $inst->id]);

        $this->actingAs($inst)->get(route('cursos.edit', $curso))->assertOk()
            ->assertSee('Ficha académica incompleta')->assertSee('Justificación');

        $this->actingAs($inst)->put(route('cursos.ficha.update', $curso), $this->datos());
        $this->actingAs($inst)->get(route('cursos.edit', $curso))->assertDontSee('Ficha académica incompleta');
    }

    public function test_bloque_de_soporte_se_oculta_si_no_hay_datos(): void
    {
        config(['lms.soporte' => ['correo' => null, 'url' => null, 'horario' => null, 'faq_url' => null]]);
        $inst = $this->instructor();
        $curso = Curso::factory()->publicado()->create(['created_by' => $inst->id]);
        CursoFicha::create(['curso_id' => $curso->id, 'justificacion' => 'Algo']);

        $this->actingAs($inst)->get(route('cursos.show', $curso))->assertOk()->assertDontSee('Soporte técnico');
    }
}
