# Sprint C (Moodle UCundinamarca) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Entregar el Sprint C de la spec: competencias/REA por curso con avance del estudiante (F7), panel de seguimiento del instructor con semáforo de riesgo (F10) y modo de edición en la vista del curso (F11), evitando FE-4 y FE-6.

**Architecture:** Competencias = dos tablas (`competencias` y pivote `actividad_competencia`) con una pantalla de gestión del instructor y una sección plegable para el estudiante calculada por `CompetenciasService`. El seguimiento = `SeguimientoService`, que calcula todas las filas con consultas agrupadas (sin N+1) reutilizando `NotaCursoService::calcular()` del Sprint B, más una columna nueva `users.ultimo_acceso` mantenida por un middleware con límite de escritura. El modo de edición = un interruptor del lado del servidor (sesión) que decide si `cursos.show` pinta los controles de edición.

**Tech Stack:** Laravel 12, PHP 8.2, Blade + Tailwind + Alpine, PHPUnit 11 con SQLite en memoria (`php artisan test`).

**Spec:** `docs/SPEC_FUNCIONES_MOODLE.md` (secciones F7, F10, F11 y FE-4, FE-6). **Depende del Sprint B** (PR #11, rama `plan-sprint-b-moodle`): usa `NotaCursoService::calcular()`, `Curso::actividadesCalificables()` y el trait de test `Tests\Concerns\ArmaActividades`. Esta rama parte de la del Sprint B; si el PR #11 se mergea primero, no hay conflictos que resolver.

## Global Constraints

- Tests: solo `php artisan test` (SQLite en memoria). **Nunca** `migrate:fresh` ni `--env=testing` contra la BD local (`dyl_lms` real).
- Migraciones aditivas con `down()` funcional.
- Textos de interfaz en español; tablas/columnas en español como el resto del repo.
- Autorización (RNF-3): todo lo del instructor va en el grupo `middleware('instructor')` **y** con `authorize('update', $curso)` (dueño o admin). El estudiante solo ve datos propios.
- Consultas sin N+1 (RNF-2): el seguimiento hace un número constante de consultas aunque crezca la cantidad de estudiantes (hay un test).
- Hora: se muestra tal como está guardada (app en UTC), igual que el resto.
- Nunca mostrar `{{`/`}}` literales ni encabezados vacíos cuando falte un dato (FE-2); estados vacío/error explícitos (FE-4, FE-6). El color nunca es la única señal (RNF-4): los niveles de riesgo llevan texto.
- Escapar con `{{ }}`. Exportes CSV protegidos contra inyección de fórmulas.
- Estilo: clases Tailwind del repo (`dyl-orange-*`, `dyl-graphite-*`), `alert alert-*` (existe `alert-warning`).
- Worktree: `composer install` real (no junction a `vendor/`), `.env` desde `.env.example` + `php artisan key:generate`, `public/build` copiado. Ya resuelto en el worktree actual.
- Commits pequeños en español, terminan con `Co-Authored-By: Claude Sonnet 5.5 <noreply@anthropic.com>`.

## Decisiones tomadas (la spec dejaba abierto)

1. **Última conexión:** no existe. Se agrega `users.ultimo_acceso`, actualizada por middleware como mucho cada 10 minutos y con `toBase()->update` (sin eventos de Eloquent: no toca `updated_at` ni la auditoría).
2. **"Cumplida" (competencias):** una actividad enlazada cuenta como cumplida si el estudiante la entregó (hay respuesta) o la marcó como hecha — la misma noción de "entregada" de la línea de tiempo. La **nota media** es el promedio del % de las actividades enlazadas ya calificadas.
3. **Filtro por actividad** (spec F7): se omite. La sección es corta y no hay uso real aún (YAGNI).
4. **Seguimiento = página propia** `/reportes/cursos/{curso}/seguimiento`, sin iframe ni servicio externo. Umbrales por curso en dos columnas: `seguimiento_dias_sin_acceso` (def. 7) y `seguimiento_max_atrasadas` (def. 2).
5. **Semáforo:** señales = `sin_acceso` (nunca entró o pasó el umbral), `atrasadas` (≥ umbral de entregas vencidas sin entregar) y `nota_baja` (promedio a la fecha < nota mínima **o** ya no alcanza la mínima con lo pendiente). 0 señales = Al día, 1 = Atención, ≥ 2 = En riesgo.
6. **Modo de edición del lado del servidor** (clave de sesión `modo_edicion`, interruptor por POST) en vez de `localStorage`: es testable, los controles de edición nunca están en el HTML del estudiante ni del instructor con el modo apagado, y "recordado" significa durante la sesión.
7. **Sin enlaces nuevos en el menú lateral.** Competencias y seguimiento se alcanzan desde la edición del curso, el modo de edición y el reporte del curso.

## Review Focus

Condiciones que la spec implica y ningún test "obvio" cubriría; cada una tiene su test en la tarea indicada:

1. Competencias: ids de actividades de OTRO curso se ignoran; una actividad eliminada (soft delete) no cuenta; un curso sin competencias no muestra nada al estudiante y guía al instructor → Task 1 y 2.
2. Seguimiento: justo en el umbral (7 días, 2 atrasadas), estudiante que nunca entró (`ultimo_acceso` nulo), estudiante sin nada calificado (no es `nota_baja`), curso sin lecciones/actividades (sin división por cero), constante de consultas → Task 4.
3. CSV: celdas que empiezan con `=`, `+`, `-`, `@` no se ejecutan como fórmula; tildes bien codificadas → Task 4.
4. `ultimo_acceso`: no escribe en cada request, no rompe a invitados ni la ruta pública del `.ics`, no genera filas de auditoría → Task 3.
5. Modo de edición: otro instructor y el estudiante no pueden activarlo (403); aunque la sesión tenga la bandera, el estudiante nunca ve controles → Task 5.

---

## File Structure

| Archivo | Acción | Responsabilidad |
|---|---|---|
| `database/migrations/2026_10_18_000001_create_competencias_tables.php` | crear | `competencias`, `actividad_competencia` |
| `app/Models/Competencia.php` | crear | modelo + relación con actividades |
| `app/Models/Curso.php` | modificar | `todasLasActividades()`, `competencias()`, fillable de umbrales |
| `app/Http/Controllers/CompetenciaController.php`, `resources/views/cursos/competencias.blade.php` | crear | gestión del instructor |
| `app/Services/LineaDeTiempoService.php` | modificar | `idsEntregados()` pasa a público |
| `app/Services/CompetenciasService.php`, `resources/views/cursos/partials/competencias.blade.php` | crear | avance del estudiante |
| `resources/views/cursos/show.blade.php`, `resources/views/cursos/edit.blade.php` | modificar | sección de competencias, enlaces, modo de edición |
| `database/migrations/2026_10_18_000002_add_ultimo_acceso_to_users_table.php`, `app/Http/Middleware/RegistrarUltimoAcceso.php`, `bootstrap/app.php`, `app/Models/User.php` | crear/modificar | última conexión |
| `database/migrations/2026_10_18_000003_add_umbrales_seguimiento_to_cursos_table.php` | crear | umbrales por curso |
| `app/Services/SeguimientoService.php`, `app/Http/Controllers/SeguimientoController.php`, `resources/views/reportes/seguimiento.blade.php` | crear | panel de seguimiento, umbrales y CSV |
| `resources/views/reportes/curso.blade.php`, `routes/web.php`, `routes/breadcrumbs.php` | modificar | enlace, rutas, migas |
| `app/Http/Controllers/ModoEdicionController.php` | crear | interruptor del modo de edición |
| `tests/Feature/CompetenciasInstructorTest.php`, `CompetenciasAvanceTest.php`, `UltimoAccesoTest.php`, `SeguimientoTest.php`, `ModoEdicionTest.php` | crear | tests de cada tarea |

Comando de test: `php artisan test --filter=<Nombre>` (desde la raíz del worktree).

---

### Task 1: Competencias del curso — datos y gestión del instructor (F7)

**Files:**
- Create: `database/migrations/2026_10_18_000001_create_competencias_tables.php`, `app/Models/Competencia.php`, `app/Http/Controllers/CompetenciaController.php`, `resources/views/cursos/competencias.blade.php`
- Modify: `app/Models/Curso.php` (`todasLasActividades()`, `competencias()`, y `actividadesCalificables()` pasa a derivar de la anterior), `routes/web.php` (grupo `instructor`), `routes/breadcrumbs.php`, `resources/views/cursos/edit.blade.php` (enlace "Competencias" junto a "Ponderaciones")
- Test: `tests/Feature/CompetenciasInstructorTest.php`

**Interfaces:**
- Produces: tablas `competencias` (`id`, `curso_id` fk cascade, `descripcion` string 500, `orden` unsignedSmallInteger default 0, timestamps) y `actividad_competencia` (`competencia_id` fk cascade, `actividad_id` fk cascade, clave primaria compuesta); `Competencia` con `curso()` y `actividades(): BelongsToMany`; `Curso::competencias(): HasMany` (ordenada por `orden`, `id`); `Curso::todasLasActividades(): Collection` (todas las actividades vivas del curso, ordenadas módulo→lección→actividad); rutas `cursos.competencias.index` (`GET /cursos/{curso}/competencias`), `.store` (`POST`), `.update` (`PUT /cursos/{curso}/competencias/{competencia}`), `.destroy` (`DELETE`); breadcrumb `cursos.competencias`.

- [ ] **Step 1: Test que falla**

```php
<?php

namespace Tests\Feature;

use App\Models\Competencia;
use App\Models\Curso;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ArmaActividades;
use Tests\Concerns\ArmaCursoCompleto;
use Tests\TestCase;

class CompetenciasInstructorTest extends TestCase
{
    use RefreshDatabase, ArmaCursoCompleto, ArmaActividades;

    private function escenario(): array
    {
        $inst  = $this->instructor();
        $curso = Curso::factory()->publicado()->create(['created_by' => $inst->id]);
        $a1 = $this->actividadEn($curso, ['titulo' => 'Actividad uno']);
        $a2 = $this->actividadEn($curso, ['titulo' => 'Actividad dos']);

        return [$inst, $curso, $a1, $a2];
    }

    public function test_estado_vacio_guia_al_instructor(): void
    {
        [$inst, $curso] = $this->escenario();

        $this->actingAs($inst)->get(route('cursos.competencias.index', $curso))->assertOk()
            ->assertSee('Aún no hay competencias')->assertSee('Agregar competencia');
    }

    public function test_crear_competencia_con_actividades(): void
    {
        [$inst, $curso, $a1, $a2] = $this->escenario();

        $this->actingAs($inst)->post(route('cursos.competencias.store', $curso), [
            'descripcion' => 'Aplicar fundamentos de seguridad', 'actividad_ids' => [$a1->id, $a2->id],
        ])->assertRedirect(route('cursos.competencias.index', $curso));

        $c = Competencia::firstOrFail();
        $this->assertSame($curso->id, $c->curso_id);
        $this->assertEqualsCanonicalizing([$a1->id, $a2->id], $c->actividades()->pluck('actividades.id')->all());
    }

    public function test_actividades_de_otro_curso_se_ignoran(): void
    {
        [$inst, $curso, $a1] = $this->escenario();
        $otroCurso = Curso::factory()->publicado()->create(['created_by' => $inst->id]);
        $ajena = $this->actividadEn($otroCurso);

        $this->actingAs($inst)->post(route('cursos.competencias.store', $curso), [
            'descripcion' => 'X', 'actividad_ids' => [$a1->id, $ajena->id],
        ]);

        $this->assertSame([$a1->id], Competencia::firstOrFail()->actividades()->pluck('actividades.id')->all());
    }

    public function test_actualizar_reemplaza_enlaces_y_eliminar_borra(): void
    {
        [$inst, $curso, $a1, $a2] = $this->escenario();
        $c = $curso->competencias()->create(['descripcion' => 'Vieja']);
        $c->actividades()->sync([$a1->id]);

        $this->actingAs($inst)->put(route('cursos.competencias.update', [$curso, $c]), [
            'descripcion' => 'Nueva', 'actividad_ids' => [$a2->id],
        ])->assertRedirect(route('cursos.competencias.index', $curso));

        $this->assertSame('Nueva', $c->fresh()->descripcion);
        $this->assertSame([$a2->id], $c->actividades()->pluck('actividades.id')->all());

        $this->actingAs($inst)->delete(route('cursos.competencias.destroy', [$curso, $c]))->assertRedirect();
        $this->assertDatabaseCount('competencias', 0);
        $this->assertDatabaseCount('actividad_competencia', 0);
    }

    public function test_validacion_de_la_descripcion(): void
    {
        [$inst, $curso] = $this->escenario();

        $this->actingAs($inst)->post(route('cursos.competencias.store', $curso), ['descripcion' => ''])
            ->assertSessionHasErrors('descripcion');
        $this->actingAs($inst)->post(route('cursos.competencias.store', $curso), ['descripcion' => str_repeat('a', 501)])
            ->assertSessionHasErrors('descripcion');
        $this->actingAs($inst)->post(route('cursos.competencias.store', $curso), ['descripcion' => 'ok', 'actividad_ids' => 'basura'])
            ->assertSessionHasErrors('actividad_ids');
    }

    public function test_otro_instructor_estudiante_y_competencia_de_otro_curso(): void
    {
        [$inst, $curso] = $this->escenario();
        $otroCurso = Curso::factory()->publicado()->create(['created_by' => $inst->id]);
        $ajena = $otroCurso->competencias()->create(['descripcion' => 'De otro curso']);

        $this->actingAs($this->instructor())->get(route('cursos.competencias.index', $curso))->assertForbidden();
        $this->actingAs($this->estudiante())->get(route('cursos.competencias.index', $curso))->assertForbidden();
        $this->actingAs($inst)->put(route('cursos.competencias.update', [$curso, $ajena]), ['descripcion' => 'hack'])->assertNotFound();
        $this->assertSame('De otro curso', $ajena->fresh()->descripcion);
    }

    public function test_actividad_eliminada_no_aparece_en_la_pantalla(): void
    {
        [$inst, $curso, $a1, $a2] = $this->escenario();
        $a2->delete();

        $this->actingAs($inst)->get(route('cursos.competencias.index', $curso))->assertOk()
            ->assertSee('Actividad uno')->assertDontSee('Actividad dos');
    }
}
```

- [ ] **Step 2: Verificar que falla** — `php artisan test --filter=CompetenciasInstructorTest` → FAIL (ruta no definida).

- [ ] **Step 3: Migración**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('competencias', function (Blueprint $table) {
            $table->id();
            $table->foreignId('curso_id')->constrained('cursos')->onDelete('cascade');
            $table->string('descripcion', 500);
            $table->unsignedSmallInteger('orden')->default(0);
            $table->timestamps();

            $table->index('curso_id');
        });

        Schema::create('actividad_competencia', function (Blueprint $table) {
            $table->foreignId('competencia_id')->constrained('competencias')->onDelete('cascade');
            $table->foreignId('actividad_id')->constrained('actividades')->onDelete('cascade');
            $table->primary(['competencia_id', 'actividad_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('actividad_competencia');
        Schema::dropIfExists('competencias');
    }
};
```

- [ ] **Step 4: Modelos.** `app/Models/Competencia.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Competencia extends Model
{
    protected $table = 'competencias';
    protected $fillable = ['curso_id', 'descripcion', 'orden'];

    public function curso(): BelongsTo
    {
        return $this->belongsTo(Curso::class);
    }

    public function actividades(): BelongsToMany
    {
        return $this->belongsToMany(Actividad::class, 'actividad_competencia', 'competencia_id', 'actividad_id');
    }
}
```

En `Curso` agregar (y reescribir `actividadesCalificables()` para que derive de la nueva; los tests existentes deben seguir pasando):

```php
    /** Todas las actividades vivas del curso, ordenadas por módulo, lección y actividad. */
    public function todasLasActividades(): \Illuminate\Support\Collection
    {
        return Actividad::whereHas('leccion.modulo', fn($q) => $q->where('curso_id', $this->id))
            ->with('leccion.modulo')
            ->get()
            ->sortBy(fn($a) => [$a->leccion->modulo->orden, $a->leccion->orden, $a->orden])
            ->values();
    }

    public function actividadesCalificables(): \Illuminate\Support\Collection
    {
        return $this->todasLasActividades()->filter(fn($a) => $a->tieneCalificacion())->values();
    }

    public function competencias(): HasMany
    {
        return $this->hasMany(Competencia::class)->orderBy('orden')->orderBy('id');
    }
```

- [ ] **Step 5: Controlador**

```php
<?php

namespace App\Http\Controllers;

use App\Models\Competencia;
use App\Models\Curso;
use Illuminate\Http\Request;

class CompetenciaController extends Controller
{
    public function index(Curso $curso)
    {
        $this->authorize('update', $curso);
        $competencias = $curso->competencias()->with('actividades')->get();
        $actividades  = $curso->todasLasActividades();

        return view('cursos.competencias', compact('curso', 'competencias', 'actividades'));
    }

    public function store(Request $request, Curso $curso)
    {
        $this->authorize('update', $curso);
        [$descripcion, $ids] = $this->validar($request, $curso);

        $competencia = $curso->competencias()->create([
            'descripcion' => $descripcion,
            'orden'       => (int) $curso->competencias()->max('orden') + 1,
        ]);
        $competencia->actividades()->sync($ids);

        return redirect()->route('cursos.competencias.index', $curso)->with('success', 'Competencia agregada.');
    }

    public function update(Request $request, Curso $curso, Competencia $competencia)
    {
        $this->authorize('update', $curso);
        abort_unless($competencia->curso_id === $curso->id, 404);
        [$descripcion, $ids] = $this->validar($request, $curso);

        $competencia->update(['descripcion' => $descripcion]);
        $competencia->actividades()->sync($ids);

        return redirect()->route('cursos.competencias.index', $curso)->with('success', 'Competencia actualizada.');
    }

    public function destroy(Curso $curso, Competencia $competencia)
    {
        $this->authorize('update', $curso);
        abort_unless($competencia->curso_id === $curso->id, 404);
        $competencia->delete();

        return redirect()->route('cursos.competencias.index', $curso)->with('success', 'Competencia eliminada.');
    }

    /** @return array{0: string, 1: array<int>} descripción y ids de actividades que SÍ son de este curso */
    private function validar(Request $request, Curso $curso): array
    {
        $d = $request->validate([
            'descripcion'     => 'required|string|max:500',
            'actividad_ids'   => 'nullable|array',
            'actividad_ids.*' => 'integer',
        ]);

        $validos = $curso->todasLasActividades()->pluck('id')->all();
        $ids     = array_values(array_intersect($d['actividad_ids'] ?? [], $validos));

        return [$d['descripcion'], $ids];
    }
}
```

- [ ] **Step 6: Rutas y migas.** En `routes/web.php`, grupo `instructor` junto a `cursos.ponderaciones.*`:

```php
        Route::get('/cursos/{curso}/competencias', [\App\Http\Controllers\CompetenciaController::class, 'index'])->name('cursos.competencias.index');
        Route::post('/cursos/{curso}/competencias', [\App\Http\Controllers\CompetenciaController::class, 'store'])->name('cursos.competencias.store');
        Route::put('/cursos/{curso}/competencias/{competencia}', [\App\Http\Controllers\CompetenciaController::class, 'update'])->name('cursos.competencias.update');
        Route::delete('/cursos/{curso}/competencias/{competencia}', [\App\Http\Controllers\CompetenciaController::class, 'destroy'])->name('cursos.competencias.destroy');
```

`routes/breadcrumbs.php`: `Breadcrumbs::for('cursos.competencias', fn(Trail $t, $curso) => $t->parent('cursos.show', $curso)->push('Competencias'));`

- [ ] **Step 7: Vista** `cursos/competencias.blade.php`

```blade
@extends('layouts.app')
@section('title', 'Competencias - ' . $curso->titulo)
@section('breadcrumbs'){{ Breadcrumbs::render('cursos.competencias', $curso) }}@endsection
@section('content')
<h1 class="text-3xl font-bold text-gray-900 mb-2">Competencias</h1>
<p class="text-sm text-gray-600 mb-6 max-w-2xl">
    Define los resultados de aprendizaje de «{{ $curso->titulo }}» y enlaza las actividades que los evidencian.
    Tus estudiantes verán su avance en cada competencia dentro del curso.
</p>

@if($competencias->isEmpty())
    <div class="mb-6 rounded-xl border border-dashed border-gray-300 bg-white p-8 text-center text-gray-600">
        Aún no hay competencias. Agrega la primera con el formulario de abajo.
    </div>
@endif

@foreach($competencias as $c)
    @php $enlazadas = $c->actividades->pluck('id')->all(); @endphp
    <div class="mb-4 rounded-xl bg-white p-5 shadow">
        <form method="POST" action="{{ route('cursos.competencias.update', [$curso, $c]) }}">
            @csrf @method('PUT')
            <label class="block text-sm font-medium text-gray-700">Competencia
                <input type="text" name="descripcion" maxlength="500" required value="{{ $c->descripcion }}"
                       class="mt-1 block w-full rounded-lg border-gray-300 text-sm">
            </label>
            @include('cursos.partials.competencia-actividades', ['actividades' => $actividades, 'marcadas' => $enlazadas])
            <div class="mt-3 flex items-center gap-3">
                <button class="rounded-lg bg-dyl-orange-600 px-4 py-2 text-sm font-medium text-white hover:bg-dyl-orange-700">Guardar</button>
            </div>
        </form>
        <form method="POST" action="{{ route('cursos.competencias.destroy', [$curso, $c]) }}" class="mt-2"
              onsubmit="return confirm('¿Eliminar esta competencia?')">
            @csrf @method('DELETE')
            <button class="text-sm text-red-600 hover:underline">Eliminar competencia</button>
        </form>
    </div>
@endforeach

<div class="rounded-xl bg-white p-5 shadow">
    <h2 class="mb-3 text-lg font-bold text-gray-900">Agregar competencia</h2>
    @error('descripcion')<div class="alert alert-error mb-3">{{ $message }}</div>@enderror
    <form method="POST" action="{{ route('cursos.competencias.store', $curso) }}">
        @csrf
        <label class="block text-sm font-medium text-gray-700">Competencia
            <input type="text" name="descripcion" maxlength="500" required value="{{ old('descripcion') }}"
                   class="mt-1 block w-full rounded-lg border-gray-300 text-sm"
                   placeholder="Ej.: Aplicar los fundamentos de seguridad en sistemas y redes">
        </label>
        @include('cursos.partials.competencia-actividades', ['actividades' => $actividades, 'marcadas' => []])
        <button class="mt-3 rounded-lg bg-dyl-orange-600 px-4 py-2 text-sm font-medium text-white hover:bg-dyl-orange-700">Agregar competencia</button>
    </form>
</div>
@endsection
```

Parcial `cursos/partials/competencia-actividades.blade.php`:

```blade
@if($actividades->isEmpty())
    <p class="mt-3 text-sm text-gray-500">Este curso aún no tiene actividades para enlazar.</p>
@else
    <fieldset class="mt-3">
        <legend class="text-sm font-medium text-gray-700">Actividades que la evidencian</legend>
        <div class="mt-1 grid gap-1 sm:grid-cols-2">
            @foreach($actividades as $a)
                <label class="flex items-center gap-2 text-sm text-gray-700">
                    <input type="checkbox" name="actividad_ids[]" value="{{ $a->id }}" class="rounded"
                           @checked(in_array($a->id, $marcadas, true))>
                    <span class="truncate">{{ $a->tituloCompleto() }}</span>
                </label>
            @endforeach
        </div>
    </fieldset>
@endif
```

- [ ] **Step 8: Enlace.** En `cursos/edit.blade.php`, junto al enlace "Ponderaciones" (mismas clases): `<a href="{{ route('cursos.competencias.index', $curso) }}" ...>Competencias</a>`.

- [ ] **Step 9: Verificar que pasa** — `php artisan test --filter=CompetenciasInstructorTest`, luego `--filter=Ponderaciones`, `--filter=NotaCurso`, `--filter=Curso` y la suite completa (el refactor de `actividadesCalificables()` no debe cambiar ningún resultado).

- [ ] **Step 10: Commit**

```bash
git add database/migrations app routes resources tests
git commit -m "feat: competencias del curso y su gestion por el instructor (F7)"
```

---

### Task 2: Avance del estudiante en las competencias (F7, FE-6)

**Files:**
- Create: `app/Services/CompetenciasService.php`, `resources/views/cursos/partials/competencias.blade.php`
- Modify: `app/Services/LineaDeTiempoService.php` (`idsEntregados` de `private` a `public`), `resources/views/cursos/show.blade.php` (incluir el parcial antes de la ficha)
- Test: `tests/Feature/CompetenciasAvanceTest.php`

**Interfaces:**
- Consumes: `Curso::competencias()`, `Competencia::actividades()` (Task 1), `LineaDeTiempoService::idsEntregados(User): Collection` (actividad_id => índice, `isset()` para chequear), `NotaCursoService::para(User, Curso)['filas']` (objetos con `actividad` y `pct`).
- Produces: `CompetenciasService::avance(User $user, Curso $curso): Collection` de objetos `{competencia, total, cumplidas, nota_media}` (`nota_media` `?int`, `null` si ninguna enlazada está calificada), colección vacía si el curso no tiene competencias.

- [ ] **Step 1: Test que falla**

```php
<?php

namespace Tests\Feature;

use App\Models\ProgresoActividad;
use App\Services\CompetenciasService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ArmaActividades;
use Tests\Concerns\ArmaCursoCompleto;
use Tests\TestCase;

class CompetenciasAvanceTest extends TestCase
{
    use RefreshDatabase, ArmaCursoCompleto, ArmaActividades;

    private function escenario(): array
    {
        $u = $this->estudiante();
        $curso = $this->cursoInscrito($u);
        $a1 = $this->actividadEn($curso, ['puntaje_maximo' => 5]);                                   // se califica 4/5
        $a2 = $this->actividadEn($curso, ['tipo' => 'lectura', 'puntaje_maximo' => null]);          // se marca hecha
        $a3 = $this->actividadEn($curso, ['puntaje_maximo' => 5]);                                   // pendiente
        $c = $curso->competencias()->create(['descripcion' => 'Aplicar fundamentos de seguridad']);
        $c->actividades()->sync([$a1->id, $a2->id, $a3->id]);

        return [$u, $curso, $c, $a1, $a2, $a3];
    }

    public function test_avance_cuenta_cumplidas_y_nota_media(): void
    {
        [$u, $curso, $c, $a1, $a2] = $this->escenario();
        $this->calificar($u, $a1, 4);
        ProgresoActividad::create(['user_id' => $u->id, 'actividad_id' => $a2->id, 'completado' => true, 'fecha_completado' => now()]);

        $fila = app(CompetenciasService::class)->avance($u, $curso)->first();

        $this->assertSame(3, $fila->total);
        $this->assertSame(2, $fila->cumplidas);
        $this->assertSame(80, $fila->nota_media);
    }

    public function test_sin_calificadas_la_nota_media_es_nula(): void
    {
        [$u, $curso] = $this->escenario();

        $fila = app(CompetenciasService::class)->avance($u, $curso)->first();

        $this->assertSame(0, $fila->cumplidas);
        $this->assertNull($fila->nota_media);
    }

    public function test_actividad_eliminada_no_cuenta(): void
    {
        [$u, $curso, $c, $a1, $a2, $a3] = $this->escenario();
        $a3->delete();

        $this->assertSame(2, app(CompetenciasService::class)->avance($u, $curso)->first()->total);
    }

    public function test_el_estudiante_ve_la_seccion_con_su_avance(): void
    {
        [$u, $curso, $c, $a1] = $this->escenario();
        $this->calificar($u, $a1, 4);

        $this->actingAs($u)->get(route('cursos.show', $curso))->assertOk()
            ->assertSee('Competencias del curso')->assertSee('Aplicar fundamentos de seguridad')
            ->assertSeeText('1 de 3 actividades cumplidas')->assertSeeText('Nota media: 80%');
    }

    public function test_curso_sin_competencias_no_muestra_nada_al_estudiante(): void
    {
        $u = $this->estudiante();
        $curso = $this->cursoInscrito($u);
        $this->actividadEn($curso);

        $this->actingAs($u)->get(route('cursos.show', $curso))->assertOk()->assertDontSee('Competencias del curso');
    }

    public function test_competencia_sin_actividades_enlazadas_no_rompe(): void
    {
        $u = $this->estudiante();
        $curso = $this->cursoInscrito($u);
        $curso->competencias()->create(['descripcion' => 'Competencia vacía']);

        $this->actingAs($u)->get(route('cursos.show', $curso))->assertOk()
            ->assertSee('Competencia vacía')->assertSeeText('Sin actividades enlazadas');
    }

    public function test_el_instructor_no_ve_la_seccion_de_estudiante(): void
    {
        [$u, $curso] = $this->escenario();

        $this->actingAs($curso->creador)->get(route('cursos.show', $curso))->assertOk()->assertDontSee('Competencias del curso');
    }
}
```

- [ ] **Step 2: Verificar que falla** — `php artisan test --filter=CompetenciasAvanceTest`.

- [ ] **Step 3: Visibilidad.** En `LineaDeTiempoService`, cambiar `private function idsEntregados(User $user): Collection` por `public function idsEntregados(User $user): Collection` (sin tocar el cuerpo).

- [ ] **Step 4: Servicio**

```php
<?php

namespace App\Services;

use App\Models\Curso;
use App\Models\User;
use Illuminate\Support\Collection;

class CompetenciasService
{
    public function __construct(private LineaDeTiempoService $lineaDeTiempo, private NotaCursoService $notas) {}

    /** @return Collection<int, \stdClass> {competencia, total, cumplidas, nota_media} */
    public function avance(User $user, Curso $curso): Collection
    {
        $competencias = $curso->competencias()->with('actividades')->get();
        if ($competencias->isEmpty()) {
            return collect();
        }

        $entregadas = $this->lineaDeTiempo->idsEntregados($user);
        $pctPorActividad = $this->notas->para($user, $curso)['filas']
            ->filter(fn($f) => $f->pct !== null)->mapWithKeys(fn($f) => [$f->actividad->id => $f->pct]);

        return $competencias->map(function ($c) use ($entregadas, $pctPorActividad) {
            $ids       = $c->actividades->pluck('id');
            $calificas = $ids->filter(fn($id) => $pctPorActividad->has($id));

            return (object) [
                'competencia' => $c,
                'total'       => $ids->count(),
                'cumplidas'   => $ids->filter(fn($id) => isset($entregadas[$id]))->count(),
                'nota_media'  => $calificas->isEmpty() ? null : (int) round($calificas->avg(fn($id) => $pctPorActividad[$id])),
            ];
        })->values();
    }
}
```

- [ ] **Step 5: Parcial** `cursos/partials/competencias.blade.php` (recibe `$curso`):

```blade
@php
    $esEstudianteDelCurso = auth()->check() && auth()->id() !== $curso->created_by && !auth()->user()->esAdmin()
        && $curso->inscripciones()->where('user_id', auth()->id())->exists();
    $avance = $esEstudianteDelCurso ? app(\App\Services\CompetenciasService::class)->avance(auth()->user(), $curso) : collect();
@endphp
@if($avance->isNotEmpty())
<details class="mt-8 rounded-xl bg-white shadow" open>
    <summary class="cursor-pointer px-6 py-4 text-lg font-bold text-gray-900">Competencias del curso</summary>
    <ul class="divide-y divide-gray-100 px-6 pb-4">
        @foreach($avance as $f)
            <li class="py-3">
                <p class="text-sm font-medium text-gray-900">{{ $f->competencia->descripcion }}</p>
                @if($f->total === 0)
                    <p class="mt-1 text-xs text-gray-500">Sin actividades enlazadas</p>
                @else
                    @php $pct = (int) round($f->cumplidas / $f->total * 100); @endphp
                    <div class="mt-2 h-1.5 rounded-full bg-gray-200" role="progressbar" aria-valuenow="{{ $pct }}" aria-valuemin="0" aria-valuemax="100">
                        <div class="h-1.5 rounded-full bg-dyl-orange-600" style="width: {{ $pct }}%"></div>
                    </div>
                    <p class="mt-1 text-xs text-gray-600">
                        {{ $f->cumplidas }} de {{ $f->total }} actividades cumplidas
                        @if($f->nota_media !== null) · Nota media: {{ $f->nota_media }}% @endif
                    </p>
                @endif
            </li>
        @endforeach
    </ul>
</details>
@endif
```

- [ ] **Step 6: Incluirlo.** En `cursos/show.blade.php`, inmediatamente antes de `@include('cursos.partials.ficha', ...)`: `@include('cursos.partials.competencias', ['curso' => $curso])`.

- [ ] **Step 7: Verificar que pasa** — `php artisan test --filter=CompetenciasAvanceTest`, `--filter=LineaDeTiempoTest`, `--filter=FichaCurso`, `--filter=CursoInscripcion` y la suite completa.

- [ ] **Step 8: Commit**

```bash
git add app resources tests
git commit -m "feat: avance del estudiante en las competencias del curso (F7)"
```

---

### Task 3: Última conexión del usuario (F10, base)

**Files:**
- Create: `database/migrations/2026_10_18_000002_add_ultimo_acceso_to_users_table.php`, `app/Http/Middleware/RegistrarUltimoAcceso.php`
- Modify: `bootstrap/app.php` (agregar al grupo `web`), `app/Models/User.php` (cast `ultimo_acceso` a `datetime`; **no** en `$fillable`)
- Test: `tests/Feature/UltimoAccesoTest.php`

**Interfaces:**
- Produces: `users.ultimo_acceso` (timestamp nullable); `User::$ultimo_acceso` (`?Carbon`); middleware que lo actualiza como mucho cada 10 minutos.

- [ ] **Step 1: Test que falla**

```php
<?php

namespace Tests\Feature;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use OwenIt\Auditing\Models\Audit;
use Tests\Concerns\ArmaCursoCompleto;
use Tests\TestCase;

class UltimoAccesoTest extends TestCase
{
    use RefreshDatabase, ArmaCursoCompleto;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_se_registra_en_la_primera_visita_autenticada(): void
    {
        Carbon::setTestNow('2026-10-20 10:00:00');
        $u = $this->estudiante();
        $this->assertNull($u->fresh()->ultimo_acceso);

        $this->actingAs($u)->get('/dashboard')->assertOk();

        $this->assertTrue($u->fresh()->ultimo_acceso->equalTo(now()));
    }

    public function test_no_vuelve_a_escribir_antes_de_10_minutos_y_si_despues(): void
    {
        Carbon::setTestNow('2026-10-20 10:00:00');
        $u = $this->estudiante();
        $this->actingAs($u)->get('/dashboard');

        Carbon::setTestNow('2026-10-20 10:05:00');
        $this->actingAs($u->fresh())->get('/dashboard');
        $this->assertSame('2026-10-20 10:00:00', $u->fresh()->ultimo_acceso->format('Y-m-d H:i:s'));

        Carbon::setTestNow('2026-10-20 10:11:00');
        $this->actingAs($u->fresh())->get('/dashboard');
        $this->assertSame('2026-10-20 10:11:00', $u->fresh()->ultimo_acceso->format('Y-m-d H:i:s'));
    }

    public function test_invitados_y_la_ruta_publica_no_fallan(): void
    {
        $this->get('/login')->assertOk();
        $this->get('/calendario/feed/' . str_repeat('a', 64) . '.ics')->assertNotFound();
    }

    public function test_no_toca_updated_at_ni_genera_auditoria(): void
    {
        config(['audit.console' => true]);
        Carbon::setTestNow('2026-10-20 10:00:00');
        $u = $this->estudiante();
        $antesAuditorias = Audit::count();
        $updatedAt = $u->fresh()->updated_at;

        Carbon::setTestNow('2026-10-20 11:00:00');
        $this->actingAs($u)->get('/dashboard');

        $this->assertEquals($updatedAt, $u->fresh()->updated_at);
        $this->assertSame($antesAuditorias, Audit::count());
    }

    public function test_no_es_asignable_en_masa(): void
    {
        $u = $this->estudiante();
        $u->fill(['ultimo_acceso' => now()]);

        $this->assertNull($u->ultimo_acceso);
    }
}
```

- [ ] **Step 2: Verificar que falla** — `php artisan test --filter=UltimoAccesoTest`.

- [ ] **Step 3: Migración**

```php
Schema::table('users', function (Blueprint $table) {
    $table->timestamp('ultimo_acceso')->nullable()->after('remember_token');
});
// down(): $table->dropColumn('ultimo_acceso');
```

(Estructura completa de clase anónima como las demás migraciones.)

- [ ] **Step 4: Middleware**

```php
<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RegistrarUltimoAcceso
{
    private const MINUTOS_ENTRE_ESCRITURAS = 10;

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && (!$user->ultimo_acceso || $user->ultimo_acceso->lt(now()->subMinutes(self::MINUTOS_ENTRE_ESCRITURAS)))) {
            // Directo a la tabla: sin eventos de Eloquent (no toca updated_at ni la auditoría).
            User::whereKey($user->id)->toBase()->update(['ultimo_acceso' => now()]);
        }

        return $next($request);
    }
}
```

- [ ] **Step 5: Registro y modelo.** En `bootstrap/app.php`, dentro de `withMiddleware`, después de `alias([...])`: `$middleware->appendToGroup('web', \App\Http\Middleware\RegistrarUltimoAcceso::class);`. En `User::casts()` agregar `'ultimo_acceso' => 'datetime'`.

- [ ] **Step 6: Verificar que pasa** — `php artisan test --filter=UltimoAccesoTest` y la suite completa (el middleware corre en todos los tests de rutas web; ninguno debe romperse).

- [ ] **Step 7: Commit**

```bash
git add database/migrations app bootstrap tests
git commit -m "feat: registrar la ultima conexion del usuario con escritura limitada (F10)"
```

---

### Task 4: Panel de seguimiento del instructor (F10, FE-4)

**Files:**
- Create: `database/migrations/2026_10_18_000003_add_umbrales_seguimiento_to_cursos_table.php`, `app/Services/SeguimientoService.php`, `app/Http/Controllers/SeguimientoController.php`, `resources/views/reportes/seguimiento.blade.php`
- Modify: `app/Models/Curso.php` (`$fillable` += `seguimiento_dias_sin_acceso`, `seguimiento_max_atrasadas`), `routes/web.php` (grupo `instructor`, junto a `reportes.*`), `routes/breadcrumbs.php`, `resources/views/reportes/curso.blade.php` (enlace "Seguimiento")
- Test: `tests/Feature/SeguimientoTest.php`

**Interfaces:**
- Consumes: `NotaCursoService::calcular(Collection $actividades, Collection $oficiales, ?int $aprobatoria, ?Collection $conEnvio, ?Collection $soloIds): array`, `CalificacionService::respuestasOficiales`, `Curso::todasLasActividades()` (Task 1), `users.ultimo_acceso` (Task 3).
- Produces: columnas `cursos.seguimiento_dias_sin_acceso` (unsignedTinyInteger, default 7) y `cursos.seguimiento_max_atrasadas` (unsignedTinyInteger, default 2); `SeguimientoService::para(Curso $curso): array{filas: Collection, resumen: array{ok:int, atencion:int, riesgo:int}, umbrales: array{dias_sin_acceso:int, max_atrasadas:int}}` donde cada fila es `{usuario, progreso_pct, promedio (?int), atrasadas (int), ultimo_acceso (?Carbon), senales (array de 'sin_acceso'|'atrasadas'|'nota_baja'), nivel ('ok'|'atencion'|'riesgo')}` ordenada de mayor a menor riesgo y luego por nombre; rutas `reportes.seguimiento` (`GET /reportes/cursos/{curso}/seguimiento`), `reportes.seguimiento.umbrales` (`PUT .../umbrales`), `reportes.seguimiento.csv` (`GET .../csv`).

- [ ] **Step 1: Test que falla**

```php
<?php

namespace Tests\Feature;

use App\Models\Curso;
use App\Models\Inscripcion;
use App\Models\ProgresoActividad;
use App\Services\SeguimientoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\ArmaActividades;
use Tests\Concerns\ArmaCursoCompleto;
use Tests\TestCase;

class SeguimientoTest extends TestCase
{
    use RefreshDatabase, ArmaCursoCompleto, ArmaActividades;

    private function curso(array $attrs = []): array
    {
        $inst  = $this->instructor();
        $curso = Curso::factory()->publicado()->create($attrs + ['created_by' => $inst->id, 'nota_aprobatoria' => 70]);

        return [$inst, $curso];
    }

    private function inscribir(Curso $curso, ?\Carbon\Carbon $ultimoAcceso, ?string $nombre = null)
    {
        $u = $this->estudiante();
        $u->forceFill(['ultimo_acceso' => $ultimoAcceso] + ($nombre ? ['name' => $nombre] : []))->save();
        Inscripcion::factory()->create(['user_id' => $u->id, 'curso_id' => $curso->id]);

        return $u;
    }

    private function fila(Curso $curso, $user)
    {
        return app(SeguimientoService::class)->para($curso)['filas']->firstWhere('usuario.id', $user->id);
    }

    public function test_estudiante_al_dia(): void
    {
        [, $curso] = $this->curso();
        $u = $this->inscribir($curso, now()->subHour());
        $a = $this->actividadEn($curso, ['puntaje_maximo' => 5, 'fecha_cierre' => now()->subDay()]);
        $this->calificar($u, $a, 5);

        $f = $this->fila($curso, $u);

        $this->assertSame('ok', $f->nivel);
        $this->assertSame([], $f->senales);
        $this->assertSame(100, $f->promedio);
    }

    public function test_sin_acceso_justo_en_el_umbral_y_nunca_entro(): void
    {
        [, $curso] = $this->curso(); // 7 dias por defecto
        $dentro  = $this->inscribir($curso, now()->subDays(7)->addMinute());
        $fuera   = $this->inscribir($curso, now()->subDays(7)->subMinute());
        $nunca   = $this->inscribir($curso, null);

        $this->assertSame([], $this->fila($curso, $dentro)->senales);
        $this->assertSame(['sin_acceso'], $this->fila($curso, $fuera)->senales);
        $this->assertSame(['sin_acceso'], $this->fila($curso, $nunca)->senales);
        $this->assertSame('atencion', $this->fila($curso, $nunca)->nivel);
    }

    public function test_atrasadas_en_el_umbral_y_dos_senales_son_riesgo(): void
    {
        [, $curso] = $this->curso(); // 2 atrasadas por defecto
        $u = $this->inscribir($curso, now()->subDays(20));
        $this->actividadEn($curso, ['fecha_cierre' => now()->subDays(2)]);
        $a2 = $this->actividadEn($curso, ['fecha_cierre' => now()->subDay()]);

        $f = $this->fila($curso, $u);
        $this->assertSame(2, $f->atrasadas);
        $this->assertEqualsCanonicalizing(['sin_acceso', 'atrasadas'], $f->senales);
        $this->assertSame('riesgo', $f->nivel);

        ProgresoActividad::create(['user_id' => $u->id, 'actividad_id' => $a2->id, 'completado' => true, 'fecha_completado' => now()]);
        $this->assertSame(1, $this->fila($curso, $u)->atrasadas); // 1 < 2: ya no es señal
    }

    public function test_nota_baja_solo_si_hay_algo_calificado(): void
    {
        [, $curso] = $this->curso();
        $sinNada = $this->inscribir($curso, now());
        $conNota = $this->inscribir($curso, now());
        $a = $this->actividadEn($curso, ['puntaje_maximo' => 5]);
        $this->actividadEn($curso, ['puntaje_maximo' => 5]);
        $this->calificar($conNota, $a, 2); // 40 % < 70

        $this->assertSame([], $this->fila($curso, $sinNada)->senales);
        $this->assertNull($this->fila($curso, $sinNada)->promedio);
        $this->assertSame(['nota_baja'], $this->fila($curso, $conNota)->senales);
    }

    public function test_umbrales_configurables_por_curso(): void
    {
        [, $curso] = $this->curso(['seguimiento_dias_sin_acceso' => 15]);
        $u = $this->inscribir($curso, now()->subDays(10));

        $this->assertSame([], $this->fila($curso, $u)->senales);
    }

    public function test_abandonados_y_otros_cursos_no_aparecen_y_curso_vacio_no_falla(): void
    {
        [$inst, $curso] = $this->curso();
        $u = $this->estudiante();
        Inscripcion::factory()->create(['user_id' => $u->id, 'curso_id' => $curso->id, 'estado' => 'abandonado']);
        [, $otro] = $this->curso();
        $this->inscribir($otro, now());

        $datos = app(SeguimientoService::class)->para($curso);

        $this->assertCount(0, $datos['filas']);
        $this->actingAs($inst)->get(route('reportes.seguimiento', $curso))->assertOk()
            ->assertSee('Aún no hay estudiantes inscritos');
    }

    public function test_cantidad_de_consultas_no_crece_con_los_estudiantes(): void
    {
        [, $curso] = $this->curso();
        $a = $this->actividadEn($curso, ['puntaje_maximo' => 5, 'fecha_cierre' => now()->subDay()]);
        foreach (range(1, 2) as $_) { $this->calificar($this->inscribir($curso, now()), $a, 3); }
        $servicio = app(SeguimientoService::class);

        DB::enableQueryLog();
        $servicio->para($curso);
        $con2 = count(DB::getQueryLog());

        foreach (range(1, 8) as $_) { $this->calificar($this->inscribir($curso, now()), $a, 3); }
        DB::flushQueryLog();
        $servicio->para($curso);
        $con10 = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertSame($con2, $con10);
    }

    public function test_pagina_muestra_semaforo_con_texto_y_resumen(): void
    {
        [$inst, $curso] = $this->curso();
        $this->inscribir($curso, null, 'Ana Riesgo');
        $this->inscribir($curso, now(), 'Beto AlDia');

        $this->actingAs($inst)->get(route('reportes.seguimiento', $curso))->assertOk()
            ->assertSee('Ana Riesgo')->assertSee('Beto AlDia')
            ->assertSee('Atención')->assertSee('Al día')->assertSee('Nunca ha ingresado');
    }

    public function test_error_del_servicio_muestra_mensaje_con_reintento_no_pantalla_en_blanco(): void
    {
        [$inst, $curso] = $this->curso();
        $this->mock(SeguimientoService::class, fn($m) => $m->shouldReceive('para')->andThrow(new \RuntimeException('boom')));

        $this->actingAs($inst)->get(route('reportes.seguimiento', $curso))->assertOk()
            ->assertSee('No se pudo cargar el seguimiento')->assertSee('Reintentar');
    }

    public function test_actualizar_umbrales_valida_y_guarda(): void
    {
        [$inst, $curso] = $this->curso();

        $this->actingAs($inst)->put(route('reportes.seguimiento.umbrales', $curso), [
            'seguimiento_dias_sin_acceso' => 14, 'seguimiento_max_atrasadas' => 3,
        ])->assertRedirect(route('reportes.seguimiento', $curso));
        $this->assertSame(14, $curso->fresh()->seguimiento_dias_sin_acceso);

        $this->actingAs($inst)->put(route('reportes.seguimiento.umbrales', $curso), [
            'seguimiento_dias_sin_acceso' => 0, 'seguimiento_max_atrasadas' => 99,
        ])->assertSessionHasErrors(['seguimiento_dias_sin_acceso', 'seguimiento_max_atrasadas']);
    }

    public function test_csv_con_tildes_y_sin_inyeccion_de_formulas(): void
    {
        [$inst, $curso] = $this->curso();
        $this->inscribir($curso, now(), '=HYPERLINK("http://x")');
        $this->inscribir($curso, now(), 'María Pérez');

        $r = $this->actingAs($inst)->get(route('reportes.seguimiento.csv', $curso))->assertOk();
        $csv = $r->streamedContent();

        $this->assertStringContainsString('text/csv', $r->headers->get('Content-Type'));
        $this->assertStringContainsString("'=HYPERLINK", $csv);
        $this->assertStringNotContainsString(',=HYPERLINK', $csv);
        $this->assertStringContainsString('María Pérez', $csv);
        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
    }

    public function test_solo_dueno_o_admin(): void
    {
        [, $curso] = $this->curso();

        foreach (['reportes.seguimiento', 'reportes.seguimiento.csv'] as $ruta) {
            $this->actingAs($this->instructor())->get(route($ruta, $curso))->assertForbidden();
            $this->actingAs($this->estudiante())->get(route($ruta, $curso))->assertForbidden();
        }
        $this->actingAs($this->admin())->get(route('reportes.seguimiento', $curso))->assertOk();
    }
}
```

- [ ] **Step 2: Verificar que falla** — `php artisan test --filter=SeguimientoTest`. Ajustar el test (no el servicio) solo si un helper necesita otro campo (p. ej. `forceFill` de `name`).

- [ ] **Step 3: Migración**

```php
Schema::table('cursos', function (Blueprint $table) {
    $table->unsignedTinyInteger('seguimiento_dias_sin_acceso')->default(7)->after('nota_aprobatoria');
    $table->unsignedTinyInteger('seguimiento_max_atrasadas')->default(2)->after('seguimiento_dias_sin_acceso');
});
// down(): $table->dropColumn(['seguimiento_dias_sin_acceso', 'seguimiento_max_atrasadas']);
```

(Estructura completa de clase anónima.) Agregar ambas columnas a `Curso::$fillable`.

- [ ] **Step 4: Servicio**

```php
<?php

namespace App\Services;

use App\Models\Curso;
use App\Models\Inscripcion;
use App\Models\ProgresoActividad;
use App\Models\ProgresoLeccion;
use App\Models\RespuestaEstudiante;
use Illuminate\Support\Collection;

class SeguimientoService
{
    public function __construct(private NotaCursoService $notas, private CalificacionService $calificaciones) {}

    public function para(Curso $curso): array
    {
        $diasSinAcceso = (int) ($curso->seguimiento_dias_sin_acceso ?? 7);
        $maxAtrasadas  = (int) ($curso->seguimiento_max_atrasadas ?? 2);
        $aprobatoria   = (int) ($curso->nota_aprobatoria ?? 80);

        $inscripciones = Inscripcion::with('usuario')->where('curso_id', $curso->id)
            ->where('estado', '!=', 'abandonado')->get()->filter(fn($i) => $i->usuario);
        $userIds = $inscripciones->pluck('user_id');

        $leccionIds     = $curso->lecciones()->pluck('lecciones.id');
        $totalLecciones = $leccionIds->count();
        $completadas    = $totalLecciones === 0 ? collect() : ProgresoLeccion::whereIn('user_id', $userIds)
            ->whereIn('leccion_id', $leccionIds)->where('completado', true)
            ->selectRaw('user_id, count(*) as n')->groupBy('user_id')->pluck('n', 'user_id');

        $todas        = $curso->todasLasActividades();
        $calificables = $todas->filter(fn($a) => $a->tieneCalificacion())->values();
        $vencidasIds  = $todas->filter(fn($a) => $a->fecha_cierre && $a->fecha_cierre->lt(now()))->pluck('id');

        $clave      = fn($r) => "{$r->user_id}-{$r->actividad_id}";
        $entregadas = RespuestaEstudiante::whereIn('user_id', $userIds)->whereIn('actividad_id', $vencidasIds)
            ->get(['user_id', 'actividad_id'])->map($clave)
            ->merge(ProgresoActividad::whereIn('user_id', $userIds)->whereIn('actividad_id', $vencidasIds)
                ->where('completado', true)->get(['user_id', 'actividad_id'])->map($clave))
            ->flip();

        $respuestas = RespuestaEstudiante::whereIn('user_id', $userIds)->whereIn('actividad_id', $calificables->pluck('id'))
            ->with('actividad')->get()->groupBy('user_id');

        $filas = $inscripciones->map(function ($insc) use (
            $curso, $calificables, $vencidasIds, $entregadas, $respuestas, $completadas, $totalLecciones,
            $diasSinAcceso, $maxAtrasadas, $aprobatoria
        ) {
            $u    = $insc->usuario;
            $mias = $respuestas->get($u->id, collect());
            $nota = $this->notas->calcular(
                $calificables,
                $this->calificaciones->respuestasOficiales($mias->where('estado', 'calificada'))->keyBy('actividad_id'),
                $aprobatoria,
                $mias->pluck('actividad_id')->flip(),
            );

            $atrasadas = $vencidasIds->filter(fn($id) => !isset($entregadas["{$u->id}-{$id}"]))->count();
            $senales = array_keys(array_filter([
                'sin_acceso' => $u->ultimo_acceso === null || $u->ultimo_acceso->lt(now()->subDays($diasSinAcceso)),
                'atrasadas'  => $atrasadas >= $maxAtrasadas,
                'nota_baja'  => !$nota['alcanzable']
                    || ($nota['promedio_a_la_fecha'] !== null && $nota['promedio_a_la_fecha'] < $aprobatoria),
            ]));

            return (object) [
                'usuario'       => $u,
                'progreso_pct'  => $totalLecciones > 0 ? (int) round(($completadas[$u->id] ?? 0) / $totalLecciones * 100) : null,
                'promedio'      => $nota['promedio_a_la_fecha'],
                'atrasadas'     => $atrasadas,
                'ultimo_acceso' => $u->ultimo_acceso,
                'senales'       => $senales,
                'nivel'         => match (count($senales)) { 0 => 'ok', 1 => 'atencion', default => 'riesgo' },
            ];
        })->sortBy(fn($f) => [['ok' => 2, 'atencion' => 1, 'riesgo' => 0][$f->nivel], $f->usuario->name])->values();

        return [
            'filas'    => $filas,
            'resumen'  => [
                'ok'       => $filas->where('nivel', 'ok')->count(),
                'atencion' => $filas->where('nivel', 'atencion')->count(),
                'riesgo'   => $filas->where('nivel', 'riesgo')->count(),
            ],
            'umbrales' => ['dias_sin_acceso' => $diasSinAcceso, 'max_atrasadas' => $maxAtrasadas],
        ];
    }
}
```

Nota para el implementador: `calcular()` recibe `$actividades` = todas las calificables del curso (define modo y pesos); `User::ultimo_acceso` ya es `datetime` (Task 3). El test de constancia de consultas (`test_cantidad_de_consultas_no_crece...`) es el que protege RNF-2: si falla, el servicio hace una consulta por estudiante y debe agruparse.

- [ ] **Step 5: Controlador**

```php
<?php

namespace App\Http\Controllers;

use App\Models\Curso;
use App\Services\SeguimientoService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class SeguimientoController extends Controller
{
    private const NIVELES = ['ok' => 'Al día', 'atencion' => 'Atención', 'riesgo' => 'En riesgo'];
    private const SENALES = ['sin_acceso' => 'Sin ingreso reciente', 'atrasadas' => 'Entregas atrasadas', 'nota_baja' => 'Nota por debajo de la mínima'];

    public function index(Curso $curso, SeguimientoService $servicio)
    {
        $this->authorize('update', $curso);

        try {
            $datos = $servicio->para($curso);
            $error = false;
        } catch (\Throwable $e) {
            report($e);
            $datos = null;
            $error = true;
        }

        return view('reportes.seguimiento', ['curso' => $curso, 'datos' => $datos, 'error' => $error,
            'niveles' => self::NIVELES, 'senales' => self::SENALES]);
    }

    public function umbrales(Request $request, Curso $curso)
    {
        $this->authorize('update', $curso);

        $curso->update($request->validate([
            'seguimiento_dias_sin_acceso' => 'required|integer|min:1|max:90',
            'seguimiento_max_atrasadas'   => 'required|integer|min:1|max:20',
        ]));

        return redirect()->route('reportes.seguimiento', $curso)->with('success', 'Umbrales guardados.');
    }

    public function csv(Curso $curso, SeguimientoService $servicio)
    {
        $this->authorize('update', $curso);
        $filas = $servicio->para($curso)['filas'];

        return response()->streamDownload(function () use ($filas) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Estudiante', 'Correo', 'Progreso %', 'Promedio %', 'Entregas atrasadas', 'Último acceso', 'Nivel', 'Señales']);
            foreach ($filas as $f) {
                fputcsv($out, array_map([$this, 'celda'], [
                    $f->usuario->name, $f->usuario->email, $f->progreso_pct, $f->promedio, $f->atrasadas,
                    $f->ultimo_acceso?->format('Y-m-d H:i'), self::NIVELES[$f->nivel],
                    implode('; ', array_map(fn($s) => self::SENALES[$s], $f->senales)),
                ]));
            }
            fclose($out);
        }, 'seguimiento-' . Str::slug($curso->titulo) . '.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** Evita inyección de fórmulas al abrir el CSV en Excel/Sheets. */
    private function celda(mixed $valor): string
    {
        $texto = (string) $valor;

        return preg_match('/^[=+\-@\t\r]/', $texto) ? "'" . $texto : $texto;
    }
}
```

- [ ] **Step 6: Rutas y migas.** En el grupo `instructor` de `routes/web.php`, junto a `reportes.curso`:

```php
        Route::get('/reportes/cursos/{curso}/seguimiento', [\App\Http\Controllers\SeguimientoController::class, 'index'])->name('reportes.seguimiento');
        Route::put('/reportes/cursos/{curso}/seguimiento/umbrales', [\App\Http\Controllers\SeguimientoController::class, 'umbrales'])->name('reportes.seguimiento.umbrales');
        Route::get('/reportes/cursos/{curso}/seguimiento/csv', [\App\Http\Controllers\SeguimientoController::class, 'csv'])->name('reportes.seguimiento.csv');
```

`routes/breadcrumbs.php` (después de `reportes.curso`, verificando su nombre exacto): `Breadcrumbs::for('reportes.seguimiento', fn(Trail $t, $curso) => $t->parent('reportes.curso', $curso)->push('Seguimiento'));`. En `reportes/curso.blade.php`, junto a los botones de exportar, un enlace "Seguimiento" a `route('reportes.seguimiento', $curso)` con el mismo estilo.

- [ ] **Step 7: Vista** `reportes/seguimiento.blade.php`

```blade
@extends('layouts.app')
@section('title', 'Seguimiento - ' . $curso->titulo)
@section('breadcrumbs'){{ Breadcrumbs::render('reportes.seguimiento', $curso) }}@endsection
@section('content')
<div class="mb-6 flex flex-wrap items-center justify-between gap-3">
    <div>
        <h1 class="text-2xl font-bold text-gray-900">Seguimiento</h1>
        <p class="text-sm text-gray-500">{{ $curso->titulo }}</p>
    </div>
    @if(!$error && $datos && $datos['filas']->isNotEmpty())
        <a href="{{ route('reportes.seguimiento.csv', $curso) }}" class="rounded-lg border border-gray-300 px-4 py-2 text-sm hover:bg-gray-50">Exportar CSV</a>
    @endif
</div>

@if($error)
    <div class="alert alert-error" role="alert">
        No se pudo cargar el seguimiento.
        <a href="{{ route('reportes.seguimiento', $curso) }}" class="font-medium underline">Reintentar</a>
    </div>
@else
    <div class="mb-6 grid grid-cols-3 gap-4">
        @foreach(['riesgo' => 'text-red-700', 'atencion' => 'text-dyl-orange-600', 'ok' => 'text-dyl-graphite-700'] as $nivel => $color)
            <div class="rounded-xl bg-white p-4 text-center shadow">
                <p class="text-xs uppercase tracking-wide text-gray-400">{{ $niveles[$nivel] }}</p>
                <p class="mt-1 text-3xl font-bold {{ $color }}">{{ $datos['resumen'][$nivel] }}</p>
            </div>
        @endforeach
    </div>

    <form method="POST" action="{{ route('reportes.seguimiento.umbrales', $curso) }}" class="mb-6 flex flex-wrap items-end gap-3 rounded-xl bg-white p-4 shadow text-sm">
        @csrf @method('PUT')
        <label class="text-gray-700">Días sin ingresar
            <input type="number" min="1" max="90" name="seguimiento_dias_sin_acceso" value="{{ old('seguimiento_dias_sin_acceso', $datos['umbrales']['dias_sin_acceso']) }}" class="mt-1 block w-24 rounded-lg border-gray-300 text-sm">
        </label>
        <label class="text-gray-700">Entregas atrasadas (mínimo)
            <input type="number" min="1" max="20" name="seguimiento_max_atrasadas" value="{{ old('seguimiento_max_atrasadas', $datos['umbrales']['max_atrasadas']) }}" class="mt-1 block w-24 rounded-lg border-gray-300 text-sm">
        </label>
        <button class="rounded-lg bg-gray-100 px-4 py-2 hover:bg-gray-200">Guardar umbrales</button>
        @error('seguimiento_dias_sin_acceso')<span class="text-xs text-red-600">{{ $message }}</span>@enderror
        @error('seguimiento_max_atrasadas')<span class="text-xs text-red-600">{{ $message }}</span>@enderror
    </form>

    @if($datos['filas']->isEmpty())
        <div class="rounded-xl bg-white p-8 text-center text-gray-500 shadow">Aún no hay estudiantes inscritos.</div>
    @else
        <div class="overflow-x-auto rounded-xl bg-white shadow">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500">
                    <tr>
                        <th class="px-4 py-3">Estudiante</th><th class="px-4 py-3">Nivel</th><th class="px-4 py-3">Progreso</th>
                        <th class="px-4 py-3">Promedio</th><th class="px-4 py-3">Atrasadas</th><th class="px-4 py-3">Último acceso</th><th class="px-4 py-3">Señales</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach($datos['filas'] as $f)
                        <tr>
                            <td class="px-4 py-3 text-gray-900">{{ $f->usuario->name }}</td>
                            <td class="px-4 py-3">
                                <span class="rounded-full px-2.5 py-0.5 text-xs font-medium
                                    {{ $f->nivel === 'riesgo' ? 'bg-red-100 text-red-800' : ($f->nivel === 'atencion' ? 'bg-dyl-orange-100 text-dyl-orange-800' : 'bg-dyl-graphite-100 text-dyl-graphite-700') }}">
                                    {{ $niveles[$f->nivel] }}
                                </span>
                            </td>
                            <td class="px-4 py-3">{{ $f->progreso_pct === null ? '—' : $f->progreso_pct . '%' }}</td>
                            <td class="px-4 py-3">{{ $f->promedio === null ? '—' : $f->promedio . '%' }}</td>
                            <td class="px-4 py-3">{{ $f->atrasadas }}</td>
                            <td class="px-4 py-3">{{ $f->ultimo_acceso ? $f->ultimo_acceso->format('d/m/Y H:i') : 'Nunca ha ingresado' }}</td>
                            <td class="px-4 py-3 text-xs text-gray-600">
                                @forelse($f->senales as $s){{ $senales[$s] }}@if(!$loop->last) · @endif @empty — @endforelse
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
@endif
@endsection
```

- [ ] **Step 8: Verificar que pasa** — `php artisan test --filter=SeguimientoTest`, `--filter=Reporte`, `--filter=NotaCurso` y la suite completa.

- [ ] **Step 9: Commit**

```bash
git add database/migrations app routes resources tests
git commit -m "feat: panel de seguimiento del instructor con semaforo de riesgo (F10)"
```

---

### Task 5: Modo de edición en la vista del curso (F11)

**Files:**
- Create: `app/Http/Controllers/ModoEdicionController.php`
- Modify: `routes/web.php` (grupo `instructor`), `resources/views/cursos/show.blade.php` (interruptor y controles)
- Test: `tests/Feature/ModoEdicionTest.php`

**Interfaces:**
- Produces: ruta `cursos.modo-edicion` (`POST /cursos/{curso}/modo-edicion`, alterna la clave de sesión `modo_edicion` y vuelve a `cursos.show`); en `cursos.show`, variable local `$modoEdicion` = (dueño o admin) **y** sesión activa.
- Consumes: rutas existentes `modulos.edit`, `lecciones.create` (`/modulos/{modulo}/lecciones/crear`), `lecciones.edit`, `actividades.create` (`/lecciones/{leccion}/actividades/crear`), `actividades.edit`, `cursos.edit`, `cursos.ponderaciones.edit`, `cursos.ficha.edit`, `cursos.competencias.index`, `reportes.seguimiento`.

- [ ] **Step 1: Test que falla**

```php
<?php

namespace Tests\Feature;

use App\Models\Actividad;
use App\Models\Curso;
use App\Models\Inscripcion;
use App\Models\Leccion;
use App\Models\Modulo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ArmaCursoCompleto;
use Tests\TestCase;

class ModoEdicionTest extends TestCase
{
    use RefreshDatabase, ArmaCursoCompleto;

    private function escenario(): array
    {
        $inst  = $this->instructor();
        $curso = Curso::factory()->publicado()->create(['created_by' => $inst->id]);
        $modulo  = Modulo::factory()->create(['curso_id' => $curso->id]);
        $leccion = Leccion::factory()->create(['modulo_id' => $modulo->id]);
        $actividad = Actividad::factory()->tarea()->create(['leccion_id' => $leccion->id, 'titulo' => 'Mi tarea']);

        return [$inst, $curso, $modulo, $leccion, $actividad];
    }

    public function test_apagado_el_instructor_ve_el_interruptor_pero_ningun_control(): void
    {
        [$inst, $curso, $modulo, $leccion] = $this->escenario();

        $html = $this->actingAs($inst)->get(route('cursos.show', $curso))->assertOk()
            ->assertSee('Activar modo de edición')->getContent();

        $this->assertStringNotContainsString(route('lecciones.edit', $leccion), $html);
        $this->assertStringNotContainsString(route('modulos.edit', $modulo), $html);
        $this->assertStringNotContainsString(route('actividades.create', $leccion), $html);
    }

    public function test_encendido_aparecen_los_controles_y_se_recuerda_en_la_sesion(): void
    {
        [$inst, $curso, $modulo, $leccion, $actividad] = $this->escenario();

        $this->actingAs($inst)->post(route('cursos.modo-edicion', $curso))->assertRedirect(route('cursos.show', $curso));

        $html = $this->actingAs($inst)->get(route('cursos.show', $curso))->assertOk()
            ->assertSee('Salir del modo de edición')->getContent();
        foreach ([route('modulos.edit', $modulo), route('lecciones.create', $modulo), route('lecciones.edit', $leccion),
                  route('actividades.create', $leccion), route('actividades.edit', $actividad),
                  route('cursos.ponderaciones.edit', $curso), route('cursos.competencias.index', $curso),
                  route('cursos.ficha.edit', $curso), route('reportes.seguimiento', $curso)] as $url) {
            $this->assertStringContainsString($url, $html);
        }
        $this->assertStringContainsString('Mi tarea', $html);

        $this->actingAs($inst)->post(route('cursos.modo-edicion', $curso));
        $this->actingAs($inst)->get(route('cursos.show', $curso))->assertSee('Activar modo de edición');
    }

    public function test_el_estudiante_nunca_ve_controles_aunque_la_sesion_tenga_la_bandera(): void
    {
        [$inst, $curso, $modulo, $leccion] = $this->escenario();
        $est = $this->estudiante();
        Inscripcion::factory()->create(['user_id' => $est->id, 'curso_id' => $curso->id]);

        $html = $this->actingAs($est)->withSession(['modo_edicion' => true])->get(route('cursos.show', $curso))->assertOk()
            ->assertDontSee('modo de edición')->getContent();

        $this->assertStringNotContainsString(route('lecciones.edit', $leccion), $html);
        $this->assertStringNotContainsString(route('modulos.edit', $modulo), $html);
    }

    public function test_otro_instructor_y_estudiante_no_pueden_alternarlo(): void
    {
        [, $curso] = $this->escenario();

        $this->actingAs($this->instructor())->post(route('cursos.modo-edicion', $curso))->assertForbidden();
        $this->actingAs($this->estudiante())->post(route('cursos.modo-edicion', $curso))->assertForbidden();
    }

    public function test_un_instructor_con_la_bandera_en_curso_ajeno_no_ve_controles(): void
    {
        [, $curso, $modulo] = $this->escenario();
        $otro = $this->instructor();

        $html = $this->actingAs($otro)->withSession(['modo_edicion' => true])->get(route('cursos.show', $curso))->getContent();

        $this->assertStringNotContainsString(route('modulos.edit', $modulo), $html);
    }

    public function test_admin_puede_usarlo(): void
    {
        [, $curso, $modulo] = $this->escenario();
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('cursos.modo-edicion', $curso))->assertRedirect();
        $this->actingAs($admin)->get(route('cursos.show', $curso))->assertSee(route('modulos.edit', $modulo), false);
    }

    public function test_requiere_sesion(): void
    {
        [, $curso] = $this->escenario();

        $this->post(route('cursos.modo-edicion', $curso))->assertRedirect(route('login'));
    }
}
```

(Si `cursos.show` autoriza a un instructor ajeno solo por estar el curso publicado, el test `test_un_instructor_con_la_bandera...` sigue siendo válido; si respondiera 403 en lugar de 200, ajustar solo la aserción de acceso, no la de ausencia de controles.)

- [ ] **Step 2: Verificar que falla** — `php artisan test --filter=ModoEdicionTest`.

- [ ] **Step 3: Controlador y ruta**

```php
<?php

namespace App\Http\Controllers;

use App\Models\Curso;
use Illuminate\Http\Request;

class ModoEdicionController extends Controller
{
    public function alternar(Request $request, Curso $curso)
    {
        $this->authorize('update', $curso);

        $request->session()->put('modo_edicion', !$request->session()->get('modo_edicion', false));

        return redirect()->route('cursos.show', $curso);
    }
}
```

En `routes/web.php`, grupo `instructor`: `Route::post('/cursos/{curso}/modo-edicion', [\App\Http\Controllers\ModoEdicionController::class, 'alternar'])->name('cursos.modo-edicion');`

- [ ] **Step 4: Vista.** En `cursos/show.blade.php`, justo antes de `<h2 ...>Contenido del curso</h2>`:

```blade
@php
    $puedeEditar = auth()->user()->esAdmin() || auth()->id() === $curso->created_by;
    $modoEdicion = $puedeEditar && session('modo_edicion', false);
@endphp

<div class="mb-4 flex flex-wrap items-center justify-between gap-3">
    <h2 class="text-xl font-bold text-gray-900">Contenido del curso</h2>
    @if($puedeEditar)
        <form method="POST" action="{{ route('cursos.modo-edicion', $curso) }}">
            @csrf
            <button class="rounded-lg border px-4 py-2 text-sm font-medium {{ $modoEdicion ? 'border-dyl-orange-600 bg-dyl-orange-50 text-dyl-orange-800' : 'border-gray-300 text-gray-700 hover:bg-gray-50' }}">
                {{ $modoEdicion ? 'Salir del modo de edición' : 'Activar modo de edición' }}
            </button>
        </form>
    @endif
</div>

@if($modoEdicion)
    <div class="alert alert-warning mb-4" role="status">
        Modo de edición activo: ves los controles para editar el curso. Los estudiantes ven la vista normal.
        <span class="ml-2 inline-flex flex-wrap gap-3 text-sm font-medium">
            <a class="underline" href="{{ route('cursos.edit', $curso) }}">Editar curso</a>
            <a class="underline" href="{{ route('cursos.ponderaciones.edit', $curso) }}">Ponderaciones</a>
            <a class="underline" href="{{ route('cursos.ficha.edit', $curso) }}">Ficha académica</a>
            <a class="underline" href="{{ route('cursos.competencias.index', $curso) }}">Competencias</a>
            <a class="underline" href="{{ route('reportes.seguimiento', $curso) }}">Seguimiento</a>
        </span>
    </div>
@endif
```

y **eliminar** el `<h2 class="text-xl font-bold text-gray-900 mb-4">Contenido del curso</h2>` original (queda reemplazado por el del bloque de arriba). Dentro del bucle de módulos, en la cabecera de cada módulo (fuera del `<button>` del acordeón, justo debajo de él, para no anidar controles interactivos), agregar:

```blade
        @if($modoEdicion)
            <div class="flex flex-wrap gap-3 border-t border-gray-100 bg-dyl-orange-50 px-5 py-2 text-xs font-medium">
                <a class="text-dyl-orange-800 hover:underline" href="{{ route('modulos.edit', $modulo) }}">Editar módulo</a>
                <a class="text-dyl-orange-800 hover:underline" href="{{ route('lecciones.create', $modulo) }}">+ Lección</a>
            </div>
        @endif
```

y dentro del bucle de lecciones, debajo del bloque `<div class="flex-1 min-w-0">` de cada lección (todavía dentro de su fila), agregar:

```blade
                    @if($modoEdicion)
                        <div class="ml-3 flex flex-shrink-0 flex-wrap items-center gap-2 text-xs font-medium">
                            <a class="text-dyl-orange-800 hover:underline" href="{{ route('lecciones.edit', $leccion) }}">Editar</a>
                            <a class="text-dyl-orange-800 hover:underline" href="{{ route('actividades.create', $leccion) }}">+ Actividad</a>
                            @foreach($leccion->actividades as $act)
                                <a class="rounded bg-gray-100 px-1.5 py-0.5 text-gray-700 hover:bg-gray-200" href="{{ route('actividades.edit', $act) }}">{{ $act->tituloCompleto() }}</a>
                            @endforeach
                        </div>
                    @endif
```

(Abrir el archivo y ubicar los puntos de inserción respetando el HTML existente; no reorganizar nada más. El botón "Editar curso" que ya existe en el panel superior se conserva.)

- [ ] **Step 5: Verificar que pasa** — `php artisan test --filter=ModoEdicionTest`, `--filter=Curso`, `--filter=FichaCurso`, `--filter=CompetenciasAvance` y la suite completa.

- [ ] **Step 6: Commit**

```bash
git add app routes resources tests
git commit -m "feat: modo de edicion del instructor en la vista del curso (F11)"
```

---

## Verificación final del sprint

- [ ] `php artisan test` completo en verde (nunca `migrate:fresh` en la BD local).
- [ ] Revisión manual con la BD local migrada (`php artisan migrate`): como instructor crear competencias y enlazar actividades; como estudiante ver el avance en el curso; abrir `/reportes/cursos/{id}/seguimiento` (semáforo, umbrales, CSV abierto en Excel con un nombre que empiece por `=`); alternar el modo de edición y recorrer los enlaces de módulo/lección/actividad; comprobar que `users.ultimo_acceso` cambia solo cada 10 minutos.
- [ ] Ninguna página muestra `{{`, `}}` ni encabezados vacíos con datos faltantes.

## Self-Review (spec ↔ plan)

| Spec | Cubierto en | Nota |
|---|---|---|
| F7 CA1 (enlazar actividad refleja avance al calificarla) | Task 2 | avance = entregadas + nota media de las calificadas |
| F7 CA2 (estudiante sin competencias no ve la pestaña) | Task 2 | sección oculta |
| F7 CA3 (instructor sin competencias ve guía y botón) | Task 1 | FE-6 |
| F7 "filtro por actividad" | — | omitido (Decisión 3) |
| F10 CA1 (2 atrasadas → riesgo) | Task 4 | con dos señales; una sola atrasada-umbral = Atención |
| F10 CA2 (error con reintento) | Task 4 | FE-4 |
| F10 CA3 (curso sin estudiantes) | Task 4 | "Aún no hay estudiantes inscritos" |
| F10 umbrales configurables, exportar CSV | Task 4 | CSV protegido contra fórmulas; Excel ya existe en reportes |
| F10 "última conexión" | Task 3 | columna nueva |
| F11 CA (apagado = vista de estudiante) | Task 5 | controles ausentes del HTML |
| RNF-2 sin N+1 | Task 4 | test de constancia de consultas |

Consistencia de nombres revisada: `Curso::todasLasActividades/competencias`, `Competencia::actividades`, `CompetenciasService::avance`, `LineaDeTiempoService::idsEntregados` (público), `SeguimientoService::para` (claves `filas`, `resumen`, `umbrales`; campos de fila `usuario`, `progreso_pct`, `promedio`, `atrasadas`, `ultimo_acceso`, `senales`, `nivel`), rutas `cursos.competencias.*`, `reportes.seguimiento*`, `cursos.modo-edicion`, clave de sesión `modo_edicion`.

## Requiere acción de ops

- `php artisan migrate` (3 migraciones nuevas) tras el merge.
- Ninguna otra: el seguimiento no necesita scheduler; `ultimo_acceso` se llena con el uso normal (los estudiantes figurarán "Nunca ha ingresado" hasta su primera visita posterior al despliegue — conviene avisarlo a los instructores).

## Fuera de este plan

Filtro de competencias por actividad; competencias exportadas/importadas con la plantilla del curso; envío automático de alertas por riesgo al instructor; gráficas históricas del seguimiento; modo de edición con arrastrar y soltar en la vista del curso (ya existe en `cursos/edit`).
