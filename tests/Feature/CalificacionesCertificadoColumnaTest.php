<?php

namespace Tests\Feature;

use App\Models\Actividad;
use App\Models\Certificado;
use App\Models\Curso;
use App\Models\Inscripcion;
use App\Models\Leccion;
use App\Models\Modulo;
use App\Models\RespuestaEstudiante;
use App\Models\Rol;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CalificacionesCertificadoColumnaTest extends TestCase
{
    use RefreshDatabase;

    private User $instructor;
    private Curso $curso;
    private Modulo $modulo;
    private Leccion $leccion;
    private Actividad $actividad;

    protected function setUp(): void
    {
        parent::setUp();

        Rol::create(['nombre' => 'Instructor']);
        Rol::create(['nombre' => 'Estudiante']);

        $this->instructor = User::factory()->create(['estado' => 'activo']);
        $this->instructor->roles()->attach(Rol::where('nombre', 'Instructor')->first());

        $this->curso     = Curso::factory()->create(['created_by' => $this->instructor->id]);
        $this->modulo    = Modulo::factory()->create(['curso_id' => $this->curso->id]);
        $this->leccion   = Leccion::factory()->create(['modulo_id' => $this->modulo->id]);
        $this->actividad = Actividad::factory()->create([
            'leccion_id' => $this->leccion->id, 'tipo' => 'tarea', 'puntaje_maximo' => 100,
        ]);
    }

    private function inscribirEstudiante(string $nombre, string $estadoInscripcion): User
    {
        $estudiante = User::factory()->create(['name' => $nombre, 'estado' => 'activo']);
        $estudiante->roles()->attach(Rol::where('nombre', 'Estudiante')->first());

        Inscripcion::create([
            'user_id' => $estudiante->id, 'curso_id' => $this->curso->id,
            'fecha_inicio' => '2026-01-01', 'estado' => $estadoInscripcion,
        ]);

        return $estudiante;
    }

    public function test_estudiante_no_completado_no_muestra_boton_de_aprobar(): void
    {
        $estudiante = $this->inscribirEstudiante('En Progreso', 'en_progreso');

        $response = $this->actingAs($this->instructor)->get(route('calificaciones.curso', $this->curso));

        $response->assertOk();
        $response->assertDontSee(route('calificaciones.aprobarCertificado', [$this->curso, $estudiante]), false);
    }

    public function test_estudiante_completado_con_pendientes_no_muestra_boton_de_aprobar(): void
    {
        $estudiante = $this->inscribirEstudiante('Con Pendientes', 'completado');
        RespuestaEstudiante::factory()->create([
            'user_id' => $estudiante->id, 'actividad_id' => $this->actividad->id, 'estado' => 'sin_calificar',
        ]);

        $response = $this->actingAs($this->instructor)->get(route('calificaciones.curso', $this->curso));

        $response->assertOk();
        $response->assertDontSee(route('calificaciones.aprobarCertificado', [$this->curso, $estudiante]), false);
    }

    public function test_estudiante_listo_sin_certificado_muestra_boton_de_aprobar(): void
    {
        $estudiante = $this->inscribirEstudiante('Listo Para Aprobar', 'completado');
        RespuestaEstudiante::factory()->create([
            'user_id' => $estudiante->id, 'actividad_id' => $this->actividad->id,
            'estado' => 'calificada', 'calificacion' => 90,
        ]);

        $response = $this->actingAs($this->instructor)->get(route('calificaciones.curso', $this->curso));

        $response->assertOk();
        $response->assertSee(route('calificaciones.aprobarCertificado', [$this->curso, $estudiante]), false);
        $response->assertSee('Aprobar certificado');
    }

    public function test_estudiante_por_debajo_del_minimo_muestra_boton_de_advertencia(): void
    {
        $this->curso->update(['nota_aprobatoria' => 80]);
        $estudiante = $this->inscribirEstudiante('Bajo El Minimo', 'completado');
        RespuestaEstudiante::factory()->create([
            'user_id' => $estudiante->id, 'actividad_id' => $this->actividad->id,
            'estado' => 'calificada', 'calificacion' => 50,
        ]);

        $response = $this->actingAs($this->instructor)->get(route('calificaciones.curso', $this->curso));

        $response->assertOk();
        $response->assertSee('Aprobar de todas formas');
    }

    public function test_estudiante_con_certificado_muestra_insignia_de_emitido(): void
    {
        $estudiante = $this->inscribirEstudiante('Ya Aprobado', 'completado');
        Certificado::create([
            'user_id' => $estudiante->id, 'curso_id' => $this->curso->id,
            'fecha_emision' => now()->toDateString(), 'numero_certificado' => 'CERT-2026-YAAPROBADO',
            'aprobado_por_id' => $this->instructor->id,
        ]);

        $response = $this->actingAs($this->instructor)->get(route('calificaciones.curso', $this->curso));

        $response->assertOk();
        $response->assertSee('Certificado emitido');
        $response->assertDontSee(route('calificaciones.aprobarCertificado', [$this->curso, $estudiante]), false);
    }

    /**
     * Fix 1 (Critical): un curso sin actividades calificables (solo lecciones) debe
     * seguir mostrando la columna de Certificado — CertificadoService considera
     * aprobado al 100% cuando no hay actividades que calificar.
     */
    public function test_curso_sin_actividades_calificables_igual_muestra_boton_de_aprobar(): void
    {
        $cursoSinActividades = Curso::factory()->create(['created_by' => $this->instructor->id]);
        Modulo::factory()->create(['curso_id' => $cursoSinActividades->id]); // sin actividades

        $estudiante = User::factory()->create(['name' => 'Solo Lecciones', 'estado' => 'activo']);
        $estudiante->roles()->attach(Rol::where('nombre', 'Estudiante')->first());
        Inscripcion::create([
            'user_id' => $estudiante->id, 'curso_id' => $cursoSinActividades->id,
            'fecha_inicio' => '2026-01-01', 'estado' => 'completado',
        ]);

        $response = $this->actingAs($this->instructor)->get(route('calificaciones.curso', $cursoSinActividades));

        $response->assertOk();
        $response->assertSee('Solo Lecciones');
        $response->assertSee(route('calificaciones.aprobarCertificado', [$cursoSinActividades, $estudiante]), false);
        $response->assertSee('Aprobar certificado');
    }

    /**
     * Fix 3 (Important): el filtro ?modulo= no debe contaminar el gating de la
     * columna Certificado (acción irreversible). Un estudiante completo y
     * totalmente calificado en el Módulo A, pero con una actividad sin
     * calificar en el Módulo B, NO debe ver el botón de aprobar al filtrar
     * por el Módulo A — aunque las columnas filtradas de esa vista parcial
     * luzcan completas.
     */
    public function test_filtro_modulo_no_contamina_gating_de_certificado(): void
    {
        $moduloA = Modulo::factory()->create(['curso_id' => $this->curso->id, 'titulo' => 'Módulo A', 'orden' => 0]);
        $moduloB = Modulo::factory()->create(['curso_id' => $this->curso->id, 'titulo' => 'Módulo B', 'orden' => 1]);
        $leccionA = Leccion::factory()->create(['modulo_id' => $moduloA->id]);
        $leccionB = Leccion::factory()->create(['modulo_id' => $moduloB->id]);
        $actividadA = Actividad::factory()->create(['leccion_id' => $leccionA->id, 'tipo' => 'tarea', 'titulo' => 'Actividad A', 'puntaje_maximo' => 100]);
        $actividadB = Actividad::factory()->create(['leccion_id' => $leccionB->id, 'tipo' => 'tarea', 'titulo' => 'Actividad B', 'puntaje_maximo' => 100]);

        // La actividad original del curso (creada en setUp) también queda sin módulo
        // asignado por defecto vía $this->modulo — no interfiere porque no la usamos aquí.

        $estudiante = $this->inscribirEstudiante('Completo En A Pendiente En B', 'completado');
        RespuestaEstudiante::factory()->create([
            'user_id' => $estudiante->id, 'actividad_id' => $actividadA->id,
            'estado' => 'calificada', 'calificacion' => 95,
        ]);
        RespuestaEstudiante::factory()->create([
            'user_id' => $estudiante->id, 'actividad_id' => $actividadB->id,
            'estado' => 'sin_calificar',
        ]);
        // La actividad de setUp también queda sin responder: aseguremos que el curso
        // usado en este test es uno limpio sin esa actividad extra afectando el total.
        $this->actividad->delete();

        $response = $this->actingAs($this->instructor)
            ->get(route('calificaciones.curso', $this->curso) . '?modulo=' . $moduloA->id);

        $response->assertOk();
        // La vista filtrada por Módulo A solo muestra Actividad A, ya calificada.
        $response->assertSee('Actividad A');
        $response->assertDontSee('Actividad B');
        // Pero el botón de aprobar NO debe aparecer: el curso completo (incluyendo
        // el Módulo B, fuera del filtro) todavía tiene una actividad sin calificar.
        $response->assertDontSee(route('calificaciones.aprobarCertificado', [$this->curso, $estudiante]), false);
    }
}
