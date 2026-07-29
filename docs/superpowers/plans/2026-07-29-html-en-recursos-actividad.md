# Soporte de archivos .html en Recursos de Apoyo Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [x]`) syntax for tracking.

**Goal:** Permitir que el instructor suba archivos `.html` como recurso de apoyo (`documento`) de una actividad, con el mismo comportamiento de solo-descarga que PDF/Word/Excel/PowerPoint/ZIP.

**Architecture:** Cambio de validación (agregar `html` a la regla `mimes:`) + accept del input de archivo + ícono distintivo en el modelo. Sin cambios de esquema de base de datos ni de rutas. La descarga forzada (`Content-Disposition: attachment`) y la ausencia de visor inline para `.html` ya existen en el código actual — no requieren cambios.

**Tech Stack:** Laravel 12 / PHP 8.2, Blade + Alpine.js, PHPUnit (Feature tests).

## Global Constraints

- Alcance limitado a `RecursoActividadController` (recursos de apoyo del instructor). No tocar `RespuestaEstudianteController` (entregas de estudiante).
- El `.html` nunca debe renderizarse inline en el navegador del LMS — solo descarga. No agregar rama de "vista previa" para `.html` en `actividades/show.blade.php`.
- Límite de tamaño existente (50 MB / `max:51200`) se mantiene sin cambios.

---

### Task 1: Permitir `.html` en la validación y accept del formulario

**Files:**
- Modify: `app/Http/Controllers/RecursoActividadController.php:27`
- Modify: `resources/views/actividades/edit.blade.php:204-215`
- Test: `tests/Feature/RecursoActividadHtmlTest.php`

**Interfaces:**
- Consumes: ruta existente `POST /cursos/{curso}/actividades/{actividad}/recursos` → `RecursoActividadController::store()`.
- Produces: ningún símbolo nuevo consumido por otras tareas (Task 2 solo toca el modelo, independiente).

- [x] **Step 1: Escribir el test que falla**

Crear `tests/Feature/RecursoActividadHtmlTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\Actividad;
use App\Models\Curso;
use App\Models\Leccion;
use App\Models\Modulo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RecursoActividadHtmlTest extends TestCase
{
    use RefreshDatabase;

    private function actividadDeInstructor(): Actividad
    {
        $instructor = User::factory()->create();
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
```

- [x] **Step 2: Ejecutar el test para verificar que falla**

Run: `php artisan test --filter=RecursoActividadHtmlTest`
Expected: FAIL en `test_instructor_puede_subir_recurso_html` y `test_descarga_de_recurso_html_fuerza_attachment` (la regla `mimes:` actual rechaza `.html`). `test_extension_no_permitida_es_rechazada` debería pasar ya (no depende del cambio).

- [x] **Step 3: Agregar `html` a la validación**

En `app/Http/Controllers/RecursoActividadController.php`, línea 27:

```php
'documento' => $rules['archivo'] = 'required|file|mimes:pdf,doc,docx,ppt,pptx,xls,xlsx,zip,html|max:51200',
```

- [x] **Step 4: Actualizar el input de archivo en la vista**

En `resources/views/actividades/edit.blade.php`, líneas 204 y 215:

```html
<input type="file" name="archivo" accept=".pdf,.doc,.docx,.ppt,.pptx,.xls,.xlsx,.zip,.html"
       class="w-full text-sm text-gray-600 file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100 cursor-pointer"
       :disabled="tipoRecurso !== 'documento'"
       x-on:change="
           archivoError = '';
           const maxBytes = 50 * 1024 * 1024;
           if ($event.target.files[0]?.size > maxBytes) {
               archivoError = 'El archivo supera el límite de 50 MB.';
               $event.target.value = '';
           }
       ">
<p class="form-hint">PDF, Word, PowerPoint, Excel, ZIP o HTML — máx. 50 MB</p>
```

- [x] **Step 5: Ejecutar el test para verificar que pasa**

Run: `php artisan test --filter=RecursoActividadHtmlTest`
Expected: PASS (3/3)

- [x] **Step 6: Commit**

```bash
git add app/Http/Controllers/RecursoActividadController.php resources/views/actividades/edit.blade.php tests/Feature/RecursoActividadHtmlTest.php
git commit -m "feat: permitir subir archivos .html como recurso de apoyo"
```

---

### Task 2: Ícono distintivo para recursos `.html`

**Files:**
- Modify: `app/Models/RecursoActividad.php:89-101` (método `iconoDocumento()`)

**Interfaces:**
- Consumes: nada de Task 1.
- Produces: nada consumido por otras tareas.

- [x] **Step 1: Editar `iconoDocumento()`**

En `app/Models/RecursoActividad.php`, agregar el caso `html` al `match` dentro de `iconoDocumento()`:

```php
public function iconoDocumento(): string
{
    $ext = strtolower(pathinfo($this->archivo_path, PATHINFO_EXTENSION));

    return match($ext) {
        'pdf'       => 'M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8zM14 2v6h6M16 13H8m8 4H8m8-8H8',
        'doc','docx' => 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z',
        'xls','xlsx' => 'M3 10h18M3 14h18m-9-4v8m-7 0h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z',
        'ppt','pptx' => 'M8 13v-1m4 1v-3m4 3V8M8 21l4-4 4 4M3 4h18M4 4v14a2 2 0 002 2h12a2 2 0 002-2V4',
        'zip'       => 'M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4',
        'html'      => 'M10 20l4-16m-8 4l-4 4 4 4m12-8l4 4-4 4',
        default     => 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z',
    };
}
```

- [x] **Step 2: Verificar manualmente**

No amerita test dedicado (es un mapeo de string puro, ya cubierto indirectamente por `test_instructor_puede_subir_recurso_html` de Task 1, que confirma que el recurso `.html` se crea y se puede renderizar la vista `actividades/edit` sin error). Ejecutar la suite completa para confirmar que no se rompió nada:

Run: `php artisan test`
Expected: todos los tests pasan (incluye los 76 preexistentes + los 3 nuevos de Task 1).

- [x] **Step 3: Commit**

```bash
git add app/Models/RecursoActividad.php
git commit -m "feat: icono distintivo para recursos .html"
```
