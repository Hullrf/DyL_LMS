<?php

namespace Tests\Feature\Admin;

use App\Models\Rol;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UsuarioControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $instructor;
    private Rol  $rolAdmin;
    private Rol  $rolEstudiante;

    protected function setUp(): void
    {
        parent::setUp();

        $this->rolAdmin      = Rol::create(['nombre' => 'Administrador']);
        $this->rolEstudiante = Rol::create(['nombre' => 'Estudiante']);
        $rolInstructor       = Rol::create(['nombre' => 'Instructor']);

        $this->admin = User::factory()->create(['estado' => 'activo']);
        $this->admin->roles()->attach($this->rolAdmin);

        $this->instructor = User::factory()->create(['estado' => 'activo']);
        $this->instructor->roles()->attach($rolInstructor);
    }

    public function test_admin_puede_ver_lista_usuarios(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.usuarios.index'));
        $response->assertOk()->assertViewIs('admin.usuarios.index');
    }

    public function test_instructor_no_puede_ver_lista_usuarios(): void
    {
        $response = $this->actingAs($this->instructor)->get(route('admin.usuarios.index'));
        $response->assertForbidden();
    }

    public function test_admin_puede_crear_usuario(): void
    {
        $response = $this->actingAs($this->admin)->post(route('admin.usuarios.store'), [
            'name'                  => 'Nuevo Usuario',
            'email'                 => 'nuevo@test.com',
            'password'              => 'password123',
            'password_confirmation' => 'password123',
            'empresa'               => 'Empresa Test',
            'estado'                => 'activo',
            'roles'                 => [$this->rolEstudiante->id],
        ]);

        $response->assertRedirect(route('admin.usuarios.index'));
        $this->assertDatabaseHas('users', ['email' => 'nuevo@test.com']);
    }

    public function test_admin_puede_editar_usuario(): void
    {
        $usuario = User::factory()->create(['estado' => 'activo']);
        $usuario->roles()->attach($this->rolEstudiante);

        $response = $this->actingAs($this->admin)->put(route('admin.usuarios.update', $usuario), [
            'name'    => 'Nombre Modificado',
            'email'   => $usuario->email,
            'empresa' => 'Nueva Empresa',
            'estado'  => 'inactivo',
            'roles'   => [$this->rolEstudiante->id],
        ]);

        $response->assertRedirect(route('admin.usuarios.index'));
        $this->assertDatabaseHas('users', [
            'id'     => $usuario->id,
            'name'   => 'Nombre Modificado',
            'estado' => 'inactivo',
        ]);
    }

    public function test_admin_puede_eliminar_usuario(): void
    {
        $usuario  = User::factory()->create();
        $response = $this->actingAs($this->admin)->delete(route('admin.usuarios.destroy', $usuario));

        $response->assertRedirect(route('admin.usuarios.index'));
        $this->assertSoftDeleted('users', ['id' => $usuario->id]);
    }

    public function test_admin_no_puede_eliminarse_a_si_mismo(): void
    {
        $response = $this->actingAs($this->admin)->delete(route('admin.usuarios.destroy', $this->admin));

        $response->assertRedirect();
        $this->assertDatabaseHas('users', ['id' => $this->admin->id, 'deleted_at' => null]);
    }
}
