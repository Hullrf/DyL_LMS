<?php

namespace Tests\Feature;

use App\Models\Actividad;
use App\Models\Curso;
use App\Models\Inscripcion;
use App\Models\Leccion;
use App\Models\Modulo;
use App\Models\RespuestaEstudiante;
use App\Models\Rol;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ArchivosMultiplesEntregaTest extends TestCase
{
    use RefreshDatabase;

    private User $instructor;
    private User $estudiante;
    private Curso $curso;
    private Leccion $leccion;

    protected function setUp(): void
    {
        parent::setUp();

        $rolInstructor = Rol::create(['nombre' => 'Instructor']);
        $rolEstudiante = Rol::create(['nombre' => 'Estudiante']);
        Rol::create(['nombre' => 'Administrador']);

        $this->instructor = User::factory()->create(['estado' => 'activo']);
        $this->instructor->roles()->attach($rolInstructor);

        $this->estudiante = User::factory()->create(['estado' => 'activo']);
        $this->estudiante->roles()->attach($rolEstudiante);

        $this->curso = Curso::factory()->create([
            'created_by' => $this->instructor->id,
            'estado'     => 'publicado',
        ]);

        $modulo        = Modulo::factory()->create(['curso_id' => $this->curso->id]);
        $this->leccion = Leccion::factory()->create(['modulo_id' => $modulo->id]);

        Inscripcion::create([
            'user_id'      => $this->estudiante->id,
            'curso_id'     => $this->curso->id,
            'fecha_inicio' => now(),
            'estado'       => 'en_progreso',
        ]);
    }

    private function crearTarea(int $maxArchivos = 1): Actividad
    {
        return Actividad::factory()->create([
            'leccion_id'             => $this->leccion->id,
            'tipo'                   => 'tarea',
            'puntaje_maximo'         => 10,
            'max_archivos_adjuntos'  => $maxArchivos,
        ]);
    }

    public function test_actividad_creada_sin_especificar_maximo_usa_default_1(): void
    {
        $response = $this->actingAs($this->instructor)->post(
            route('actividades.store', $this->leccion),
            ['titulo' => 'Tarea simple', 'tipo' => 'tarea', 'puntaje_maximo' => 10]
        );

        $response->assertRedirect();
        $this->assertDatabaseHas('actividades', [
            'titulo'                 => 'Tarea simple',
            'max_archivos_adjuntos'  => 1,
        ]);
    }

    public function test_instructor_puede_configurar_maximo_de_archivos_al_crear_tarea(): void
    {
        $response = $this->actingAs($this->instructor)->post(
            route('actividades.store', $this->leccion),
            ['titulo' => 'Tarea con varios archivos', 'tipo' => 'tarea', 'puntaje_maximo' => 10, 'max_archivos_adjuntos' => 4]
        );

        $response->assertRedirect();
        $this->assertDatabaseHas('actividades', [
            'titulo'                => 'Tarea con varios archivos',
            'max_archivos_adjuntos' => 4,
        ]);
    }

    public function test_instructor_puede_actualizar_maximo_de_archivos(): void
    {
        $actividad = $this->crearTarea(1);

        $response = $this->actingAs($this->instructor)->put(
            route('actividades.update', $actividad),
            ['titulo' => $actividad->titulo, 'puntaje_maximo' => 10, 'max_archivos_adjuntos' => 3]
        );

        $response->assertRedirect(route('actividades.edit', $actividad));
        $this->assertEquals(3, $actividad->fresh()->max_archivos_adjuntos);
    }

    public function test_estudiante_puede_subir_hasta_el_maximo_configurado(): void
    {
        Storage::fake('public');
        $actividad = $this->crearTarea(3);

        $archivos = [
            UploadedFile::fake()->create('uno.pdf', 100, 'application/pdf'),
            UploadedFile::fake()->create('dos.pdf', 100, 'application/pdf'),
            UploadedFile::fake()->create('tres.pdf', 100, 'application/pdf'),
        ];

        $response = $this->actingAs($this->estudiante)->post(
            route('respuestas.store', $actividad),
            ['respuesta' => 'Mi entrega', 'archivos_adjuntos' => $archivos]
        );

        $response->assertRedirect(route('actividades.show', $actividad));
        $respuesta = RespuestaEstudiante::first();
        $this->assertEquals(3, $respuesta->archivos()->count());
        foreach ($respuesta->archivos as $archivo) {
            Storage::disk('public')->assertExists($archivo->path);
        }
    }

    public function test_exceder_el_maximo_de_archivos_es_rechazado(): void
    {
        Storage::fake('public');
        $actividad = $this->crearTarea(2);

        $archivos = [
            UploadedFile::fake()->create('uno.pdf', 100, 'application/pdf'),
            UploadedFile::fake()->create('dos.pdf', 100, 'application/pdf'),
            UploadedFile::fake()->create('tres.pdf', 100, 'application/pdf'),
        ];

        $response = $this->actingAs($this->estudiante)->post(
            route('respuestas.store', $actividad),
            ['respuesta' => 'Mi entrega', 'archivos_adjuntos' => $archivos]
        );

        $response->assertSessionHasErrors('archivos_adjuntos');
        $this->assertEquals(0, RespuestaEstudiante::count());
    }

    public function test_actividad_de_un_solo_archivo_sigue_aceptando_una_entrega_normal(): void
    {
        Storage::fake('public');
        $actividad = $this->crearTarea(1);

        $response = $this->actingAs($this->estudiante)->post(
            route('respuestas.store', $actividad),
            ['respuesta' => 'Mi entrega', 'archivos_adjuntos' => [UploadedFile::fake()->create('unico.pdf', 100, 'application/pdf')]]
        );

        $response->assertRedirect(route('actividades.show', $actividad));
        $respuesta = RespuestaEstudiante::first();
        $this->assertEquals(1, $respuesta->archivos()->count());
    }

    public function test_formulario_de_edicion_muestra_campo_maximo_de_archivos_para_tarea(): void
    {
        $actividad = $this->crearTarea(2);

        $response = $this->actingAs($this->instructor)->get(route('actividades.edit', $actividad));

        $response->assertOk();
        $response->assertSee('Máximo de archivos', false);
    }

    public function test_formulario_de_edicion_no_muestra_campo_maximo_de_archivos_para_cuestionario(): void
    {
        $actividad = Actividad::factory()->create([
            'leccion_id' => $this->leccion->id, 'tipo' => 'cuestionario', 'puntaje_maximo' => 10,
        ]);

        $response = $this->actingAs($this->instructor)->get(route('actividades.edit', $actividad));

        $response->assertOk();
        $response->assertDontSee('Máximo de archivos', false);
    }

    public function test_calificacion_muestra_lista_simple_cuando_hay_varios_archivos(): void
    {
        Storage::fake('public');
        $actividad = $this->crearTarea(3);
        $this->actingAs($this->estudiante)->post(
            route('respuestas.store', $actividad),
            ['respuesta' => 'Mi entrega', 'archivos_adjuntos' => [
                UploadedFile::fake()->create('uno.pdf', 100, 'application/pdf'),
                UploadedFile::fake()->create('dos.pdf', 100, 'application/pdf'),
            ]]
        );
        $respuesta = RespuestaEstudiante::first();

        $response = $this->actingAs($this->instructor)->get(route('calificaciones.show', $respuesta));

        $response->assertOk();
        $response->assertSee('uno.pdf');
        $response->assertSee('dos.pdf');
    }
}
