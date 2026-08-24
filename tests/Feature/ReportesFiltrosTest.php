<?php

namespace Tests\Feature;

use App\Models\Curso;
use App\Models\Rol;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportesFiltrosTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $instructor;

    protected function setUp(): void
    {
        parent::setUp();

        $rolAdmin = Rol::create(['nombre' => 'Administrador']);
        $rolInstructor = Rol::create(['nombre' => 'Instructor']);
        Rol::create(['nombre' => 'Estudiante']);

        $this->admin = User::factory()->create(['estado' => 'activo']);
        $this->admin->roles()->attach($rolAdmin);

        $this->instructor = User::factory()->create(['estado' => 'activo']);
        $this->instructor->roles()->attach($rolInstructor);
    }

    public function test_admin_puede_filtrar_cursos_por_titulo(): void
    {
        Curso::factory()->create(['titulo' => 'Fundamentos de ISO 9001', 'created_by' => $this->instructor->id]);
        Curso::factory()->create(['titulo' => 'Seguridad Industrial', 'created_by' => $this->instructor->id]);

        $response = $this->actingAs($this->admin)->get(route('reportes.index', ['curso_buscar' => 'ISO']));

        $response->assertOk();
        $response->assertSee('Fundamentos de ISO 9001');
        $response->assertDontSee('Seguridad Industrial');
    }

    public function test_admin_puede_filtrar_cursos_por_estado(): void
    {
        Curso::factory()->create(['titulo' => 'Curso Publicado', 'estado' => 'publicado', 'created_by' => $this->instructor->id]);
        Curso::factory()->create(['titulo' => 'Curso Borrador', 'estado' => 'borrador', 'created_by' => $this->instructor->id]);

        $response = $this->actingAs($this->admin)->get(route('reportes.index', ['curso_estado' => 'borrador']));

        $response->assertOk();
        $response->assertSee('Curso Borrador');
        $response->assertDontSee('Curso Publicado');
    }

    public function test_admin_puede_buscar_usuarios_por_nombre_o_email(): void
    {
        $laura = User::factory()->create(['name' => 'Laura Anacona', 'email' => 'laura@test.com', 'estado' => 'activo']);
        $david = User::factory()->create(['name' => 'David Gil', 'email' => 'david@test.com', 'estado' => 'activo']);

        $response = $this->actingAs($this->admin)->get(route('reportes.index', ['usuario_buscar' => 'Laura']));

        $response->assertOk();
        $response->assertSee('Laura Anacona');
        $response->assertDontSee('David Gil');
    }

    public function test_instructor_puede_filtrar_solo_sus_propios_cursos(): void
    {
        $otroInstructor = User::factory()->create(['estado' => 'activo']);
        $otroInstructor->roles()->attach(Rol::where('nombre', 'Instructor')->first());

        Curso::factory()->create(['titulo' => 'Mi Curso ISO', 'created_by' => $this->instructor->id]);
        Curso::factory()->create(['titulo' => 'Curso ISO de Otro', 'created_by' => $otroInstructor->id]);

        $response = $this->actingAs($this->instructor)->get(route('reportes.index', ['curso_buscar' => 'ISO']));

        $response->assertOk();
        $response->assertSee('Mi Curso ISO');
        $response->assertDontSee('Curso ISO de Otro');
    }

    public function test_reportes_pagina_cursos_cuando_hay_muchos(): void
    {
        Curso::factory()->count(25)->create(['created_by' => $this->instructor->id]);

        $response = $this->actingAs($this->admin)->get(route('reportes.index'));

        $response->assertOk();
        $response->assertSee('cursos_page=2', false);
    }
}
