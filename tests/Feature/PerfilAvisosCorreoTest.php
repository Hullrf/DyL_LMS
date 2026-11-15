<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\AvisosPorCorreoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PerfilAvisosCorreoTest extends TestCase
{
    use RefreshDatabase;

    public function test_el_perfil_muestra_las_cuatro_categorias(): void
    {
        $this->actingAs(User::factory()->create());

        $respuesta = $this->get(route('profile.edit'))->assertOk();

        foreach (AvisosPorCorreoService::CATEGORIAS as $etiqueta) {
            $respuesta->assertSee($etiqueta);
        }
    }

    public function test_el_formulario_refleja_lo_guardado(): void
    {
        $this->actingAs(User::factory()->create(['correo_desactivados' => ['anuncios']]));

        $html = $this->get(route('profile.edit'))->getContent();

        $this->assertDoesNotMatchRegularExpression('/value="anuncios"[^>]*checked/', $html);
        $this->assertMatchesRegularExpression('/value="vencimientos"[^>]*checked/', $html);
    }

    public function test_guarda_solo_las_categorias_marcadas(): void
    {
        $usuario = User::factory()->create();

        $this->actingAs($usuario)
            ->patch(route('profile.avisos-correo'), ['avisos' => ['anuncios']])
            ->assertRedirect(route('profile.edit'))
            ->assertSessionHas('status', 'avisos-correo-updated');

        $this->assertSame(
            ['vencimientos', 'calificaciones', 'certificados'],
            $usuario->fresh()->correo_desactivados
        );
    }

    public function test_todas_marcadas_guarda_null(): void
    {
        $usuario = User::factory()->create(['correo_desactivados' => ['anuncios']]);

        $this->actingAs($usuario)
            ->patch(route('profile.avisos-correo'), ['avisos' => array_keys(AvisosPorCorreoService::CATEGORIAS)])
            ->assertSessionHasNoErrors();

        $this->assertNull($usuario->fresh()->correo_desactivados);
    }

    public function test_sin_ninguna_marcada_desactiva_las_cuatro(): void
    {
        $usuario = User::factory()->create();

        $this->actingAs($usuario)
            ->patch(route('profile.avisos-correo'))
            ->assertSessionHasNoErrors();

        $this->assertSame(
            array_keys(AvisosPorCorreoService::CATEGORIAS),
            $usuario->fresh()->correo_desactivados
        );
    }

    public function test_rechaza_categorias_inventadas(): void
    {
        $usuario = User::factory()->create();

        $this->actingAs($usuario)
            ->patch(route('profile.avisos-correo'), ['avisos' => ['spam']])
            ->assertSessionHasErrors('avisos.0');

        $this->assertNull($usuario->fresh()->correo_desactivados);
    }

    public function test_invitado_es_redirigido_al_login(): void
    {
        $this->patch(route('profile.avisos-correo'), ['avisos' => ['anuncios']])
            ->assertRedirect(route('login'));
    }
}
