<?php
// tests/Feature/SidebarNavigationTest.php

namespace Tests\Feature;

use App\Models\Rol;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SidebarNavigationTest extends TestCase
{
    use RefreshDatabase;

    private function crearUsuarioConRol(string $nombreRol): User
    {
        $rol = Rol::factory()->create(['nombre' => $nombreRol]);
        $user = User::factory()->create(['estado' => 'activo']);
        $user->roles()->attach($rol);
        return $user;
    }

    public function test_dashboard_renders_sidebar_element_instead_of_old_navbar(): void
    {
        $student = $this->crearUsuarioConRol('Estudiante');

        $response = $this->actingAs($student)->get('/dashboard');

        $response->assertOk();
        $response->assertSee('<aside', false);
        $response->assertSee('Cursos');
        $response->assertDontSee('bg-dyl-orange-600 shadow-md', false);
    }

    public function test_sidebar_hides_admin_only_links_from_estudiante(): void
    {
        $student = $this->crearUsuarioConRol('Estudiante');

        $response = $this->actingAs($student)->get('/dashboard');

        $response->assertDontSee('Usuarios');
        $response->assertDontSee('Auditoría');
        $response->assertDontSee('Reportes');
    }

    public function test_sidebar_shows_admin_only_links_to_administrador(): void
    {
        $admin = $this->crearUsuarioConRol('Administrador');

        $response = $this->actingAs($admin)->get('/dashboard');

        $response->assertSee('Usuarios');
        $response->assertSee('Auditoría');
        $response->assertSee('Reportes');
    }
}
