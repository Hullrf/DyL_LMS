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

class EditarEntregaEstudianteTest extends TestCase
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

    private function entregar(Actividad $a, int $n = 1): void
    {
        $files = array_map(fn ($i) => UploadedFile::fake()->create("a{$i}.pdf", 10, 'application/pdf'), range(1, $n));
        $this->actingAs($this->estudiante)->post(route('respuestas.store', $a), ['respuesta' => 'v1', 'archivos_adjuntos' => $files]);
    }

    public function test_estudiante_puede_reemplazar_archivos_con_plazo_abierto(): void
    {
        Storage::fake('public');
        $a = $this->crearTarea(2);
        $this->entregar($a, 1);
        $r = RespuestaEstudiante::first();
        $viejo = $r->archivos->first()->path;

        $this->actingAs($this->estudiante)->put(route('respuestas.update', $a), [
            'respuesta' => 'v2',
            'archivos_adjuntos' => [
                UploadedFile::fake()->create('b1.pdf', 10, 'application/pdf'),
                UploadedFile::fake()->create('b2.pdf', 10, 'application/pdf'),
            ],
        ])->assertSessionHas('success');

        $r->refresh();
        $this->assertSame('v2', $r->respuesta);
        $this->assertCount(2, $r->archivos);
        Storage::disk('public')->assertMissing($viejo);
    }

    public function test_sin_archivos_nuevos_conserva_los_anteriores(): void
    {
        Storage::fake('public');
        $a = $this->crearTarea(2);
        $this->entregar($a, 1);

        $this->actingAs($this->estudiante)->put(route('respuestas.update', $a), ['respuesta' => 'solo texto']);

        $r = RespuestaEstudiante::first();
        $this->assertSame('solo texto', $r->respuesta);
        $this->assertCount(1, $r->archivos);
    }

    public function test_no_puede_editar_si_ya_esta_calificada(): void
    {
        Storage::fake('public');
        $a = $this->crearTarea();
        $this->entregar($a);
        RespuestaEstudiante::first()->update(['calificacion' => 8]);

        $this->actingAs($this->estudiante)->put(route('respuestas.update', $a), ['respuesta' => 'cambio'])
            ->assertSessionHas('error');
        $this->assertSame('v1', RespuestaEstudiante::first()->respuesta);
    }

    public function test_no_puede_editar_si_el_plazo_cerro(): void
    {
        Storage::fake('public');
        $a = $this->crearTarea();
        $this->entregar($a);
        $a->update(['fecha_cierre' => now()->subDay()]);

        $this->actingAs($this->estudiante)->put(route('respuestas.update', $a), ['respuesta' => 'tarde'])
            ->assertSessionHas('error');
        $this->assertSame('v1', RespuestaEstudiante::first()->respuesta);
    }

    public function test_vista_muestra_boton_editar_solo_si_es_editable(): void
    {
        Storage::fake('public');
        $a = $this->crearTarea();
        $this->entregar($a);

        $this->actingAs($this->estudiante)->get(route('actividades.show', $a))->assertSee('Editar entrega');

        RespuestaEstudiante::first()->update(['calificacion' => 8]);
        $this->actingAs($this->estudiante)->get(route('actividades.show', $a))->assertDontSee('Editar entrega');
    }
}
