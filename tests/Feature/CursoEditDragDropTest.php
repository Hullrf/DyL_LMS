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

class CursoEditDragDropTest extends TestCase
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

    public function test_vista_de_edicion_del_curso_renderiza_marcado_de_drag_and_drop(): void
    {
        $instructor = $this->crearInstructor();
        $curso   = Curso::factory()->publicado()->create(['created_by' => $instructor->id]);
        $modulo  = Modulo::factory()->create(['curso_id' => $curso->id]);
        $leccion = Leccion::factory()->create(['modulo_id' => $modulo->id]);
        $actividad = Actividad::factory()->create(['leccion_id' => $leccion->id, 'orden' => 0]);

        $response = $this->actingAs($instructor)->get(route('cursos.edit', $curso));

        $response->assertOk();
        $response->assertSee('actividades-lista', false);
        $response->assertSee('data-leccion-id="' . $leccion->id . '"', false);
        $response->assertSee('data-actividad-id="' . $actividad->id . '"', false);
        $response->assertSee('https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js', false);
    }
}
