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
}
