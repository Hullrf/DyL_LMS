# Importar cuestionarios desde Google Forms Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [x]`) syntax for tracking.

**Goal:** El instructor puede subir un JSON (generado por una plantilla de Apps Script) en el editor de un cuestionario y que el LMS cree las preguntas/opciones automáticamente, agregándolas al final de las existentes.

**Architecture:** Un nuevo controlador (`ImportacionCuestionarioController`) valida el JSON y crea `Pregunta`/`Opcion` reutilizando el modelo `Pregunta`/`Opcion` existentes. Se extrae la redistribución de puntaje de `PreguntaController` a un método público en `Actividad` para compartirlo. Se agrega `OpcionController::marcarCorrecta()` porque hoy no existe forma de corregir la opción correcta de una pregunta `opcion_multiple` ya creada (necesario para el caso de Forms sin modo "Cuestionario"). El puente con Google Forms (el propio Apps Script) es un archivo de documentación, no código Laravel.

**Tech Stack:** Laravel 12 / PHP 8.2, Blade + Alpine.js, PHPUnit (Feature tests), Google Apps Script (JavaScript, fuera del repo Laravel).

## Global Constraints

- El importador **agrega** preguntas al final de las existentes; nunca borra ni reemplaza preguntas ya creadas en la actividad.
- El importador solo acepta `tipo: "opcion_multiple"` o `"respuesta_corta"` en el JSON; cualquier otro valor hace fallar la validación completa del archivo (los tipos no soportados de Forms se filtran en el propio Apps Script, nunca llegan al LMS).
- Detección automática de Verdadero/Falso: una pregunta `opcion_multiple` con **exactamente 2 opciones** cuyo texto normalizado (minúsculas, sin acentos) sea `{verdadero, falso}` o `{true, false}` se guarda como `tipo = verdadero_falso`.
- El puntaje de cada pregunta siempre se reparte equitativamente sobre `puntaje_maximo` de la actividad (mismo comportamiento que ya existe hoy) — el importador no usa ningún peso individual del Form.
- Todas las rutas nuevas van dentro del grupo de middleware `instructor` ya existente en `routes/web.php`, salvo `opciones.marcarCorrecta` (mismo criterio que `opciones.store`/`opciones.destroy`, que también están ahí).

---

### Task 1: Compartir la redistribución de puntaje entre preguntas

**Files:**
- Modify: `app/Models/Actividad.php` (agregar método al final de la clase, antes del `}`)
- Modify: `app/Http/Controllers/PreguntaController.php:131-145` (quitar método privado, usar el del modelo)
- Test: `tests/Feature/PreguntaControllerTest.php` (nuevo — no existía un test dedicado a este controlador)

**Interfaces:**
- Consumes: nada de tareas anteriores.
- Produces: `Actividad::redistribuirPuntajesPreguntas(): void` — lo consume Task 3.

- [x] **Step 1: Escribir el test que falla**

Crear `tests/Feature/PreguntaControllerTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\Actividad;
use App\Models\Curso;
use App\Models\Leccion;
use App\Models\Modulo;
use App\Models\Pregunta;
use App\Models\Rol;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PreguntaControllerTest extends TestCase
{
    use RefreshDatabase;

    private function crearInstructorConActividad(float $puntajeMaximo = 100): Actividad
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

    public function test_agregar_pregunta_redistribuye_el_puntaje_entre_todas(): void
    {
        $actividad = $this->crearInstructorConActividad(90);
        Pregunta::factory()->create(['actividad_id' => $actividad->id, 'tipo' => 'respuesta_corta', 'puntaje' => 999]);

        $this->post(route('preguntas.store', $actividad), [
            'pregunta_texto' => '¿Segunda pregunta?',
            'tipo'           => 'respuesta_corta',
        ]);

        $this->assertEquals(2, $actividad->preguntas()->count());
        $this->assertEquals(90, $actividad->preguntas()->sum('puntaje'));
        $this->assertEquals(45, $actividad->preguntas()->orderBy('orden')->first()->puntaje);
    }

    public function test_eliminar_pregunta_redistribuye_el_puntaje_entre_las_restantes(): void
    {
        $actividad = $this->crearInstructorConActividad(90);
        $p1 = Pregunta::factory()->create(['actividad_id' => $actividad->id, 'tipo' => 'respuesta_corta', 'orden' => 1]);
        $p2 = Pregunta::factory()->create(['actividad_id' => $actividad->id, 'tipo' => 'respuesta_corta', 'orden' => 2]);
        $p3 = Pregunta::factory()->create(['actividad_id' => $actividad->id, 'tipo' => 'respuesta_corta', 'orden' => 3]);

        $this->delete(route('preguntas.destroy', $p2));

        $this->assertEquals(2, $actividad->preguntas()->count());
        $this->assertEquals(90, $actividad->preguntas()->sum('puntaje'));
    }
}
```

- [x] **Step 2: Ejecutar el test para verificar que pasa (es una extracción, no debería fallar)**

Run: `php artisan test --filter=PreguntaControllerTest`
Expected: PASS — este test cubre el comportamiento ya existente de `PreguntaController`, sirve como red de seguridad antes de mover el código.

- [x] **Step 3: Extraer el método al modelo**

En `app/Models/Actividad.php`, agregar antes del `}` final de la clase:

```php
    /**
     * Reparte el puntaje_maximo de la actividad equitativamente entre
     * todas sus preguntas. La última pregunta absorbe el residuo del
     * redondeo para que la suma sea exactamente puntaje_maximo.
     */
    public function redistribuirPuntajesPreguntas(): void
    {
        $preguntas = $this->preguntas()->orderBy('orden')->get();
        $total     = $preguntas->count();

        if ($total === 0) return;

        $base    = (int) floor($this->puntaje_maximo / $total);
        $residuo = $this->puntaje_maximo - ($base * $total);

        foreach ($preguntas as $i => $pregunta) {
            $puntaje = $base + ($i === $total - 1 ? $residuo : 0);
            $pregunta->update(['puntaje' => max(1, $puntaje)]);
        }
    }
```

En `app/Http/Controllers/PreguntaController.php`, quitar el método privado `redistribuirPuntajes()` (líneas 126-145, el bloque completo incluido el comentario de documentación) y reemplazar sus dos llamadas:

```php
// En store(), línea 51:
$this->redistribuirPuntajes($actividad);
// →
$actividad->redistribuirPuntajesPreguntas();

// En destroy(), línea 117:
$this->redistribuirPuntajes($actividad);
// →
$actividad->redistribuirPuntajesPreguntas();
```

- [x] **Step 4: Ejecutar el test y la suite completa**

Run: `php artisan test --filter=PreguntaControllerTest`
Expected: PASS (2/2)

Run: `php artisan test`
Expected: todos los tests existentes siguen pasando (137 antes de esta tarea).

- [x] **Step 5: Commit**

```bash
git add app/Models/Actividad.php app/Http/Controllers/PreguntaController.php tests/Feature/PreguntaControllerTest.php
git commit -m "refactor: mueve redistribucion de puntaje de preguntas al modelo Actividad"
```

---

### Task 2: Permitir corregir la opción correcta de una pregunta ya creada

**Files:**
- Modify: `app/Http/Controllers/OpcionController.php` (agregar método)
- Modify: `routes/web.php:104-105` (agregar ruta junto a `opciones.store`/`opciones.destroy`)
- Test: agregar casos a `tests/Feature/PreguntaControllerTest.php`

**Interfaces:**
- Consumes: nada de Task 1 directamente (independiente).
- Produces: ruta `opciones.marcarCorrecta` — la usa Task 4 (frontend).

- [x] **Step 1: Escribir el test que falla**

Agregar a `tests/Feature/PreguntaControllerTest.php`:

```php
    public function test_marcar_correcta_en_pregunta_de_opcion_unica_desmarca_las_demas(): void
    {
        $actividad = $this->crearInstructorConActividad(10);
        $pregunta  = Pregunta::factory()->create([
            'actividad_id' => $actividad->id, 'tipo' => 'opcion_multiple', 'seleccion_multiple' => false,
        ]);
        $a = $pregunta->opciones()->create(['texto' => 'A', 'es_correcta' => true, 'orden' => 1]);
        $b = $pregunta->opciones()->create(['texto' => 'B', 'es_correcta' => false, 'orden' => 2]);

        $this->put(route('opciones.marcarCorrecta', $b));

        $this->assertFalse($a->fresh()->es_correcta);
        $this->assertTrue($b->fresh()->es_correcta);
    }

    public function test_marcar_correcta_en_pregunta_de_seleccion_multiple_solo_alterna_esa_opcion(): void
    {
        $actividad = $this->crearInstructorConActividad(10);
        $pregunta  = Pregunta::factory()->create([
            'actividad_id' => $actividad->id, 'tipo' => 'opcion_multiple', 'seleccion_multiple' => true,
        ]);
        $a = $pregunta->opciones()->create(['texto' => 'A', 'es_correcta' => true, 'orden' => 1]);
        $b = $pregunta->opciones()->create(['texto' => 'B', 'es_correcta' => false, 'orden' => 2]);

        $this->put(route('opciones.marcarCorrecta', $b));

        $this->assertTrue($a->fresh()->es_correcta);
        $this->assertTrue($b->fresh()->es_correcta);

        $this->put(route('opciones.marcarCorrecta', $b));
        $this->assertFalse($b->fresh()->es_correcta);
    }

    public function test_estudiante_no_puede_marcar_correcta(): void
    {
        $actividad = $this->crearInstructorConActividad(10);
        $pregunta  = Pregunta::factory()->create(['actividad_id' => $actividad->id, 'tipo' => 'opcion_multiple']);
        $opcion    = $pregunta->opciones()->create(['texto' => 'A', 'es_correcta' => false, 'orden' => 1]);

        $rolEstudiante = Rol::firstOrCreate(['nombre' => 'Estudiante'], ['descripcion' => 'Estudiante role']);
        $estudiante = User::factory()->create(['estado' => 'activo']);
        $estudiante->roles()->attach($rolEstudiante);

        $response = $this->actingAs($estudiante)->put(route('opciones.marcarCorrecta', $opcion));

        $response->assertForbidden();
        $this->assertFalse($opcion->fresh()->es_correcta);
    }
```

- [x] **Step 2: Ejecutar el test para verificar que falla**

Run: `php artisan test --filter=PreguntaControllerTest`
Expected: FAIL con "Route [opciones.marcarCorrecta] not defined" en los 3 tests nuevos.

- [x] **Step 3: Agregar el método al controlador**

En `app/Http/Controllers/OpcionController.php`, agregar antes del método `destroy()`:

```php
    public function marcarCorrecta(Opcion $opcion)
    {
        $pregunta = $opcion->pregunta;
        $this->authorize('update', $pregunta->actividad->leccion->modulo->curso);

        if ($pregunta->seleccion_multiple) {
            $opcion->update(['es_correcta' => !$opcion->es_correcta]);
        } else {
            $pregunta->opciones()->update(['es_correcta' => false]);
            $opcion->update(['es_correcta' => true]);
        }

        return redirect()
            ->route('actividades.edit', $pregunta->actividad)
            ->with('success', 'Respuesta correcta actualizada');
    }
```

- [x] **Step 4: Agregar la ruta**

En `routes/web.php`, dentro del grupo `instructor` de "Preguntas y Opciones" (junto a `opciones.destroy`, línea 105):

```php
        Route::put('/opciones/{opcion}/marcar-correcta', [OpcionController::class, 'marcarCorrecta'])->name('opciones.marcarCorrecta');
```

- [x] **Step 5: Ejecutar el test para verificar que pasa**

Run: `php artisan test --filter=PreguntaControllerTest`
Expected: PASS (5/5 — los 2 de Task 1 + los 3 nuevos)

- [x] **Step 6: Commit**

```bash
git add app/Http/Controllers/OpcionController.php routes/web.php tests/Feature/PreguntaControllerTest.php
git commit -m "feat: permite corregir la opcion correcta de una pregunta ya creada"
```

---

### Task 3: Controlador de importación desde JSON

**Files:**
- Create: `app/Http/Controllers/ImportacionCuestionarioController.php`
- Modify: `routes/web.php` (agregar ruta `preguntas.importar`)
- Test: `tests/Feature/ImportarCuestionarioGoogleFormsTest.php`

**Interfaces:**
- Consumes: `Actividad::redistribuirPuntajesPreguntas()` de Task 1.
- Produces: ruta `preguntas.importar` — la usa Task 4 (frontend).

- [x] **Step 1: Escribir el test que falla**

Crear `tests/Feature/ImportarCuestionarioGoogleFormsTest.php`:

```php
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
}
```

- [x] **Step 2: Ejecutar el test para verificar que falla**

Run: `php artisan test --filter=ImportarCuestionarioGoogleFormsTest`
Expected: FAIL con "Route [preguntas.importar] not defined" en todos los tests.

- [x] **Step 3: Crear el controlador**

Crear `app/Http/Controllers/ImportacionCuestionarioController.php`:

```php
<?php

namespace App\Http\Controllers;

use App\Models\Actividad;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class ImportacionCuestionarioController extends Controller
{
    public function store(Request $request, Actividad $actividad)
    {
        $this->authorize('update', $actividad->leccion->modulo->curso);
        abort_unless($actividad->tipo === 'cuestionario', 403);

        $request->validate([
            'archivo' => 'required|file|mimes:json|max:2048',
        ]);

        $contenido = json_decode($request->file('archivo')->get(), true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return back()->withErrors(['archivo' => 'El archivo no tiene un formato JSON válido.']);
        }

        $validator = Validator::make((array) $contenido, [
            'version'                         => 'required|integer|in:1',
            'preguntas'                       => 'required|array|min:1',
            'preguntas.*.texto'               => 'required|string',
            'preguntas.*.tipo'                => 'required|in:opcion_multiple,respuesta_corta',
            'preguntas.*.multiple'            => 'nullable|boolean',
            'preguntas.*.opciones'            => 'required_if:preguntas.*.tipo,opcion_multiple|array|min:2',
            'preguntas.*.opciones.*.texto'    => 'required|string',
            'preguntas.*.opciones.*.correcta' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return back()->withErrors(['archivo' => 'El archivo no tiene la estructura esperada: ' . $validator->errors()->first()]);
        }

        $preguntasJson = $contenido['preguntas'];
        $pendientes    = 0;

        DB::transaction(function () use ($actividad, $preguntasJson, &$pendientes) {
            $orden = $actividad->preguntas()->max('orden') + 1;

            foreach ($preguntasJson as $item) {
                $esOpcionMultiple = $item['tipo'] === 'opcion_multiple';
                $opciones         = $item['opciones'] ?? [];
                $esVerdaderoFalso = $esOpcionMultiple && $this->esVerdaderoFalso($opciones);

                $pregunta = $actividad->preguntas()->create([
                    'pregunta_texto'     => $item['texto'],
                    'tipo'               => $esVerdaderoFalso ? 'verdadero_falso' : $item['tipo'],
                    'seleccion_multiple' => $esOpcionMultiple && !$esVerdaderoFalso && !empty($item['multiple']),
                    'puntaje'            => 1,
                    'orden'              => $orden++,
                ]);

                if ($esOpcionMultiple) {
                    $tieneCorrecta = false;
                    foreach ($opciones as $i => $opcionItem) {
                        $correcta      = (bool) ($opcionItem['correcta'] ?? false);
                        $tieneCorrecta = $tieneCorrecta || $correcta;
                        $pregunta->opciones()->create([
                            'texto'       => $opcionItem['texto'],
                            'es_correcta' => $correcta,
                            'orden'       => $i + 1,
                        ]);
                    }
                    if (!$tieneCorrecta) $pendientes++;
                }
            }

            $actividad->redistribuirPuntajesPreguntas();
        });

        $mensaje = count($preguntasJson) . ' preguntas importadas.';
        if ($pendientes > 0) {
            $mensaje .= " {$pendientes} necesita" . ($pendientes === 1 ? '' : 'n') . ' que marques la respuesta correcta.';
        }

        return redirect()
            ->route('actividades.edit', $actividad)
            ->with('success', $mensaje);
    }

    private function esVerdaderoFalso(array $opciones): bool
    {
        if (count($opciones) !== 2) return false;

        $normalizados = collect($opciones)
            ->map(fn($o) => Str::of($o['texto'] ?? '')->lower()->ascii()->trim()->toString())
            ->sort()
            ->values()
            ->toArray();

        return $normalizados === ['falso', 'verdadero'] || $normalizados === ['false', 'true'];
    }
}
```

- [x] **Step 4: Agregar la ruta**

En `routes/web.php`, dentro del grupo `instructor` de "Preguntas y Opciones" (junto a `preguntas.store`, línea 100), y agregar el `use` del controlador junto a los demás `use App\Http\Controllers\...` al inicio del archivo:

```php
        Route::post('/actividades/{actividad}/preguntas/importar', [ImportacionCuestionarioController::class, 'store'])->name('preguntas.importar');
```

- [x] **Step 5: Ejecutar el test para verificar que pasa**

Run: `php artisan test --filter=ImportarCuestionarioGoogleFormsTest`
Expected: PASS (6/6)

- [x] **Step 6: Ejecutar la suite completa**

Run: `php artisan test`
Expected: todos los tests pasan (los 137 anteriores + 2 de Task 1 + 3 de Task 2 + 6 de esta tarea).

- [x] **Step 7: Commit**

```bash
git add app/Http/Controllers/ImportacionCuestionarioController.php routes/web.php tests/Feature/ImportarCuestionarioGoogleFormsTest.php
git commit -m "feat: importa preguntas de cuestionario desde un JSON de Google Forms"
```

---

### Task 4: Frontend — subir el JSON y corregir respuestas pendientes

**Files:**
- Modify: `resources/views/actividades/edit.blade.php`

**Interfaces:**
- Consumes: rutas `preguntas.importar` (Task 3) y `opciones.marcarCorrecta` (Task 2).
- Produces: nada (última pieza de UI).

- [x] **Step 1: Agregar la tarjeta de importación**

En `resources/views/actividades/edit.blade.php`, justo después del `</div>` que cierra la tarjeta "Agregar Pregunta" (después de la línea `</div>` que sigue a `</form>` del bloque que empieza en `{{-- Agregar pregunta --}}`, antes de `{{-- Lista de preguntas --}}`):

```blade
            {{-- Importar desde Google Forms --}}
            <div class="card p-6 mb-4">
                <h3 class="text-lg font-bold text-gray-900 mb-2">Importar desde Google Forms</h3>
                <p class="text-xs text-gray-500 mb-3">
                    Exporta tu Google Form a JSON con la plantilla de Apps Script en
                    <code class="bg-gray-100 px-1 rounded">docs/apps-script/</code> y sube el archivo aquí.
                    Las preguntas se agregan al final de las que ya existen.
                </p>
                <form action="{{ route('preguntas.importar', $actividad) }}" method="POST" enctype="multipart/form-data"
                      class="flex gap-2 items-start">
                    @csrf
                    <input type="file" name="archivo" accept=".json"
                           class="flex-1 text-sm text-gray-600 file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100 cursor-pointer"
                           required>
                    <button type="submit" class="btn bg-blue-600 text-white hover:bg-blue-700 shrink-0">Importar</button>
                </form>
                @error('archivo')<p class="form-error mt-1">{{ $message }}</p>@enderror
            </div>
```

- [x] **Step 2: Badge "Falta marcar la correcta" en el encabezado de cada pregunta**

En el mismo archivo, dentro del `@forelse($preguntas as $pregunta)`, justo después de la línea que muestra el badge `seleccion múltiple` (después del `@if($pregunta->seleccion_multiple) ... @endif`, antes de `<p class="font-medium text-gray-900 mt-1" ...>`):

```blade
                            @if($pregunta->tipo !== 'respuesta_corta' && !$pregunta->opciones->contains('es_correcta', true))
                                <span class="ml-2 badge text-[10px] bg-yellow-100 text-yellow-700">Falta marcar la correcta</span>
                            @endif
```

- [x] **Step 3: Botón "Marcar correcta" en cada opción de tipo `opcion_multiple`**

En el bloque `@elseif($pregunta->tipo === 'opcion_multiple')`, reemplazar el `<div class="flex items-center gap-2">...</div>` que hoy solo muestra ✓/○ estático:

```blade
                                <div class="flex items-center gap-2">
                                    <span class="text-gray-300">○</span>
                                    <span class="text-sm text-gray-800">{{ $opcion->texto }}</span>
                                </div>
```

por:

```blade
                                <div class="flex items-center gap-2">
                                    <form action="{{ route('opciones.marcarCorrecta', $opcion) }}" method="POST">
                                        @csrf @method('PUT')
                                        <button type="submit"
                                                class="text-lg leading-none {{ $opcion->es_correcta ? 'text-green-600 font-bold' : 'text-gray-300 hover:text-gray-400' }}"
                                                title="{{ $opcion->es_correcta ? 'Correcta' : 'Marcar como correcta' }}">
                                            {{ $opcion->es_correcta ? '✓' : '○' }}
                                        </button>
                                    </form>
                                    <span class="text-sm text-gray-800">{{ $opcion->texto }}</span>
                                </div>
```

(Nota: revisar el contenido exacto actual del bloque en el archivo antes de reemplazar — el `@if($opcion->es_correcta) ✓ @else ○ @endif` puede estar en líneas separadas; el resultado debe verse igual que arriba.)

- [x] **Step 4: Verificación manual**

No amerita test PHPUnit dedicado (es solo Blade/HTML, ya cubierto indirectamente porque las rutas que usa ya tienen tests en Tasks 2 y 3). Ejecutar la suite completa para confirmar que las vistas siguen compilando sin error:

Run: `php artisan test`
Expected: todos los tests pasan (mismo conteo que al final de Task 3).

Con el servidor local corriendo (`php artisan serve`), como instructor:
1. Entrar a editar un cuestionario existente.
2. Confirmar que aparece la tarjeta "Importar desde Google Forms".
3. Subir un `.json` de prueba con una pregunta sin `correcta` marcada → confirmar que aparece el badge "Falta marcar la correcta" y que el botón ○ de la opción, al hacer clic, la marca ✓ y desmarca las demás.

- [x] **Step 5: Commit**

```bash
git add resources/views/actividades/edit.blade.php
git commit -m "feat: UI para importar cuestionario desde Google Forms y corregir respuestas pendientes"
```

---

### Task 5: Plantilla de Apps Script y documentación

**Files:**
- Create: `docs/apps-script/importar-cuestionario.gs`
- Create: `docs/apps-script/README.md`

**Interfaces:**
- Consumes: el esquema JSON validado por `ImportacionCuestionarioController` (Task 3) — debe generar exactamente ese formato.
- Produces: nada (documentación, no se ejecuta en el LMS).

- [x] **Step 1: Crear la plantilla del script**

Crear `docs/apps-script/importar-cuestionario.gs`:

```javascript
/**
 * Exporta las preguntas del Google Form activo al formato JSON que
 * espera el importador de cuestionarios del LMS DyL Quality.
 * Ver README.md en esta misma carpeta para instrucciones de uso.
 */
function exportarCuestionario() {
  const form = FormApp.getActiveForm();
  const items = form.getItems();
  const preguntas = [];
  const omitidas = [];

  items.forEach(function (item) {
    const tipo = item.getType();

    if (tipo === FormApp.ItemType.MULTIPLE_CHOICE || tipo === FormApp.ItemType.CHECKBOX || tipo === FormApp.ItemType.LIST) {
      preguntas.push(mapearOpcionMultiple(item, tipo));
    } else if (tipo === FormApp.ItemType.TEXT || tipo === FormApp.ItemType.PARAGRAPH_TEXT) {
      preguntas.push({ texto: item.getTitle(), tipo: 'respuesta_corta' });
    } else {
      omitidas.push(item.getTitle() + ' (' + tipo + ')');
    }
  });

  const resultado = { version: 1, preguntas: preguntas };
  const nombreArchivo = 'cuestionario-' + form.getId() + '.json';
  const archivo = DriveApp.createFile(nombreArchivo, JSON.stringify(resultado, null, 2), MimeType.PLAIN_TEXT);

  Logger.log('Archivo generado: ' + archivo.getUrl());
  Logger.log(preguntas.length + ' preguntas exportadas.');
  if (omitidas.length > 0) {
    Logger.log('Omitidas (tipo no soportado por el LMS): ' + omitidas.join(', '));
  }
}

function mapearOpcionMultiple(item, tipo) {
  const wrapped = tipo === FormApp.ItemType.MULTIPLE_CHOICE
    ? item.asMultipleChoiceItem()
    : (tipo === FormApp.ItemType.CHECKBOX ? item.asCheckboxItem() : item.asListItem());

  const opciones = wrapped.getChoices().map(function (choice) {
    var correcta = null;
    try {
      correcta = choice.isCorrectAnswer();
    } catch (e) {
      correcta = null; // el Form no tiene activado el modo "Cuestionario"
    }
    return { texto: choice.getValue(), correcta: correcta };
  });

  return {
    texto: item.getTitle(),
    tipo: 'opcion_multiple',
    multiple: tipo === FormApp.ItemType.CHECKBOX,
    opciones: opciones
  };
}
```

- [x] **Step 2: Crear el README de instrucciones**

Crear `docs/apps-script/README.md`:

```markdown
# Importar cuestionarios desde Google Forms

`importar-cuestionario.gs` exporta las preguntas de un Google Form al
formato JSON que entiende el LMS (botón "Importar desde Google Forms"
al editar un cuestionario).

## Uso

1. Abre tu Google Form.
2. Extensiones → Apps Script.
3. Reemplaza el contenido de `Code.gs` por el de `importar-cuestionario.gs`.
4. En la barra de herramientas, selecciona la función `exportarCuestionario`
   y ejecútala.
5. La primera vez te pedirá autorizar permisos (acceso al Form y a Drive) —
   acéptalos.
6. Revisa `Ver → Registros de ejecución`: ahí aparece el link al archivo
   generado en tu Drive y cuántas preguntas se exportaron u omitieron.
7. Abre ese archivo en Drive, descárgalo y súbelo en el LMS.

## Qué se importa y qué no

- Opción múltiple, casillas de verificación y desplegable → preguntas de
  opción múltiple en el LMS (si la pregunta tiene exactamente dos opciones
  "Verdadero"/"Falso", el LMS la reconoce como Verdadero/Falso automáticamente).
- Respuesta corta y párrafo → preguntas de respuesta corta (se califican a
  mano en el LMS, igual que si se crearan ahí directamente).
- Si el Form tiene activado **Configuración → Convertir en cuestionario**
  con respuestas correctas marcadas, esa información se exporta y el LMS
  la usa directamente. Si no, las preguntas se importan igual, pero el LMS
  las marca como "pendientes de marcar la respuesta correcta" para que las
  corrijas ahí mismo.
- Escala lineal, cuadrícula, fecha, hora y carga de archivos **no se
  importan** — quedan listadas en el registro de ejecución del script
  (`Ver → Registros de ejecución`), el LMS nunca las recibe.
```

- [x] **Step 3: Commit**

```bash
git add docs/apps-script/importar-cuestionario.gs docs/apps-script/README.md
git commit -m "docs: agrega plantilla de Apps Script para importar cuestionarios desde Google Forms"
```
