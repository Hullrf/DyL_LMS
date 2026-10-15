<?php

namespace Tests\Feature;

use App\Models\Actividad;
use App\Models\Curso;
use App\Models\Inscripcion;
use App\Models\Leccion;
use App\Models\Modulo;
use App\Models\ProgresoActividad;
use App\Models\ProgresoLeccion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ArmaCursoCompleto;
use Tests\TestCase;

class ActividadDescompletarTest extends TestCase
{
    use RefreshDatabase, ArmaCursoCompleto;

    private function escenario(): array
    {
        $user  = $this->estudiante();
        $curso = Curso::factory()->publicado()->create();
        Inscripcion::factory()->create(['user_id' => $user->id, 'curso_id' => $curso->id]);
        $leccion   = Leccion::factory()->create(['modulo_id' => Modulo::factory()->create(['curso_id' => $curso->id])->id]);
        $actividad = Actividad::factory()->sinNota()->create(['leccion_id' => $leccion->id]);

        return [$user, $curso, $leccion, $actividad];
    }

    public function test_desmarcar_revierte_actividad_leccion_e_inscripcion(): void
    {
        [$user, $curso, $leccion, $actividad] = $this->escenario();
        $actividad->completarPara($user->id);

        $this->assertDatabaseHas('inscripciones', ['user_id' => $user->id, 'curso_id' => $curso->id, 'estado' => 'completado']);

        $this->actingAs($user)->post(route('actividades.descompletar', $actividad))
            ->assertRedirect(route('actividades.show', $actividad));

        $this->assertFalse(ProgresoActividad::where('user_id', $user->id)->where('actividad_id', $actividad->id)->value('completado'));
        $this->assertFalse((bool) ProgresoLeccion::where('user_id', $user->id)->where('leccion_id', $leccion->id)->value('completado'));
        $this->assertDatabaseHas('inscripciones', ['user_id' => $user->id, 'curso_id' => $curso->id, 'estado' => 'en_progreso', 'fecha_fin' => null]);
    }

    public function test_desmarcar_sin_haber_completado_no_falla(): void
    {
        [$user, , , $actividad] = $this->escenario();

        $this->actingAs($user)->post(route('actividades.descompletar', $actividad))->assertRedirect();
    }

    public function test_no_inscrito_no_puede_desmarcar(): void
    {
        [, , , $actividad] = $this->escenario();

        $this->actingAs($this->estudiante())->post(route('actividades.descompletar', $actividad))->assertForbidden();
    }

    public function test_actividades_con_nota_no_se_desmarcan(): void
    {
        [$user, , $leccion] = $this->escenario();
        $conNota = Actividad::factory()->tarea()->create(['leccion_id' => $leccion->id]);

        $this->actingAs($user)->post(route('actividades.descompletar', $conNota))->assertForbidden();
    }
}
