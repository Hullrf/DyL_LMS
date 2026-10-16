<?php

namespace Tests\Feature;

use App\Models\Actividad;
use App\Models\Curso;
use App\Models\Inscripcion;
use App\Models\Leccion;
use App\Models\Modulo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ArmaCursoCompleto;
use Tests\TestCase;

class ModoEdicionTest extends TestCase
{
    use RefreshDatabase, ArmaCursoCompleto;

    private function escenario(): array
    {
        $inst  = $this->instructor();
        $curso = Curso::factory()->publicado()->create(['created_by' => $inst->id]);
        $modulo  = Modulo::factory()->create(['curso_id' => $curso->id]);
        $leccion = Leccion::factory()->create(['modulo_id' => $modulo->id]);
        $actividad = Actividad::factory()->tarea()->create(['leccion_id' => $leccion->id, 'titulo' => 'Mi tarea']);

        return [$inst, $curso, $modulo, $leccion, $actividad];
    }

    public function test_apagado_el_instructor_ve_el_interruptor_pero_ningun_control(): void
    {
        [$inst, $curso, $modulo, $leccion] = $this->escenario();

        $html = $this->actingAs($inst)->get(route('cursos.show', $curso))->assertOk()
            ->assertSee('Activar modo de edición')->getContent();

        $this->assertStringNotContainsString(route('lecciones.edit', $leccion), $html);
        $this->assertStringNotContainsString(route('modulos.edit', $modulo), $html);
        $this->assertStringNotContainsString(route('actividades.create', $leccion), $html);
    }

    public function test_encendido_aparecen_los_controles_y_se_recuerda_en_la_sesion(): void
    {
        [$inst, $curso, $modulo, $leccion, $actividad] = $this->escenario();

        $this->actingAs($inst)->post(route('cursos.modo-edicion', $curso))->assertRedirect(route('cursos.show', $curso));

        $html = $this->actingAs($inst)->get(route('cursos.show', $curso))->assertOk()
            ->assertSee('Salir del modo de edición')->getContent();
        foreach ([route('modulos.edit', $modulo), route('lecciones.create', $modulo), route('lecciones.edit', $leccion),
                  route('actividades.create', $leccion), route('actividades.edit', $actividad),
                  route('cursos.ponderaciones.edit', $curso), route('cursos.competencias.index', $curso),
                  route('cursos.ficha.edit', $curso), route('reportes.seguimiento', $curso)] as $url) {
            $this->assertStringContainsString($url, $html);
        }
        $this->assertStringContainsString('Mi tarea', $html);

        $this->actingAs($inst)->post(route('cursos.modo-edicion', $curso));
        $this->actingAs($inst)->get(route('cursos.show', $curso))->assertSee('Activar modo de edición');
    }

    public function test_el_estudiante_nunca_ve_controles_aunque_la_sesion_tenga_la_bandera(): void
    {
        [$inst, $curso, $modulo, $leccion] = $this->escenario();
        $est = $this->estudiante();
        Inscripcion::factory()->create(['user_id' => $est->id, 'curso_id' => $curso->id]);

        $html = $this->actingAs($est)->withSession(['modo_edicion' => true])->get(route('cursos.show', $curso))->assertOk()
            ->assertDontSee('modo de edición')->getContent();

        $this->assertStringNotContainsString(route('lecciones.edit', $leccion), $html);
        $this->assertStringNotContainsString(route('modulos.edit', $modulo), $html);
    }

    public function test_otro_instructor_y_estudiante_no_pueden_alternarlo(): void
    {
        [, $curso] = $this->escenario();

        $this->actingAs($this->instructor())->post(route('cursos.modo-edicion', $curso))->assertForbidden();
        $this->actingAs($this->estudiante())->post(route('cursos.modo-edicion', $curso))->assertForbidden();
    }

    public function test_un_instructor_con_la_bandera_en_curso_ajeno_no_ve_controles(): void
    {
        [, $curso, $modulo] = $this->escenario();
        $otro = $this->instructor();

        $html = $this->actingAs($otro)->withSession(['modo_edicion' => true])->get(route('cursos.show', $curso))->getContent();

        $this->assertStringNotContainsString(route('modulos.edit', $modulo), $html);
    }

    public function test_admin_puede_usarlo(): void
    {
        [, $curso, $modulo] = $this->escenario();
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('cursos.modo-edicion', $curso))->assertRedirect();
        $this->actingAs($admin)->get(route('cursos.show', $curso))->assertSee(route('modulos.edit', $modulo), false);
    }

    public function test_requiere_sesion(): void
    {
        [, $curso] = $this->escenario();

        $this->post(route('cursos.modo-edicion', $curso))->assertRedirect(route('login'));
    }
}
