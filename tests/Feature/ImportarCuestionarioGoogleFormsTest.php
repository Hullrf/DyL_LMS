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
use Tests\TestCase;

class ImportarCuestionarioGoogleFormsTest extends TestCase
{
    use RefreshDatabase;

    private function crearInstructorConCuestionario(float $puntajeMaximo = 100): Actividad
    {
        $rol = Rol::firstOrCreate(['nombre' => 'Instructor'], ['descripcion' => 'Instructor role']);
        $instructor = User::factory()->create(['estado' => 'activo']);
        $instructor->roles()->attach($rol);

        $curso   = Curso::factory()->create(['created_by' => $instructor->id]);
        $modulo  = Modulo::factory()->create(['curso_id' => $curso->id]);
        $leccion = Leccion::factory()->create(['modulo_id' => $modulo->id]);

        $actividad = Actividad::factory()->create([
            'leccion_id'     => $leccion->id,
            'tipo'           => 'cuestionario',
            'puntaje_maximo' => $puntajeMaximo,
        ]);

        $this->actingAs($instructor);

        return $actividad;
    }

    private function archivoJson(array $contenido): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('cuestionario.json', json_encode($contenido));
    }

    public function test_importa_pregunta_opcion_multiple_con_correcta_conocida_y_respuesta_corta(): void
    {
        $actividad = $this->crearInstructorConCuestionario(100);

        $json = [
            'version' => 1,
            'preguntas' => [
                [
                    'texto' => '¿Cuál es la capital de Francia?',
                    'tipo' => 'opcion_multiple',
                    'multiple' => false,
                    'opciones' => [
                        ['texto' => 'Madrid', 'correcta' => false],
                        ['texto' => 'París', 'correcta' => true],
                    ],
                ],
                ['texto' => 'Explica el proceso', 'tipo' => 'respuesta_corta'],
            ],
        ];

        $response = $this->post(route('preguntas.importar', $actividad), ['archivo' => $this->archivoJson($json)]);

        $response->assertRedirect(route('actividades.edit', $actividad));
        $this->assertEquals(2, $actividad->preguntas()->count());

        $pregunta = $actividad->preguntas()->where('tipo', 'opcion_multiple')->first();
        $this->assertEquals(2, $pregunta->opciones()->count());
        $this->assertEquals('París', $pregunta->opciones()->where('es_correcta', true)->first()->texto);

        $this->assertEquals(100, $actividad->preguntas()->sum('puntaje'));
    }

    public function test_detecta_verdadero_falso_por_texto_de_las_opciones(): void
    {
        $actividad = $this->crearInstructorConCuestionario(10);

        $json = [
            'version' => 1,
            'preguntas' => [[
                'texto' => 'El cielo es azul',
                'tipo' => 'opcion_multiple',
                'multiple' => false,
                'opciones' => [
                    ['texto' => 'Verdadero', 'correcta' => true],
                    ['texto' => 'Falso', 'correcta' => false],
                ],
            ]],
        ];

        $this->post(route('preguntas.importar', $actividad), ['archivo' => $this->archivoJson($json)]);

        $pregunta = $actividad->preguntas()->first();
        $this->assertEquals('verdadero_falso', $pregunta->tipo);
        $this->assertEquals('Verdadero', $pregunta->opciones()->where('es_correcta', true)->first()->texto);
    }

    public function test_pregunta_sin_correcta_conocida_queda_marcada_como_pendiente(): void
    {
        $actividad = $this->crearInstructorConCuestionario(10);

        $json = [
            'version' => 1,
            'preguntas' => [[
                'texto' => '¿Cuál es la capital de Perú?',
                'tipo' => 'opcion_multiple',
                'multiple' => false,
                'opciones' => [
                    ['texto' => 'Lima', 'correcta' => null],
                    ['texto' => 'Cusco', 'correcta' => null],
                ],
            ]],
        ];

        $response = $this->post(route('preguntas.importar', $actividad), ['archivo' => $this->archivoJson($json)]);

        $pregunta = $actividad->preguntas()->first();
        $this->assertEquals(0, $pregunta->opciones()->where('es_correcta', true)->count());
        $response->assertSessionHas('success', function ($mensaje) {
            return str_contains($mensaje, '1 necesita');
        });
    }

    public function test_importar_agrega_al_final_sin_borrar_preguntas_existentes(): void
    {
        $actividad = $this->crearInstructorConCuestionario(10);
        $actividad->preguntas()->create([
            'pregunta_texto' => 'Pregunta manual', 'tipo' => 'respuesta_corta', 'puntaje' => 10, 'orden' => 1,
        ]);

        $json = ['version' => 1, 'preguntas' => [['texto' => 'Importada', 'tipo' => 'respuesta_corta']]];
        $this->post(route('preguntas.importar', $actividad), ['archivo' => $this->archivoJson($json)]);

        $this->assertEquals(2, $actividad->preguntas()->count());
        $this->assertTrue($actividad->preguntas()->where('pregunta_texto', 'Pregunta manual')->exists());
        $this->assertTrue($actividad->preguntas()->where('pregunta_texto', 'Importada')->exists());
    }

    public function test_json_malformado_no_crea_ninguna_pregunta(): void
    {
        $actividad = $this->crearInstructorConCuestionario(10);

        $json = ['version' => 1, 'preguntas' => [['texto' => 'Falta el tipo']]];
        $response = $this->post(route('preguntas.importar', $actividad), ['archivo' => $this->archivoJson($json)]);

        $response->assertSessionHasErrors('archivo');
        $this->assertEquals(0, $actividad->preguntas()->count());
    }

    public function test_estudiante_no_puede_importar(): void
    {
        $actividad = $this->crearInstructorConCuestionario(10);
        $rolEstudiante = Rol::firstOrCreate(['nombre' => 'Estudiante'], ['descripcion' => 'Estudiante role']);
        $estudiante = User::factory()->create(['estado' => 'activo']);
        $estudiante->roles()->attach($rolEstudiante);

        $json = ['version' => 1, 'preguntas' => [['texto' => 'x', 'tipo' => 'respuesta_corta']]];
        $response = $this->actingAs($estudiante)->post(route('preguntas.importar', $actividad), ['archivo' => $this->archivoJson($json)]);

        $response->assertForbidden();
        $this->assertEquals(0, $actividad->preguntas()->count());
    }

    public function test_descarga_json_de_ejemplo_y_se_importa_correctamente(): void
    {
        $actividad = $this->crearInstructorConCuestionario(100);

        $response = $this->get(route('preguntas.ejemplo'));

        $response->assertOk();
        $this->assertStringContainsString(
            'ejemplo-cuestionario.json',
            $response->headers->get('Content-Disposition')
        );

        $contenidoDescargado = $response->streamedContent();

        $archivo = UploadedFile::fake()->createWithContent('ejemplo-cuestionario.json', $contenidoDescargado);
        $importResponse = $this->post(route('preguntas.importar', $actividad), ['archivo' => $archivo]);

        $importResponse->assertRedirect(route('actividades.edit', $actividad));
        $this->assertEquals(4, $actividad->preguntas()->count());

        $francia = $actividad->preguntas()->where('pregunta_texto', '¿Cuál es la capital de Francia?')->first();
        $this->assertEquals('opcion_multiple', $francia->tipo);
        $this->assertFalse($francia->seleccion_multiple);
        $this->assertEquals('París', $francia->opciones()->where('es_correcta', true)->first()->texto);

        $lenguajes = $actividad->preguntas()->where('pregunta_texto', 'Selecciona los lenguajes de programación (puede haber varias correctas)')->first();
        $this->assertEquals('opcion_multiple', $lenguajes->tipo);
        $this->assertTrue($lenguajes->seleccion_multiple);
        $this->assertEquals(2, $lenguajes->opciones()->where('es_correcta', true)->count());

        $sol = $actividad->preguntas()->where('pregunta_texto', 'El sol es una estrella')->first();
        $this->assertEquals('verdadero_falso', $sol->tipo);
        $this->assertEquals('Verdadero', $sol->opciones()->where('es_correcta', true)->first()->texto);

        $fotosintesis = $actividad->preguntas()->where('pregunta_texto', 'Explica brevemente qué es la fotosíntesis')->first();
        $this->assertEquals('respuesta_corta', $fotosintesis->tipo);
    }
}
