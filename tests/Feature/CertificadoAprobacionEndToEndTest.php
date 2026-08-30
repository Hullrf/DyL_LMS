<?php
// tests/Feature/CertificadoAprobacionEndToEndTest.php

namespace Tests\Feature;

use App\Models\Curso;
use App\Models\Inscripcion;
use App\Models\Rol;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CertificadoAprobacionEndToEndTest extends TestCase
{
    use RefreshDatabase;

    private User $instructor;

    protected function setUp(): void
    {
        parent::setUp();
        $rol = Rol::create(['nombre' => 'Instructor']);
        Rol::create(['nombre' => 'Estudiante']);
        $this->instructor = User::factory()->create(['estado' => 'activo']);
        $this->instructor->roles()->attach($rol);
    }

    private function crearEstudiante(array $atributos = []): User
    {
        $user = User::factory()->create(array_merge(['estado' => 'activo'], $atributos));
        $user->roles()->attach(Rol::where('nombre', 'Estudiante')->first());
        return $user;
    }

    private function completarInscripcion(User $estudiante, Curso $curso): void
    {
        Inscripcion::create([
            'user_id' => $estudiante->id, 'curso_id' => $curso->id,
            'fecha_inicio' => '2026-01-01', 'fecha_fin' => '2026-02-01', 'estado' => 'completado',
        ]);
    }

    public function test_instructor_aprueba_y_el_estudiante_recibe_su_diploma(): void
    {
        $curso      = Curso::factory()->create(['created_by' => $this->instructor->id]);
        $estudiante = $this->crearEstudiante();
        $this->completarInscripcion($estudiante, $curso);

        $response = $this->actingAs($this->instructor)
            ->post(route('calificaciones.aprobarCertificado', [$curso, $estudiante]));

        $response->assertRedirect(route('calificaciones.curso', $curso));
        $this->assertDatabaseHas('certificados', [
            'user_id' => $estudiante->id, 'curso_id' => $curso->id, 'aprobado_por_id' => $this->instructor->id,
        ]);
        $this->assertDatabaseHas('notificaciones', ['user_id' => $estudiante->id, 'tipo' => 'certificado']);
    }

    public function test_instructor_aprueba_y_el_estudiante_recibe_su_carta_de_diplomado(): void
    {
        $curso      = Curso::factory()->diplomado()->create(['created_by' => $this->instructor->id]);
        $estudiante = $this->crearEstudiante(['numero_documento' => '1000790950', 'ciudad_expedicion' => 'Bogotá']);
        $this->completarInscripcion($estudiante, $curso);

        $response = $this->actingAs($this->instructor)
            ->post(route('calificaciones.aprobarCertificado', [$curso, $estudiante]));

        $response->assertRedirect(route('calificaciones.curso', $curso));
        $this->assertDatabaseHas('certificados', [
            'user_id' => $estudiante->id, 'curso_id' => $curso->id, 'aprobado_por_id' => $this->instructor->id,
        ]);
    }

    public function test_estudiante_sin_documento_no_recibe_carta_y_queda_notificado(): void
    {
        $curso      = Curso::factory()->diplomado()->create(['created_by' => $this->instructor->id]);
        $estudiante = $this->crearEstudiante(['numero_documento' => null]);
        $this->completarInscripcion($estudiante, $curso);

        $response = $this->actingAs($this->instructor)
            ->post(route('calificaciones.aprobarCertificado', [$curso, $estudiante]));

        $response->assertRedirect(route('calificaciones.curso', $curso));
        $response->assertSessionHas('error');
        $this->assertDatabaseCount('certificados', 0);
        $this->assertDatabaseHas('notificaciones', ['user_id' => $estudiante->id, 'tipo' => 'certificado']);
    }

    public function test_estudiante_no_puede_autogenerar_su_certificado(): void
    {
        $curso      = Curso::factory()->create(['created_by' => $this->instructor->id]);
        $estudiante = $this->crearEstudiante();
        $this->completarInscripcion($estudiante, $curso);

        $this->actingAs($estudiante)->get(route('cursos.show', $curso))
            ->assertSee('pendiente de revisión del instructor');

        $this->assertDatabaseCount('certificados', 0);
    }
}
