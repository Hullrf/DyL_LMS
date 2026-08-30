<?php
// tests/Feature/CursoNotaAprobatoriaFormTest.php

namespace Tests\Feature;

use App\Models\Curso;
use App\Models\Rol;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CursoNotaAprobatoriaFormTest extends TestCase
{
    use RefreshDatabase;

    private User $instructor;

    protected function setUp(): void
    {
        parent::setUp();
        $rol = Rol::create(['nombre' => 'Instructor']);
        $this->instructor = User::factory()->create(['estado' => 'activo']);
        $this->instructor->roles()->attach($rol);
    }

    public function test_instructor_puede_crear_curso_con_nota_aprobatoria_personalizada(): void
    {
        $response = $this->actingAs($this->instructor)->post(route('cursos.store'), [
            'titulo' => 'Curso con nota personalizada',
            'descripcion' => str_repeat('a', 25),
            'duracion_horas' => 10,
            'nota_aprobatoria' => 90,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('cursos', [
            'titulo' => 'Curso con nota personalizada',
            'nota_aprobatoria' => 90,
        ]);
    }

    public function test_crear_curso_sin_especificar_nota_aprobatoria_usa_el_default(): void
    {
        $this->actingAs($this->instructor)->post(route('cursos.store'), [
            'titulo' => 'Curso sin nota especificada',
            'descripcion' => str_repeat('a', 25),
            'duracion_horas' => 10,
        ]);

        $this->assertDatabaseHas('cursos', [
            'titulo' => 'Curso sin nota especificada',
            'nota_aprobatoria' => 80,
        ]);
    }

    public function test_instructor_puede_editar_nota_aprobatoria(): void
    {
        $curso = Curso::factory()->create(['created_by' => $this->instructor->id]);

        $response = $this->actingAs($this->instructor)->put(route('cursos.update', $curso), [
            'titulo' => $curso->titulo,
            'descripcion' => str_repeat('b', 25),
            'duracion_horas' => 5,
            'estado' => 'publicado',
            'tipo_certificado' => 'diploma',
            'nota_aprobatoria' => 70,
        ]);

        $response->assertRedirect();
        $this->assertSame(70, $curso->fresh()->nota_aprobatoria);
    }

    public function test_nota_aprobatoria_fuera_de_rango_es_rechazada(): void
    {
        $response = $this->actingAs($this->instructor)->post(route('cursos.store'), [
            'titulo' => 'Curso con nota inválida',
            'descripcion' => str_repeat('a', 25),
            'duracion_horas' => 10,
            'nota_aprobatoria' => 150,
        ]);

        $response->assertSessionHasErrors('nota_aprobatoria');
    }
}
