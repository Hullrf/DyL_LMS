<?php

namespace Tests\Feature;

use App\Models\Curso;
use App\Models\Inscripcion;
use App\Models\Rol;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CertificadoGenerarEndToEndTest extends TestCase
{
    use RefreshDatabase;

    private function crearEstudiante(array $atributos = []): User
    {
        $rol = Rol::factory()->create(['nombre' => 'Estudiante']);
        $user = User::factory()->create(array_merge(['estado' => 'activo'], $atributos));
        $user->roles()->attach($rol);
        return $user;
    }

    private function completarInscripcion(User $estudiante, Curso $curso): void
    {
        Inscripcion::create([
            'user_id' => $estudiante->id, 'curso_id' => $curso->id,
            'fecha_inicio' => '2026-01-01', 'fecha_fin' => '2026-02-01', 'estado' => 'completado',
        ]);
    }

    public function test_estudiante_obtiene_diploma_via_ruta_generar(): void
    {
        $instructor = User::factory()->create();
        $curso      = Curso::factory()->create(['created_by' => $instructor->id]);
        $estudiante = $this->crearEstudiante();
        $this->completarInscripcion($estudiante, $curso);

        $response = $this->actingAs($estudiante)->post(route('certificados.generar', $curso));

        $response->assertRedirect();
        $this->assertDatabaseHas('certificados', ['user_id' => $estudiante->id, 'curso_id' => $curso->id]);
    }

    public function test_estudiante_obtiene_carta_de_diplomado_via_ruta_generar(): void
    {
        $instructor = User::factory()->create();
        $curso      = Curso::factory()->diplomado()->create(['created_by' => $instructor->id]);
        $estudiante = $this->crearEstudiante(['numero_documento' => '1000790950', 'ciudad_expedicion' => 'Bogotá']);
        $this->completarInscripcion($estudiante, $curso);

        $response = $this->actingAs($estudiante)->post(route('certificados.generar', $curso));

        $response->assertRedirect(route('certificados.show', \App\Models\Certificado::first()));
        $this->assertDatabaseHas('certificados', ['user_id' => $estudiante->id, 'curso_id' => $curso->id]);
    }

    public function test_estudiante_sin_documento_no_obtiene_carta_y_queda_notificado(): void
    {
        $instructor = User::factory()->create();
        $curso      = Curso::factory()->diplomado()->create(['created_by' => $instructor->id]);
        $estudiante = $this->crearEstudiante(['numero_documento' => null]);
        $this->completarInscripcion($estudiante, $curso);

        $response = $this->actingAs($estudiante)->post(route('certificados.generar', $curso));

        $response->assertRedirect(route('cursos.show', $curso));
        $this->assertDatabaseCount('certificados', 0);
        $this->assertDatabaseHas('notificaciones', ['user_id' => $estudiante->id, 'tipo' => 'certificado']);
    }
}
