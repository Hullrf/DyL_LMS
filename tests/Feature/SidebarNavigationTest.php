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

    public function test_topbar_tiene_boton_de_tema_con_nombre_accesible(): void
    {
        $student = $this->crearUsuarioConRol('Estudiante');

        $this->actingAs($student)->get('/dashboard')
            ->assertSee('data-theme-toggle', false)
            ->assertSee('aria-label="Modo oscuro"', false);
    }

    public function test_el_enlace_de_la_seccion_actual_marca_aria_current(): void
    {
        $student = $this->crearUsuarioConRol('Estudiante');

        $this->actingAs($student)->get('/cursos')
            ->assertSee('aria-current="page"', false);
    }

    public function test_login_muestra_el_logo_de_letra_blanca_en_modo_oscuro(): void
    {
        $this->get('/login')
            ->assertSee('logo-icon-light-256.png', false)
            ->assertSee('logo-icon-dark-256.png', false)
            ->assertSee('hidden dark:block', false);
    }
}
