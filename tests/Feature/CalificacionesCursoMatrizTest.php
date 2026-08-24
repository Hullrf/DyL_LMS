<?php

namespace Tests\Feature;

use App\Models\Actividad;
use App\Models\Curso;
use App\Models\Inscripcion;
use App\Models\Leccion;
use App\Models\Modulo;
use App\Models\RespuestaEstudiante;
use App\Models\Rol;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CalificacionesCursoMatrizTest extends TestCase
{
    use RefreshDatabase;

    private User $instructor;

    protected function setUp(): void
    {
        parent::setUp();

        Rol::create(['nombre' => 'Administrador']);
        $rolInstructor = Rol::create(['nombre' => 'Instructor']);
        Rol::create(['nombre' => 'Estudiante']);

        $this->instructor = User::factory()->create(['estado' => 'activo']);
        $this->instructor->roles()->attach($rolInstructor);
    }

    private function inscribirEstudiante(Curso $curso, string $nombre): User
    {
        $rolEstudiante = Rol::where('nombre', 'Estudiante')->first();
        $estudiante = User::factory()->create(['name' => $nombre, 'estado' => 'activo']);
        $estudiante->roles()->attach($rolEstudiante);

        Inscripcion::create([
            'user_id'      => $estudiante->id,
            'curso_id'     => $curso->id,
            'fecha_inicio' => now(),
            'estado'       => 'en_progreso',
        ]);

        return $estudiante;
    }

    public function test_index_lista_cursos_del_instructor_con_conteo_de_pendientes(): void
    {
        $curso = Curso::factory()->create(['created_by' => $this->instructor->id]);
        $modulo = Modulo::factory()->create(['curso_id' => $curso->id]);
        $leccion = Leccion::factory()->create(['modulo_id' => $modulo->id]);
        $actividad = Actividad::factory()->create(['leccion_id' => $leccion->id, 'tipo' => 'tarea']);
        $estudiante = $this->inscribirEstudiante($curso, 'Estudiante Uno');

        RespuestaEstudiante::factory()->create([
            'user_id' => $estudiante->id, 'actividad_id' => $actividad->id, 'estado' => 'sin_calificar',
        ]);

        $response = $this->actingAs($this->instructor)->get(route('calificaciones.index'));

        $response->assertOk()->assertViewIs('calificaciones.index');
        $response->assertSee($curso->titulo);
        $response->assertSee('1'); // conteo de pendientes
    }

    public function test_instructor_no_puede_ver_calificaciones_de_curso_ajeno(): void
    {
        $otroInstructor = User::factory()->create(['estado' => 'activo']);
        $otroInstructor->roles()->attach(Rol::where('nombre', 'Instructor')->first());
        $curso = Curso::factory()->create(['created_by' => $otroInstructor->id]);

        $response = $this->actingAs($this->instructor)->get(route('calificaciones.curso', $curso));

        $response->assertForbidden();
    }

    public function test_matriz_muestra_estudiantes_y_actividades_calificables(): void
    {
        $curso = Curso::factory()->create(['created_by' => $this->instructor->id]);
        $modulo = Modulo::factory()->create(['curso_id' => $curso->id]);
        $leccion = Leccion::factory()->create(['modulo_id' => $modulo->id]);
        $actividad = Actividad::factory()->create(['leccion_id' => $leccion->id, 'tipo' => 'tarea', 'titulo' => 'Tarea Final']);
        $estudiante = $this->inscribirEstudiante($curso, 'Laura Anacona');

        RespuestaEstudiante::factory()->calificada()->create([
            'user_id' => $estudiante->id, 'actividad_id' => $actividad->id, 'calificacion' => 90,
        ]);

        $response = $this->actingAs($this->instructor)->get(route('calificaciones.curso', $curso));

        $response->assertOk()->assertViewIs('calificaciones.curso');
        $response->assertSee('Laura Anacona');
        $response->assertSee('Tarea Final');
        $response->assertSee('90');
    }

    public function test_matriz_excluye_actividades_sin_nota_como_encuesta(): void
    {
        $curso = Curso::factory()->create(['created_by' => $this->instructor->id]);
        $modulo = Modulo::factory()->create(['curso_id' => $curso->id]);
        $leccion = Leccion::factory()->create(['modulo_id' => $modulo->id]);
        Actividad::factory()->create(['leccion_id' => $leccion->id, 'tipo' => 'encuesta', 'titulo' => 'Encuesta Satisfacción', 'puntaje_maximo' => null]);
        $this->inscribirEstudiante($curso, 'Laura Anacona');

        $response = $this->actingAs($this->instructor)->get(route('calificaciones.curso', $curso));

        $response->assertOk();
        $response->assertDontSee('Encuesta Satisfacción');
    }

    public function test_matriz_muestra_guion_cuando_estudiante_no_ha_enviado(): void
    {
        $curso = Curso::factory()->create(['created_by' => $this->instructor->id]);
        $modulo = Modulo::factory()->create(['curso_id' => $curso->id]);
        $leccion = Leccion::factory()->create(['modulo_id' => $modulo->id]);
        Actividad::factory()->create(['leccion_id' => $leccion->id, 'tipo' => 'tarea']);
        $this->inscribirEstudiante($curso, 'Laura Anacona');

        $response = $this->actingAs($this->instructor)->get(route('calificaciones.curso', $curso));

        $response->assertOk();
        $response->assertSee('—', false);
    }

    public function test_filtro_buscar_estudiante(): void
    {
        $curso = Curso::factory()->create(['created_by' => $this->instructor->id]);
        $modulo = Modulo::factory()->create(['curso_id' => $curso->id]);
        $leccion = Leccion::factory()->create(['modulo_id' => $modulo->id]);
        Actividad::factory()->create(['leccion_id' => $leccion->id, 'tipo' => 'tarea']);
        $this->inscribirEstudiante($curso, 'Laura Anacona');
        $this->inscribirEstudiante($curso, 'David Gil');

        $response = $this->actingAs($this->instructor)
            ->get(route('calificaciones.curso', $curso) . '?buscar=Laura');

        $response->assertOk();
        $response->assertSee('Laura Anacona');
        $response->assertDontSee('David Gil');
    }

    public function test_filtro_estado_con_pendientes(): void
    {
        $curso = Curso::factory()->create(['created_by' => $this->instructor->id]);
        $modulo = Modulo::factory()->create(['curso_id' => $curso->id]);
        $leccion = Leccion::factory()->create(['modulo_id' => $modulo->id]);
        $actividad = Actividad::factory()->create(['leccion_id' => $leccion->id, 'tipo' => 'tarea']);

        $conPendiente = $this->inscribirEstudiante($curso, 'Laura Anacona');
        $completo = $this->inscribirEstudiante($curso, 'David Gil');

        RespuestaEstudiante::factory()->create([
            'user_id' => $conPendiente->id, 'actividad_id' => $actividad->id, 'estado' => 'sin_calificar',
        ]);
        RespuestaEstudiante::factory()->calificada()->create([
            'user_id' => $completo->id, 'actividad_id' => $actividad->id,
        ]);

        $response = $this->actingAs($this->instructor)
            ->get(route('calificaciones.curso', $curso) . '?estado=pendientes');

        $response->assertOk();
        $response->assertSee('Laura Anacona');
        $response->assertDontSee('David Gil');
    }

    public function test_filtro_modulo(): void
    {
        $curso = Curso::factory()->create(['created_by' => $this->instructor->id]);
        $modulo1 = Modulo::factory()->create(['curso_id' => $curso->id, 'titulo' => 'Módulo Uno', 'orden' => 0]);
        $modulo2 = Modulo::factory()->create(['curso_id' => $curso->id, 'titulo' => 'Módulo Dos', 'orden' => 1]);
        $leccion1 = Leccion::factory()->create(['modulo_id' => $modulo1->id]);
        $leccion2 = Leccion::factory()->create(['modulo_id' => $modulo2->id]);
        Actividad::factory()->create(['leccion_id' => $leccion1->id, 'tipo' => 'tarea', 'titulo' => 'Actividad Módulo Uno']);
        Actividad::factory()->create(['leccion_id' => $leccion2->id, 'tipo' => 'tarea', 'titulo' => 'Actividad Módulo Dos']);
        $this->inscribirEstudiante($curso, 'Laura Anacona');

        $response = $this->actingAs($this->instructor)
            ->get(route('calificaciones.curso', $curso) . '?modulo=' . $modulo1->id);

        $response->assertOk();
        $response->assertSee('Actividad Módulo Uno');
        $response->assertDontSee('Actividad Módulo Dos');
    }
}
