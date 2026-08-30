<?php

namespace Tests\Feature;

use App\Models\Curso;
use App\Models\Inscripcion;
use App\Models\Rol;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AprobarCertificadoTest extends TestCase
{
    use RefreshDatabase;

    private User $instructor;
    private User $estudiante;
    private Curso $curso;

    protected function setUp(): void
    {
        parent::setUp();

        $rolInstructor  = Rol::create(['nombre' => 'Instructor']);
        Rol::create(['nombre' => 'Estudiante']);

        $this->instructor = User::factory()->create(['estado' => 'activo']);
        $this->instructor->roles()->attach($rolInstructor);

        $this->estudiante = User::factory()->create(['estado' => 'activo']);
        $this->curso = Curso::factory()->create(['created_by' => $this->instructor->id]);

        Inscripcion::create([
            'user_id' => $this->estudiante->id, 'curso_id' => $this->curso->id,
            'fecha_inicio' => '2026-01-01', 'fecha_fin' => '2026-02-01', 'estado' => 'completado',
        ]);
    }

    public function test_instructor_dueno_del_curso_puede_aprobar(): void
    {
        $response = $this->actingAs($this->instructor)
            ->post(route('calificaciones.aprobarCertificado', [$this->curso, $this->estudiante]));

        $response->assertRedirect(route('calificaciones.curso', $this->curso));
        $response->assertSessionHas('success');
        $this->assertDatabaseHas('certificados', [
            'user_id' => $this->estudiante->id,
            'curso_id' => $this->curso->id,
            'aprobado_por_id' => $this->instructor->id,
        ]);
    }

    public function test_instructor_que_no_es_dueno_del_curso_no_puede_aprobar(): void
    {
        $otroInstructor = User::factory()->create(['estado' => 'activo']);
        $otroInstructor->roles()->attach(Rol::where('nombre', 'Instructor')->first());

        $response = $this->actingAs($otroInstructor)
            ->post(route('calificaciones.aprobarCertificado', [$this->curso, $this->estudiante]));

        $response->assertForbidden();
        $this->assertDatabaseCount('certificados', 0);
    }

    public function test_admin_puede_aprobar_certificado_de_cualquier_curso(): void
    {
        $admin = User::factory()->create(['estado' => 'activo']);
        $admin->roles()->attach(Rol::create(['nombre' => 'Administrador']));

        $response = $this->actingAs($admin)
            ->post(route('calificaciones.aprobarCertificado', [$this->curso, $this->estudiante]));

        $response->assertRedirect(route('calificaciones.curso', $this->curso));
        $this->assertDatabaseHas('certificados', ['aprobado_por_id' => $admin->id]);
    }

    public function test_no_se_puede_aprobar_a_un_estudiante_que_no_completo_el_curso(): void
    {
        $otroEstudiante = User::factory()->create(['estado' => 'activo']);
        Inscripcion::create([
            'user_id' => $otroEstudiante->id, 'curso_id' => $this->curso->id,
            'fecha_inicio' => '2026-01-01', 'estado' => 'en_progreso',
        ]);

        $response = $this->actingAs($this->instructor)
            ->post(route('calificaciones.aprobarCertificado', [$this->curso, $otroEstudiante]));

        $response->assertRedirect(route('calificaciones.curso', $this->curso));
        $response->assertSessionHas('error');
        $this->assertDatabaseCount('certificados', 0);
    }

    public function test_aprobar_diplomado_sin_documento_notifica_en_vez_de_generar(): void
    {
        $curso = Curso::factory()->diplomado()->create(['created_by' => $this->instructor->id]);
        $estudiante = User::factory()->create(['estado' => 'activo', 'numero_documento' => null]);
        Inscripcion::create([
            'user_id' => $estudiante->id, 'curso_id' => $curso->id,
            'fecha_inicio' => '2026-01-01', 'fecha_fin' => '2026-02-01', 'estado' => 'completado',
        ]);

        $response = $this->actingAs($this->instructor)
            ->post(route('calificaciones.aprobarCertificado', [$curso, $estudiante]));

        $response->assertRedirect(route('calificaciones.curso', $curso));
        $response->assertSessionHas('error');
        $this->assertDatabaseCount('certificados', 0);
        $this->assertDatabaseHas('notificaciones', ['user_id' => $estudiante->id, 'tipo' => 'certificado']);
    }
}
