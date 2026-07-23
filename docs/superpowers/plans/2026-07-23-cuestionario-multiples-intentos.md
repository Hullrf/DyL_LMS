# Múltiples Intentos para Cuestionarios Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Permitir que el instructor configure, por actividad de tipo `cuestionario`, cuántos intentos puede realizar cada estudiante (1 o más), qué calificación cuenta cuando hay varios intentos (más alto o último), y si el estudiante ve el historial de sus intentos anteriores — sin afectar el comportamiento de otros tipos de actividad ni duplicar puntaje en reportes/certificados.

**Architecture:** Tres columnas nuevas en `actividades` gobiernan el comportamiento por actividad. `RespuestaEstudianteController::store()` gana una rama específica para `cuestionario` que cuenta intentos en vez de bloquear tras el primero. Un helper centralizado en `CalificacionService` (`respuestasOficiales()`) deduplica cualquier colección de respuestas a una fila por `(user_id, actividad_id)` según la política de la actividad, y se reutiliza en los cuatro puntos que hoy suman calificaciones sin deduplicar (dos en `ReporteService`, uno en `CertificadoService`, uno en `CalificacionController`).

**Tech Stack:** Laravel 12, PHP 8.2, SQLite en memoria para tests (`phpunit.xml`), Blade + Alpine.js para las vistas, PHPUnit vía `php artisan test`.

## Global Constraints

- Los tipos de actividad distintos a `cuestionario` (`ensayo`, `tarea`, `practica`) **no cambian de comportamiento**: siguen limitados a una sola entrega, sin importar el valor de `intentos_permitidos`.
- Los defaults de las columnas nuevas (`intentos_permitidos=1`, `criterio_calificacion_intentos='mas_alto'`, `mostrar_historial_intentos=true`) deben preservar exactamente el comportamiento actual para toda actividad existente.
- No se implementa la opción de "intentos ilimitados" — siempre es un entero fijo entre 1 y 20.
- Cada intento se guarda como una fila nueva en `respuestas_estudiantes` — nunca se sobrescribe un intento anterior.
- Todos los comandos de test se ejecutan desde `C:\xampp\htdocs\LMS_DyL\lms-dyl-quality` con `php artisan test --filter=<Nombre>`.

---

### Task 1: Migración y modelo `Actividad`

**Files:**
- Create: `database/migrations/2026_07_23_100000_add_intentos_to_actividades_table.php`
- Modify: `app/Models/Actividad.php:22-36` (fillable/casts), agregar método nuevo tras `tieneCalificacion()` (línea 137)
- Test: `tests/Unit/ActividadIntentosTest.php`

**Interfaces:**
- Produces: columnas `Actividad::$intentos_permitidos` (int), `Actividad::$criterio_calificacion_intentos` (string `'mas_alto'|'ultimo'`), `Actividad::$mostrar_historial_intentos` (bool); método `Actividad::permiteMultiplesIntentos(): bool`.

- [ ] **Step 1: Escribir el test que falla**

Crear `tests/Unit/ActividadIntentosTest.php`:

```php
<?php

namespace Tests\Unit;

use App\Models\Actividad;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ActividadIntentosTest extends TestCase
{
    use RefreshDatabase;

    public function test_actividad_nueva_tiene_un_intento_permitido_por_defecto(): void
    {
        $actividad = Actividad::factory()->create(['tipo' => 'cuestionario']);

        $this->assertEquals(1, $actividad->intentos_permitidos);
        $this->assertEquals('mas_alto', $actividad->criterio_calificacion_intentos);
        $this->assertTrue($actividad->mostrar_historial_intentos);
    }

    public function test_permite_multiples_intentos_es_falso_con_un_intento(): void
    {
        $actividad = Actividad::factory()->create(['tipo' => 'cuestionario', 'intentos_permitidos' => 1]);

        $this->assertFalse($actividad->permiteMultiplesIntentos());
    }

    public function test_permite_multiples_intentos_es_verdadero_con_mas_de_uno(): void
    {
        $actividad = Actividad::factory()->create(['tipo' => 'cuestionario', 'intentos_permitidos' => 3]);

        $this->assertTrue($actividad->permiteMultiplesIntentos());
    }

    public function test_permite_multiples_intentos_es_falso_para_otros_tipos_aunque_el_campo_sea_mayor_a_uno(): void
    {
        $actividad = Actividad::factory()->create(['tipo' => 'tarea', 'intentos_permitidos' => 5]);

        $this->assertFalse($actividad->permiteMultiplesIntentos());
    }
}
```

- [ ] **Step 2: Ejecutar el test para confirmar que falla**

Run: `php artisan test --filter=ActividadIntentosTest`
Expected: FAIL — columna `intentos_permitidos` no existe / método `permiteMultiplesIntentos` no definido.

- [ ] **Step 3: Crear la migración**

Crear `database/migrations/2026_07_23_100000_add_intentos_to_actividades_table.php`:

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
            $table->unsignedTinyInteger('intentos_permitidos')->default(1)->after('puntaje_maximo');
            $table->enum('criterio_calificacion_intentos', ['mas_alto', 'ultimo'])->default('mas_alto')->after('intentos_permitidos');
            $table->boolean('mostrar_historial_intentos')->default(true)->after('criterio_calificacion_intentos');
        });
    }

    public function down(): void
    {
        Schema::table('actividades', function (Blueprint $table) {
            $table->dropColumn(['intentos_permitidos', 'criterio_calificacion_intentos', 'mostrar_historial_intentos']);
        });
    }
};
```

- [ ] **Step 4: Modificar el modelo `Actividad`**

En `app/Models/Actividad.php`, reemplazar el bloque `$fillable`/`$casts` (líneas 22-36):

```php
    protected $table = 'actividades';
    protected $fillable = [
        'leccion_id', 'tipo', 'titulo', 'descripcion',
        'orden', 'puntaje_maximo', 'duracion_minutos', 'es_obligatoria',
        'fecha_apertura', 'fecha_cierre', 'usa_rubrica',
        'permitir_descarga_adjuntos',
        'intentos_permitidos', 'criterio_calificacion_intentos', 'mostrar_historial_intentos',
    ];

    protected $casts = [
        'fecha_apertura'              => 'datetime',
        'fecha_cierre'                => 'datetime',
        'es_obligatoria'              => 'boolean',
        'usa_rubrica'                 => 'boolean',
        'permitir_descarga_adjuntos'  => 'boolean',
        'puntaje_maximo'              => 'decimal:2',
        'mostrar_historial_intentos'  => 'boolean',
    ];
```

Y agregar el método nuevo justo después de `tieneCalificacion()` (tras la línea 137, antes del `}` de cierre de la clase):

```php
    public function tieneCalificacion(): bool
    {
        return !in_array($this->tipo, self::TIPOS_SIN_NOTA);
    }

    public function permiteMultiplesIntentos(): bool
    {
        return $this->tipo === 'cuestionario' && $this->intentos_permitidos > 1;
    }
}
```

- [ ] **Step 5: Ejecutar el test para confirmar que pasa**

Run: `php artisan test --filter=ActividadIntentosTest`
Expected: PASS (4 tests)

- [ ] **Step 6: Commit**

```bash
git add database/migrations/2026_07_23_100000_add_intentos_to_actividades_table.php app/Models/Actividad.php tests/Unit/ActividadIntentosTest.php
git commit -m "Agrega columnas de intentos a actividades y helper permiteMultiplesIntentos()"
```

---

### Task 2: `CalificacionService::respuestasOficiales()`

**Files:**
- Modify: `app/Services/CalificacionService.php:1-9` (imports/namespace), agregar método nuevo tras `calificarConRubrica()` (fin de archivo, línea 120)
- Test: `tests/Unit/CalificacionServiceTest.php:1-13` (imports), agregar métodos de test al final de la clase

**Interfaces:**
- Consumes: `Actividad::$criterio_calificacion_intentos` (Task 1).
- Produces: `CalificacionService::respuestasOficiales(Collection $respuestas): Collection` — recibe respuestas con `actividad` cargada (eager o ya asignada vía `setRelation`), devuelve una respuesta por cada par `(user_id, actividad_id)`.

- [ ] **Step 1: Escribir los tests que fallan**

En `tests/Unit/CalificacionServiceTest.php`, agregar `use App\Models\User;` al bloque de imports (tras `use App\Models\RespuestaEstudiante;`) y agregar estos métodos al final de la clase, antes del `}` de cierre:

```php
    public function test_respuestas_oficiales_elige_el_mas_alto_por_defecto(): void
    {
        $actividad = Actividad::factory()->create(['tipo' => 'cuestionario', 'criterio_calificacion_intentos' => 'mas_alto']);
        $usuario   = User::factory()->create();

        RespuestaEstudiante::factory()->create([
            'user_id' => $usuario->id, 'actividad_id' => $actividad->id,
            'calificacion' => 60, 'estado' => 'calificada', 'fecha_envio' => now()->subDay(),
        ]);
        RespuestaEstudiante::factory()->create([
            'user_id' => $usuario->id, 'actividad_id' => $actividad->id,
            'calificacion' => 90, 'estado' => 'calificada', 'fecha_envio' => now(),
        ]);

        $respuestas = RespuestaEstudiante::with('actividad')->get();
        $oficiales  = $this->service->respuestasOficiales($respuestas);

        $this->assertCount(1, $oficiales);
        $this->assertEquals(90, $oficiales->first()->calificacion);
    }

    public function test_respuestas_oficiales_elige_el_ultimo_cuando_la_politica_lo_indica(): void
    {
        $actividad = Actividad::factory()->create(['tipo' => 'cuestionario', 'criterio_calificacion_intentos' => 'ultimo']);
        $usuario   = User::factory()->create();

        RespuestaEstudiante::factory()->create([
            'user_id' => $usuario->id, 'actividad_id' => $actividad->id,
            'calificacion' => 90, 'estado' => 'calificada', 'fecha_envio' => now()->subDay(),
        ]);
        $masReciente = RespuestaEstudiante::factory()->create([
            'user_id' => $usuario->id, 'actividad_id' => $actividad->id,
            'calificacion' => 60, 'estado' => 'calificada', 'fecha_envio' => now(),
        ]);

        $respuestas = RespuestaEstudiante::with('actividad')->get();
        $oficiales  = $this->service->respuestasOficiales($respuestas);

        $this->assertCount(1, $oficiales);
        $this->assertEquals($masReciente->id, $oficiales->first()->id);
        $this->assertEquals(60, $oficiales->first()->calificacion);
    }

    public function test_respuestas_oficiales_mantiene_separadas_distintas_actividades(): void
    {
        $usuario = User::factory()->create();
        $act1 = Actividad::factory()->create(['tipo' => 'cuestionario']);
        $act2 = Actividad::factory()->create(['tipo' => 'cuestionario']);

        RespuestaEstudiante::factory()->create(['user_id' => $usuario->id, 'actividad_id' => $act1->id, 'calificacion' => 70, 'estado' => 'calificada']);
        RespuestaEstudiante::factory()->create(['user_id' => $usuario->id, 'actividad_id' => $act2->id, 'calificacion' => 80, 'estado' => 'calificada']);

        $respuestas = RespuestaEstudiante::with('actividad')->get();
        $oficiales  = $this->service->respuestasOficiales($respuestas);

        $this->assertCount(2, $oficiales);
    }
```

- [ ] **Step 2: Ejecutar los tests para confirmar que fallan**

Run: `php artisan test --filter=CalificacionServiceTest`
Expected: FAIL — método `respuestasOficiales` no existe en `CalificacionService`.

- [ ] **Step 3: Implementar el método**

En `app/Services/CalificacionService.php`, agregar el import y el método. Reemplazar la línea de imports (línea 7):

```php
use App\Models\RespuestaEstudiante;
use Illuminate\Support\Collection;
```

Y agregar el método al final de la clase, tras `calificarConRubrica()` (antes del `}` de cierre en la línea 121):

```php
    /**
     * Dado un conjunto de respuestas (con 'actividad' cargada), devuelve una sola
     * respuesta por cada par (user_id, actividad_id), eligiendo según la política
     * de esa actividad: 'mas_alto' -> mayor calificación, 'ultimo' -> fecha_envio más reciente.
     */
    public function respuestasOficiales(Collection $respuestas): Collection
    {
        return $respuestas
            ->groupBy(fn($r) => $r->user_id . '-' . $r->actividad_id)
            ->map(function ($grupo) {
                $actividad = $grupo->first()->actividad;
                return $actividad->criterio_calificacion_intentos === 'ultimo'
                    ? $grupo->sortByDesc('fecha_envio')->first()
                    : $grupo->sortByDesc('calificacion')->first();
            })
            ->values();
    }
```

- [ ] **Step 4: Ejecutar los tests para confirmar que pasan**

Run: `php artisan test --filter=CalificacionServiceTest`
Expected: PASS (todos los tests existentes + los 3 nuevos)

- [ ] **Step 5: Commit**

```bash
git add app/Services/CalificacionService.php tests/Unit/CalificacionServiceTest.php
git commit -m "Agrega CalificacionService::respuestasOficiales() para deduplicar intentos"
```

---

### Task 3: Lógica de envío de intentos en `RespuestaEstudianteController`

**Files:**
- Modify: `app/Http/Controllers/RespuestaEstudianteController.php:21-33`
- Test: `tests/Feature/CuestionarioIntentosTest.php` (nuevo)

**Interfaces:**
- Consumes: `Actividad::$intentos_permitidos` (Task 1).
- Produces: comportamiento HTTP de `POST respuestas.store` — cada intento crea una fila nueva en `respuestas_estudiantes` hasta agotar `intentos_permitidos`; bloquea si hay un intento `en_revision`.

- [ ] **Step 1: Escribir los tests que fallan**

Crear `tests/Feature/CuestionarioIntentosTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\Actividad;
use App\Models\Curso;
use App\Models\Inscripcion;
use App\Models\Leccion;
use App\Models\Modulo;
use App\Models\Opcion;
use App\Models\Pregunta;
use App\Models\Rol;
use App\Models\RespuestaEstudiante;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CuestionarioIntentosTest extends TestCase
{
    use RefreshDatabase;

    private User $instructor;
    private User $estudiante;
    private Curso $curso;
    private Leccion $leccion;

    protected function setUp(): void
    {
        parent::setUp();

        $rolInstructor  = Rol::create(['nombre' => 'Instructor']);
        $rolEstudiante  = Rol::create(['nombre' => 'Estudiante']);
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

    private function crearCuestionarioOpcionMultiple(int $intentosPermitidos): Actividad
    {
        $actividad = Actividad::factory()->create([
            'leccion_id'          => $this->leccion->id,
            'tipo'                => 'cuestionario',
            'puntaje_maximo'      => 100,
            'intentos_permitidos' => $intentosPermitidos,
        ]);

        $pregunta = Pregunta::factory()->create([
            'actividad_id' => $actividad->id,
            'tipo'         => 'opcion_multiple',
            'puntaje'      => 100,
        ]);
        Opcion::factory()->correcta()->create(['pregunta_id' => $pregunta->id]);
        Opcion::factory()->create(['pregunta_id' => $pregunta->id, 'es_correcta' => false]);

        return $actividad->fresh();
    }

    private function enviarRespuesta(Actividad $actividad): \Illuminate\Testing\TestResponse
    {
        $pregunta = $actividad->preguntas()->with('opciones')->first();
        $correcta = $pregunta->opciones->firstWhere('es_correcta', true);

        return $this->actingAs($this->estudiante)->post(
            route('respuestas.store', $actividad),
            ['respuesta' => json_encode([$pregunta->id => $correcta->id])]
        );
    }

    public function test_cuestionario_de_un_intento_bloquea_el_segundo_envio(): void
    {
        $actividad = $this->crearCuestionarioOpcionMultiple(1);

        $this->enviarRespuesta($actividad)->assertRedirect(route('actividades.show', $actividad));
        $response = $this->enviarRespuesta($actividad);

        $response->assertRedirect(route('actividades.show', $actividad));
        $response->assertSessionHas('error', 'Ya has respondido esta actividad.');
        $this->assertEquals(1, RespuestaEstudiante::count());
    }

    public function test_cuestionario_con_varios_intentos_permite_reintentar_hasta_el_limite(): void
    {
        $actividad = $this->crearCuestionarioOpcionMultiple(3);

        $this->enviarRespuesta($actividad)->assertRedirect(route('actividades.show', $actividad));
        $this->enviarRespuesta($actividad)->assertRedirect(route('actividades.show', $actividad));
        $this->enviarRespuesta($actividad)->assertRedirect(route('actividades.show', $actividad));

        $this->assertEquals(3, RespuestaEstudiante::count());

        $response = $this->enviarRespuesta($actividad);
        $response->assertSessionHas('error', 'Ya usaste todos los intentos permitidos para este cuestionario.');
        $this->assertEquals(3, RespuestaEstudiante::count());
    }

    public function test_intento_en_revision_bloquea_un_nuevo_intento(): void
    {
        $actividad = Actividad::factory()->create([
            'leccion_id'          => $this->leccion->id,
            'tipo'                => 'cuestionario',
            'puntaje_maximo'      => 100,
            'intentos_permitidos' => 3,
        ]);
        $pregunta = Pregunta::factory()->create([
            'actividad_id' => $actividad->id,
            'tipo'         => 'respuesta_corta',
            'puntaje'      => 100,
        ]);

        $response = $this->actingAs($this->estudiante)->post(
            route('respuestas.store', $actividad),
            ['respuesta' => json_encode([$pregunta->id => 'mi respuesta libre'])]
        );
        $response->assertRedirect(route('actividades.show', $actividad));
        $this->assertDatabaseHas('respuestas_estudiantes', ['actividad_id' => $actividad->id, 'estado' => 'en_revision']);

        $segundoIntento = $this->actingAs($this->estudiante)->post(
            route('respuestas.store', $actividad),
            ['respuesta' => json_encode([$pregunta->id => 'otra respuesta'])]
        );
        $segundoIntento->assertSessionHas('error', 'Tienes un intento pendiente de revisión. Espera a que el instructor lo califique antes de reintentar.');
        $this->assertEquals(1, RespuestaEstudiante::count());
    }

    public function test_otros_tipos_de_actividad_siguen_limitados_a_un_solo_envio(): void
    {
        $actividad = Actividad::factory()->create([
            'leccion_id'          => $this->leccion->id,
            'tipo'                => 'tarea',
            'puntaje_maximo'      => 5,
            'intentos_permitidos' => 5,
        ]);

        $primero = $this->actingAs($this->estudiante)->post(
            route('respuestas.store', $actividad),
            ['respuesta' => 'Mi entrega']
        );
        $primero->assertRedirect(route('actividades.show', $actividad));

        $segundo = $this->actingAs($this->estudiante)->post(
            route('respuestas.store', $actividad),
            ['respuesta' => 'Otro intento']
        );
        $segundo->assertSessionHas('error', 'Ya has respondido esta actividad.');
        $this->assertEquals(1, RespuestaEstudiante::count());
    }
}
```

- [ ] **Step 2: Ejecutar los tests para confirmar que fallan**

Run: `php artisan test --filter=CuestionarioIntentosTest`
Expected: FAIL en `test_cuestionario_con_varios_intentos_permite_reintentar_hasta_el_limite` e `test_intento_en_revision_bloquea_un_nuevo_intento` (el segundo envío se rechaza siempre con "Ya has respondido esta actividad" en vez de permitir varios intentos). El resto puede pasar por casualidad con el código actual.

- [ ] **Step 3: Implementar la lógica en el controlador**

En `app/Http/Controllers/RespuestaEstudianteController.php`, reemplazar el bloque de las líneas 25-33:

```php
        $yaRespondio = RespuestaEstudiante::where('user_id', Auth::id())
            ->where('actividad_id', $actividad->id)
            ->exists();

        if ($yaRespondio) {
            return redirect()
                ->route('actividades.show', $actividad)
                ->with('error', 'Ya has respondido esta actividad.');
        }
```

por:

```php
        if ($actividad->tipo === 'cuestionario') {
            $intentosUsados = RespuestaEstudiante::where('user_id', Auth::id())
                ->where('actividad_id', $actividad->id)
                ->count();

            if ($intentosUsados >= $actividad->intentos_permitidos) {
                return redirect()
                    ->route('actividades.show', $actividad)
                    ->with('error', 'Ya usaste todos los intentos permitidos para este cuestionario.');
            }

            $tieneIntentoEnRevision = RespuestaEstudiante::where('user_id', Auth::id())
                ->where('actividad_id', $actividad->id)
                ->where('estado', 'en_revision')
                ->exists();

            if ($tieneIntentoEnRevision) {
                return redirect()
                    ->route('actividades.show', $actividad)
                    ->with('error', 'Tienes un intento pendiente de revisión. Espera a que el instructor lo califique antes de reintentar.');
            }
        } else {
            $yaRespondio = RespuestaEstudiante::where('user_id', Auth::id())
                ->where('actividad_id', $actividad->id)
                ->exists();

            if ($yaRespondio) {
                return redirect()
                    ->route('actividades.show', $actividad)
                    ->with('error', 'Ya has respondido esta actividad.');
            }
        }
```

- [ ] **Step 4: Ejecutar los tests para confirmar que pasan**

Run: `php artisan test --filter=CuestionarioIntentosTest`
Expected: PASS (5 tests)

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/RespuestaEstudianteController.php tests/Feature/CuestionarioIntentosTest.php
git commit -m "Permite múltiples intentos en cuestionarios respetando el límite y el estado en_revision"
```

---

### Task 4: Validación de configuración en `ActividadController`

**Files:**
- Modify: `app/Http/Controllers/ActividadController.php:28-54` (store), `app/Http/Controllers/ActividadController.php:98-123` (update)
- Test: `tests/Feature/CuestionarioIntentosTest.php` (agregar métodos)

**Interfaces:**
- Consumes: `Actividad::$intentos_permitidos`, `$criterio_calificacion_intentos`, `$mostrar_historial_intentos` (Task 1).
- Produces: `ActividadController::store()`/`update()` aceptan y persisten los 3 campos con defaults (`1`, `'mas_alto'`, `true`) cuando no vienen en el request.

- [ ] **Step 1: Escribir los tests que fallan**

Agregar a `tests/Feature/CuestionarioIntentosTest.php`, antes del `}` final de la clase:

```php
    public function test_instructor_puede_configurar_intentos_al_crear_cuestionario(): void
    {
        $response = $this->actingAs($this->instructor)->post(
            route('actividades.store', $this->leccion),
            [
                'titulo'                         => 'Quiz con reintentos',
                'tipo'                            => 'cuestionario',
                'puntaje_maximo'                  => 100,
                'intentos_permitidos'             => 3,
                'criterio_calificacion_intentos'  => 'ultimo',
                'mostrar_historial_intentos'      => '0',
            ]
        );

        $response->assertRedirect();
        $this->assertDatabaseHas('actividades', [
            'titulo'                         => 'Quiz con reintentos',
            'intentos_permitidos'            => 3,
            'criterio_calificacion_intentos' => 'ultimo',
            'mostrar_historial_intentos'     => 0,
        ]);
    }

    public function test_actividad_creada_sin_especificar_intentos_usa_defaults(): void
    {
        $response = $this->actingAs($this->instructor)->post(
            route('actividades.store', $this->leccion),
            ['titulo' => 'Quiz simple', 'tipo' => 'cuestionario', 'puntaje_maximo' => 100]
        );

        $response->assertRedirect();
        $this->assertDatabaseHas('actividades', [
            'titulo'                         => 'Quiz simple',
            'intentos_permitidos'            => 1,
            'criterio_calificacion_intentos' => 'mas_alto',
            'mostrar_historial_intentos'     => 1,
        ]);
    }

    public function test_instructor_puede_actualizar_intentos_permitidos(): void
    {
        $actividad = $this->crearCuestionarioOpcionMultiple(1);

        $response = $this->actingAs($this->instructor)->put(
            route('actividades.update', $actividad),
            [
                'titulo'                         => $actividad->titulo,
                'puntaje_maximo'                 => 100,
                'intentos_permitidos'            => 2,
                'criterio_calificacion_intentos' => 'ultimo',
                'mostrar_historial_intentos'     => '1',
            ]
        );

        $response->assertRedirect(route('actividades.edit', $actividad));
        $actividad->refresh();
        $this->assertEquals(2, $actividad->intentos_permitidos);
        $this->assertEquals('ultimo', $actividad->criterio_calificacion_intentos);
        $this->assertTrue($actividad->mostrar_historial_intentos);
    }
```

- [ ] **Step 2: Ejecutar los tests para confirmar que fallan**

Run: `php artisan test --filter=CuestionarioIntentosTest`
Expected: FAIL — `intentos_permitidos` no está en las reglas de validación, así que `$actividad->intentos_permitidos` queda en el default de la migración (`1`) incluso cuando se envía `3`, y `mostrar_historial_intentos` no se persiste en `update()`.

- [ ] **Step 3: Modificar `store()`**

En `app/Http/Controllers/ActividadController.php`, reemplazar el bloque de las líneas 28-54:

```php
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
            'permitir_descarga_adjuntos' => 'nullable|in:0,1,leccion',
            'intentos_permitidos'            => 'nullable|integer|min:1|max:20',
            'criterio_calificacion_intentos'  => 'nullable|in:mas_alto,ultimo',
            'mostrar_historial_intentos'      => 'boolean',
        ]);

        $orden = $leccion->actividades()->max('orden') + 1;

        $descargaRaw = $validated['permitir_descarga_adjuntos'] ?? null;

        $actividad = $leccion->actividades()->create([
            ...$validated,
            'puntaje_maximo'  => in_array($request->tipo, Actividad::TIPOS_SIN_NOTA) ? null : ($validated['puntaje_maximo'] ?? null),
            'es_obligatoria'  => $request->boolean('es_obligatoria', true),
            'permitir_descarga_adjuntos' => in_array($descargaRaw, ['leccion', null], true) ? null : (bool) $descargaRaw,
            'orden'           => $orden,
            'intentos_permitidos'            => $validated['intentos_permitidos'] ?? 1,
            'criterio_calificacion_intentos' => $validated['criterio_calificacion_intentos'] ?? 'mas_alto',
            'mostrar_historial_intentos'     => $request->boolean('mostrar_historial_intentos', true),
        ]);

        return redirect()
            ->route('actividades.edit', $actividad)
            ->with('success', 'Actividad creada. Ahora configura su contenido.');
```

- [ ] **Step 4: Modificar `update()`**

En el mismo archivo, reemplazar el bloque de las líneas 98-123:

```php
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
            'permitir_descarga_adjuntos' => 'nullable|in:0,1,leccion',
            'intentos_permitidos'            => 'nullable|integer|min:1|max:20',
            'criterio_calificacion_intentos'  => 'nullable|in:mas_alto,ultimo',
            'mostrar_historial_intentos'      => 'boolean',
        ]);

        $descargaRaw = $validated['permitir_descarga_adjuntos'] ?? null;

        $actividad->update([
            ...$validated,
            'es_obligatoria'  => $request->boolean('es_obligatoria', true),
            'permitir_descarga_adjuntos' => in_array($descargaRaw, ['leccion', null], true) ? null : (bool) $descargaRaw,
            'fecha_apertura'  => $request->filled('fecha_apertura') ? $request->fecha_apertura : null,
            'fecha_cierre'    => $request->filled('fecha_cierre')   ? $request->fecha_cierre   : null,
            'intentos_permitidos'            => $validated['intentos_permitidos'] ?? $actividad->intentos_permitidos,
            'criterio_calificacion_intentos' => $validated['criterio_calificacion_intentos'] ?? $actividad->criterio_calificacion_intentos,
            'mostrar_historial_intentos'     => $request->boolean('mostrar_historial_intentos', $actividad->mostrar_historial_intentos),
        ]);

        return redirect()
            ->route('actividades.edit', $actividad)
            ->with('success', 'Actividad actualizada correctamente');
```

- [ ] **Step 5: Ejecutar los tests para confirmar que pasan**

Run: `php artisan test --filter=CuestionarioIntentosTest`
Expected: PASS (8 tests)

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/ActividadController.php tests/Feature/CuestionarioIntentosTest.php
git commit -m "Valida y persiste la configuración de intentos al crear/actualizar actividades"
```

---

### Task 5: `ActividadController::show()` y vista de intentos del estudiante

**Files:**
- Modify: `app/Http/Controllers/ActividadController.php:61-84` (show)
- Create: `resources/views/actividades/partials/formulario-cuestionario.blade.php`
- Modify: `resources/views/actividades/show.blade.php:480-702`
- Test: `tests/Feature/CuestionarioIntentosTest.php` (agregar métodos)

**Interfaces:**
- Consumes: `CalificacionService::respuestasOficiales()` (Task 2), `Actividad::permiteMultiplesIntentos()` (Task 1).
- Produces: la vista recibe `$intentos` (Collection ordenada por fecha), `$intentosUsados` (int), `$intentosRestantes` (int), `$tieneIntentoEnRevision` (bool), además de `$respuesta` (ahora "la respuesta oficial" para cuestionario, o el último intento para otros tipos).

- [ ] **Step 1: Escribir los tests que fallan**

Agregar a `tests/Feature/CuestionarioIntentosTest.php`, antes del `}` final de la clase:

```php
    public function test_muestra_intento_x_de_y_cuando_hay_multiples_intentos(): void
    {
        $actividad = $this->crearCuestionarioOpcionMultiple(3);
        $this->enviarRespuesta($actividad);

        $response = $this->actingAs($this->estudiante)->get(route('actividades.show', $actividad));

        $response->assertOk();
        $response->assertSee('Intento 1 de 3');
        $response->assertSee('Reintentar', false);
    }

    public function test_no_muestra_reintentar_cuando_se_agotan_los_intentos_multiples(): void
    {
        $actividad = $this->crearCuestionarioOpcionMultiple(2);
        $this->enviarRespuesta($actividad);
        $this->enviarRespuesta($actividad);

        $response = $this->actingAs($this->estudiante)->get(route('actividades.show', $actividad));

        $response->assertOk();
        $response->assertSee('Intento 2 de 2');
        $response->assertDontSee('Reintentar');
    }

    public function test_muestra_bloqueo_por_revision_pendiente(): void
    {
        $actividad = Actividad::factory()->create([
            'leccion_id' => $this->leccion->id, 'tipo' => 'cuestionario',
            'puntaje_maximo' => 100, 'intentos_permitidos' => 3,
        ]);
        Pregunta::factory()->create(['actividad_id' => $actividad->id, 'tipo' => 'respuesta_corta', 'puntaje' => 100]);
        $pregunta = $actividad->preguntas()->first();

        $this->actingAs($this->estudiante)->post(
            route('respuestas.store', $actividad),
            ['respuesta' => json_encode([$pregunta->id => 'texto libre'])]
        );

        $response = $this->actingAs($this->estudiante)->get(route('actividades.show', $actividad));

        $response->assertOk();
        $response->assertSee('Intento pendiente de revisión');
        $response->assertDontSee('Reintentar');
    }

    public function test_historial_de_intentos_se_oculta_si_la_configuracion_lo_indica(): void
    {
        $actividad = $this->crearCuestionarioOpcionMultiple(3);
        $actividad->update(['mostrar_historial_intentos' => false]);
        $this->enviarRespuesta($actividad);

        $response = $this->actingAs($this->estudiante)->get(route('actividades.show', $actividad));

        $response->assertOk();
        $response->assertDontSee('Historial de intentos');
    }

    public function test_cuestionario_de_un_intento_mantiene_el_mensaje_actual(): void
    {
        $actividad = $this->crearCuestionarioOpcionMultiple(1);
        $this->enviarRespuesta($actividad);

        $response = $this->actingAs($this->estudiante)->get(route('actividades.show', $actividad));

        $response->assertOk();
        $response->assertSee('Ya respondiste esta actividad');
    }
```

- [ ] **Step 2: Ejecutar los tests para confirmar que fallan**

Run: `php artisan test --filter=CuestionarioIntentosTest`
Expected: FAIL en los 5 tests nuevos — la vista actual siempre muestra "Ya respondiste esta actividad" y no tiene botón "Reintentar" ni bloque de revisión pendiente.

- [ ] **Step 3: Modificar `ActividadController::show()`**

En `app/Http/Controllers/ActividadController.php`, reemplazar el método completo (líneas 61-84):

```php
    public function show(Actividad $actividad)
    {
        $this->authorize('view', $actividad->leccion->modulo->curso);

        $intentos = $actividad->respuestas()
            ->where('user_id', auth()->id())
            ->with('seleccionesRubrica')
            ->orderBy('fecha_envio')
            ->get();
        $intentos->each(fn($r) => $r->setRelation('actividad', $actividad));

        $respuestaOficial = $intentos->isNotEmpty()
            ? app(\App\Services\CalificacionService::class)->respuestasOficiales($intentos)->first()
            : null;

        $respuesta = $actividad->tipo === 'cuestionario' ? $respuestaOficial : $intentos->last();

        $intentosUsados         = $intentos->count();
        $intentosRestantes      = max(0, $actividad->intentos_permitidos - $intentosUsados);
        $tieneIntentoEnRevision = $intentos->contains('estado', 'en_revision');

        $actividadCompletada = ProgresoActividad::where('user_id', auth()->id())
            ->where('actividad_id', $actividad->id)
            ->where('completado', true)
            ->exists();

        $criteriosRubrica = $actividad->usa_rubrica
            ? $actividad->criteriosRubrica()->with('niveles')->get()
            : collect();

        $seleccionesMap = $respuesta
            ? $respuesta->seleccionesRubrica->pluck('nivel_criterio_id', 'criterio_id')
            : collect();

        return view('actividades.show', compact(
            'actividad', 'respuesta', 'actividadCompletada', 'criteriosRubrica', 'seleccionesMap',
            'intentos', 'intentosUsados', 'intentosRestantes', 'tieneIntentoEnRevision'
        ));
    }
```

- [ ] **Step 4: Extraer el formulario de preguntas a un partial**

Crear `resources/views/actividades/partials/formulario-cuestionario.blade.php`:

```blade
@php
    $preguntas  = $actividad->preguntas()->with('opciones')->orderBy('orden')->get();
    $oldAnswers = old('respuesta') ? (json_decode(old('respuesta'), true) ?? []) : [];
@endphp
<div class="space-y-6">
    @foreach($preguntas as $index => $pregunta)
    @php $oldVal = $oldAnswers[$pregunta->id] ?? null; @endphp
    <div class="bg-white rounded-lg shadow p-6">
        <p class="font-medium text-gray-900 mb-1">
            {{ $index + 1 }}. {{ $pregunta->pregunta_texto }}
            <span class="text-xs text-gray-400 ml-2">({{ $pregunta->puntaje }} pts)</span>
        </p>

        @if($pregunta->imagen_path)
        <img src="{{ $pregunta->imagenUrl() }}"
             alt="Imagen de apoyo"
             class="my-4 w-full h-64 object-contain rounded-lg border border-gray-200 bg-gray-50">
        @endif

        @if($pregunta->tipo === 'respuesta_corta')
            <input type="text" name="respuesta_{{ $pregunta->id }}"
                   value="{{ old('respuesta_' . $pregunta->id) }}"
                   class="mt-3 w-full px-3 py-2 border border-gray-300 rounded-lg text-sm" required>

        @elseif($pregunta->seleccion_multiple)
            <p class="mt-2 text-xs text-blue-600 font-medium">
                Selecciona todas las respuestas correctas.
            </p>
            <div class="mt-2 space-y-2">
                @foreach($pregunta->opciones as $opcion)
                @php $checked = is_array($oldVal) && in_array((string)$opcion->id, array_map('strval', $oldVal)); @endphp
                <label class="flex items-center gap-3 p-3 border border-gray-200 rounded-lg cursor-pointer hover:bg-blue-50 transition-colors">
                    <input type="checkbox"
                           name="respuesta_{{ $pregunta->id }}[]"
                           value="{{ $opcion->id }}"
                           {{ $checked ? 'checked' : '' }}
                           class="w-4 h-4 rounded text-blue-600">
                    <span class="text-sm text-gray-800">{{ $opcion->texto }}</span>
                </label>
                @endforeach
            </div>

        @else
            <div class="mt-3 space-y-2">
                @foreach($pregunta->opciones as $opcion)
                @php $checked = $oldVal !== null && (string)$oldVal === (string)$opcion->id; @endphp
                <label class="flex items-center gap-3 p-3 border border-gray-200 rounded-lg cursor-pointer hover:bg-gray-50 transition-colors">
                    <input type="radio" name="respuesta_{{ $pregunta->id }}" value="{{ $opcion->id }}"
                           {{ $checked ? 'checked' : '' }}
                           class="text-blue-600">
                    <span class="text-sm text-gray-800">{{ $opcion->texto }}</span>
                </label>
                @endforeach
            </div>
        @endif
    </div>
    @endforeach
</div>

<input type="hidden" name="respuesta" id="respuesta-json">
<script>
document.getElementById('form-respuesta').addEventListener('submit', function(e) {
    const data = {};

    this.querySelectorAll('[name^="respuesta_"]:not([type=checkbox])').forEach(function(el) {
        if (el.type === 'radio' && !el.checked) return;
        if (!el.value) return;
        const id = el.name.replace('respuesta_', '');
        data[id] = el.value;
    });

    this.querySelectorAll('[name^="respuesta_"][type=checkbox]:checked').forEach(function(el) {
        const id = el.name.replace('respuesta_', '').replace('[]', '');
        if (!data[id]) data[id] = [];
        data[id].push(el.value);
    });

    document.getElementById('respuesta-json').value = JSON.stringify(data);
});
</script>
```

- [ ] **Step 5: Reescribir la sección de calificación en `show.blade.php`**

En `resources/views/actividades/show.blade.php`, reemplazar todo el bloque desde la línea 480 (`@if($actividad->tieneCalificacion())`) hasta la línea 702 (`@endsection`) por:

```blade
    @if($actividad->tieneCalificacion())
        @if($actividad->permiteMultiplesIntentos())
            {{-- Cuestionario con múltiples intentos --}}
            @if($tieneIntentoEnRevision)
            <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-6 mb-6">
                <h2 class="font-bold text-yellow-800 mb-2">Intento pendiente de revisión</h2>
                <p class="text-gray-600">
                    Tu intento más reciente incluye preguntas de respuesta corta que el instructor debe revisar
                    antes de que puedas iniciar un nuevo intento.
                </p>
                <div class="mt-4">
                    <a href="{{ route('lecciones.show', $actividad->leccion) }}" class="inline-flex items-center bg-yellow-600 hover:bg-yellow-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors">
                        Volver a la lección
                    </a>
                </div>
            </div>
            @else
                @if($respuesta)
                <div class="bg-green-50 border border-green-200 rounded-lg p-6 mb-6">
                    <h2 class="font-bold text-green-800 mb-2">
                        Intento {{ $intentosUsados }} de {{ $actividad->intentos_permitidos }}
                    </h2>
                    @if($respuesta->calificacion !== null)
                        <p class="text-2xl font-bold text-green-700">{{ $respuesta->calificacion }}/{{ $actividad->puntaje_maximo }} puntos</p>
                        <p class="text-xs text-gray-500 mt-1">
                            Calificación vigente ({{ $actividad->criterio_calificacion_intentos === 'ultimo' ? 'último intento' : 'intento más alto' }})
                        </p>
                    @else
                        <p class="text-gray-600">Tu respuesta está pendiente de calificación.</p>
                    @endif
                    @if($respuesta->feedback)
                        <div class="mt-3 pt-3 border-t border-green-200">
                            <p class="text-sm font-medium text-gray-700 mb-1">Retroalimentación:</p>
                            <p class="text-sm text-gray-600">{{ $respuesta->feedback }}</p>
                        </div>
                    @endif

                    @if($actividad->mostrar_historial_intentos)
                    <div class="mt-4 pt-4 border-t border-green-200">
                        <p class="text-sm font-medium text-gray-700 mb-2">Historial de intentos</p>
                        <ul class="space-y-1">
                            @foreach($intentos as $index => $intento)
                            <li class="text-sm text-gray-600 flex items-center justify-between gap-3">
                                <span>Intento {{ $index + 1 }} — {{ $intento->fecha_envio->format('d/m/Y H:i') }}</span>
                                <span class="font-medium {{ $intento->id === $respuesta->id ? 'text-green-700' : 'text-gray-500' }}">
                                    {{ $intento->calificacion !== null ? number_format($intento->calificacion, 2) . '/' . $actividad->puntaje_maximo : 'Pendiente' }}
                                    @if($intento->id === $respuesta->id)<span class="text-xs">(vigente)</span>@endif
                                </span>
                            </li>
                            @endforeach
                        </ul>
                    </div>
                    @endif

                    <div class="mt-4">
                        <a href="{{ route('lecciones.show', $actividad->leccion) }}" class="inline-flex items-center bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors">
                            <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                            Volver a la lección
                        </a>
                    </div>
                </div>
                @endif

                @if($intentosRestantes > 0 && $actividad->estaAbierta())
                <form id="form-respuesta" action="{{ route('respuestas.store', $actividad) }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    @include('actividades.partials.formulario-cuestionario')
                    <div class="mt-6 flex justify-end">
                        <button type="submit" class="bg-blue-600 text-white px-8 py-3 rounded-lg hover:bg-blue-700 font-medium">
                            {{ $respuesta ? 'Reintentar' : 'Enviar respuesta' }}
                        </button>
                    </div>
                </form>
                @elseif(!$respuesta && !$actividad->estaAbierta())
                <div class="bg-white rounded-lg shadow p-10 text-center">
                    @if($estadoPlazo === 'pendiente')
                        <svg class="w-12 h-12 text-yellow-400 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <p class="text-gray-700 font-medium">La actividad estará disponible el</p>
                        <p class="text-xl font-bold text-yellow-600 mt-1">{{ $actividad->fecha_apertura->format('d/m/Y \a \l\a\s H:i') }}</p>
                    @else
                        <svg class="w-12 h-12 text-red-400 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 15v2m0 0v2m0-2h2m-2 0H10m2-5V7m0 0V5m0 2h2M12 7H10m10 5a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <p class="text-gray-700 font-medium">El plazo de entrega venció el</p>
                        <p class="text-xl font-bold text-red-600 mt-1">{{ $actividad->fecha_cierre->format('d/m/Y H:i') }}</p>
                    @endif
                </div>
                @endif
            @endif

        @else
        {{-- Comportamiento actual sin cambios: cuestionario de 1 intento, ensayo, tarea, practica --}}
        @if($respuesta)
        <div class="bg-green-50 border border-green-200 rounded-lg p-6 mb-6">
            <h2 class="font-bold text-green-800 mb-2">Ya respondiste esta actividad</h2>
            @if($respuesta->calificacion !== null)
                <p class="text-2xl font-bold text-green-700">{{ $respuesta->calificacion }}/{{ $actividad->puntaje_maximo }} puntos</p>
            @else
                <p class="text-gray-600">Tu respuesta está pendiente de calificación.</p>
            @endif
            @if($respuesta->feedback)
                <div class="mt-3 pt-3 border-t border-green-200">
                    <p class="text-sm font-medium text-gray-700 mb-1">Retroalimentación:</p>
                    <p class="text-sm text-gray-600">{{ $respuesta->feedback }}</p>
                </div>
            @endif
            <div class="mt-4">
                <a href="{{ route('lecciones.show', $actividad->leccion) }}" class="inline-flex items-center bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors">
                    <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                    Volver a la lección
                </a>
            </div>
        </div>

        @elseif(!$actividad->estaAbierta())
        {{-- Actividad cerrada o pendiente: no se puede responder --}}
        <div class="bg-white rounded-lg shadow p-10 text-center">
            @if($estadoPlazo === 'pendiente')
                <svg class="w-12 h-12 text-yellow-400 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <p class="text-gray-700 font-medium">La actividad estará disponible el</p>
                <p class="text-xl font-bold text-yellow-600 mt-1">{{ $actividad->fecha_apertura->format('d/m/Y \a \l\a\s H:i') }}</p>
            @else
                <svg class="w-12 h-12 text-red-400 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 15v2m0 0v2m0-2h2m-2 0H10m2-5V7m0 0V5m0 2h2M12 7H10m10 5a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <p class="text-gray-700 font-medium">El plazo de entrega venció el</p>
                <p class="text-xl font-bold text-red-600 mt-1">{{ $actividad->fecha_cierre->format('d/m/Y H:i') }}</p>
            @endif
        </div>

        @else
        {{-- Formulario de respuesta --}}
        <form id="form-respuesta" action="{{ route('respuestas.store', $actividad) }}" method="POST" enctype="multipart/form-data">
            @csrf

            @if($actividad->tipo === 'cuestionario')
                @include('actividades.partials.formulario-cuestionario')
            @else
                <div class="bg-white rounded-lg shadow p-6">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Tu respuesta</label>
                    <textarea name="respuesta" rows="8"
                              class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                              placeholder="Escribe tu respuesta aquí...">{{ old('respuesta') }}</textarea>
                    @error('respuesta')<p class="text-red-600 text-sm mt-1">{{ $message }}</p>@enderror
                </div>

                <div class="bg-white rounded-lg shadow p-6" x-data="{ nombre: null, errorArchivo: '' }">
                    <p class="text-sm font-medium text-gray-700 mb-3">
                        Adjuntar archivo
                        <span class="text-gray-400 font-normal">(opcional)</span>
                    </p>
                    <label class="flex flex-col items-center justify-center w-full h-28 border-2 border-dashed rounded-xl cursor-pointer transition-colors"
                           :class="nombre && !errorArchivo
                               ? 'border-green-400 bg-green-50/40 hover:bg-green-50'
                               : errorArchivo
                                   ? 'border-red-400 bg-red-50/40'
                                   : 'border-gray-300 bg-gray-50/40 hover:border-blue-400 hover:bg-blue-50/30'">
                        <div x-show="!nombre && !errorArchivo" class="flex flex-col items-center gap-1.5 text-gray-400 pointer-events-none">
                            <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                      d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
                            </svg>
                            <p class="text-sm">Haz clic para seleccionar</p>
                            <p class="text-xs">Imagen, PDF, Word, video — máx. 50 MB</p>
                        </div>
                        <div x-show="nombre && !errorArchivo" class="flex items-center gap-2 px-4 text-green-700 pointer-events-none">
                            <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            <span class="text-sm font-medium truncate max-w-xs" x-text="nombre"></span>
                        </div>
                        <div x-show="errorArchivo" class="flex items-center gap-2 px-4 text-red-600 pointer-events-none">
                            <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            <span class="text-sm font-medium" x-text="errorArchivo"></span>
                        </div>
                        <input type="file" name="archivo_adjunto" class="sr-only"
                               accept="image/*,video/*,.pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.zip"
                               @change="
                                   errorArchivo = '';
                                   const f = $event.target.files[0];
                                   if (f) {
                                       if (f.size > 50 * 1024 * 1024) {
                                           errorArchivo = 'El archivo supera el límite de 50 MB.';
                                           $event.target.value = '';
                                           nombre = null;
                                       } else {
                                           nombre = f.name;
                                       }
                                   } else {
                                       nombre = null;
                                   }
                               ">
                    </label>
                    <p x-show="errorArchivo" x-text="errorArchivo" class="text-red-600 text-xs mt-1"></p>
                    @error('archivo_adjunto')<p class="text-red-600 text-sm mt-1">{{ $message }}</p>@enderror
                </div>
            @endif

            <div class="mt-6 flex justify-end">
                <button type="submit" class="bg-blue-600 text-white px-8 py-3 rounded-lg hover:bg-blue-700 font-medium">
                    Enviar respuesta
                </button>
            </div>
        </form>
        @endif
        @endif

    @else
        {{-- Actividad sin calificación: solo consulta --}}
        <div class="bg-gray-50 border border-gray-200 rounded-xl p-6 flex items-start gap-4">
            <svg class="w-6 h-6 text-gray-400 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <div>
                <p class="font-medium text-gray-700">Esta actividad es de consulta</p>
                <p class="text-sm text-gray-500 mt-1">No requiere entrega ni tiene calificación. Revisa los recursos disponibles arriba.</p>
            </div>
            <a href="{{ route('lecciones.show', $actividad->leccion) }}" class="ml-auto inline-flex items-center text-blue-600 hover:text-blue-800 text-sm font-medium shrink-0">
                Continuar <svg class="w-4 h-4 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"/></svg>
            </a>
        </div>
    @endif
</div>
@endsection
```

- [ ] **Step 6: Ejecutar los tests para confirmar que pasan**

Run: `php artisan test --filter=CuestionarioIntentosTest`
Expected: PASS (13 tests)

- [ ] **Step 7: Ejecutar toda la suite para descartar regresiones en otras vistas de actividad**

Run: `php artisan test --filter=RubricaTest`
Expected: PASS (sin cambios — `show()` para tarea/ensayo sigue devolviendo `$respuesta` como el último intento, que para esos tipos siempre es el único)

Run: `php artisan test --filter=ActividadSinNotaTest`
Expected: PASS

- [ ] **Step 8: Commit**

```bash
git add app/Http/Controllers/ActividadController.php resources/views/actividades/partials/formulario-cuestionario.blade.php resources/views/actividades/show.blade.php tests/Feature/CuestionarioIntentosTest.php
git commit -m "Muestra intento X de Y, historial y bloqueo por revisión en la página de la actividad"
```

---

### Task 6: Deduplicar calificaciones en reportes y certificados

**Files:**
- Modify: `app/Services/ReporteService.php:1-50` (kpiGenerales + constructor/imports), `app/Services/ReporteService.php:84-96` (reportePorCurso), `app/Services/ReporteService.php:153-165` (reportePorEstudiante)
- Modify: `app/Services/CertificadoService.php:1-14` (constructor/imports), `app/Services/CertificadoService.php:109-133` (calcularCalificacionFinal)
- Test: `tests/Unit/ReporteServiceIntentosTest.php` (nuevo), `tests/Unit/CertificadoServiceIntentosTest.php` (nuevo)

**Interfaces:**
- Consumes: `CalificacionService::respuestasOficiales()` (Task 2).
- Produces: `ReporteService::kpiGenerales()`, `reportePorCurso()`, `reportePorEstudiante()` y `CertificadoService::generarSiCorresponde()` calculan promedios sin duplicar puntaje cuando un estudiante tiene varios intentos calificados en la misma actividad.

- [ ] **Step 1: Escribir los tests que fallan**

Crear `tests/Unit/ReporteServiceIntentosTest.php`:

```php
<?php

namespace Tests\Unit;

use App\Models\Actividad;
use App\Models\Curso;
use App\Models\Inscripcion;
use App\Models\Leccion;
use App\Models\Modulo;
use App\Models\RespuestaEstudiante;
use App\Models\User;
use App\Services\ReporteService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReporteServiceIntentosTest extends TestCase
{
    use RefreshDatabase;

    public function test_reporte_por_curso_no_duplica_puntaje_con_multiples_intentos(): void
    {
        $instructor = User::factory()->create();
        $estudiante = User::factory()->create();
        $curso      = Curso::factory()->create(['created_by' => $instructor->id, 'estado' => 'publicado']);
        $modulo     = Modulo::factory()->create(['curso_id' => $curso->id]);
        $leccion    = Leccion::factory()->create(['modulo_id' => $modulo->id]);

        Inscripcion::create([
            'user_id' => $estudiante->id, 'curso_id' => $curso->id,
            'fecha_inicio' => now(), 'estado' => 'en_progreso',
        ]);

        $actividad = Actividad::factory()->create([
            'leccion_id' => $leccion->id, 'tipo' => 'cuestionario',
            'puntaje_maximo' => 100, 'intentos_permitidos' => 2,
            'criterio_calificacion_intentos' => 'mas_alto',
        ]);

        RespuestaEstudiante::factory()->create([
            'user_id' => $estudiante->id, 'actividad_id' => $actividad->id,
            'calificacion' => 40, 'estado' => 'calificada',
        ]);
        RespuestaEstudiante::factory()->create([
            'user_id' => $estudiante->id, 'actividad_id' => $actividad->id,
            'calificacion' => 90, 'estado' => 'calificada',
        ]);

        $reporte = app(ReporteService::class)->reportePorCurso($curso);
        $datosEstudiante = $reporte['estudiantes']->firstWhere('usuario.id', $estudiante->id);

        // Con dedup: 90/100 = 90%. Sin dedup (bug): (40+90)/(100+100) = 65%.
        $this->assertEquals(90, $datosEstudiante['promedio']);
    }

    public function test_reporte_usa_el_ultimo_intento_cuando_la_politica_lo_indica(): void
    {
        $instructor = User::factory()->create();
        $estudiante = User::factory()->create();
        $curso      = Curso::factory()->create(['created_by' => $instructor->id, 'estado' => 'publicado']);
        $modulo     = Modulo::factory()->create(['curso_id' => $curso->id]);
        $leccion    = Leccion::factory()->create(['modulo_id' => $modulo->id]);

        Inscripcion::create([
            'user_id' => $estudiante->id, 'curso_id' => $curso->id,
            'fecha_inicio' => now(), 'estado' => 'en_progreso',
        ]);

        $actividad = Actividad::factory()->create([
            'leccion_id' => $leccion->id, 'tipo' => 'cuestionario',
            'puntaje_maximo' => 100, 'intentos_permitidos' => 2,
            'criterio_calificacion_intentos' => 'ultimo',
        ]);

        RespuestaEstudiante::factory()->create([
            'user_id' => $estudiante->id, 'actividad_id' => $actividad->id,
            'calificacion' => 90, 'estado' => 'calificada', 'fecha_envio' => now()->subDay(),
        ]);
        RespuestaEstudiante::factory()->create([
            'user_id' => $estudiante->id, 'actividad_id' => $actividad->id,
            'calificacion' => 40, 'estado' => 'calificada', 'fecha_envio' => now(),
        ]);

        $reporte = app(ReporteService::class)->reportePorCurso($curso);
        $datosEstudiante = $reporte['estudiantes']->firstWhere('usuario.id', $estudiante->id);

        $this->assertEquals(40, $datosEstudiante['promedio']);
    }
}
```

Crear `tests/Unit/CertificadoServiceIntentosTest.php`:

```php
<?php

namespace Tests\Unit;

use App\Models\Actividad;
use App\Models\Curso;
use App\Models\Inscripcion;
use App\Models\Leccion;
use App\Models\Modulo;
use App\Models\RespuestaEstudiante;
use App\Models\User;
use App\Services\CertificadoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CertificadoServiceIntentosTest extends TestCase
{
    use RefreshDatabase;

    public function test_calificacion_final_no_duplica_puntaje_con_multiples_intentos(): void
    {
        $instructor = User::factory()->create();
        $estudiante = User::factory()->create();
        $curso      = Curso::factory()->create(['created_by' => $instructor->id, 'estado' => 'publicado']);
        $modulo     = Modulo::factory()->create(['curso_id' => $curso->id]);
        $leccion    = Leccion::factory()->create(['modulo_id' => $modulo->id]);

        Inscripcion::create([
            'user_id' => $estudiante->id, 'curso_id' => $curso->id,
            'fecha_inicio' => now(), 'fecha_fin' => now()->toDateString(), 'estado' => 'completado',
        ]);

        $actividad = Actividad::factory()->create([
            'leccion_id' => $leccion->id, 'tipo' => 'cuestionario',
            'puntaje_maximo' => 100, 'intentos_permitidos' => 2,
            'criterio_calificacion_intentos' => 'mas_alto',
        ]);

        RespuestaEstudiante::factory()->create([
            'user_id' => $estudiante->id, 'actividad_id' => $actividad->id,
            'calificacion' => 50, 'estado' => 'calificada',
        ]);
        RespuestaEstudiante::factory()->create([
            'user_id' => $estudiante->id, 'actividad_id' => $actividad->id,
            'calificacion' => 100, 'estado' => 'calificada',
        ]);

        $certificado = app(CertificadoService::class)->generarSiCorresponde($estudiante, $curso);

        $this->assertNotNull($certificado);
        $this->assertEquals(100, $certificado->calificacion_final);
    }
}
```

- [ ] **Step 2: Ejecutar los tests para confirmar que fallan**

Run: `php artisan test --filter=ReporteServiceIntentosTest`
Run: `php artisan test --filter=CertificadoServiceIntentosTest`
Expected: FAIL — ambos servicios suman `calificacion`/`puntaje_maximo` de las dos filas sin deduplicar, dando un promedio distinto al esperado (65% en vez de 90%, y una calificación final incorrecta en el certificado).

- [ ] **Step 3: Inyectar `CalificacionService` y modificar `ReporteService`**

En `app/Services/ReporteService.php`, agregar el import y el constructor. Reemplazar las líneas 1-16:

```php
<?php

namespace App\Services;

use App\Models\Certificado;
use App\Models\Curso;
use App\Models\Inscripcion;
use App\Models\RespuestaEstudiante;
use App\Models\User;
use Illuminate\Support\Collection;

class ReporteService
{
    public function __construct(private CalificacionService $calificacionService)
    {
    }

    // ---------------------------------------------------------------
    // KPIs globales (para admin)
    // ---------------------------------------------------------------
```

Reemplazar el cálculo de `$promedioCalificacion` dentro de `kpiGenerales()` (líneas 34-38 del archivo original):

```php
        $respuestasCuestionarios = RespuestaEstudiante::where('estado', 'calificada')
            ->whereHas('actividad', fn($q) => $q->where('tipo', 'cuestionario'))
            ->with('actividad')
            ->get();
        $oficialesCuestionarios = $this->calificacionService->respuestasOficiales($respuestasCuestionarios);
        $promedioCalificacion   = $oficialesCuestionarios->isNotEmpty() ? $oficialesCuestionarios->avg('calificacion') : null;
```

En `reportePorCurso()`, reemplazar el bloque de las líneas 83-96:

```php
            $respuestas = $usuario->respuestas()
                ->where('estado', 'calificada')
                ->whereHas('actividad', fn($q) => $q->whereNotNull('puntaje_maximo'))
                ->whereHas('actividad.leccion.modulo', fn($q) => $q->where('curso_id', $curso->id))
                ->with('actividad')
                ->get();
            $respuestasOficiales = $this->calificacionService->respuestasOficiales($respuestas);

            $promedio = null;
            if ($respuestasOficiales->isNotEmpty()) {
                $totalPts    = $respuestasOficiales->sum(fn($r) => $r->actividad->puntaje_maximo);
                $obtenidoPts = $respuestasOficiales->sum('calificacion');
                $promedio    = $totalPts > 0 ? (int) round(($obtenidoPts / $totalPts) * 100) : null;
            }
```

En `reportePorEstudiante()`, reemplazar el bloque análogo (líneas 152-165):

```php
            $respuestas = $usuario->respuestas()
                ->where('estado', 'calificada')
                ->whereHas('actividad', fn($q) => $q->whereNotNull('puntaje_maximo'))
                ->whereHas('actividad.leccion.modulo', fn($q) => $q->where('curso_id', $curso->id))
                ->with('actividad')
                ->get();
            $respuestasOficiales = $this->calificacionService->respuestasOficiales($respuestas);

            $promedio = null;
            if ($respuestasOficiales->isNotEmpty()) {
                $totalPts    = $respuestasOficiales->sum(fn($r) => $r->actividad->puntaje_maximo);
                $obtenidoPts = $respuestasOficiales->sum('calificacion');
                $promedio    = $totalPts > 0 ? (int) round(($obtenidoPts / $totalPts) * 100) : null;
            }
```

- [ ] **Step 4: Inyectar `CalificacionService` y modificar `CertificadoService`**

En `app/Services/CertificadoService.php`, reemplazar el encabezado de la clase (líneas 1-15):

```php
<?php

namespace App\Services;

use App\Models\Certificado;
use App\Models\Curso;
use App\Models\Inscripcion;
use App\Models\User;
use Illuminate\Support\Str;
use Mpdf\Mpdf;
use Mpdf\Config\ConfigVariables;
use Mpdf\Config\FontVariables;

class CertificadoService
{
    public function __construct(private CalificacionService $calificacionService)
    {
    }

```

Reemplazar `calcularCalificacionFinal()` completo (líneas 112-132 del archivo original):

```php
    private function calcularCalificacionFinal(User $usuario, Curso $curso): int
    {
        $respuestas = $usuario->respuestas()
            ->where('estado', 'calificada')
            ->whereHas('actividad.leccion.modulo', fn($q) => $q->where('curso_id', $curso->id))
            ->with('actividad')
            ->get();

        $respuestasOficiales = $this->calificacionService->respuestasOficiales($respuestas);

        if ($respuestasOficiales->isEmpty()) {
            return 100; // Si no hay actividades, aprobado por completar lecciones
        }

        $total    = $respuestasOficiales->sum(fn($r) => $r->actividad->puntaje_maximo);
        $obtenido = $respuestasOficiales->sum('calificacion');

        if ($total === 0) {
            return 100;
        }

        return (int) round(($obtenido / $total) * 100);
    }
}
```

- [ ] **Step 5: Ejecutar los tests para confirmar que pasan**

Run: `php artisan test --filter=ReporteServiceIntentosTest`
Run: `php artisan test --filter=CertificadoServiceIntentosTest`
Expected: PASS (ambos archivos)

- [ ] **Step 6: Ejecutar la suite completa para descartar regresiones**

Run: `php artisan test`
Expected: PASS — en particular ningún test existente de `ReporteController`/`CertificadoController` debe romperse por la nueva dependencia inyectada en el constructor (se resuelve automáticamente vía el contenedor de Laravel).

- [ ] **Step 7: Commit**

```bash
git add app/Services/ReporteService.php app/Services/CertificadoService.php tests/Unit/ReporteServiceIntentosTest.php tests/Unit/CertificadoServiceIntentosTest.php
git commit -m "Deduplica calificaciones por intento en reportes y certificados"
```

---

### Task 7: Campos de configuración en los formularios de instructor

**Files:**
- Modify: `resources/views/actividades/create.blade.php:56-70`
- Modify: `resources/views/actividades/edit.blade.php:36-44`
- Test: `tests/Feature/CuestionarioIntentosTest.php` (agregar métodos)

**Interfaces:**
- Consumes: los campos `name="intentos_permitidos"`, `name="criterio_calificacion_intentos"`, `name="mostrar_historial_intentos"` ya son aceptados por `ActividadController::store()`/`update()` (Task 4).

- [ ] **Step 1: Escribir los tests que fallan**

Agregar a `tests/Feature/CuestionarioIntentosTest.php`, antes del `}` final de la clase:

```php
    public function test_formulario_de_edicion_muestra_campos_de_intentos_para_cuestionario(): void
    {
        $actividad = $this->crearCuestionarioOpcionMultiple(2);

        $response = $this->actingAs($this->instructor)->get(route('actividades.edit', $actividad));

        $response->assertOk();
        $response->assertSee('Intentos permitidos');
        $response->assertSee('Cuando hay varios intentos', false);
        $response->assertSee('Mostrar a los estudiantes el resumen de sus intentos anteriores');
    }

    public function test_formulario_de_edicion_no_muestra_campos_de_intentos_para_tarea(): void
    {
        $actividad = Actividad::factory()->create([
            'leccion_id' => $this->leccion->id, 'tipo' => 'tarea', 'puntaje_maximo' => 5,
        ]);

        $response = $this->actingAs($this->instructor)->get(route('actividades.edit', $actividad));

        $response->assertOk();
        $response->assertDontSee('Intentos permitidos');
    }
```

- [ ] **Step 2: Ejecutar los tests para confirmar que fallan**

Run: `php artisan test --filter=CuestionarioIntentosTest`
Expected: FAIL en `test_formulario_de_edicion_muestra_campos_de_intentos_para_cuestionario` — los campos todavía no existen en el formulario.

- [ ] **Step 3: Agregar los campos a `create.blade.php`**

En `resources/views/actividades/create.blade.php`, insertar el siguiente bloque justo después del `</div>` que cierra el grid de `puntaje_maximo`/`duracion_minutos` (línea 70) y antes del `<div class="mb-6">` de "Actividad obligatoria" (línea 71):

```blade
            <div class="mb-6 p-5 bg-blue-50 rounded-xl border border-blue-200" x-show="tipo === 'cuestionario'" x-cloak>
                <p class="text-sm font-semibold text-gray-800 mb-3">Intentos del cuestionario</p>
                <div class="grid grid-cols-2 gap-4 mb-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Intentos permitidos</label>
                        <input type="number" name="intentos_permitidos" value="{{ old('intentos_permitidos', 1) }}"
                               min="1" max="20"
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Cuando hay varios intentos, ¿cuál cuenta?</label>
                        <select name="criterio_calificacion_intentos" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                            <option value="mas_alto" {{ old('criterio_calificacion_intentos', 'mas_alto') === 'mas_alto' ? 'selected' : '' }}>El intento más alto</option>
                            <option value="ultimo" {{ old('criterio_calificacion_intentos') === 'ultimo' ? 'selected' : '' }}>El último intento</option>
                        </select>
                    </div>
                </div>
                <label class="flex items-center gap-3 cursor-pointer select-none">
                    <div class="relative">
                        <input type="checkbox" name="mostrar_historial_intentos" value="1" {{ old('mostrar_historial_intentos', true) ? 'checked' : '' }} class="sr-only peer">
                        <div class="w-10 h-6 bg-gray-200 peer-checked:bg-dyl-blue rounded-full transition-colors"></div>
                        <div class="absolute top-1 left-1 w-4 h-4 bg-white rounded-full shadow transition-transform peer-checked:translate-x-4"></div>
                    </div>
                    <span class="text-sm text-gray-700">Mostrar a los estudiantes el resumen de sus intentos anteriores</span>
                </label>
            </div>
```

- [ ] **Step 4: Agregar los campos a `edit.blade.php`**

En `resources/views/actividades/edit.blade.php`, insertar el siguiente bloque justo después del `@endif` que cierra el campo `puntaje_maximo` (línea 44) y antes del `<div class="mb-3">` de "Tiempo límite" (línea 45):

```blade
                @if($actividad->tipo === 'cuestionario')
                <div class="mb-4 p-4 bg-blue-50 rounded-xl border border-blue-200">
                    <p class="text-sm font-semibold text-gray-800 mb-3">Intentos del cuestionario</p>
                    <div class="mb-3">
                        <label class="form-label">Intentos permitidos</label>
                        <input type="number" name="intentos_permitidos"
                               value="{{ old('intentos_permitidos', $actividad->intentos_permitidos) }}"
                               min="1" max="20" class="form-input">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Cuando hay varios intentos, ¿cuál cuenta?</label>
                        <select name="criterio_calificacion_intentos" class="form-select">
                            <option value="mas_alto" {{ old('criterio_calificacion_intentos', $actividad->criterio_calificacion_intentos) === 'mas_alto' ? 'selected' : '' }}>El intento más alto</option>
                            <option value="ultimo" {{ old('criterio_calificacion_intentos', $actividad->criterio_calificacion_intentos) === 'ultimo' ? 'selected' : '' }}>El último intento</option>
                        </select>
                    </div>
                    <label class="flex items-center gap-3 cursor-pointer select-none">
                        <div class="relative">
                            <input type="checkbox" name="mostrar_historial_intentos" value="1"
                                   {{ old('mostrar_historial_intentos', $actividad->mostrar_historial_intentos) ? 'checked' : '' }}
                                   class="sr-only peer">
                            <div class="w-10 h-6 bg-gray-200 peer-checked:bg-dyl-blue rounded-full transition-colors"></div>
                            <div class="absolute top-1 left-1 w-4 h-4 bg-white rounded-full shadow transition-transform peer-checked:translate-x-4"></div>
                        </div>
                        <span class="text-sm text-gray-700">Mostrar a los estudiantes el resumen de sus intentos anteriores</span>
                    </label>
                </div>
                @endif
```

- [ ] **Step 5: Ejecutar los tests para confirmar que pasan**

Run: `php artisan test --filter=CuestionarioIntentosTest`
Expected: PASS (15 tests)

- [ ] **Step 6: Commit**

```bash
git add resources/views/actividades/create.blade.php resources/views/actividades/edit.blade.php tests/Feature/CuestionarioIntentosTest.php
git commit -m "Agrega campos de configuración de intentos a los formularios de actividad"
```

---

### Task 8: Marca de "calificación oficial" en el historial del estudiante y verificación final

**Files:**
- Modify: `app/Http/Controllers/CalificacionController.php:206-226` (misCalificaciones)
- Modify: `resources/views/calificaciones/mis-calificaciones.blade.php:16-21,53-58,78-85`
- Test: `tests/Feature/CuestionarioIntentosTest.php` (agregar método)

**Interfaces:**
- Consumes: `CalificacionService::respuestasOficiales()` (Task 2, ya inyectado en `CalificacionController` desde su constructor existente).
- Produces: la vista `mis-calificaciones` calcula sus promedios solo sobre respuestas oficiales, y marca visualmente cuál intento de cada actividad es el que cuenta.

- [ ] **Step 1: Escribir el test que falla**

Agregar a `tests/Feature/CuestionarioIntentosTest.php`, antes del `}` final de la clase:

```php
    public function test_mis_calificaciones_no_duplica_el_promedio_con_multiples_intentos(): void
    {
        $actividad = Actividad::factory()->create([
            'leccion_id' => $this->leccion->id, 'tipo' => 'cuestionario',
            'puntaje_maximo' => 100, 'intentos_permitidos' => 2,
            'criterio_calificacion_intentos' => 'mas_alto',
        ]);

        RespuestaEstudiante::factory()->create([
            'user_id' => $this->estudiante->id, 'actividad_id' => $actividad->id,
            'calificacion' => 40, 'estado' => 'calificada',
        ]);
        RespuestaEstudiante::factory()->create([
            'user_id' => $this->estudiante->id, 'actividad_id' => $actividad->id,
            'calificacion' => 90, 'estado' => 'calificada',
        ]);

        $response = $this->actingAs($this->estudiante)->get(route('calificaciones.mis'));

        $response->assertOk();
        $response->assertSee('90%'); // Promedio general: 90/100, no (40+90)/(100+100)=65%
        $response->assertSee('Cuenta para tu nota');
    }
```

- [ ] **Step 2: Ejecutar el test para confirmar que falla**

Run: `php artisan test --filter=CuestionarioIntentosTest`
Expected: FAIL — el promedio actual es 65% (duplica puntaje) y no existe la marca "Cuenta para tu nota".

- [ ] **Step 3: Modificar `CalificacionController::misCalificaciones()`**

En `app/Http/Controllers/CalificacionController.php`, reemplazar el método completo (líneas 206-226):

```php
    public function misCalificaciones()
    {
        $respuestas = RespuestaEstudiante::with(['actividad.leccion.modulo.curso'])
            ->where('user_id', Auth::id())
            ->whereHas('actividad')
            ->orderBy('fecha_envio', 'desc')
            ->get();

        $oficialesIds = $this->calificacionService
            ->respuestasOficiales($respuestas->where('estado', 'calificada'))
            ->pluck('id')
            ->all();

        // Agrupar por curso, manteniendo el orden cronológico inverso
        $porCurso = $respuestas
            ->groupBy(fn($r) => $r->actividad->leccion->modulo->curso->id)
            ->map(fn($grupo) => (object)[
                'curso'         => $grupo->first()->actividad->leccion->modulo->curso,
                'respuestas'    => $grupo->sortByDesc('fecha_envio')->values(),
                'ultima_envio'  => $grupo->max('fecha_envio'),
            ])
            ->sortByDesc('ultima_envio')
            ->values();

        return view('calificaciones.mis-calificaciones', compact('respuestas', 'porCurso', 'oficialesIds'));
    }
```

- [ ] **Step 4: Modificar `mis-calificaciones.blade.php`**

Reemplazar el bloque `@php` del resumen rápido (líneas 16-21):

```blade
@php
    $calificadas = $respuestas->where('estado', 'calificada')->whereIn('id', $oficialesIds);
    $promedio    = $calificadas->count() > 0
        ? round($calificadas->avg(fn($r) => $r->actividad->puntaje_maximo > 0
            ? ($r->calificacion / $r->actividad->puntaje_maximo) * 100 : 0))
        : 0;
@endphp
```

Reemplazar el bloque `@php` del promedio por curso (líneas 53-58):

```blade
        @php
            $grupoCalificadas = $grupo->respuestas->where('estado', 'calificada')->whereIn('id', $oficialesIds);
            $promedioCurso    = $grupoCalificadas->count() > 0
                ? round($grupoCalificadas->avg(fn($r) => $r->actividad->puntaje_maximo > 0
                    ? ($r->calificacion / $r->actividad->puntaje_maximo) * 100 : 0))
                : null;
        @endphp
```

Reemplazar la celda de "Actividad" en la tabla (líneas 80-85) para agregar la marca:

```blade
                    <td class="px-6 py-4">
                        <p class="text-sm font-medium text-gray-900">{{ $respuesta->actividad->titulo }}</p>
                        <span class="inline-block mt-1 px-2 py-0.5 bg-gray-100 text-gray-500 text-xs rounded">
                            {{ ucfirst($respuesta->actividad->tipo) }}
                        </span>
                        @if(in_array($respuesta->id, $oficialesIds) && $grupo->respuestas->where('actividad_id', $respuesta->actividad_id)->count() > 1)
                            <span class="inline-block mt-1 ml-1 px-2 py-0.5 bg-green-100 text-green-700 text-xs rounded font-medium">Cuenta para tu nota</span>
                        @endif
                    </td>
```

- [ ] **Step 5: Ejecutar el test para confirmar que pasa**

Run: `php artisan test --filter=CuestionarioIntentosTest`
Expected: PASS (16 tests)

- [ ] **Step 6: Ejecutar toda la suite de tests del proyecto**

Run: `php artisan test`
Expected: PASS — sin regresiones en `RubricaTest`, `ActividadSinNotaTest`, `CalificacionServiceTest`, `CursoInscripcionTest`, `MoverActividadesTest`, ni el resto de la suite.

- [ ] **Step 7: Commit**

```bash
git add app/Http/Controllers/CalificacionController.php resources/views/calificaciones/mis-calificaciones.blade.php tests/Feature/CuestionarioIntentosTest.php
git commit -m "Corrige el promedio de Mis Calificaciones para no duplicar puntaje entre intentos"
```

---

## Fuera de alcance (heredado de la spec)

- Intentos ilimitados.
- Múltiples intentos para `ensayo`, `tarea`, `practica`.
- Combinar automáticamente el feedback textual de varios intentos.
- Deshacer o eliminar un intento ya enviado.
