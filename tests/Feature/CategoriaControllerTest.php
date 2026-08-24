<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Rol;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class CategoriaControllerTest extends TestCase
{
    use RefreshDatabase;

    private function crearInstructor(): User
    {
        $rol = Rol::factory()->instructor()->create();
        $user = User::factory()->create(['estado' => 'activo']);
        $user->roles()->attach($rol);
        return $user;
    }

    private function crearAdmin(): User
    {
        $rol = Rol::factory()->administrador()->create();
        $user = User::factory()->create(['estado' => 'activo']);
        $user->roles()->attach($rol);
        return $user;
    }

    private function crearEstudiante(): User
    {
        $rol = Rol::factory()->estudiante()->create();
        $user = User::factory()->create(['estado' => 'activo']);
        $user->roles()->attach($rol);
        return $user;
    }

    public function test_instructor_puede_crear_categoria_nueva(): void
    {
        $instructor = $this->crearInstructor();

        $response = $this->actingAs($instructor)->postJson('/categorias', [
            'nombre' => 'Auditorías Internas',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure(['id', 'nombre', 'color'])
            ->assertJson(['nombre' => 'Auditorías Internas']);

        $this->assertDatabaseHas('categorias', ['nombre' => 'Auditorías Internas']);
    }

    public function test_admin_puede_crear_categoria_nueva(): void
    {
        $admin = $this->crearAdmin();

        $response = $this->actingAs($admin)->postJson('/categorias', [
            'nombre' => 'Riesgos',
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('categorias', ['nombre' => 'Riesgos']);
    }

    public function test_estudiante_no_puede_crear_categoria(): void
    {
        $estudiante = $this->crearEstudiante();

        $response = $this->actingAs($estudiante)->postJson('/categorias', [
            'nombre' => 'Riesgos',
        ]);

        $response->assertStatus(403);
        $this->assertDatabaseMissing('categorias', ['nombre' => 'Riesgos']);
    }

    public function test_nombre_es_requerido(): void
    {
        $instructor = $this->crearInstructor();

        $response = $this->actingAs($instructor)->postJson('/categorias', []);

        $response->assertStatus(422)->assertJsonValidationErrors('nombre');
    }

    public function test_no_permite_categoria_duplicada_sin_importar_mayusculas_o_espacios(): void
    {
        $instructor = $this->crearInstructor();
        Categoria::create(['nombre' => 'Normas ISO', 'slug' => Str::slug('Normas ISO')]);

        $response = $this->actingAs($instructor)->postJson('/categorias', [
            'nombre' => '  normas iso  ',
        ]);

        $response->assertStatus(422);
        $this->assertSame(1, Categoria::where('nombre', 'Normas ISO')->count());
    }

    public function test_genera_slug_unico_cuando_colisiona_con_uno_existente(): void
    {
        $instructor = $this->crearInstructor();
        Categoria::create(['nombre' => 'Punto Com', 'slug' => 'punto-com']);

        $response = $this->actingAs($instructor)->postJson('/categorias', [
            'nombre' => 'Punto-Com',
        ]);

        $response->assertStatus(201);
        $nuevaCategoria = Categoria::where('nombre', 'Punto-Com')->firstOrFail();
        $this->assertNotSame('punto-com', $nuevaCategoria->slug);
    }
}
