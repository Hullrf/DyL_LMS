<?php

namespace Tests\Feature;

use App\Models\Actividad;
use App\Models\Curso;
use App\Models\Leccion;
use App\Models\Modulo;
use App\Models\Rol;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MoverActividadesTest extends TestCase
{
    use RefreshDatabase;

    private function crearInstructor(): User
    {
        $rol  = Rol::firstOrCreate(
            ['nombre' => 'Instructor'],
            ['descripcion' => 'Instructor role']
        );
        $user = User::factory()->create(['estado' => 'activo']);
        $user->roles()->attach($rol);
        return $user;
    }

    private function crearEstudiante(): User
    {
        $rol  = Rol::firstOrCreate(
            ['nombre' => 'Estudiante'],
            ['descripcion' => 'Estudiante role']
        );
        $user = User::factory()->create(['estado' => 'activo']);
        $user->roles()->attach($rol);
        return $user;
    }

    public function test_instructor_mueve_actividad_entre_lecciones_de_distinto_modulo(): void
    {
        $instructor = $this->crearInstructor();
        $curso    = Curso::factory()->publicado()->create(['created_by' => $instructor->id]);
        $moduloA  = Modulo::factory()->create(['curso_id' => $curso->id]);
        $moduloB  = Modulo::factory()->create(['curso_id' => $curso->id]);
        $leccionA = Leccion::factory()->create(['modulo_id' => $moduloA->id]);
        $leccionB = Leccion::factory()->create(['modulo_id' => $moduloB->id]);

        $act1 = Actividad::factory()->create(['leccion_id' => $leccionA->id, 'orden' => 0]);
        $act2 = Actividad::factory()->create(['leccion_id' => $leccionA->id, 'orden' => 1]);
        $act3 = Actividad::factory()->create(['leccion_id' => $leccionB->id, 'orden' => 0]);

        $response = $this->actingAs($instructor)->postJson(route('actividades.mover', $curso), [
            'leccion_destino_id' => $leccionB->id,
            'orden_destino'      => [$act2->id, $act3->id],
            'leccion_origen_id'  => $leccionA->id,
            'orden_origen'       => [$act1->id],
        ]);

        $response->assertOk();
        $response->assertJson(['ok' => true]);

        $this->assertDatabaseHas('actividades', ['id' => $act2->id, 'leccion_id' => $leccionB->id, 'orden' => 0]);
        $this->assertDatabaseHas('actividades', ['id' => $act3->id, 'leccion_id' => $leccionB->id, 'orden' => 1]);
        $this->assertDatabaseHas('actividades', ['id' => $act1->id, 'leccion_id' => $leccionA->id, 'orden' => 0]);
    }

    public function test_instructor_reordena_actividades_dentro_de_la_misma_leccion(): void
    {
        $instructor = $this->crearInstructor();
        $curso   = Curso::factory()->publicado()->create(['created_by' => $instructor->id]);
        $modulo  = Modulo::factory()->create(['curso_id' => $curso->id]);
        $leccion = Leccion::factory()->create(['modulo_id' => $modulo->id]);

        $act1 = Actividad::factory()->create(['leccion_id' => $leccion->id, 'orden' => 0]);
        $act2 = Actividad::factory()->create(['leccion_id' => $leccion->id, 'orden' => 1]);

        $response = $this->actingAs($instructor)->postJson(route('actividades.mover', $curso), [
            'leccion_destino_id' => $leccion->id,
            'orden_destino'      => [$act2->id, $act1->id],
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('actividades', ['id' => $act2->id, 'leccion_id' => $leccion->id, 'orden' => 0]);
        $this->assertDatabaseHas('actividades', ['id' => $act1->id, 'leccion_id' => $leccion->id, 'orden' => 1]);
    }

    public function test_estudiante_no_puede_mover_actividades(): void
    {
        $instructor = $this->crearInstructor();
        $estudiante = $this->crearEstudiante();
        $curso   = Curso::factory()->publicado()->create(['created_by' => $instructor->id]);
        $modulo  = Modulo::factory()->create(['curso_id' => $curso->id]);
        $leccion = Leccion::factory()->create(['modulo_id' => $modulo->id]);
        $act     = Actividad::factory()->create(['leccion_id' => $leccion->id, 'orden' => 0]);

        $response = $this->actingAs($estudiante)->postJson(route('actividades.mover', $curso), [
            'leccion_destino_id' => $leccion->id,
            'orden_destino'      => [$act->id],
        ]);

        $response->assertForbidden();
    }

    public function test_no_puede_mover_actividad_usando_leccion_de_otro_curso(): void
    {
        $instructorA = $this->crearInstructor();
        $instructorB = $this->crearInstructor();

        $cursoA   = Curso::factory()->publicado()->create(['created_by' => $instructorA->id]);
        $moduloA  = Modulo::factory()->create(['curso_id' => $cursoA->id]);
        $leccionA = Leccion::factory()->create(['modulo_id' => $moduloA->id]);
        $actA     = Actividad::factory()->create(['leccion_id' => $leccionA->id, 'orden' => 0]);

        $cursoB   = Curso::factory()->publicado()->create(['created_by' => $instructorB->id]);
        $moduloB  = Modulo::factory()->create(['curso_id' => $cursoB->id]);
        $leccionB = Leccion::factory()->create(['modulo_id' => $moduloB->id]);

        $response = $this->actingAs($instructorA)->postJson(route('actividades.mover', $cursoA), [
            'leccion_destino_id' => $leccionB->id,
            'orden_destino'      => [$actA->id],
        ]);

        $response->assertStatus(422);
    }
}
