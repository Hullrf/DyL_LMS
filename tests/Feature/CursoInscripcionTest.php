<?php

namespace Tests\Feature;

use App\Models\Actividad;
use App\Models\Curso;
use App\Models\Inscripcion;
use App\Models\Leccion;
use App\Models\Modulo;
use App\Models\ProgresoLeccion;
use App\Models\Rol;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CursoInscripcionTest extends TestCase
{
    use RefreshDatabase;

    private function crearEstudiante(): User
    {
        $rol = Rol::factory()->estudiante()->create();
        $user = User::factory()->create(['estado' => 'activo']);
        $user->roles()->attach($rol);
        return $user;
    }

    private function crearInstructor(): User
    {
        $rol = Rol::factory()->instructor()->create();
        $user = User::factory()->create(['estado' => 'activo']);
        $user->roles()->attach($rol);
        return $user;
    }

    private function crearCursoConLeccion(User $instructor): array
    {
        $curso = Curso::factory()->publicado()->create(['created_by' => $instructor->id]);
        $modulo = Modulo::factory()->create(['curso_id' => $curso->id]);
        $leccion = Leccion::factory()->create(['modulo_id' => $modulo->id]);
        return [$curso, $modulo, $leccion];
    }

    public function test_cursos_index_muestra_cursos_publicados(): void
    {
        $instructor = $this->crearInstructor();
        $student = $this->crearEstudiante();
        Curso::factory()->publicado()->count(3)->create(['created_by' => $instructor->id]);
        Curso::factory()->borrador()->create(['created_by' => $instructor->id]);

        $response = $this->actingAs($student)->get('/cursos');

        $response->assertStatus(200);
    }

    public function test_estudiante_puede_ver_leccion_si_esta_inscrito(): void
    {
        $instructor = $this->crearInstructor();
        $student = $this->crearEstudiante();
        [$curso, $modulo, $leccion] = $this->crearCursoConLeccion($instructor);

        Inscripcion::factory()->create([
            'user_id'  => $student->id,
            'curso_id' => $curso->id,
        ]);

        $response = $this->actingAs($student)->get("/lecciones/{$leccion->id}");

        $response->assertStatus(200);
        $response->assertSee($leccion->titulo);
    }

    public function test_marcar_leccion_completada(): void
    {
        $instructor = $this->crearInstructor();
        $student = $this->crearEstudiante();
        [$curso, $modulo, $leccion] = $this->crearCursoConLeccion($instructor);

        Inscripcion::factory()->create([
            'user_id'  => $student->id,
            'curso_id' => $curso->id,
        ]);

        $response = $this->actingAs($student)->post("/lecciones/{$leccion->id}/completar");

        $response->assertRedirect();
        $this->assertDatabaseHas('progreso_lecciones', [
            'user_id'    => $student->id,
            'leccion_id' => $leccion->id,
            'completado' => true,
        ]);
    }

    public function test_curso_se_marca_completado_cuando_todas_lecciones_terminadas(): void
    {
        $instructor = $this->crearInstructor();
        $student = $this->crearEstudiante();
        [$curso, $modulo, $leccion] = $this->crearCursoConLeccion($instructor);

        $inscripcion = Inscripcion::factory()->create([
            'user_id'  => $student->id,
            'curso_id' => $curso->id,
            'estado'   => 'en_progreso',
        ]);

        $this->actingAs($student)->post("/lecciones/{$leccion->id}/completar");

        $this->assertEquals('completado', $inscripcion->fresh()->estado);
    }

    public function test_no_inscrito_no_puede_ver_leccion_publicada(): void
    {
        $instructor = $this->crearInstructor();
        $student = $this->crearEstudiante();
        [$curso, $modulo, $leccion] = $this->crearCursoConLeccion($instructor);

        // Curso publicado pero sin inscripción: solo un admin/instructor puede dar acceso.
        $response = $this->actingAs($student)->get("/lecciones/{$leccion->id}");

        $response->assertStatus(403);
    }

    public function test_no_inscrito_no_puede_completar_leccion(): void
    {
        $instructor = $this->crearInstructor();
        $student = $this->crearEstudiante();
        [$curso, $modulo, $leccion] = $this->crearCursoConLeccion($instructor);

        $response = $this->actingAs($student)->post("/lecciones/{$leccion->id}/completar");

        $response->assertStatus(403);
    }

    public function test_no_inscrito_no_puede_ver_actividad(): void
    {
        $instructor = $this->crearInstructor();
        $student = $this->crearEstudiante();
        [$curso, $modulo, $leccion] = $this->crearCursoConLeccion($instructor);
        $actividad = Actividad::factory()->create(['leccion_id' => $leccion->id]);

        $response = $this->actingAs($student)->get("/actividades/{$actividad->id}");

        $response->assertStatus(403);
    }

    public function test_no_inscrito_no_puede_responder_actividad(): void
    {
        $instructor = $this->crearInstructor();
        $student = $this->crearEstudiante();
        [$curso, $modulo, $leccion] = $this->crearCursoConLeccion($instructor);
        $actividad = Actividad::factory()->tarea()->create(['leccion_id' => $leccion->id]);

        $response = $this->actingAs($student)->post("/actividades/{$actividad->id}/responder", [
            'respuesta' => 'Mi respuesta',
        ]);

        $response->assertStatus(403);
        $this->assertDatabaseMissing('respuestas_estudiantes', [
            'user_id'      => $student->id,
            'actividad_id' => $actividad->id,
        ]);
    }

    public function test_inscrito_puede_ver_actividad(): void
    {
        $instructor = $this->crearInstructor();
        $student = $this->crearEstudiante();
        [$curso, $modulo, $leccion] = $this->crearCursoConLeccion($instructor);
        $actividad = Actividad::factory()->create(['leccion_id' => $leccion->id]);

        Inscripcion::factory()->create([
            'user_id'  => $student->id,
            'curso_id' => $curso->id,
        ]);

        $response = $this->actingAs($student)->get("/actividades/{$actividad->id}");

        $response->assertStatus(200);
    }

    public function test_ruta_auto_inscripcion_ya_no_existe(): void
    {
        $instructor = $this->crearInstructor();
        $student = $this->crearEstudiante();
        [$curso, $modulo, $leccion] = $this->crearCursoConLeccion($instructor);

        $response = $this->actingAs($student)->post("/cursos/{$curso->id}/inscribirse");

        $response->assertStatus(404);
        $this->assertDatabaseMissing('inscripciones', [
            'user_id'  => $student->id,
            'curso_id' => $curso->id,
        ]);
    }

    public function test_leccion_de_curso_borrador_no_accesible_para_estudiante(): void
    {
        $instructor = $this->crearInstructor();
        $student = $this->crearEstudiante();
        $curso = Curso::factory()->borrador()->create(['created_by' => $instructor->id]);
        $modulo = Modulo::factory()->create(['curso_id' => $curso->id]);
        $leccion = Leccion::factory()->create(['modulo_id' => $modulo->id]);

        $response = $this->actingAs($student)->get("/lecciones/{$leccion->id}");

        $response->assertStatus(403);
    }

    /**
     * Reproduce el bug: un estudiante ya inscrito en un curso publicado
     * no debe perder el acceso si el instructor lo regresa a borrador
     * (o lo inscribio masivamente mientras seguia en borrador).
     */
    public function test_estudiante_inscrito_conserva_acceso_si_curso_pasa_a_borrador(): void
    {
        $instructor = $this->crearInstructor();
        $student = $this->crearEstudiante();
        [$curso, $modulo, $leccion] = $this->crearCursoConLeccion($instructor);

        Inscripcion::factory()->create([
            'user_id'  => $student->id,
            'curso_id' => $curso->id,
        ]);

        $curso->update(['estado' => 'borrador']);

        $responseCurso = $this->actingAs($student)->get("/cursos/{$curso->id}");
        $responseCurso->assertStatus(200);

        $responseLeccion = $this->actingAs($student)->get("/lecciones/{$leccion->id}");
        $responseLeccion->assertStatus(200);
    }
}
