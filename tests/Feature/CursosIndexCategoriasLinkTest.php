<?php

namespace Tests\Feature;

use App\Models\Curso;
use App\Models\Rol;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CursosIndexCategoriasLinkTest extends TestCase
{
    use RefreshDatabase;

    private function crearUsuarioConRol(string $nombreRol): User
    {
        $rol = Rol::factory()->create(['nombre' => $nombreRol]);
        $user = User::factory()->create(['estado' => 'activo']);
        $user->roles()->attach($rol);
        return $user;
    }

    public function test_admin_ve_enlace_gestionar_categorias_en_catalogo_de_cursos(): void
    {
        $admin = $this->crearUsuarioConRol('Administrador');
        Curso::factory()->publicado()->create(['created_by' => $admin->id]);

        $response = $this->actingAs($admin)->get(route('cursos.index'));

        $response->assertOk();
        $response->assertSee('Gestionar categorías');
        $response->assertSee(route('admin.categorias.index'), false);
    }

    public function test_instructor_no_ve_enlace_gestionar_categorias_en_catalogo_de_cursos(): void
    {
        $instructor = $this->crearUsuarioConRol('Instructor');
        Curso::factory()->publicado()->create(['created_by' => $instructor->id]);

        $response = $this->actingAs($instructor)->get(route('cursos.index'));

        $response->assertOk();
        $response->assertDontSee('Gestionar categorías');
    }

    public function test_categorias_no_aparece_como_seccion_propia_en_el_sidebar(): void
    {
        $admin = $this->crearUsuarioConRol('Administrador');

        $response = $this->actingAs($admin)->get('/dashboard');

        $response->assertOk();
        $response->assertDontSee('dyl-sb-label">Categorías<', false);
    }
}
