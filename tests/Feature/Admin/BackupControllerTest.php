<?php

namespace Tests\Feature\Admin;

use App\Models\Rol;
use App\Models\User;
use App\Services\BackupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class BackupControllerTest extends TestCase
{
    use RefreshDatabase;

    private function crearUsuario(string $rolNombre): User
    {
        $rol  = Rol::firstOrCreate(['nombre' => $rolNombre]);
        $user = User::factory()->create(['estado' => 'activo']);
        $user->roles()->attach($rol);
        return $user;
    }

    public function test_admin_puede_acceder_a_la_pantalla_de_backups(): void
    {
        $admin = $this->crearUsuario('Administrador');

        $response = $this->actingAs($admin)->get(route('admin.backups.index'));

        $response->assertOk();
        $response->assertViewIs('admin.backups.index');
    }

    public function test_instructor_no_puede_acceder_a_pantalla_de_backups(): void
    {
        $instructor = $this->crearUsuario('Instructor');

        $response = $this->actingAs($instructor)->get(route('admin.backups.index'));

        $response->assertForbidden();
    }

    public function test_crear_backup_devuelve_el_dump_como_descarga(): void
    {
        $admin = $this->crearUsuario('Administrador');

        $this->mock(BackupService::class, function ($mock) {
            $mock->shouldReceive('crearDump')
                ->once()
                ->with('php://output')
                ->andReturnUsing(function () {
                    echo "-- dump de prueba\nDROP TABLE IF EXISTS `x`;\n";
                });
        });

        $response = $this->actingAs($admin)->post(route('admin.backups.crear'));

        $response->assertOk();
        $response->assertHeader('content-disposition');
        $this->assertStringContainsString(
            'attachment',
            $response->headers->get('content-disposition')
        );
        $this->assertStringContainsString(
            'DROP TABLE IF EXISTS `x`;',
            $response->streamedContent()
        );
    }

    public function test_instructor_no_puede_crear_backup(): void
    {
        $instructor = $this->crearUsuario('Instructor');

        $response = $this->actingAs($instructor)->post(route('admin.backups.crear'));

        $response->assertForbidden();
    }

    public function test_restaurar_requiere_escribir_restaurar_exacto(): void
    {
        $admin = $this->crearUsuario('Administrador');

        $this->mock(BackupService::class, function ($mock) {
            $mock->shouldNotReceive('restaurarDesdeArchivo');
        });

        $response = $this->actingAs($admin)->post(route('admin.backups.restaurar'), [
            'confirmacion' => 'restaurar',
            'archivo'      => UploadedFile::fake()->create('backup.sql', 10),
        ]);

        $response->assertSessionHasErrors('confirmacion');
    }

    public function test_restaurar_rechaza_archivo_sin_extension_sql(): void
    {
        $admin = $this->crearUsuario('Administrador');

        $this->mock(BackupService::class, function ($mock) {
            $mock->shouldNotReceive('restaurarDesdeArchivo');
        });

        $response = $this->actingAs($admin)->post(route('admin.backups.restaurar'), [
            'confirmacion' => 'RESTAURAR',
            'archivo'      => UploadedFile::fake()->create('backup.txt', 10),
        ]);

        $response->assertSessionHasErrors('archivo');
    }

    public function test_restaurar_con_archivo_valido_llama_al_servicio_y_redirige(): void
    {
        $admin = $this->crearUsuario('Administrador');

        $this->mock(BackupService::class, function ($mock) {
            $mock->shouldReceive('restaurarDesdeArchivo')->once()->andReturn(42);
        });

        $response = $this->actingAs($admin)->post(route('admin.backups.restaurar'), [
            'confirmacion' => 'RESTAURAR',
            'archivo'      => UploadedFile::fake()->create('backup.sql', 10),
        ]);

        $response->assertRedirect(route('admin.backups.index'));
        $response->assertSessionHas('success');
    }

    public function test_instructor_no_puede_restaurar(): void
    {
        $instructor = $this->crearUsuario('Instructor');

        $response = $this->actingAs($instructor)->post(route('admin.backups.restaurar'), [
            'confirmacion' => 'RESTAURAR',
            'archivo'      => UploadedFile::fake()->create('backup.sql', 10),
        ]);

        $response->assertForbidden();
    }

    public function test_pantalla_de_backups_muestra_los_dos_formularios(): void
    {
        $admin = $this->crearUsuario('Administrador');

        $response = $this->actingAs($admin)->get(route('admin.backups.index'));

        $response->assertOk();
        $response->assertSee('Descargar backup ahora');
        $response->assertSee('Descargar backup de seguridad del estado actual');
        $response->assertSee('RESTAURAR', false);
    }

    public function test_sidebar_muestra_enlace_de_backups_para_admin(): void
    {
        $admin = $this->crearUsuario('Administrador');

        $response = $this->actingAs($admin)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee(route('admin.backups.index'), false);
    }
}
