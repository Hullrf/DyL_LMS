<?php

namespace Tests\Feature;

use App\Models\Rol;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ErrorPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_404_se_muestra_en_espanol_con_la_marca(): void
    {
        $this->get('/esta-ruta-no-existe')
            ->assertNotFound()
            ->assertSee('Página no encontrada')
            ->assertSee('Ir al inicio');
    }

    public function test_403_se_muestra_en_espanol(): void
    {
        $rol = Rol::create(['nombre' => 'Instructor']);
        $instructor = User::factory()->create(['estado' => 'activo']);
        $instructor->roles()->attach($rol);

        $this->actingAs($instructor)->get(route('admin.usuarios.index'))
            ->assertForbidden()
            ->assertSee('Acceso denegado');
    }
}
