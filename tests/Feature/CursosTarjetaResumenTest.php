<?php

namespace Tests\Feature;

use App\Models\Actividad;
use App\Models\Curso;
use App\Models\Inscripcion;
use App\Models\Leccion;
use App\Models\Modulo;
use App\Models\ProgresoLeccion;
use App\Services\ResumenCursosService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ArmaActividades;
use Tests\Concerns\ArmaCursoCompleto;
use Tests\TestCase;

class CursosTarjetaResumenTest extends TestCase
{
    use RefreshDatabase, ArmaCursoCompleto, ArmaActividades;

    public function test_subtitulo_combina_codigo_y_grupo(): void
    {
        $this->assertSame('CAD1 · Grupo 703M', Curso::factory()->make(['codigo' => 'CAD1', 'grupo' => '703M'])->subtitulo());
        $this->assertSame('CAD1', Curso::factory()->make(['codigo' => 'CAD1'])->subtitulo());
        $this->assertNull(Curso::factory()->make()->subtitulo());
    }

    public function test_resumen_calcula_porcentaje_y_proxima_entrega(): void
    {
        $user  = $this->estudiante();
        $curso = Curso::factory()->publicado()->create();
        Inscripcion::factory()->create(['user_id' => $user->id, 'curso_id' => $curso->id]);
        $modulo = Modulo::factory()->create(['curso_id' => $curso->id]);
        [$l1, $l2, $l3, $l4] = Leccion::factory()->count(4)->create(['modulo_id' => $modulo->id])->all();
        ProgresoLeccion::create(['user_id' => $user->id, 'leccion_id' => $l1->id, 'completado' => true, 'fecha_completado' => now()]);
        Actividad::factory()->tarea()->create(['leccion_id' => $l2->id, 'fecha_cierre' => now()->addDays(3)]);

        $r = app(ResumenCursosService::class)->para($user, collect([$curso]));

        $this->assertSame(25, $r[$curso->id]['porcentaje']);
        $this->assertNotNull($r[$curso->id]['proxima']);
    }

    public function test_curso_sin_lecciones_dice_sin_contenido_y_no_divide_por_cero(): void
    {
        $user  = $this->estudiante();
        $curso = Curso::factory()->publicado()->create(['titulo' => 'Curso vacio', 'codigo' => 'CAD9', 'grupo' => '701M']);
        Inscripcion::factory()->create(['user_id' => $user->id, 'curso_id' => $curso->id]);

        $r = app(ResumenCursosService::class)->para($user, collect([$curso]));
        $this->assertNull($r[$curso->id]['porcentaje']);

        $this->actingAs($user)->get(route('cursos.index'))->assertOk()
            ->assertSee('Curso vacio')
            ->assertSee('CAD9 · Grupo 701M')
            ->assertSee('Sin contenido aún')
            ->assertDontSee('role="progressbar"', false);
    }

    public function test_tarjeta_muestra_porcentaje_y_proxima_entrega(): void
    {
        $user  = $this->estudiante();
        $curso = Curso::factory()->publicado()->create();
        Inscripcion::factory()->create(['user_id' => $user->id, 'curso_id' => $curso->id]);
        $modulo = Modulo::factory()->create(['curso_id' => $curso->id]);
        $l = Leccion::factory()->count(2)->create(['modulo_id' => $modulo->id]);
        ProgresoLeccion::create(['user_id' => $user->id, 'leccion_id' => $l[0]->id, 'completado' => true, 'fecha_completado' => now()]);
        Actividad::factory()->tarea()->create(['leccion_id' => $l[1]->id, 'fecha_cierre' => now()->addDays(2)]);

        $this->actingAs($user)->get(route('cursos.index'))->assertOk()
            ->assertSee('50%')->assertSee('Próxima entrega');
    }

    public function test_tarjeta_muestra_nota_actual_o_nada(): void
    {
        $user  = $this->estudiante();
        $curso = $this->cursoInscrito($user);
        $a1 = $this->actividadEn($curso, ['puntaje_maximo' => 5]);
        $this->actividadEn($curso, ['puntaje_maximo' => 5]);

        $this->actingAs($user)->get(route('cursos.index'))->assertOk()->assertDontSee('Nota actual');

        $this->calificar($user, $a1, 4);
        $r = app(ResumenCursosService::class)->para($user, collect([$curso]));
        $this->assertSame(80, $r[$curso->id]['nota']);

        $this->actingAs($user)->get(route('cursos.index'))->assertSeeText('Nota actual: 80%');
    }
}
