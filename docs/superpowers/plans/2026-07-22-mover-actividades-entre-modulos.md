# Mover Actividades entre Módulos/Lecciones (Drag & Drop) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Permitir a instructores/admin mover actividades entre lecciones (de cualquier módulo, mismo curso) y reordenarlas dentro de una lección, arrastrándolas en `cursos/edit.blade.php`.

**Architecture:** Un endpoint backend (`POST /cursos/{curso}/actividades/mover`) recalcula `leccion_id` + `orden` para las actividades afectadas dentro de una transacción, validando que todo pertenezca al curso de la URL. El frontend usa SortableJS (CDN) sobre listas de actividades por lección, todas en el mismo `group`, y en el evento `onEnd` postea el nuevo orden por `fetch`.

**Tech Stack:** Laravel 12 / PHP 8.2 (backend + feature tests con PHPUnit), Blade + Alpine.js existente, SortableJS 1.15.2 vía CDN (nueva dependencia, sin build step, mismo patrón que Quill/mammoth.js/SheetJS).

## Global Constraints

- Alcance solo dentro del mismo curso — nunca mover una actividad a un curso distinto (spec §"Fuera de alcance").
- No se agrega drag & drop para módulos ni lecciones, solo para actividades.
- No hay función de deshacer (undo); si el instructor se equivoca, vuelve a arrastrar.
- No se restringe mover actividades con respuestas/progreso ya registrado — el movimiento es válido siempre que la actividad y las lecciones involucradas pertenezcan al mismo curso.
- Spec de referencia: `docs/superpowers/specs/2026-07-22-mover-actividades-entre-modulos-design.md`.

---

### Task 1: Endpoint backend `actividades.mover` con tests

**Files:**
- Modify: `routes/web.php:81-87` (grupo `instructor` de actividades)
- Modify: `app/Http/Controllers/ActividadController.php`
- Test: `tests/Feature/MoverActividadesTest.php` (nuevo)

**Interfaces:**
- Produces: ruta con nombre `actividades.mover` (`POST /cursos/{curso}/actividades/mover`), método `ActividadController::mover(Request $request, Curso $curso): \Illuminate\Http\JsonResponse`. Responde `{"ok": true}` con 200 en éxito, 403 si no autorizado, 422 si algún ID de lección/actividad no pertenece a `$curso`.
- Consumes: nada de tasks previas (es la primera task).

- [ ] **Step 1: Escribir el test que falla**

Crear `tests/Feature/MoverActividadesTest.php`:

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

class MoverActividadesTest extends TestCase
{
    use RefreshDatabase;

    private function crearInstructor(): User
    {
        $rol  = Rol::factory()->instructor()->create();
        $user = User::factory()->create(['estado' => 'activo']);
        $user->roles()->attach($rol);
        return $user;
    }

    private function crearEstudiante(): User
    {
        $rol  = Rol::factory()->estudiante()->create();
        $user = User::factory()->create(['estado' => 'activo']);
        $user->roles()->attach($rol);
        return $user;
    }

    public function test_instructor_mueve_actividad_entre_lecciones_de_distinto_modulo(): void
    {
        $instructor = $this->crearInstructor();
        $curso    = Curso::factory()->publicado()->create(['created_by' => $instructor->id]);
        $moduloA  = Modulo::factory()->create(['curso_id' => $curso->id]);
        $moduloB  = Modulo::factory()->create(['curso_id' => $curso->id]);
        $leccionA = Leccion::factory()->create(['modulo_id' => $moduloA->id]);
        $leccionB = Leccion::factory()->create(['modulo_id' => $moduloB->id]);

        $act1 = Actividad::factory()->create(['leccion_id' => $leccionA->id, 'orden' => 0]);
        $act2 = Actividad::factory()->create(['leccion_id' => $leccionA->id, 'orden' => 1]);
        $act3 = Actividad::factory()->create(['leccion_id' => $leccionB->id, 'orden' => 0]);

        $response = $this->actingAs($instructor)->postJson(route('actividades.mover', $curso), [
            'leccion_destino_id' => $leccionB->id,
            'orden_destino'      => [$act2->id, $act3->id],
            'leccion_origen_id'  => $leccionA->id,
            'orden_origen'       => [$act1->id],
        ]);

        $response->assertOk();
        $response->assertJson(['ok' => true]);

        $this->assertDatabaseHas('actividades', ['id' => $act2->id, 'leccion_id' => $leccionB->id, 'orden' => 0]);
        $this->assertDatabaseHas('actividades', ['id' => $act3->id, 'leccion_id' => $leccionB->id, 'orden' => 1]);
        $this->assertDatabaseHas('actividades', ['id' => $act1->id, 'leccion_id' => $leccionA->id, 'orden' => 0]);
    }

    public function test_instructor_reordena_actividades_dentro_de_la_misma_leccion(): void
    {
        $instructor = $this->crearInstructor();
        $curso   = Curso::factory()->publicado()->create(['created_by' => $instructor->id]);
        $modulo  = Modulo::factory()->create(['curso_id' => $curso->id]);
        $leccion = Leccion::factory()->create(['modulo_id' => $modulo->id]);

        $act1 = Actividad::factory()->create(['leccion_id' => $leccion->id, 'orden' => 0]);
        $act2 = Actividad::factory()->create(['leccion_id' => $leccion->id, 'orden' => 1]);

        $response = $this->actingAs($instructor)->postJson(route('actividades.mover', $curso), [
            'leccion_destino_id' => $leccion->id,
            'orden_destino'      => [$act2->id, $act1->id],
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('actividades', ['id' => $act2->id, 'leccion_id' => $leccion->id, 'orden' => 0]);
        $this->assertDatabaseHas('actividades', ['id' => $act1->id, 'leccion_id' => $leccion->id, 'orden' => 1]);
    }

    public function test_estudiante_no_puede_mover_actividades(): void
    {
        $instructor = $this->crearInstructor();
        $estudiante = $this->crearEstudiante();
        $curso   = Curso::factory()->publicado()->create(['created_by' => $instructor->id]);
        $modulo  = Modulo::factory()->create(['curso_id' => $curso->id]);
        $leccion = Leccion::factory()->create(['modulo_id' => $modulo->id]);
        $act     = Actividad::factory()->create(['leccion_id' => $leccion->id, 'orden' => 0]);

        $response = $this->actingAs($estudiante)->postJson(route('actividades.mover', $curso), [
            'leccion_destino_id' => $leccion->id,
            'orden_destino'      => [$act->id],
        ]);

        $response->assertForbidden();
    }

    public function test_no_puede_mover_actividad_usando_leccion_de_otro_curso(): void
    {
        $instructorA = $this->crearInstructor();
        $instructorB = $this->crearInstructor();

        $cursoA   = Curso::factory()->publicado()->create(['created_by' => $instructorA->id]);
        $moduloA  = Modulo::factory()->create(['curso_id' => $cursoA->id]);
        $leccionA = Leccion::factory()->create(['modulo_id' => $moduloA->id]);
        $actA     = Actividad::factory()->create(['leccion_id' => $leccionA->id, 'orden' => 0]);

        $cursoB   = Curso::factory()->publicado()->create(['created_by' => $instructorB->id]);
        $moduloB  = Modulo::factory()->create(['curso_id' => $cursoB->id]);
        $leccionB = Leccion::factory()->create(['modulo_id' => $moduloB->id]);

        $response = $this->actingAs($instructorA)->postJson(route('actividades.mover', $cursoA), [
            'leccion_destino_id' => $leccionB->id,
            'orden_destino'      => [$actA->id],
        ]);

        $response->assertStatus(422);
    }
}
```

- [ ] **Step 2: Correr los tests y verificar que fallan**

Run: `php artisan test --filter=MoverActividadesTest`
Expected: FAIL — `route('actividades.mover', ...)` lanza `RouteNotFoundException` porque la ruta no existe todavía.

- [ ] **Step 3: Agregar la ruta**

En `routes/web.php`, dentro del grupo `instructor` de actividades (línea 81-87), agregar la nueva ruta junto a las demás de ese bloque:

```php
    Route::middleware('instructor')->group(function () {
        Route::get('/lecciones/{leccion}/actividades/crear', [ActividadController::class, 'create'])->name('actividades.create');
        Route::post('/lecciones/{leccion}/actividades', [ActividadController::class, 'store'])->name('actividades.store');
        Route::get('/actividades/{actividad}/editar', [ActividadController::class, 'edit'])->name('actividades.edit');
        Route::put('/actividades/{actividad}', [ActividadController::class, 'update'])->name('actividades.update');
        Route::delete('/actividades/{actividad}', [ActividadController::class, 'destroy'])->name('actividades.destroy');
        Route::post('/cursos/{curso}/actividades/mover', [ActividadController::class, 'mover'])->name('actividades.mover');
    });
```

- [ ] **Step 4: Implementar el método del controlador**

En `app/Http/Controllers/ActividadController.php`, agregar el import de `Leccion` (ya está importado en este archivo) y de `Curso` y `DB`, y el método `mover`:

Al inicio del archivo, junto a los `use` existentes, agregar:

```php
use App\Models\Curso;
use Illuminate\Support\Facades\DB;
```

Agregar el método (por ejemplo después de `destroy`):

```php
    public function mover(Request $request, Curso $curso)
    {
        $this->authorize('update', $curso);

        $validated = $request->validate([
            'leccion_destino_id'  => 'required|exists:lecciones,id',
            'orden_destino'       => 'required|array|min:1',
            'orden_destino.*'     => 'integer|exists:actividades,id',
            'leccion_origen_id'   => 'nullable|exists:lecciones,id',
            'orden_origen'        => 'nullable|array',
            'orden_origen.*'      => 'integer|exists:actividades,id',
        ]);

        $leccionIds = array_filter([$validated['leccion_destino_id'], $validated['leccion_origen_id'] ?? null]);
        $leccionesValidas = Leccion::whereIn('id', $leccionIds)
            ->whereHas('modulo', fn($q) => $q->where('curso_id', $curso->id))
            ->count();
        abort_unless($leccionesValidas === count($leccionIds), 422);

        $actividadIds = array_unique(array_merge($validated['orden_destino'], $validated['orden_origen'] ?? []));
        $actividadesValidas = Actividad::whereIn('id', $actividadIds)
            ->whereHas('leccion.modulo', fn($q) => $q->where('curso_id', $curso->id))
            ->count();
        abort_unless($actividadesValidas === count($actividadIds), 422);

        DB::transaction(function () use ($validated) {
            foreach ($validated['orden_destino'] as $index => $actividadId) {
                Actividad::where('id', $actividadId)->update([
                    'leccion_id' => $validated['leccion_destino_id'],
                    'orden'      => $index,
                ]);
            }
            foreach ($validated['orden_origen'] ?? [] as $index => $actividadId) {
                Actividad::where('id', $actividadId)->update(['orden' => $index]);
            }
        });

        return response()->json(['ok' => true]);
    }
```

- [ ] **Step 5: Correr los tests y verificar que pasan**

Run: `php artisan test --filter=MoverActividadesTest`
Expected: PASS — 4 tests, 0 failures.

- [ ] **Step 6: Correr toda la suite para descartar regresiones**

Run: `php artisan test`
Expected: PASS — todos los tests existentes siguen en verde (56+ tests previos según memoria del proyecto, más los 4 nuevos).

- [ ] **Step 7: Commit**

```bash
git add routes/web.php app/Http/Controllers/ActividadController.php tests/Feature/MoverActividadesTest.php
git commit -m "Agrega endpoint actividades.mover para mover/reordenar actividades entre lecciones"
```

---

### Task 2: Drag & drop en la vista de edición del curso

**Files:**
- Modify: `resources/views/cursos/edit.blade.php`

**Interfaces:**
- Consumes: ruta `actividades.mover` de Task 1 (`POST /cursos/{curso}/actividades/mover`, body `{leccion_destino_id, orden_destino[], leccion_origen_id?, orden_origen?}`, responde `{"ok": true}` en éxito o status ≥400 en error).
- Produces: nada (última task de este plan).

- [ ] **Step 1: Envolver las actividades de cada lección en un contenedor "sorteable" e identificar filas**

En `resources/views/cursos/edit.blade.php`, reemplazar el bloque de sub-filas de actividades (dentro del `@foreach($modulo->lecciones as $leccion)`, actualmente):

```blade
                {{-- Sub-filas de actividades --}}
                @foreach($leccion->actividades as $actividad)
                <div class="flex items-center justify-between pl-12 pr-6 py-2 bg-purple-50/40 border-t border-purple-100/60 hover:bg-purple-50">
                    <div class="flex items-center gap-2">
                        <svg class="w-3.5 h-3.5 text-purple-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                        </svg>
                        <span class="text-sm text-gray-700">{{ $actividad->titulo }}</span>
                        <span class="text-xs bg-purple-100 text-purple-600 px-2 py-0.5 rounded">{{ ucfirst($actividad->tipo) }}</span>
                        <span class="text-xs text-gray-400">{{ $actividad->puntaje_maximo }} pts</span>
                    </div>
                    <a href="{{ route('actividades.edit', $actividad) }}"
                       class="text-xs bg-purple-100 text-purple-700 px-3 py-1 rounded hover:bg-purple-200">
                        Editar
                    </a>
                </div>
                @endforeach
```

por:

```blade
                {{-- Sub-filas de actividades --}}
                <div class="actividades-lista" data-leccion-id="{{ $leccion->id }}">
                @foreach($leccion->actividades as $actividad)
                <div class="flex items-center justify-between pl-12 pr-6 py-2 bg-purple-50/40 border-t border-purple-100/60 hover:bg-purple-50"
                     data-actividad-id="{{ $actividad->id }}">
                    <div class="flex items-center gap-2">
                        <svg class="drag-handle w-3.5 h-3.5 text-purple-400 shrink-0 cursor-grab active:cursor-grabbing" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                        </svg>
                        <span class="text-sm text-gray-700">{{ $actividad->titulo }}</span>
                        <span class="text-xs bg-purple-100 text-purple-600 px-2 py-0.5 rounded">{{ ucfirst($actividad->tipo) }}</span>
                        <span class="text-xs text-gray-400">{{ $actividad->puntaje_maximo }} pts</span>
                    </div>
                    <a href="{{ route('actividades.edit', $actividad) }}"
                       class="text-xs bg-purple-100 text-purple-700 px-3 py-1 rounded hover:bg-purple-200">
                        Editar
                    </a>
                </div>
                @endforeach
                </div>
```

Nota: el ícono de flecha (`.drag-handle`) pasa a ser el "agarre" para arrastrar — el resto de la fila (incluido el link "Editar") no inicia el drag, así que el click en "Editar" sigue funcionando igual que antes.

- [ ] **Step 2: Agregar SortableJS y el script de inicialización**

Al final de `resources/views/cursos/edit.blade.php`, después de `@include('components.quill-init')`, agregar:

```blade

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js"></script>
<script>
(function() {
    const csrfMeta = document.querySelector('meta[name=csrf-token]');
    const csrfToken = csrfMeta ? csrfMeta.content : '';
    const cursoId = {{ $curso->id }};

    function idsDe(lista) {
        return Array.from(lista.children).map(el => parseInt(el.dataset.actividadId, 10));
    }

    async function guardarMovimiento(destino, origen) {
        const body = {
            leccion_destino_id: parseInt(destino.dataset.leccionId, 10),
            orden_destino: idsDe(destino),
        };
        if (origen && origen !== destino) {
            body.leccion_origen_id = parseInt(origen.dataset.leccionId, 10);
            body.orden_origen = idsDe(origen);
        }

        const res = await fetch(`/cursos/${cursoId}/actividades/mover`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json',
            },
            body: JSON.stringify(body),
        });

        if (!res.ok) {
            alert('No se pudo guardar el nuevo orden. Se recargará la página.');
            location.reload();
        }
    }

    document.querySelectorAll('.actividades-lista').forEach(function(lista) {
        new Sortable(lista, {
            group: 'actividades',
            handle: '.drag-handle',
            animation: 150,
            onEnd: function(evt) {
                guardarMovimiento(evt.to, evt.from);
            },
        });
    });
})();
</script>
@endpush
```

- [ ] **Step 3: Verificar manualmente en el navegador**

Este cambio es solo de UI/JS y no tiene cobertura automática (el proyecto no usa Dusk ni ningún test de navegador). Verificar a mano:

1. Arrancar el servidor: `php artisan serve` (o el flujo habitual de XAMPP).
2. Iniciar sesión como `instructor@dyl-quality.test` / `password123`.
3. Ir a un curso con al menos 2 módulos, cada uno con 1 lección y 2+ actividades (usar el curso de prueba "ISO 9001" o crear módulos/lecciones/actividades de prueba desde `cursos/{id}/editar`).
4. Arrastrar una actividad desde el ícono de flecha hacia la lista de actividades de una lección de otro módulo. Confirmar que la tarjeta se mueve visualmente y que, al recargar la página (F5), la actividad sigue apareciendo en la nueva lección (persistió en BD).
5. Arrastrar dos actividades dentro de la misma lección para cambiar su orden. Recargar y confirmar que el nuevo orden persiste.
6. Abrir las herramientas de red del navegador (Network tab) y confirmar que cada drop dispara un único `POST /cursos/{id}/actividades/mover` con status 200.
7. Como estudiante (`student@dyl-quality.test` / `password123`), confirmar que no ve la vista de edición del curso en absoluto (ya protegida por `authorize('update', $curso)` desde antes de este cambio — no hay nada nuevo que romper aquí, solo confirmar que sigue igual).

Si algún paso falla, no continuar a Step 4 — diagnosticar con systematic-debugging antes de commitear.

- [ ] **Step 4: Commit**

```bash
git add resources/views/cursos/edit.blade.php
git commit -m "Agrega drag and drop para mover/reordenar actividades entre lecciones en cursos/edit"
```

---

## Self-Review Notes

- **Cobertura del spec:** Task 1 cubre backend (§2) y los 4 casos de testing (§3). Task 2 cubre frontend (§1). Los límites de "Fuera de alcance" (§final) quedan reflejados en Global Constraints y en que ningún task toca módulos/lecciones ni cursos cruzados.
- **Sin placeholders:** cada step tiene código completo, comandos exactos y salida esperada.
- **Consistencia de tipos:** `orden_destino` / `orden_origen` son arrays de IDs enteros de principio a fin (test → validación → controlador → JS). `leccion_destino_id` / `leccion_origen_id` igual. El nombre de ruta `actividades.mover` y el método `ActividadController::mover` se usan de forma consistente en ambos tasks.
