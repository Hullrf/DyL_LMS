<?php

namespace Tests\Feature\Admin;

use App\Models\Categoria;
use App\Models\Curso;
use App\Models\Rol;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoriaControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $instructor;

    protected function setUp(): void
    {
        parent::setUp();

        $rolAdmin      = Rol::create(['nombre' => 'Administrador']);
        $rolInstructor = Rol::create(['nombre' => 'Instructor']);

        $this->admin = User::factory()->create(['estado' => 'activo']);
        $this->admin->roles()->attach($rolAdmin);

        $this->instructor = User::factory()->create(['estado' => 'activo']);
        $this->instructor->roles()->attach($rolInstructor);
    }

    public function test_admin_puede_ver_lista_categorias(): void
    {
        Categoria::create(['nombre' => 'Normas ISO', 'slug' => 'normas-iso']);

        $response = $this->actingAs($this->admin)->get(route('admin.categorias.index'));

        $response->assertOk()->assertViewIs('admin.categorias.index');
    }

    public function test_instructor_no_puede_ver_lista_categorias(): void
    {
        $response = $this->actingAs($this->instructor)->get(route('admin.categorias.index'));

        $response->assertForbidden();
    }

    public function test_admin_puede_crear_categoria(): void
    {
        $response = $this->actingAs($this->admin)->post(route('admin.categorias.store'), [
            'nombre' => 'Auditorías Internas',
            'color'  => '#059669',
        ]);

        $response->assertRedirect(route('admin.categorias.index'));
        $this->assertDatabaseHas('categorias', [
            'nombre' => 'Auditorías Internas',
            'color'  => '#059669',
            'slug'   => 'auditorias-internas',
        ]);
    }

    public function test_no_permite_crear_categoria_con_nombre_duplicado(): void
    {
        Categoria::create(['nombre' => 'Normas ISO', 'slug' => 'normas-iso']);

        $response = $this->actingAs($this->admin)->post(route('admin.categorias.store'), [
            'nombre' => 'Normas ISO',
        ]);

        $response->assertSessionHasErrors('nombre');
        $this->assertSame(1, Categoria::where('nombre', 'Normas ISO')->count());
    }

    public function test_admin_puede_editar_categoria(): void
    {
        $categoria = Categoria::create(['nombre' => 'Normas ISO', 'slug' => 'normas-iso']);

        $response = $this->actingAs($this->admin)->put(route('admin.categorias.update', $categoria), [
            'nombre' => 'Normas ISO 27001',
            'color'  => '#D97706',
        ]);

        $response->assertRedirect(route('admin.categorias.index'));
        $this->assertDatabaseHas('categorias', [
            'id'     => $categoria->id,
            'nombre' => 'Normas ISO 27001',
            'color'  => '#D97706',
        ]);
    }

    public function test_admin_puede_eliminar_categoria_y_los_cursos_quedan_sin_categoria(): void
    {
        $categoria = Categoria::create(['nombre' => 'Normas ISO', 'slug' => 'normas-iso']);
        $curso = Curso::factory()->create(['categoria_id' => $categoria->id]);

        $response = $this->actingAs($this->admin)->delete(route('admin.categorias.destroy', $categoria));

        $response->assertRedirect(route('admin.categorias.index'));
        $this->assertDatabaseMissing('categorias', ['id' => $categoria->id]);
        $this->assertNull($curso->fresh()->categoria_id);
    }
}
