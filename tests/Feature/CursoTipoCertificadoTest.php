<?php
// tests/Feature/CursoTipoCertificadoTest.php

namespace Tests\Feature;

use App\Models\Curso;
use App\Models\Rol;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CursoTipoCertificadoTest extends TestCase
{
    use RefreshDatabase;

    private function crearInstructor(): User
    {
        $rol = Rol::factory()->create(['nombre' => 'Instructor']);
        $user = User::factory()->create(['estado' => 'activo']);
        $user->roles()->attach($rol);
        return $user;
    }

    public function test_crear_curso_con_tipo_certificado_diplomado(): void
    {
        $instructor = $this->crearInstructor();

        $response = $this->actingAs($instructor)->post(route('cursos.store'), [
            'titulo'           => 'Diplomado en Calidad',
            'descripcion'      => str_repeat('Contenido de prueba. ', 3),
            'duracion_horas'   => 120,
            'tipo_certificado' => 'diplomado',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('cursos', [
            'titulo'           => 'Diplomado en Calidad',
            'tipo_certificado' => 'diplomado',
        ]);
    }

    public function test_crear_curso_sin_especificar_tipo_certificado_usa_diploma(): void
    {
        $instructor = $this->crearInstructor();

        $response = $this->actingAs($instructor)->post(route('cursos.store'), [
            'titulo'         => 'Curso Corto',
            'descripcion'    => str_repeat('Contenido de prueba. ', 3),
            'duracion_horas' => 8,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('cursos', [
            'titulo'           => 'Curso Corto',
            'tipo_certificado' => 'diploma',
        ]);
    }

    public function test_editar_curso_puede_cambiar_tipo_certificado(): void
    {
        $instructor = $this->crearInstructor();
        $curso = Curso::factory()->create(['created_by' => $instructor->id]);

        $response = $this->actingAs($instructor)->put(route('cursos.update', $curso), [
            'titulo'           => $curso->titulo,
            'descripcion'      => str_repeat('Contenido de prueba. ', 3),
            'duracion_horas'   => $curso->duracion_horas,
            'estado'           => 'publicado',
            'tipo_certificado' => 'diplomado',
        ]);

        $response->assertRedirect();
        $this->assertSame('diplomado', $curso->fresh()->tipo_certificado);
    }

    public function test_formulario_de_crear_curso_incluye_el_selector(): void
    {
        $instructor = $this->crearInstructor();

        $response = $this->actingAs($instructor)->get(route('cursos.create'));

        $response->assertOk();
        $response->assertSee('name="tipo_certificado"', false);
        $response->assertSee('diplomado', false);
    }
}
