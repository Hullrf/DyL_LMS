<?php
// tests/Feature/ProfileDocumentoTest.php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileDocumentoTest extends TestCase
{
    use RefreshDatabase;

    public function test_usuario_puede_guardar_numero_documento_y_ciudad_desde_su_perfil(): void
    {
        $user = User::factory()->create(['estado' => 'activo']);

        $response = $this->actingAs($user)->patch(route('profile.update'), [
            'name'              => $user->name,
            'email'             => $user->email,
            'numero_documento'  => '1000790950',
            'ciudad_expedicion' => 'Bogotá',
        ]);

        $response->assertRedirect(route('profile.edit'));
        $user->refresh();
        $this->assertSame('1000790950', $user->numero_documento);
        $this->assertSame('Bogotá', $user->ciudad_expedicion);
    }

    public function test_numero_documento_y_ciudad_son_opcionales(): void
    {
        $user = User::factory()->create(['estado' => 'activo']);

        $response = $this->actingAs($user)->patch(route('profile.update'), [
            'name'  => $user->name,
            'email' => $user->email,
        ]);

        $response->assertRedirect(route('profile.edit'));
        $user->refresh();
        $this->assertNull($user->numero_documento);
    }

    public function test_pantalla_de_perfil_muestra_los_campos_nuevos(): void
    {
        $user = User::factory()->create(['estado' => 'activo', 'numero_documento' => '999888']);

        $response = $this->actingAs($user)->get(route('profile.edit'));

        $response->assertOk();
        $response->assertSee('numero_documento', false);
        $response->assertSee('999888');
    }
}
