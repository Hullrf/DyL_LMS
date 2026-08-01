# Archivo de ejemplo descargable para importar cuestionarios Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [x]`) syntax for tracking.

**Goal:** El instructor puede descargar un JSON de ejemplo (con una pregunta de cada tipo soportado) desde la pantalla de edición de un cuestionario, para saber exactamente qué estructura espera el importador antes de subir su propio archivo.

**Architecture:** Se agrega un método `ejemplo()` a `ImportacionCuestionarioController` (ya existente, contiene `store()`) que devuelve un array PHP estático como descarga JSON vía `response()->streamDownload()`. Se expone en una nueva ruta `GET /preguntas/ejemplo` con solo middleware `auth` (mismo criterio que `rubrica.ejemplo`, no depende de una actividad concreta). En la vista, se agrega un enlace de descarga junto al formulario de importación existente.

**Tech Stack:** Laravel 12 / PHP 8.2, Blade, PHPUnit (Feature tests).

## Global Constraints

- El JSON de ejemplo debe cubrir los 4 casos que interpreta `ImportacionCuestionarioController::store()`: opción múltiple con respuesta única conocida, selección múltiple (varias correctas), verdadero/falso auto-detectado por texto de opciones, y respuesta corta.
- Nombre del archivo descargado: `ejemplo-cuestionario.json`.
- La ruta usa middleware `auth` únicamente (no `instructor`), igual que `rubrica.ejemplo` en `routes/web.php:186-187`.

---

### Task 1: Ruta y controlador para descargar el JSON de ejemplo

**Files:**
- Modify: `app/Http/Controllers/ImportacionCuestionarioController.php:88` (agregar método `ejemplo()` después de `store()`, antes de `esVerdaderoFalso()`)
- Modify: `routes/web.php:187` (agregar ruta después de `rubrica.ejemplo`)
- Test: `tests/Feature/ImportarCuestionarioGoogleFormsTest.php` (agregar caso nuevo)

**Interfaces:**
- Consumes: nada de tareas anteriores.
- Produces: ruta con nombre `preguntas.ejemplo` (GET, sin parámetros) — la usa Task 2 (frontend).

- [x] **Step 1: Escribir el test que falla**

En `tests/Feature/ImportarCuestionarioGoogleFormsTest.php`, agregar el import de `Validator` junto a los `use` existentes (después de `use Illuminate\Http\UploadedFile;`):

```php
use Illuminate\Support\Facades\Validator;
```

Agregar este método al final de la clase, antes del `}` de cierre:

```php
    public function test_descarga_json_de_ejemplo_pasa_la_validacion_del_importador(): void
    {
        $rol = Rol::firstOrCreate(['nombre' => 'Instructor'], ['descripcion' => 'Instructor role']);
        $instructor = User::factory()->create(['estado' => 'activo']);
        $instructor->roles()->attach($rol);

        $response = $this->actingAs($instructor)->get(route('preguntas.ejemplo'));

        $response->assertOk();
        $this->assertStringContainsString(
            'ejemplo-cuestionario.json',
            $response->headers->get('Content-Disposition')
        );

        $contenido = json_decode($response->streamedContent(), true);

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

        $this->assertFalse($validator->fails(), $validator->errors()->first());
        $this->assertGreaterThanOrEqual(4, count($contenido['preguntas']));
    }
```

- [x] **Step 2: Ejecutar el test para verificar que falla**

Run: `php artisan test --filter=test_descarga_json_de_ejemplo_pasa_la_validacion_del_importador`
Expected: FAIL con "Route [preguntas.ejemplo] not defined".

- [x] **Step 3: Agregar el método al controlador**

En `app/Http/Controllers/ImportacionCuestionarioController.php`, insertar después del `}` que cierra `store()` (línea 88) y antes de `private function esVerdaderoFalso`:

```php

    public function ejemplo()
    {
        $contenido = [
            'version' => 1,
            'preguntas' => [
                [
                    'texto' => '¿Cuál es la capital de Francia?',
                    'tipo' => 'opcion_multiple',
                    'multiple' => false,
                    'opciones' => [
                        ['texto' => 'Madrid', 'correcta' => false],
                        ['texto' => 'París', 'correcta' => true],
                        ['texto' => 'Roma', 'correcta' => false],
                    ],
                ],
                [
                    'texto' => 'Selecciona los lenguajes de programación (puede haber varias correctas)',
                    'tipo' => 'opcion_multiple',
                    'multiple' => true,
                    'opciones' => [
                        ['texto' => 'PHP', 'correcta' => true],
                        ['texto' => 'Photoshop', 'correcta' => false],
                        ['texto' => 'JavaScript', 'correcta' => true],
                    ],
                ],
                [
                    'texto' => 'El sol es una estrella',
                    'tipo' => 'opcion_multiple',
                    'multiple' => false,
                    'opciones' => [
                        ['texto' => 'Verdadero', 'correcta' => true],
                        ['texto' => 'Falso', 'correcta' => false],
                    ],
                ],
                [
                    'texto' => 'Explica brevemente qué es la fotosíntesis',
                    'tipo' => 'respuesta_corta',
                ],
            ],
        ];

        return response()->streamDownload(
            fn () => print(json_encode($contenido, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)),
            'ejemplo-cuestionario.json',
            ['Content-Type' => 'application/json']
        );
    }
```

- [x] **Step 4: Agregar la ruta**

En `routes/web.php`, después de la línea 187 (`->name('rubrica.ejemplo');`), agregar:

```php
Route::middleware('auth')->get('/preguntas/ejemplo', [ImportacionCuestionarioController::class, 'ejemplo'])
    ->name('preguntas.ejemplo');
```

- [x] **Step 5: Ejecutar el test para verificar que pasa**

Run: `php artisan test --filter=ImportarCuestionarioGoogleFormsTest`
Expected: PASS (7/7 — los 6 existentes + el nuevo).

- [x] **Step 6: Ejecutar la suite completa**

Run: `php artisan test`
Expected: todos los tests pasan, ninguna regresión en el resto de la suite.

- [x] **Step 7: Commit**

```bash
git add app/Http/Controllers/ImportacionCuestionarioController.php routes/web.php tests/Feature/ImportarCuestionarioGoogleFormsTest.php
git commit -m "feat: agrega descarga de JSON de ejemplo para importar cuestionarios"
```

---

### Task 2: Enlace de descarga en la vista de importación

**Files:**
- Modify: `resources/views/actividades/edit.blade.php:432-449` (tarjeta "Importar desde Google Forms")

**Interfaces:**
- Consumes: ruta `preguntas.ejemplo` (Task 1).
- Produces: nada (última pieza de UI).

- [x] **Step 1: Agregar el enlace de descarga**

En `resources/views/actividades/edit.blade.php`, dentro de la tarjeta que empieza en la línea 432 (`{{-- Importar desde Google Forms --}}`), insertar el enlace justo antes del `<form action="{{ route('preguntas.importar', $actividad) }}" ...>` (línea 440):

Contenido actual de la tarjeta (líneas 432-449):

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

Reemplazar por (agrega el `<a>` de descarga entre el `<p>` y el `<form>`):

```blade
            {{-- Importar desde Google Forms --}}
            <div class="card p-6 mb-4">
                <h3 class="text-lg font-bold text-gray-900 mb-2">Importar desde Google Forms</h3>
                <p class="text-xs text-gray-500 mb-3">
                    Exporta tu Google Form a JSON con la plantilla de Apps Script en
                    <code class="bg-gray-100 px-1 rounded">docs/apps-script/</code> y sube el archivo aquí.
                    Las preguntas se agregan al final de las que ya existen.
                </p>
                <a href="{{ route('preguntas.ejemplo') }}" target="_blank"
                   class="btn-outline w-full mb-3 flex items-center justify-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                    </svg>
                    Descargar archivo de ejemplo
                </a>
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

- [x] **Step 2: Ejecutar la suite completa para confirmar que las vistas siguen compilando**

Run: `php artisan test`
Expected: mismo conteo de tests que al final de Task 1, todos en verde (esto no agrega tests nuevos, solo confirma que el Blade no rompe nada).

- [x] **Step 3: Verificación manual**

Con el servidor local corriendo (`php artisan serve`), como instructor:
1. Entrar a editar un cuestionario existente.
2. En la tarjeta "Importar desde Google Forms", confirmar que aparece el botón "Descargar archivo de ejemplo" antes del selector de archivo.
3. Hacer clic y confirmar que descarga `ejemplo-cuestionario.json` con las 4 preguntas de ejemplo.
4. Subir ese mismo archivo descargado usando el formulario de importación de la misma tarjeta y confirmar que las 4 preguntas se importan sin error de validación.

- [x] **Step 4: Commit**

```bash
git add resources/views/actividades/edit.blade.php
git commit -m "feat: agrega enlace de descarga del JSON de ejemplo en la vista de importacion"
```
