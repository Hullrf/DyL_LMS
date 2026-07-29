<?php

namespace Tests\Feature;

use App\Models\Actividad;
use App\Models\Curso;
use App\Models\Leccion;
use App\Models\Modulo;
use App\Models\Rol;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RecursoActividadHtmlTest extends TestCase
{
    use RefreshDatabase;

    private function crearInstructor(): User
    {
        $rol = Rol::firstOrCreate(
            ['nombre' => 'Instructor'],
            ['descripcion' => 'Instructor role']
        );
        $user = User::factory()->create(['estado' => 'activo']);
        $user->roles()->attach($rol);
        return $user;
    }

    private function actividadDeInstructor(): Actividad
    {
        $instructor = $this->crearInstructor();
        $curso = Curso::factory()->create(['created_by' => $instructor->id]);
        $modulo = Modulo::factory()->create(['curso_id' => $curso->id]);
        $leccion = Leccion::factory()->create(['modulo_id' => $modulo->id]);
        $actividad = Actividad::factory()->create(['leccion_id' => $leccion->id]);

        $this->actingAs($instructor);

        return $actividad;
    }

    public function test_instructor_puede_subir_recurso_html(): void
    {
        Storage::fake('public');
        $actividad = $this->actividadDeInstructor();

        $archivo = UploadedFile::fake()->create('pagina.html', 10, 'text/html');

        $response = $this->post(route('recursos.store', $actividad), [
            'tipo'   => 'documento',
            'titulo' => 'Página de práctica',
            'archivo' => $archivo,
        ]);

        $response->assertRedirect(route('actividades.edit', $actividad));
        $this->assertDatabaseHas('recurso_actividades', [
            'actividad_id' => $actividad->id,
            'tipo'         => 'documento',
        ]);
    }

    public function test_descarga_de_recurso_html_fuerza_attachment(): void
    {
        Storage::fake('public');
        $actividad = $this->actividadDeInstructor();

        $archivo = UploadedFile::fake()->create('pagina.html', 10, 'text/html');
        $this->post(route('recursos.store', $actividad), [
            'tipo'    => 'documento',
            'titulo'  => 'Página de práctica',
            'archivo' => $archivo,
        ]);

        $recurso = $actividad->recursos()->first();

        $response = $this->get(route('recursos.descargar', $recurso));

        $response->assertOk();
        $response->assertHeader('content-disposition');
        $this->assertStringContainsString('attachment', $response->headers->get('content-disposition'));
    }

    public function test_extension_no_permitida_es_rechazada(): void
    {
        Storage::fake('public');
        $actividad = $this->actividadDeInstructor();

        $archivo = UploadedFile::fake()->create('script.exe', 10, 'application/octet-stream');

        $response = $this->post(route('recursos.store', $actividad), [
            'tipo'    => 'documento',
            'titulo'  => 'Ejecutable',
            'archivo' => $archivo,
        ]);

        $response->assertSessionHasErrors('archivo');
        $this->assertDatabaseMissing('recurso_actividades', [
            'actividad_id' => $actividad->id,
            'titulo'       => 'Ejecutable',
        ]);
    }
}
