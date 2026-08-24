<?php

namespace Tests\Feature;

use App\Models\Rol;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ComunicacionTabsTest extends TestCase
{
    use RefreshDatabase;

    private function crearUsuarioConRol(string $nombreRol): User
    {
        $rol = Rol::factory()->create(['nombre' => $nombreRol]);
        $user = User::factory()->create(['estado' => 'activo']);
        $user->roles()->attach($rol);
        return $user;
    }

    public function test_sidebar_ya_no_tiene_seccion_anuncios_separada(): void
    {
        $student = $this->crearUsuarioConRol('Estudiante');

        $response = $this->actingAs($student)->get('/dashboard');

        $response->assertOk();
        $response->assertDontSee('dyl-sb-label">Anuncios<', false);
    }

    public function test_bandeja_de_mensajes_muestra_pestanas_mensajes_y_anuncios(): void
    {
        $student = $this->crearUsuarioConRol('Estudiante');

        $response = $this->actingAs($student)->get(route('mensajes.bandeja'));

        $response->assertOk();
        $response->assertSee('Mensajes');
        $response->assertSee('Anuncios');
        $response->assertSee(route('anuncios.todos'), false);
    }

    public function test_todos_los_anuncios_muestra_pestanas_mensajes_y_anuncios(): void
    {
        $student = $this->crearUsuarioConRol('Estudiante');

        $response = $this->actingAs($student)->get(route('anuncios.todos'));

        $response->assertOk();
        $response->assertSee('Mensajes');
        $response->assertSee('Anuncios');
        $response->assertSee(route('mensajes.bandeja'), false);
    }
}
