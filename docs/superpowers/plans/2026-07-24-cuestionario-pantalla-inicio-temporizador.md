# Pantalla de Inicio y Temporizador para Cuestionarios Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Antes de ver las preguntas de un cuestionario, el estudiante ve una pantalla de inicio (intentos permitidos, número de preguntas, tiempo límite si aplica) con un botón para iniciar; las preguntas nunca llegan al HTML hasta que el intento se registra en el servidor. Si hay tiempo límite, aparece un cronómetro persistido en el servidor (sobrevive a un refresco de página) que auto-envía la respuesta al llegar a 0.

**Architecture:** Nueva tabla `intentos_en_progreso` (patrón idéntico a `progreso_actividades`) marca cuándo un estudiante inició su intento actual. `ActividadController::show()` calcula, para cualquier `cuestionario`, si existe un intento en progreso y (si hay `duracion_minutos`) cuántos segundos quedan — incluyendo el caso borde de auto-enviar una respuesta vacía si el tiempo ya expiró sin que el JS del navegador alcanzara a hacerlo. Un nuevo endpoint `actividades.iniciarIntento` registra el inicio. Un nuevo partial de Blade decide entre mostrar la tarjeta de inicio o el formulario con cronómetro, reemplazando el `<form>` que hoy se muestra directamente en las dos ramas de `show.blade.php` que aplican a cuestionarios.

**Tech Stack:** Laravel 12, PHP 8.2, SQLite en memoria para tests (`phpunit.xml`), Blade + Alpine.js, PHPUnit vía `php artisan test`.

## Global Constraints

- Esta funcionalidad es exclusiva de `tipo === 'cuestionario'` — `ensayo`, `tarea`, `practica` no cambian.
- Las preguntas de un cuestionario **nunca** deben aparecer en el HTML de la respuesta hasta que exista un `IntentoEnProgreso` registrado en el servidor para ese usuario+actividad — ni siquiera ocultas por CSS/Alpine.
- Al llegar a 0 el cronómetro, se auto-envía la respuesta actual (`requestSubmit()` sobre el mismo formulario, mismo camino que un envío manual).
- Si el estudiante refresca o cierra la pestaña, el tiempo restante se calcula desde `fecha_inicio` persistido — nunca se reinicia con un refresco.
- El botón de "Iniciar cuestionario" / "Reintentar" siempre hace una petición real al servidor (no hay un camino puramente client-side), tenga o no tiempo límite la actividad.
- Todos los comandos de test se ejecutan desde `C:\xampp\htdocs\LMS_DyL\lms-dyl-quality` con `php artisan test --filter=<Nombre>`.

---

### Task 1: Migración y modelo `IntentoEnProgreso`

**Files:**
- Create: `database/migrations/2026_07_24_100000_create_intentos_en_progreso_table.php`
- Create: `app/Models/IntentoEnProgreso.php`
- Test: `tests/Unit/IntentoEnProgresoTest.php`

**Interfaces:**
- Produces: tabla `intentos_en_progreso` (columnas `user_id`, `actividad_id`, `fecha_inicio`, único por `user_id`+`actividad_id`); modelo `App\Models\IntentoEnProgreso` con `$fillable = ['user_id', 'actividad_id', 'fecha_inicio']`, cast `fecha_inicio` como `datetime`, relaciones `usuario(): BelongsTo` y `actividad(): BelongsTo`.

- [ ] **Step 1: Escribir el test que falla**

Crear `tests/Unit/IntentoEnProgresoTest.php`:

```php
<?php

namespace Tests\Unit;

use App\Models\Actividad;
use App\Models\IntentoEnProgreso;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IntentoEnProgresoTest extends TestCase
{
    use RefreshDatabase;

    public function test_puede_crear_un_intento_en_progreso(): void
    {
        $usuario   = User::factory()->create();
        $actividad = Actividad::factory()->create(['tipo' => 'cuestionario']);

        $intento = IntentoEnProgreso::create([
            'user_id'      => $usuario->id,
            'actividad_id' => $actividad->id,
            'fecha_inicio' => now(),
        ]);

        $this->assertDatabaseHas('intentos_en_progreso', [
            'user_id'      => $usuario->id,
            'actividad_id' => $actividad->id,
        ]);
        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $intento->fresh()->fecha_inicio);
    }

    public function test_no_permite_dos_intentos_en_progreso_para_el_mismo_usuario_y_actividad(): void
    {
        $usuario   = User::factory()->create();
        $actividad = Actividad::factory()->create(['tipo' => 'cuestionario']);

        IntentoEnProgreso::create(['user_id' => $usuario->id, 'actividad_id' => $actividad->id, 'fecha_inicio' => now()]);

        $this->expectException(\Illuminate\Database\QueryException::class);
        IntentoEnProgreso::create(['user_id' => $usuario->id, 'actividad_id' => $actividad->id, 'fecha_inicio' => now()]);
    }

    public function test_relaciones_usuario_y_actividad(): void
    {
        $usuario   = User::factory()->create();
        $actividad = Actividad::factory()->create(['tipo' => 'cuestionario']);
        $intento   = IntentoEnProgreso::create(['user_id' => $usuario->id, 'actividad_id' => $actividad->id, 'fecha_inicio' => now()]);

        $this->assertTrue($intento->usuario->is($usuario));
        $this->assertTrue($intento->actividad->is($actividad));
    }
}
```

- [ ] **Step 2: Ejecutar el test para confirmar que falla**

Run: `php artisan test --filter=IntentoEnProgresoTest`
Expected: FAIL — la tabla `intentos_en_progreso` y el modelo `IntentoEnProgreso` no existen.

- [ ] **Step 3: Crear la migración**

Crear `database/migrations/2026_07_24_100000_create_intentos_en_progreso_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('intentos_en_progreso', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('actividad_id')->constrained('actividades')->onDelete('cascade');
            $table->timestamp('fecha_inicio');
            $table->timestamps();
            $table->unique(['user_id', 'actividad_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('intentos_en_progreso');
    }
};
```

- [ ] **Step 4: Crear el modelo**

Crear `app/Models/IntentoEnProgreso.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IntentoEnProgreso extends Model
{
    use HasFactory;

    protected $table = 'intentos_en_progreso';

    protected $fillable = ['user_id', 'actividad_id', 'fecha_inicio'];

    protected $casts = [
        'fecha_inicio' => 'datetime',
    ];

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function actividad(): BelongsTo
    {
        return $this->belongsTo(Actividad::class);
    }
}
```

- [ ] **Step 5: Ejecutar el test para confirmar que pasa**

Run: `php artisan test --filter=IntentoEnProgresoTest`
Expected: PASS (3 tests)

- [ ] **Step 6: Commit**

```bash
git add database/migrations/2026_07_24_100000_create_intentos_en_progreso_table.php app/Models/IntentoEnProgreso.php tests/Unit/IntentoEnProgresoTest.php
git commit -m "Agrega tabla y modelo IntentoEnProgreso para rastrear el inicio de un intento de cuestionario"
```

---

### Task 2: Helpers en `Actividad` y refactor de `RespuestaEstudianteController::store()`

**Files:**
- Modify: `app/Models/Actividad.php` (agregar 2 métodos)
- Modify: `app/Http/Controllers/RespuestaEstudianteController.php:21-56`
- Test: `tests/Unit/ActividadIntentosTest.php` (agregar métodos)

**Interfaces:**
- Produces: `Actividad::intentosUsadosPor(int $userId): int` y `Actividad::tieneIntentoEnRevisionPara(int $userId): bool` — usados por `RespuestaEstudianteController::store()` (esta tarea) y por `ActividadController::iniciarIntento()` (Task 4).

Este refactor no cambia ningún comportamiento observable — los tests existentes de `CuestionarioIntentosTest.php` deben seguir pasando sin modificarlos. Es una extracción de lógica duplicada antes de que una tercera consumidora (Task 4) la necesite.

- [ ] **Step 1: Escribir los tests que fallan**

Agregar a `tests/Unit/ActividadIntentosTest.php`, antes del `}` final de la clase (agregar `use App\Models\RespuestaEstudiante;` a los imports si no está):

```php
    public function test_intentos_usados_por_cuenta_las_respuestas_del_usuario(): void
    {
        $actividad = Actividad::factory()->create(['tipo' => 'cuestionario']);
        $usuario   = \App\Models\User::factory()->create();
        $otro      = \App\Models\User::factory()->create();

        \App\Models\RespuestaEstudiante::factory()->create(['user_id' => $usuario->id, 'actividad_id' => $actividad->id]);
        \App\Models\RespuestaEstudiante::factory()->create(['user_id' => $usuario->id, 'actividad_id' => $actividad->id]);
        \App\Models\RespuestaEstudiante::factory()->create(['user_id' => $otro->id, 'actividad_id' => $actividad->id]);

        $this->assertEquals(2, $actividad->intentosUsadosPor($usuario->id));
        $this->assertEquals(1, $actividad->intentosUsadosPor($otro->id));
    }

    public function test_tiene_intento_en_revision_para_detecta_respuesta_en_revision_del_usuario(): void
    {
        $actividad = Actividad::factory()->create(['tipo' => 'cuestionario']);
        $usuario   = \App\Models\User::factory()->create();

        $this->assertFalse($actividad->tieneIntentoEnRevisionPara($usuario->id));

        \App\Models\RespuestaEstudiante::factory()->create([
            'user_id' => $usuario->id, 'actividad_id' => $actividad->id, 'estado' => 'en_revision',
        ]);

        $this->assertTrue($actividad->tieneIntentoEnRevisionPara($usuario->id));
    }
```

- [ ] **Step 2: Ejecutar los tests para confirmar que fallan**

Run: `php artisan test --filter=ActividadIntentosTest`
Expected: FAIL — los métodos `intentosUsadosPor` y `tieneIntentoEnRevisionPara` no existen.

- [ ] **Step 3: Agregar los métodos al modelo**

En `app/Models/Actividad.php`, agregar tras `permiteMultiplesIntentos()` (antes del `}` de cierre de la clase):

```php
    public function permiteMultiplesIntentos(): bool
    {
        return $this->tipo === 'cuestionario' && $this->intentos_permitidos > 1;
    }

    public function intentosUsadosPor(int $userId): int
    {
        return $this->respuestas()->where('user_id', $userId)->count();
    }

    public function tieneIntentoEnRevisionPara(int $userId): bool
    {
        return $this->respuestas()
            ->where('user_id', $userId)
            ->where('estado', 'en_revision')
            ->exists();
    }
}
```

- [ ] **Step 4: Ejecutar los tests para confirmar que pasan**

Run: `php artisan test --filter=ActividadIntentosTest`
Expected: PASS (todos los tests existentes + los 2 nuevos)

- [ ] **Step 5: Refactorizar `RespuestaEstudianteController::store()` para usar los helpers, y limpiar `IntentoEnProgreso` al enviar**

En `app/Http/Controllers/RespuestaEstudianteController.php`, agregar el import (tras la línea 8):

```php
use App\Models\IntentoEnProgreso;
use App\Models\Notificacion;
```

Reemplazar el bloque de las líneas 25-45 (dentro de `store()`):

```php
        if ($actividad->tipo === 'cuestionario') {
            if ($actividad->intentosUsadosPor(Auth::id()) >= $actividad->intentos_permitidos) {
                return redirect()
                    ->route('actividades.show', $actividad)
                    ->with('error', 'Ya usaste todos los intentos permitidos para este cuestionario.');
            }

            if ($actividad->tieneIntentoEnRevisionPara(Auth::id())) {
                return redirect()
                    ->route('actividades.show', $actividad)
                    ->with('error', 'Tienes un intento pendiente de revisión. Espera a que el instructor lo califique antes de reintentar.');
            }
        } else {
```

(el resto del `else` — líneas 47-55 originales — no cambia).

Luego, agregar la limpieza del intento en progreso justo después de la creación de `RespuestaEstudiante` (tras la línea 111 original, antes de `$actividad->completarPara(Auth::id());`):

```php
        RespuestaEstudiante::create([
            'user_id'         => Auth::id(),
            'actividad_id'    => $actividad->id,
            'respuesta'       => $request->respuesta ?? '',
            'archivo_adjunto' => $archivoPath,
            'calificacion'    => $calificacion,
            'estado'          => $estado,
            'fecha_envio'     => now(),
        ]);

        IntentoEnProgreso::where('user_id', Auth::id())
            ->where('actividad_id', $actividad->id)
            ->delete();

        $actividad->completarPara(Auth::id());
```

- [ ] **Step 6: Ejecutar toda la suite para confirmar que no hay regresiones**

Run: `php artisan test`
Expected: PASS — en particular `CuestionarioIntentosTest` (todos sus tests existentes, sin modificarlos) debe seguir pasando exactamente igual, ya que el refactor no cambia ningún mensaje ni comportamiento observable.

- [ ] **Step 7: Commit**

```bash
git add app/Models/Actividad.php app/Http/Controllers/RespuestaEstudianteController.php tests/Unit/ActividadIntentosTest.php
git commit -m "Extrae intentosUsadosPor/tieneIntentoEnRevisionPara y limpia IntentoEnProgreso al enviar respuesta"
```

---

### Task 3: `CalificacionService::registrarIntentoExpirado()`

**Files:**
- Modify: `app/Services/CalificacionService.php` (agregar método al final)
- Test: `tests/Unit/CalificacionServiceTest.php` (agregar métodos)

**Interfaces:**
- Consumes: `CalificacionService::tienePreguntasCortas()`, `CalificacionService::calcularCuestionario()` (ya existentes).
- Produces: `CalificacionService::registrarIntentoExpirado(Actividad $actividad, int $userId): RespuestaEstudiante` — usado por `ActividadController::show()` en la Task 5.

- [ ] **Step 1: Escribir los tests que fallan**

Agregar a `tests/Unit/CalificacionServiceTest.php`, antes del `}` final de la clase:

```php
    public function test_registrar_intento_expirado_crea_respuesta_calificada_en_cero_sin_preguntas_cortas(): void
    {
        $actividad = Actividad::factory()->create(['tipo' => 'cuestionario', 'puntaje_maximo' => 100]);
        Pregunta::factory()->create(['actividad_id' => $actividad->id, 'tipo' => 'opcion_multiple', 'puntaje' => 100]);
        $usuario = User::factory()->create();

        $respuesta = $this->service->registrarIntentoExpirado($actividad, $usuario->id);

        $this->assertEquals($usuario->id, $respuesta->user_id);
        $this->assertEquals($actividad->id, $respuesta->actividad_id);
        $this->assertEquals('{}', $respuesta->respuesta);
        $this->assertEquals(0, (float) $respuesta->calificacion);
        $this->assertEquals('calificada', $respuesta->estado);
        $this->assertDatabaseHas('respuestas_estudiantes', ['id' => $respuesta->id, 'estado' => 'calificada']);
    }

    public function test_registrar_intento_expirado_queda_en_revision_si_hay_preguntas_cortas(): void
    {
        $actividad = Actividad::factory()->create(['tipo' => 'cuestionario', 'puntaje_maximo' => 100]);
        Pregunta::factory()->create(['actividad_id' => $actividad->id, 'tipo' => 'respuesta_corta', 'puntaje' => 100]);
        $usuario = User::factory()->create();

        $respuesta = $this->service->registrarIntentoExpirado($actividad, $usuario->id);

        $this->assertNull($respuesta->calificacion);
        $this->assertEquals('en_revision', $respuesta->estado);
    }

    public function test_registrar_intento_expirado_marca_la_actividad_como_completada(): void
    {
        $actividad = Actividad::factory()->create(['tipo' => 'cuestionario', 'puntaje_maximo' => 100]);
        Pregunta::factory()->create(['actividad_id' => $actividad->id, 'tipo' => 'opcion_multiple', 'puntaje' => 100]);
        $usuario = User::factory()->create();

        $this->service->registrarIntentoExpirado($actividad, $usuario->id);

        $this->assertDatabaseHas('progreso_actividades', [
            'user_id' => $usuario->id, 'actividad_id' => $actividad->id, 'completado' => 1,
        ]);
    }
```

- [ ] **Step 2: Ejecutar los tests para confirmar que fallan**

Run: `php artisan test --filter=CalificacionServiceTest`
Expected: FAIL — el método `registrarIntentoExpirado` no existe.

- [ ] **Step 3: Implementar el método**

En `app/Services/CalificacionService.php`, agregar al final de la clase (tras `respuestasOficiales()`, antes del `}` de cierre):

```php
    /**
     * Registra un intento de cuestionario vencido sin envío del estudiante (el tiempo se
     * agotó mientras la pestaña estaba cerrada, por lo que el auto-envío de JS nunca corrió).
     * Usa la misma lógica de calificación que un envío manual, con respuesta vacía.
     */
    public function registrarIntentoExpirado(Actividad $actividad, int $userId): RespuestaEstudiante
    {
        if ($this->tienePreguntasCortas($actividad)) {
            $calificacion = null;
            $estado       = 'en_revision';
        } else {
            $calificacion = $this->calcularCuestionario($actividad, '{}');
            $estado       = 'calificada';
        }

        $respuesta = RespuestaEstudiante::create([
            'user_id'      => $userId,
            'actividad_id' => $actividad->id,
            'respuesta'    => '{}',
            'calificacion' => $calificacion,
            'estado'       => $estado,
            'fecha_envio'  => now(),
        ]);

        $actividad->completarPara($userId);

        return $respuesta;
    }
```

- [ ] **Step 4: Ejecutar los tests para confirmar que pasan**

Run: `php artisan test --filter=CalificacionServiceTest`
Expected: PASS (todos los tests existentes + los 3 nuevos)

- [ ] **Step 5: Commit**

```bash
git add app/Services/CalificacionService.php tests/Unit/CalificacionServiceTest.php
git commit -m "Agrega CalificacionService::registrarIntentoExpirado() para intentos vencidos sin envío"
```

---

### Task 4: `ActividadController::iniciarIntento()` y ruta

**Files:**
- Modify: `app/Http/Controllers/ActividadController.php` (agregar import + método)
- Modify: `routes/web.php:80`
- Test: `tests/Feature/CuestionarioTemporizadorTest.php` (nuevo)

**Interfaces:**
- Consumes: `Actividad::intentosUsadosPor()`, `Actividad::tieneIntentoEnRevisionPara()` (Task 2), `IntentoEnProgreso` (Task 1).
- Produces: `POST actividades/{actividad}/iniciar-intento` → `actividades.iniciarIntento`, usado por el partial de la Task 6.

- [ ] **Step 1: Escribir los tests que fallan**

Crear `tests/Feature/CuestionarioTemporizadorTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\Actividad;
use App\Models\Curso;
use App\Models\Inscripcion;
use App\Models\IntentoEnProgreso;
use App\Models\Leccion;
use App\Models\Modulo;
use App\Models\Opcion;
use App\Models\Pregunta;
use App\Models\Rol;
use App\Models\RespuestaEstudiante;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CuestionarioTemporizadorTest extends TestCase
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

    private function crearCuestionario(?int $duracionMinutos, int $intentosPermitidos = 1): Actividad
    {
        $actividad = Actividad::factory()->create([
            'leccion_id'          => $this->leccion->id,
            'tipo'                => 'cuestionario',
            'puntaje_maximo'      => 100,
            'duracion_minutos'    => $duracionMinutos,
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

    public function test_iniciar_intento_crea_la_fila_con_fecha_inicio_cercana_a_ahora(): void
    {
        $actividad = $this->crearCuestionario(20);

        $response = $this->actingAs($this->estudiante)->post(route('actividades.iniciarIntento', $actividad));

        $response->assertRedirect(route('actividades.show', $actividad));
        $this->assertDatabaseHas('intentos_en_progreso', [
            'user_id' => $this->estudiante->id, 'actividad_id' => $actividad->id,
        ]);
        $intento = IntentoEnProgreso::where('actividad_id', $actividad->id)->first();
        $this->assertTrue($intento->fecha_inicio->diffInSeconds(now()) < 5);
    }

    public function test_segundo_post_no_reinicia_fecha_inicio(): void
    {
        $actividad = $this->crearCuestionario(20);

        $this->actingAs($this->estudiante)->post(route('actividades.iniciarIntento', $actividad));
        $original = IntentoEnProgreso::where('actividad_id', $actividad->id)->first()->fecha_inicio;

        $this->travel(2)->minutes();
        $this->actingAs($this->estudiante)->post(route('actividades.iniciarIntento', $actividad));

        $actual = IntentoEnProgreso::where('actividad_id', $actividad->id)->first()->fecha_inicio;
        $this->assertTrue($original->equalTo($actual));
    }

    public function test_iniciar_intento_rechaza_actividades_que_no_son_cuestionario(): void
    {
        $actividad = Actividad::factory()->create([
            'leccion_id' => $this->leccion->id, 'tipo' => 'tarea', 'puntaje_maximo' => 5,
        ]);

        $response = $this->actingAs($this->estudiante)->post(route('actividades.iniciarIntento', $actividad));

        $response->assertForbidden();
    }

    public function test_iniciar_intento_rechaza_si_ya_se_agotaron_los_intentos(): void
    {
        $actividad = $this->crearCuestionario(20, 1);
        RespuestaEstudiante::factory()->create(['user_id' => $this->estudiante->id, 'actividad_id' => $actividad->id]);

        $response = $this->actingAs($this->estudiante)->post(route('actividades.iniciarIntento', $actividad));

        $response->assertRedirect(route('actividades.show', $actividad));
        $response->assertSessionHas('error', 'Ya usaste todos los intentos permitidos para este cuestionario.');
        $this->assertDatabaseMissing('intentos_en_progreso', ['actividad_id' => $actividad->id]);
    }

    public function test_iniciar_intento_rechaza_si_hay_intento_en_revision(): void
    {
        $actividad = $this->crearCuestionario(20, 3);
        RespuestaEstudiante::factory()->create([
            'user_id' => $this->estudiante->id, 'actividad_id' => $actividad->id, 'estado' => 'en_revision',
        ]);

        $response = $this->actingAs($this->estudiante)->post(route('actividades.iniciarIntento', $actividad));

        $response->assertSessionHas('error', 'Tienes un intento pendiente de revisión. Espera a que el instructor lo califique antes de reintentar.');
        $this->assertDatabaseMissing('intentos_en_progreso', ['actividad_id' => $actividad->id]);
    }
}
```

- [ ] **Step 2: Ejecutar los tests para confirmar que fallan**

Run: `php artisan test --filter=CuestionarioTemporizadorTest`
Expected: FAIL — la ruta `actividades.iniciarIntento` no existe (error de ruta no definida).

- [ ] **Step 3: Agregar la ruta**

En `routes/web.php`, agregar justo después de la línea 80 (`Route::post('/actividades/{actividad}/completar', ...)`):

```php
    Route::get('/actividades/{actividad}', [ActividadController::class, 'show'])->name('actividades.show');
    Route::post('/actividades/{actividad}/completar', [ActividadController::class, 'completar'])->name('actividades.completar');
    Route::post('/actividades/{actividad}/iniciar-intento', [ActividadController::class, 'iniciarIntento'])->name('actividades.iniciarIntento');
```

- [ ] **Step 4: Implementar el método en el controlador**

En `app/Http/Controllers/ActividadController.php`, agregar un único import nuevo, justo antes de la línea 9 (`use App\Models\ProgresoActividad;` — que, junto con `Curso`, `Inscripcion` y `Leccion`, ya están importados en el archivo; no se deben duplicar):

```php
use App\Models\IntentoEnProgreso;
use App\Models\ProgresoActividad;
```

Agregar el método justo después de `completar()` (tras la línea 164 original, antes de `destroy()`):

```php
    public function iniciarIntento(Actividad $actividad)
    {
        $this->authorize('view', $actividad->leccion->modulo->curso);
        abort_unless($actividad->tipo === 'cuestionario', 403);

        if (!$actividad->estaAbierta()) {
            return redirect()
                ->route('actividades.show', $actividad)
                ->with('error', 'Esta actividad no está abierta.');
        }

        if ($actividad->intentosUsadosPor(auth()->id()) >= $actividad->intentos_permitidos) {
            return redirect()
                ->route('actividades.show', $actividad)
                ->with('error', 'Ya usaste todos los intentos permitidos para este cuestionario.');
        }

        if ($actividad->tieneIntentoEnRevisionPara(auth()->id())) {
            return redirect()
                ->route('actividades.show', $actividad)
                ->with('error', 'Tienes un intento pendiente de revisión. Espera a que el instructor lo califique antes de reintentar.');
        }

        IntentoEnProgreso::firstOrCreate(
            ['user_id' => auth()->id(), 'actividad_id' => $actividad->id],
            ['fecha_inicio' => now()]
        );

        return redirect()->route('actividades.show', $actividad);
    }
```

- [ ] **Step 5: Ejecutar los tests para confirmar que pasan**

Run: `php artisan test --filter=CuestionarioTemporizadorTest`
Expected: PASS (5 tests)

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/ActividadController.php routes/web.php tests/Feature/CuestionarioTemporizadorTest.php
git commit -m "Agrega ActividadController::iniciarIntento() para registrar el inicio de un cuestionario"
```

---

### Task 5: `ActividadController::show()` — intento en progreso, segundos restantes, auto-envío por expiración

**Files:**
- Modify: `app/Http/Controllers/ActividadController.php:67-105` (show)
- Test: `tests/Feature/CuestionarioTemporizadorTest.php` (agregar métodos)

**Interfaces:**
- Consumes: `IntentoEnProgreso` (Task 1), `CalificacionService::registrarIntentoExpirado()` (Task 3).
- Produces: la vista recibe `$intentoEnProgreso` (modelo o `null`) y `$segundosRestantes` (int o `null`) además de las variables ya existentes.

- [ ] **Step 1: Escribir los tests que fallan**

Agregar a `tests/Feature/CuestionarioTemporizadorTest.php`, antes del `}` final de la clase:

```php
    public function test_show_no_expone_las_preguntas_antes_de_iniciar_el_intento(): void
    {
        $actividad = $this->crearCuestionario(20);
        $pregunta  = $actividad->preguntas()->first();

        $response = $this->actingAs($this->estudiante)->get(route('actividades.show', $actividad));

        $response->assertOk();
        $response->assertDontSee($pregunta->pregunta_texto);
        $response->assertDontSee('id="form-respuesta"', false);
    }

    public function test_show_con_intento_en_progreso_y_tiempo_restante_no_expira(): void
    {
        $actividad = $this->crearCuestionario(20);
        $this->actingAs($this->estudiante)->post(route('actividades.iniciarIntento', $actividad));

        $response = $this->actingAs($this->estudiante)->get(route('actividades.show', $actividad));

        $response->assertOk();
        $pregunta = $actividad->preguntas()->first();
        $response->assertSee($pregunta->pregunta_texto);
        $this->assertDatabaseHas('intentos_en_progreso', ['actividad_id' => $actividad->id]);
    }

    public function test_show_sin_tiempo_limite_no_calcula_segundos_restantes(): void
    {
        $actividad = $this->crearCuestionario(null);
        $this->actingAs($this->estudiante)->post(route('actividades.iniciarIntento', $actividad));

        $response = $this->actingAs($this->estudiante)->get(route('actividades.show', $actividad));

        $response->assertOk();
        $response->assertViewHas('segundosRestantes', fn($value) => $value === null);
    }

    public function test_show_auto_envia_respuesta_vacia_si_el_intento_ya_expiro(): void
    {
        $actividad = $this->crearCuestionario(20);
        $intento   = IntentoEnProgreso::create([
            'user_id'      => $this->estudiante->id,
            'actividad_id' => $actividad->id,
            'fecha_inicio' => now()->subMinutes(21),
        ]);

        $response = $this->actingAs($this->estudiante)->get(route('actividades.show', $actividad));

        $response->assertRedirect(route('actividades.show', $actividad));
        $this->assertDatabaseHas('respuestas_estudiantes', [
            'user_id' => $this->estudiante->id, 'actividad_id' => $actividad->id,
            'respuesta' => '{}', 'estado' => 'calificada',
        ]);
        $this->assertDatabaseMissing('intentos_en_progreso', ['id' => $intento->id]);
    }
```

- [ ] **Step 2: Ejecutar los tests para confirmar que fallan**

Run: `php artisan test --filter=CuestionarioTemporizadorTest`
Expected: FAIL en los 4 tests nuevos — `show()` todavía no calcula `$intentoEnProgreso`/`$segundosRestantes` ni auto-envía por expiración; las preguntas siguen visibles siempre que haya intentos disponibles.

- [ ] **Step 3: Modificar `ActividadController::show()`**

Reemplazar el método completo (líneas 67-105 originales) por:

```php
    public function show(Actividad $actividad)
    {
        $this->authorize('view', $actividad->leccion->modulo->curso);

        $intentoEnProgreso = null;
        $segundosRestantes = null;

        if ($actividad->tipo === 'cuestionario') {
            $intentoEnProgreso = IntentoEnProgreso::where('user_id', auth()->id())
                ->where('actividad_id', $actividad->id)
                ->first();

            if ($intentoEnProgreso && $actividad->duracion_minutos) {
                $segundosRestantes = $actividad->duracion_minutos * 60
                    - now()->diffInSeconds($intentoEnProgreso->fecha_inicio);

                if ($segundosRestantes <= 0) {
                    app(\App\Services\CalificacionService::class)
                        ->registrarIntentoExpirado($actividad, auth()->id());
                    $intentoEnProgreso->delete();

                    return redirect()->route('actividades.show', $actividad);
                }
            }
        }

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
            'intentos', 'intentosUsados', 'intentosRestantes', 'tieneIntentoEnRevision',
            'intentoEnProgreso', 'segundosRestantes'
        ));
    }
```

- [ ] **Step 4: Ejecutar los tests para confirmar que pasan**

Run: `php artisan test --filter=CuestionarioTemporizadorTest`
Expected: PASS (9 tests)

- [ ] **Step 5: Ejecutar toda la suite para descartar regresiones**

Run: `php artisan test`
Expected: PASS — en particular `CuestionarioIntentosTest` no debe tener ninguna regresión (sus tests actuales no dependen de ver las preguntas antes de iniciar un intento en progreso: los que llegan a ver el formulario lo hacen después de que `enviarRespuesta()` ya creó una `RespuestaEstudiante`, lo cual es independiente de `IntentoEnProgreso`).

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/ActividadController.php tests/Feature/CuestionarioTemporizadorTest.php
git commit -m "Calcula intento en progreso y segundos restantes en show(), con auto-envío si ya expiró"
```

---

### Task 6: Partial de inicio/temporizador y verificación final

**Files:**
- Create: `resources/views/actividades/partials/cuestionario-con-inicio.blade.php`
- Modify: `resources/views/actividades/show.blade.php:543-555` (rama de múltiples intentos)
- Modify: `resources/views/actividades/show.blade.php:586-664` (rama sin cambios / un solo intento)
- Test: `tests/Feature/CuestionarioTemporizadorTest.php` (agregar métodos)

**Interfaces:**
- Consumes: `$intentoEnProgreso`, `$segundosRestantes`, `$respuesta`, `$intentosUsados` (Task 5); `actividades.iniciarIntento` (Task 4); `actividades.partials.formulario-cuestionario` (ya existente).

- [ ] **Step 1: Escribir los tests que fallan**

Agregar a `tests/Feature/CuestionarioTemporizadorTest.php`, antes del `}` final de la clase:

```php
    public function test_pantalla_de_inicio_muestra_intentos_permitidos_preguntas_y_tiempo_limite(): void
    {
        $actividad = $this->crearCuestionario(20, 3);

        $response = $this->actingAs($this->estudiante)->get(route('actividades.show', $actividad));

        $response->assertOk();
        $response->assertSee('Iniciar cuestionario');
        $response->assertSee('3'); // intentos_permitidos
        $response->assertSee('20'); // duracion_minutos
        $response->assertSee('minutos');
        $response->assertSee('pregunta'); // singular: crearCuestionario() crea exactamente 1 pregunta
        $response->assertDontSee('preguntas'); // confirma que no cayó en la rama plural
    }

    public function test_pantalla_de_inicio_no_muestra_tiempo_limite_si_no_hay(): void
    {
        $actividad = $this->crearCuestionario(null);

        $response = $this->actingAs($this->estudiante)->get(route('actividades.show', $actividad));

        $response->assertOk();
        $response->assertSee('Iniciar cuestionario');
        $response->assertDontSee('minutos');
    }

    public function test_boton_dice_reintentar_si_ya_hubo_un_intento_previo(): void
    {
        $actividad = $this->crearCuestionario(20, 3);
        RespuestaEstudiante::factory()->create([
            'user_id' => $this->estudiante->id, 'actividad_id' => $actividad->id,
            'calificacion' => 100, 'estado' => 'calificada',
        ]);

        $response = $this->actingAs($this->estudiante)->get(route('actividades.show', $actividad));

        $response->assertOk();
        $response->assertSee('Reintentar');
    }

    public function test_formulario_con_intento_en_progreso_muestra_las_preguntas_y_el_cronometro(): void
    {
        $actividad = $this->crearCuestionario(20);
        $this->actingAs($this->estudiante)->post(route('actividades.iniciarIntento', $actividad));

        $response = $this->actingAs($this->estudiante)->get(route('actividades.show', $actividad));

        $response->assertOk();
        $response->assertSee('id="form-respuesta"', false);
        $response->assertSee('mmss', false);
    }

    public function test_formulario_sin_tiempo_limite_no_muestra_cronometro(): void
    {
        $actividad = $this->crearCuestionario(null);
        $this->actingAs($this->estudiante)->post(route('actividades.iniciarIntento', $actividad));

        $response = $this->actingAs($this->estudiante)->get(route('actividades.show', $actividad));

        $response->assertOk();
        $response->assertSee('id="form-respuesta"', false);
        $response->assertDontSee('mmss', false);
    }
```

- [ ] **Step 2: Ejecutar los tests para confirmar que fallan**

Run: `php artisan test --filter=CuestionarioTemporizadorTest`
Expected: FAIL en los 5 tests nuevos — el partial `cuestionario-con-inicio.blade.php` todavía no existe y `show.blade.php` sigue mostrando el formulario directamente.

- [ ] **Step 3: Crear el partial**

Crear `resources/views/actividades/partials/cuestionario-con-inicio.blade.php`:

```blade
@if($intentoEnProgreso)
    <form id="form-respuesta" action="{{ route('respuestas.store', $actividad) }}" method="POST" enctype="multipart/form-data">
        @csrf

        @if($segundosRestantes !== null)
        <div x-data="{
                segundos: {{ $segundosRestantes }},
                intervalo: null,
                init() {
                    this.intervalo = setInterval(() => {
                        this.segundos--;
                        if (this.segundos <= 0) {
                            clearInterval(this.intervalo);
                            document.getElementById('form-respuesta').requestSubmit();
                        }
                    }, 1000);
                },
                get mmss() {
                    const m = Math.floor(this.segundos / 60);
                    const s = this.segundos % 60;
                    return String(m).padStart(2, '0') + ':' + String(s).padStart(2, '0');
                }
             }"
             class="sticky top-4 z-10 mb-4 flex items-center justify-center gap-2 px-4 py-2 rounded-lg font-mono text-lg font-bold"
             :class="segundos <= 60 ? 'bg-red-50 text-red-700 animate-pulse' : 'bg-blue-50 text-blue-700'">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <span x-text="mmss"></span>
        </div>
        @endif

        @include('actividades.partials.formulario-cuestionario')

        <div class="mt-6 flex justify-end">
            <button type="submit" class="bg-blue-600 text-white px-8 py-3 rounded-lg hover:bg-blue-700 font-medium">
                Enviar respuesta
            </button>
        </div>
    </form>
@else
    @php $totalPreguntas = $actividad->preguntas()->count(); @endphp
    <div class="bg-white rounded-lg shadow p-8 text-center">
        <svg class="w-12 h-12 text-blue-400 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
        </svg>
        <div class="flex justify-center flex-wrap gap-6 text-sm text-gray-600 mb-6">
            <span>
                <strong class="block text-gray-900 text-lg">{{ $actividad->intentos_permitidos }}</strong>
                {{ $actividad->intentos_permitidos === 1 ? 'intento permitido' : 'intentos permitidos' }}
                @if($actividad->permiteMultiplesIntentos())
                    <span class="block text-xs text-gray-400">(ya usaste {{ $intentosUsados }} de {{ $actividad->intentos_permitidos }})</span>
                @endif
            </span>
            @if($actividad->duracion_minutos)
            <span>
                <strong class="block text-gray-900 text-lg">{{ $actividad->duracion_minutos }}</strong>
                minutos
            </span>
            @endif
            <span>
                <strong class="block text-gray-900 text-lg">{{ $totalPreguntas }}</strong>
                {{ $totalPreguntas === 1 ? 'pregunta' : 'preguntas' }}
            </span>
        </div>
        <form action="{{ route('actividades.iniciarIntento', $actividad) }}" method="POST">
            @csrf
            <button type="submit" class="bg-blue-600 text-white px-8 py-3 rounded-lg hover:bg-blue-700 font-medium">
                {{ $respuesta ? 'Reintentar' : 'Iniciar cuestionario' }}
            </button>
        </form>
    </div>
@endif
```

- [ ] **Step 4: Reemplazar la rama de múltiples intentos en `show.blade.php`**

En `resources/views/actividades/show.blade.php`, reemplazar el bloque de las líneas 543-555:

```blade
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
                @include('actividades.partials.estado-plazo-bloqueado')
                @endif
```

por:

```blade
                @if($intentosRestantes > 0 && $actividad->estaAbierta())
                    @include('actividades.partials.cuestionario-con-inicio')
                @elseif(!$respuesta && !$actividad->estaAbierta())
                    @include('actividades.partials.estado-plazo-bloqueado')
                @endif
```

- [ ] **Step 5: Reemplazar la rama sin cambios (un solo intento) en `show.blade.php`**

En el mismo archivo, reemplazar el bloque de las líneas 586-664:

```blade
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
```

por:

```blade
        @else
            @if($actividad->tipo === 'cuestionario')
                @include('actividades.partials.cuestionario-con-inicio')
            @else
                {{-- Formulario de respuesta (ensayo/tarea/practica) --}}
                <form id="form-respuesta" action="{{ route('respuestas.store', $actividad) }}" method="POST" enctype="multipart/form-data">
                    @csrf
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

                    <div class="mt-6 flex justify-end">
                        <button type="submit" class="bg-blue-600 text-white px-8 py-3 rounded-lg hover:bg-blue-700 font-medium">
                            Enviar respuesta
                        </button>
                    </div>
                </form>
            @endif
        @endif
```

- [ ] **Step 6: Ejecutar los tests para confirmar que pasan**

Run: `php artisan test --filter=CuestionarioTemporizadorTest`
Expected: PASS (14 tests)

- [ ] **Step 7: Ejecutar toda la suite del proyecto**

Run: `php artisan test`
Expected: PASS — sin regresiones en `CuestionarioIntentosTest`, `RubricaTest`, `ActividadSinNotaTest`, ni el resto de la suite (en particular, la rama `ensayo`/`tarea`/`practica` del formulario de respuesta no cambió su HTML ni su comportamiento).

- [ ] **Step 8: Commit**

```bash
git add resources/views/actividades/partials/cuestionario-con-inicio.blade.php resources/views/actividades/show.blade.php tests/Feature/CuestionarioTemporizadorTest.php
git commit -m "Agrega pantalla de inicio y temporizador de cuenta regresiva a los cuestionarios"
```

---

## Fuera de alcance (heredado de la spec)

- Pausar o extender el tiempo manualmente.
- Advertencias sonoras o notificaciones del navegador.
- Sincronizar el reloj del cliente con el servidor más allá del valor inicial.
- Pantalla de inicio o temporizador para tipos de actividad distintos a `cuestionario`.
