# Actividades sin nota Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Agregar 4 tipos de actividad sin calificación (ejercicio, lectura, encuesta, reflexion) que son de consulta únicamente — muestran descripción y recursos pero sin formulario de respuesta ni nota.

**Architecture:** Se hace `puntaje_maximo` nullable en DB; se añade constante `TIPOS_SIN_NOTA` y método `tieneCalificacion()` al modelo `Actividad`; la validación del controlador se vuelve condicional; las vistas `create`/`edit`/`show` adaptan su UI según el tipo.

**Tech Stack:** Laravel 12, PHP 8.2, MySQL 8.0, Alpine.js, Blade, PHPUnit

---

## Archivos afectados

| Archivo | Acción |
|---------|--------|
| `database/migrations/YYYY_MM_DD_make_puntaje_nullable.php` | Crear |
| `app/Models/Actividad.php` | Modificar — añadir constante + método |
| `database/factories/ActividadFactory.php` | Modificar — añadir estado `sinNota()` |
| `app/Http/Controllers/ActividadController.php` | Modificar — validación condicional |
| `resources/views/actividades/create.blade.php` | Modificar — 4 tipos + Alpine.js |
| `resources/views/actividades/edit.blade.php` | Modificar — ocultar puntaje, nueva columna derecha |
| `resources/views/actividades/show.blade.php` | Modificar — sin nota: sin form, badge, aviso |
| `tests/Feature/ActividadSinNotaTest.php` | Crear — 4 tests |

---

## Task 1: Escribir los tests que deben fallar

**Files:**
- Create: `tests/Feature/ActividadSinNotaTest.php`

- [ ] **Step 1: Crear el archivo de tests**

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
use Tests\TestCase;

class ActividadSinNotaTest extends TestCase
{
    use RefreshDatabase;

    private function crearInstructor(): User
    {
        $rol  = Rol::factory()->instructor()->create();
        $user = User::factory()->create(['estado' => 'activo']);
        $user->roles()->attach($rol);
        return $user;
    }

    private function crearEscenario(User $instructor): array
    {
        $curso   = Curso::factory()->publicado()->create(['created_by' => $instructor->id]);
        $modulo  = Modulo::factory()->create(['curso_id' => $curso->id]);
        $leccion = Leccion::factory()->create(['modulo_id' => $modulo->id]);
        return [$curso, $modulo, $leccion];
    }

    /** Test 1: tipo lectura sin puntaje_maximo → se guarda con NULL */
    public function test_instructor_puede_crear_actividad_lectura_sin_puntaje(): void
    {
        $instructor = $this->crearInstructor();
        [, , $leccion] = $this->crearEscenario($instructor);

        $response = $this->actingAs($instructor)->post(route('actividades.store', $leccion), [
            'titulo'        => 'Lectura ISO 9001',
            'tipo'          => 'lectura',
            'descripcion'   => 'Lee el capítulo 4.',
            'es_obligatoria' => '0',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('actividades', [
            'leccion_id'    => $leccion->id,
            'tipo'          => 'lectura',
            'puntaje_maximo' => null,
        ]);
    }

    /** Test 2: tipo cuestionario sin puntaje_maximo → error de validación */
    public function test_cuestionario_sin_puntaje_falla_validacion(): void
    {
        $instructor = $this->crearInstructor();
        [, , $leccion] = $this->crearEscenario($instructor);

        $response = $this->actingAs($instructor)->post(route('actividades.store', $leccion), [
            'titulo'        => 'Cuestionario sin nota',
            'tipo'          => 'cuestionario',
            'descripcion'   => 'Sin puntaje.',
            'es_obligatoria' => '1',
        ]);

        $response->assertSessionHasErrors('puntaje_maximo');
        $this->assertDatabaseMissing('actividades', ['titulo' => 'Cuestionario sin nota']);
    }

    /** Test 3: tieneCalificacion() devuelve false para tipos sin nota */
    public function test_metodo_tiene_calificacion_retorna_false_para_tipos_sin_nota(): void
    {
        foreach (['ejercicio', 'lectura', 'encuesta', 'reflexion'] as $tipo) {
            $actividad = new Actividad(['tipo' => $tipo]);
            $this->assertFalse(
                $actividad->tieneCalificacion(),
                "tieneCalificacion() debe ser false para tipo={$tipo}"
            );
        }

        foreach (['cuestionario', 'ensayo', 'tarea', 'practica'] as $tipo) {
            $actividad = new Actividad(['tipo' => $tipo]);
            $this->assertTrue(
                $actividad->tieneCalificacion(),
                "tieneCalificacion() debe ser true para tipo={$tipo}"
            );
        }
    }

    /** Test 4: vista show de actividad lectura no contiene formulario de respuesta */
    public function test_show_actividad_lectura_no_tiene_formulario_de_respuesta(): void
    {
        $instructor = $this->crearInstructor();
        [, , $leccion] = $this->crearEscenario($instructor);

        $actividad = Actividad::factory()->create([
            'leccion_id'    => $leccion->id,
            'tipo'          => 'lectura',
            'puntaje_maximo' => null,
        ]);

        $rolEstudiante = Rol::factory()->estudiante()->create();
        $estudiante    = User::factory()->create(['estado' => 'activo']);
        $estudiante->roles()->attach($rolEstudiante);
        $leccion->modulo->curso->inscripciones()->create([
            'user_id'     => $estudiante->id,
            'estado'      => 'en_progreso',
            'fecha_inicio' => now(),
        ]);

        $response = $this->actingAs($estudiante)->get(route('actividades.show', $actividad));

        $response->assertStatus(200);
        $response->assertDontSee('form-respuesta', false);
        $response->assertSee('Sin calificación');
    }
}
```

- [ ] **Step 2: Ejecutar los tests para confirmar que fallan**

```bash
cd "C:/xampp/htdocs/LMS DyL/lms-dyl-quality"
php artisan test tests/Feature/ActividadSinNotaTest.php
```

Resultado esperado: los 4 tests fallan (errores de DB o método no encontrado).

---

## Task 2: Migración — `puntaje_maximo` nullable

**Files:**
- Create: `database/migrations/2026_05_15_000001_make_puntaje_maximo_nullable_in_actividades.php`

- [ ] **Step 1: Crear la migración**

```bash
cd "C:/xampp/htdocs/LMS DyL/lms-dyl-quality"
php artisan make:migration make_puntaje_maximo_nullable_in_actividades
```

- [ ] **Step 2: Editar el archivo generado** (está en `database/migrations/`)

Reemplazar el contenido con:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('actividades', function (Blueprint $table) {
            $table->decimal('puntaje_maximo', 8, 2)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('actividades', function (Blueprint $table) {
            $table->decimal('puntaje_maximo', 8, 2)->nullable(false)->default(0)->change();
        });
    }
};
```

- [ ] **Step 3: Ejecutar la migración**

```bash
php artisan migrate
```

Resultado esperado: `Migrating: ...make_puntaje_maximo_nullable... Done.`

- [ ] **Step 4: Verificar que test 3 ya pasa (tieneCalificacion) — los demás siguen fallando**

```bash
php artisan test tests/Feature/ActividadSinNotaTest.php --filter=test_metodo_tiene_calificacion
```

Sigue fallando (método no existe). Los otros también. Normal.

---

## Task 3: Modelo — constante y método `tieneCalificacion()`

**Files:**
- Modify: `app/Models/Actividad.php`
- Modify: `database/factories/ActividadFactory.php`

- [ ] **Step 1: Editar `app/Models/Actividad.php`**

Agregar después de `use HasFactory, SoftDeletes, AuditableTrait;`:

```php
    const TIPOS_SIN_NOTA = ['ejercicio', 'lectura', 'encuesta', 'reflexion'];
```

Agregar al final de la clase (antes del último `}`), después del método `puntajeRubrica()`:

```php
    public function tieneCalificacion(): bool
    {
        return !in_array($this->tipo, self::TIPOS_SIN_NOTA);
    }
```

- [ ] **Step 2: Editar `database/factories/ActividadFactory.php`**

Agregar al final de la clase (antes del `}`):

```php
    public function sinNota(): static
    {
        return $this->state([
            'tipo'           => 'lectura',
            'puntaje_maximo' => null,
        ]);
    }
```

- [ ] **Step 3: Ejecutar test 3**

```bash
php artisan test tests/Feature/ActividadSinNotaTest.php --filter=test_metodo_tiene_calificacion
```

Resultado esperado: **PASS**.

- [ ] **Step 4: Commit parcial**

```bash
git add app/Models/Actividad.php database/factories/ActividadFactory.php database/migrations/
git commit -m "feat: puntaje_maximo nullable + tieneCalificacion() en Actividad"
```

---

## Task 4: Controlador — validación condicional

**Files:**
- Modify: `app/Http/Controllers/ActividadController.php`

- [ ] **Step 1: Agregar `use Illuminate\Validation\Rule;` al inicio del controlador**

En `app/Http/Controllers/ActividadController.php`, agregar después de `use Illuminate\Http\Request;`:

```php
use Illuminate\Validation\Rule;
```

- [ ] **Step 2: Reemplazar el método `store()` completo**

```php
    public function store(Request $request, Leccion $leccion)
    {
        $this->authorize('update', $leccion->modulo->curso);

        $validated = $request->validate([
            'titulo'           => 'required|string|max:255',
            'tipo'             => 'required|in:cuestionario,ensayo,tarea,practica,ejercicio,lectura,encuesta,reflexion',
            'descripcion'      => 'nullable|string',
            'puntaje_maximo'   => [
                Rule::requiredIf(fn() => !in_array($request->tipo, Actividad::TIPOS_SIN_NOTA)),
                'nullable',
                'decimal:0,2',
                'min:0.01',
                'max:999.99',
            ],
            'duracion_minutos' => 'nullable|integer|min:1',
            'es_obligatoria'   => 'boolean',
        ]);

        $orden = $leccion->actividades()->max('orden') + 1;

        $actividad = $leccion->actividades()->create([
            ...$validated,
            'puntaje_maximo'  => in_array($request->tipo, Actividad::TIPOS_SIN_NOTA) ? null : $validated['puntaje_maximo'],
            'es_obligatoria'  => $request->boolean('es_obligatoria', true),
            'orden'           => $orden,
        ]);

        return redirect()
            ->route('actividades.edit', $actividad)
            ->with('success', 'Actividad creada. Ahora configura su contenido.');
    }
```

- [ ] **Step 3: Reemplazar el método `update()` completo**

```php
    public function update(Request $request, Actividad $actividad)
    {
        $this->authorize('update', $actividad->leccion->modulo->curso);

        $validated = $request->validate([
            'titulo'           => 'required|string|max:255',
            'descripcion'      => 'nullable|string',
            'puntaje_maximo'   => [
                Rule::requiredIf(fn() => $actividad->tieneCalificacion()),
                'nullable',
                'decimal:0,2',
                'min:0.01',
                'max:999.99',
            ],
            'duracion_minutos' => 'nullable|integer|min:1',
            'es_obligatoria'   => 'boolean',
            'fecha_apertura'   => 'nullable|date',
            'fecha_cierre'     => 'nullable|date|after_or_equal:fecha_apertura',
        ]);

        $actividad->update([
            ...$validated,
            'es_obligatoria'  => $request->boolean('es_obligatoria', true),
            'fecha_apertura'  => $request->filled('fecha_apertura') ? $request->fecha_apertura : null,
            'fecha_cierre'    => $request->filled('fecha_cierre')   ? $request->fecha_cierre   : null,
        ]);

        return redirect()
            ->route('actividades.edit', $actividad)
            ->with('success', 'Actividad actualizada correctamente');
    }
```

- [ ] **Step 4: Ejecutar tests 1 y 2**

```bash
php artisan test tests/Feature/ActividadSinNotaTest.php --filter="test_instructor_puede_crear|test_cuestionario_sin_puntaje"
```

Resultado esperado: ambos **PASS**.

- [ ] **Step 5: Ejecutar la suite completa para detectar regresiones**

```bash
php artisan test
```

Resultado esperado: todos los tests anteriores siguen pasando. Los tests 1, 2 y 3 de `ActividadSinNotaTest` pasan. El test 4 (vista) aún falla.

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/ActividadController.php
git commit -m "feat: validación condicional de puntaje_maximo según tipo de actividad"
```

---

## Task 5: Vista `create.blade.php` — 4 tipos nuevos + Alpine.js

**Files:**
- Modify: `resources/views/actividades/create.blade.php`

- [ ] **Step 1: Reemplazar el bloque `<div class="mb-4" x-data="{ tipo: 'cuestionario' }">` completo**

Localizar desde la línea 14 (`<div class="mb-4" x-data=...`) hasta la línea 29 (cierre del `</div>` del bloque tipo). Reemplazarlo con:

```blade
            <div class="mb-4" x-data="{ tipo: 'cuestionario' }">
                <label class="block text-sm font-medium text-gray-700 mb-2">Tipo de actividad</label>
                <select name="tipo" x-model="tipo" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                    <optgroup label="Con calificación">
                        <option value="cuestionario">Cuestionario (calificación automática)</option>
                        <option value="ensayo">Ensayo (calificación manual)</option>
                        <option value="tarea">Tarea (entrega de archivo, rúbrica disponible)</option>
                        <option value="practica">Práctica (calificación manual)</option>
                    </optgroup>
                    <optgroup label="Sin calificación">
                        <option value="ejercicio">Ejercicio (consulta, sin nota)</option>
                        <option value="lectura">Lectura / Recurso (consulta, sin nota)</option>
                        <option value="encuesta">Encuesta / Sondeo (sin nota)</option>
                        <option value="reflexion">Reflexión (consulta, sin nota)</option>
                    </optgroup>
                </select>
                <p x-show="tipo === 'tarea'" x-cloak
                   class="mt-2 text-xs text-blue-700 bg-blue-50 border border-blue-200 rounded-lg px-3 py-2 flex items-start gap-2">
                    <svg class="w-4 h-4 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    Las actividades de tipo <strong>Tarea</strong> permiten configurar una rúbrica de evaluación por criterios (0–5.0). Podrás crearla en la página de edición después de guardar.
                </p>
                <p x-show="['ejercicio','lectura','encuesta','reflexion'].includes(tipo)" x-cloak
                   class="mt-2 text-xs text-gray-600 bg-gray-50 border border-gray-200 rounded-lg px-3 py-2 flex items-start gap-2">
                    <svg class="w-4 h-4 mt-0.5 shrink-0 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    Esta actividad es de <strong>consulta</strong>: solo muestra descripción y recursos. Los estudiantes no envían respuestas ni reciben nota.
                </p>
            </div>
```

- [ ] **Step 2: Envolver el campo "Puntaje máximo" con `x-show`**

Localizar el `<div class="grid grid-cols-2 gap-4 mb-4">` (línea 41). Reemplazar ese bloque completo con:

```blade
            <div class="grid grid-cols-2 gap-4 mb-4"
                 x-show="!['ejercicio','lectura','encuesta','reflexion'].includes(tipo)"
                 x-cloak>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Puntaje máximo</label>
                    <input type="number" name="puntaje_maximo" value="{{ old('puntaje_maximo', 5.00) }}"
                           min="0.01" step="0.01"
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Tiempo límite (minutos)</label>
                    <input type="number" name="duracion_minutos" value="{{ old('duracion_minutos') }}" min="1" placeholder="Sin límite"
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                </div>
            </div>
```

Nota: se quitó `required` del input `puntaje_maximo` porque con `x-cloak` el campo está oculto y el `required` HTML no debería activarse, pero como medida de seguridad se elimina — la validación real está en el servidor.

- [ ] **Step 3: Verificar manualmente en el navegador**

Con XAMPP corriendo, navegar a un curso → editar → agregar actividad. Verificar que al seleccionar "Lectura" el campo "Puntaje máximo" desaparece.

- [ ] **Step 4: Commit**

```bash
git add resources/views/actividades/create.blade.php
git commit -m "feat: 4 tipos sin nota en formulario crear actividad"
```

---

## Task 6: Vista `edit.blade.php` — campo puntaje + columna derecha para tipos sin nota

**Files:**
- Modify: `resources/views/actividades/edit.blade.php`

- [ ] **Step 1: Ocultar el campo "Puntaje máximo" para tipos sin nota**

Localizar el bloque `<div class="mb-3">` que contiene `<label class="form-label">Puntaje máximo</label>` (línea ~39). Envolverlo con:

```blade
                @if($actividad->tieneCalificacion())
                <div class="mb-3">
                    <label class="form-label">Puntaje máximo</label>
                    <input type="number" name="puntaje_maximo"
                           value="{{ old('puntaje_maximo', number_format($actividad->puntaje_maximo, 2, '.', '')) }}"
                           min="0.01" step="0.01"
                           class="form-input" required>
                </div>
                @endif
```

- [ ] **Step 2: Agregar columna derecha para tipos sin nota**

Localizar `@if($actividad->tipo === 'cuestionario')` (línea ~232). Agregar un `@elseif` justo después del bloque cuestionario y antes del `@else`:

```blade
        @elseif(!$actividad->tieneCalificacion())
            <div class="card p-8 text-center">
                <svg class="w-12 h-12 text-gray-300 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
                <p class="font-semibold text-gray-700 mb-1">Actividad de consulta</p>
                <p class="text-sm text-gray-500">Esta actividad no tiene calificación ni formulario de respuesta.<br>Agrega recursos en el panel izquierdo para que los estudiantes los consulten.</p>
            </div>
```

La estructura completa del condicional queda:
```
@if($actividad->tipo === 'cuestionario')
    ... (sin cambios)
@elseif(!$actividad->tieneCalificacion())
    ... (nuevo bloque arriba)
@else
    ... (ensayo, tarea, practica — sin cambios)
@endif
```

- [ ] **Step 3: Commit**

```bash
git add resources/views/actividades/edit.blade.php
git commit -m "feat: vista editar actividad soporta tipos sin nota"
```

---

## Task 7: Vista `show.blade.php` — display condicional sin nota

**Files:**
- Modify: `resources/views/actividades/show.blade.php`

- [ ] **Step 1: Modificar el encabezado — reemplazar el bloque `<div class="text-right">` dentro del encabezado**

Localizar (líneas ~17-20):

```blade
            <div class="text-right">
                <p class="text-2xl font-bold text-blue-600">{{ $actividad->puntaje_maximo }}</p>
                <p class="text-xs text-gray-500">puntos</p>
            </div>
```

Reemplazar con:

```blade
            <div class="text-right">
                @if($actividad->tieneCalificacion())
                    <p class="text-2xl font-bold text-blue-600">{{ $actividad->puntaje_maximo }}</p>
                    <p class="text-xs text-gray-500">puntos</p>
                @else
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-600">
                        Sin calificación
                    </span>
                @endif
            </div>
```

- [ ] **Step 2: Ocultar la sección de respuesta completa para tipos sin nota**

Al final de `show.blade.php`, antes del cierre `</div>` principal, localizar el bloque que comienza con `{{-- Resultado si ya respondió --}}` (línea ~224). Envolver TODO ese bloque (resultado + formulario) con:

```blade
    @if($actividad->tieneCalificacion())
        {{-- Resultado si ya respondió --}}
        ... (código existente sin cambios)
    @else
        <div class="bg-gray-50 border border-gray-200 rounded-xl p-6 flex items-start gap-4">
            <svg class="w-6 h-6 text-gray-400 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <div>
                <p class="font-medium text-gray-700">Esta actividad es de consulta</p>
                <p class="text-sm text-gray-500 mt-1">No requiere entrega ni tiene calificación. Revisa los recursos disponibles arriba.</p>
            </div>
        </div>
    @endif
```

- [ ] **Step 3: Ejecutar el test 4 (show)**

```bash
php artisan test tests/Feature/ActividadSinNotaTest.php --filter=test_show_actividad_lectura
```

Resultado esperado: **PASS**.

- [ ] **Step 4: Ejecutar la suite completa**

```bash
php artisan test
```

Resultado esperado: 60+ tests pasando, 0 fallidos.

- [ ] **Step 5: Commit final**

```bash
git add resources/views/actividades/show.blade.php tests/Feature/ActividadSinNotaTest.php
git commit -m "feat: vista show soporta actividades sin nota — sin formulario de respuesta"
```

---

## Task 8: Verificación final

- [ ] **Step 1: Ejecutar la suite completa una última vez**

```bash
php artisan test
```

Resultado esperado: todos los tests (incluyendo los 4 nuevos) pasan.

- [ ] **Step 2: Verificar en el navegador**

1. Crear una actividad tipo "Lectura" en un curso — confirmar que no pide puntaje.
2. Abrir la actividad como estudiante inscrito — confirmar que aparece "Sin calificación" y no hay formulario.
3. Crear una actividad tipo "Cuestionario" sin puntaje — confirmar que da error de validación.
4. Editar una actividad "Lectura" existente — confirmar que no aparece el campo puntaje y la columna derecha dice "Actividad de consulta".

- [ ] **Step 3: Commit de cierre**

```bash
git add -A
git commit -m "feat: actividades sin nota completo (ejercicio, lectura, encuesta, reflexion)"
```
