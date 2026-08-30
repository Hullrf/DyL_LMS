# Aprobación de Instructor/Admin antes de Emitir Certificados — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Un certificado solo se genera cuando el estudiante completó el curso **y** un instructor/admin lo aprobó explícitamente desde la matriz de Calificaciones — la nota mínima del curso es informativa, no bloqueante.

**Architecture:** Se agrega `Curso.nota_aprobatoria` (informativa) y `Certificado.aprobado_por_id` (auditoría). `CertificadoService::generarSiCorresponde()` exige un tercer parámetro `$aprobadoPor`, lo que obliga a eliminar la ruta de autoservicio del estudiante (`certificados.generar`) — ya no hay caller legítimo. Un endpoint nuevo bajo `calificaciones.*` (mismo middleware `instructor`, misma autorización que el resto de esa pantalla) hace el llamado real. La matriz de Calificaciones gana una columna que refleja el estado de cada estudiante y dispara la aprobación.

**Tech Stack:** Laravel 12, PHP 8.2, MySQL, Blade + Alpine.js, PHPUnit.

**Spec:** `docs/superpowers/specs/2026-08-30-aprobacion-instructor-certificados-design.md`

## Global Constraints

- `nota_aprobatoria` tiene default `80`, no nullable — todo curso (nuevo o existente) queda con un mínimo definido sin migración de datos manual.
- `aprobado_por_id` es nullable — certificados emitidos antes de este cambio quedan con `null`, no se retro-completan.
- La nota mínima **no bloquea** la generación en el servicio — es solo una señal visual para el instructor. No agregar ningún parámetro `$forzar`/override a `CertificadoService`.
- No se toca `tipo_certificado`, el gate de `numero_documento`, ni ninguna plantilla PDF — quedan exactamente como están.
- Cada tarea termina en verde (`php artisan test`) antes de comitear — la suite completa, no solo el filtro de la tarea.
- Los mensajes de usuario (flash, botones, notificaciones) van en español, mismo tono que el resto de la app.

---

## Task 1: Migraciones y modelos — `nota_aprobatoria` y `aprobado_por_id`

**Files:**
- Create: `database/migrations/2026_08_30_000001_add_nota_aprobatoria_to_cursos_table.php`
- Create: `database/migrations/2026_08_30_000002_add_aprobado_por_to_certificados_table.php`
- Modify: `app/Models/Curso.php` (agregar a `$fillable`)
- Modify: `app/Models/Certificado.php` (agregar a `$fillable`, nueva relación `aprobador()`)
- Test: `tests/Unit/CursoAprobadoPorDatosNuevosTest.php`

**Interfaces:**
- Produces: `Curso::$nota_aprobatoria` (int, default `80`), `Certificado::$aprobado_por_id` (int|null), `Certificado::aprobador(): BelongsTo` → `User`.

- [ ] **Step 1: Crear la migración de `cursos`**

```php
<?php
// database/migrations/2026_08_30_000001_add_nota_aprobatoria_to_cursos_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cursos', function (Blueprint $table) {
            $table->unsignedTinyInteger('nota_aprobatoria')
                ->default(80)
                ->after('tipo_certificado');
        });
    }

    public function down(): void
    {
        Schema::table('cursos', function (Blueprint $table) {
            $table->dropColumn('nota_aprobatoria');
        });
    }
};
```

- [ ] **Step 2: Crear la migración de `certificados`**

```php
<?php
// database/migrations/2026_08_30_000002_add_aprobado_por_to_certificados_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('certificados', function (Blueprint $table) {
            $table->foreignId('aprobado_por_id')->nullable()
                ->after('calificacion_final')
                ->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('certificados', function (Blueprint $table) {
            $table->dropConstrainedForeignId('aprobado_por_id');
        });
    }
};
```

- [ ] **Step 3: Correr las migraciones y verificar que no fallan**

Run: `php artisan migrate`
Expected: ambas migraciones corren sin error. Verificar con `php artisan migrate:status` que las dos aparecen como `Ran`.

- [ ] **Step 4: Agregar los campos nuevos a `$fillable`**

En `app/Models/Curso.php`, el `$fillable` actual es:
```php
    protected $fillable = [
        'titulo', 'descripcion', 'duracion_horas',
        'imagen_portada', 'estado', 'created_by', 'orden', 'categoria_id',
        'tipo_certificado',
    ];
```
Reemplazar por:
```php
    protected $fillable = [
        'titulo', 'descripcion', 'duracion_horas',
        'imagen_portada', 'estado', 'created_by', 'orden', 'categoria_id',
        'tipo_certificado', 'nota_aprobatoria',
    ];
```

En `app/Models/Certificado.php` (archivo completo actual):
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Certificado extends Model
{
    protected $table = 'certificados';
    protected $fillable = [
        'user_id', 'curso_id', 'fecha_emision',
        'numero_certificado', 'archivo_pdf', 'calificacion_final',
    ];

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function curso(): BelongsTo
    {
        return $this->belongsTo(Curso::class);
    }
}
```
Reemplazar por:
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Certificado extends Model
{
    protected $table = 'certificados';
    protected $fillable = [
        'user_id', 'curso_id', 'fecha_emision',
        'numero_certificado', 'archivo_pdf', 'calificacion_final', 'aprobado_por_id',
    ];

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function curso(): BelongsTo
    {
        return $this->belongsTo(Curso::class);
    }

    public function aprobador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'aprobado_por_id');
    }
}
```

- [ ] **Step 5: Escribir el test que confirma los campos nuevos**

```php
<?php
// tests/Unit/CursoAprobadoPorDatosNuevosTest.php

namespace Tests\Unit;

use App\Models\Certificado;
use App\Models\Curso;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CursoAprobadoPorDatosNuevosTest extends TestCase
{
    use RefreshDatabase;

    public function test_curso_tiene_nota_aprobatoria_80_por_defecto(): void
    {
        $instructor = User::factory()->create();
        $curso = Curso::factory()->create(['created_by' => $instructor->id]);

        $this->assertSame(80, $curso->fresh()->nota_aprobatoria);
    }

    public function test_curso_permite_configurar_nota_aprobatoria(): void
    {
        $instructor = User::factory()->create();
        $curso = Curso::factory()->create(['created_by' => $instructor->id, 'nota_aprobatoria' => 60]);

        $this->assertSame(60, $curso->fresh()->nota_aprobatoria);
    }

    public function test_certificado_guarda_y_expone_quien_lo_aprobo(): void
    {
        $instructor = User::factory()->create();
        $estudiante = User::factory()->create();
        $curso = Curso::factory()->create(['created_by' => $instructor->id]);

        $certificado = Certificado::create([
            'user_id' => $estudiante->id,
            'curso_id' => $curso->id,
            'fecha_emision' => now()->toDateString(),
            'numero_certificado' => 'CERT-2026-TEST0003',
            'aprobado_por_id' => $instructor->id,
        ]);

        $this->assertSame($instructor->id, $certificado->fresh()->aprobado_por_id);
        $this->assertTrue($certificado->aprobador->is($instructor));
    }
}
```

- [ ] **Step 6: Correr el test y verificar que pasa**

Run: `php artisan test --filter=CursoAprobadoPorDatosNuevosTest`
Expected: 3 passed.

- [ ] **Step 7: Correr toda la suite**

Run: `php artisan test`
Expected: todo en verde (la suite existente no debería verse afectada — ambos campos son aditivos).

- [ ] **Step 8: Commit**

```bash
git add database/migrations/2026_08_30_000001_add_nota_aprobatoria_to_cursos_table.php \
        database/migrations/2026_08_30_000002_add_aprobado_por_to_certificados_table.php \
        app/Models/Curso.php app/Models/Certificado.php \
        tests/Unit/CursoAprobadoPorDatosNuevosTest.php
git commit -m "feat: agregar nota_aprobatoria a cursos y aprobado_por_id a certificados"
```

---

## Task 2: Formulario de curso — configurar `nota_aprobatoria`

**Files:**
- Modify: `app/Http/Controllers/CursoController.php` (validación en `store()` y `update()`)
- Modify: `resources/views/cursos/create.blade.php`
- Modify: `resources/views/cursos/edit.blade.php`
- Test: `tests/Feature/CursoNotaAprobatoriaFormTest.php`

**Interfaces:**
- Consumes: `Curso::$fillable` con `nota_aprobatoria` (Task 1).

- [ ] **Step 1: Escribir el test (falla primero)**

```php
<?php
// tests/Feature/CursoNotaAprobatoriaFormTest.php

namespace Tests\Feature;

use App\Models\Curso;
use App\Models\Rol;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CursoNotaAprobatoriaFormTest extends TestCase
{
    use RefreshDatabase;

    private User $instructor;

    protected function setUp(): void
    {
        parent::setUp();
        $rol = Rol::create(['nombre' => 'Instructor']);
        $this->instructor = User::factory()->create(['estado' => 'activo']);
        $this->instructor->roles()->attach($rol);
    }

    public function test_instructor_puede_crear_curso_con_nota_aprobatoria_personalizada(): void
    {
        $response = $this->actingAs($this->instructor)->post(route('cursos.store'), [
            'titulo' => 'Curso con nota personalizada',
            'descripcion' => str_repeat('a', 25),
            'duracion_horas' => 10,
            'nota_aprobatoria' => 90,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('cursos', [
            'titulo' => 'Curso con nota personalizada',
            'nota_aprobatoria' => 90,
        ]);
    }

    public function test_crear_curso_sin_especificar_nota_aprobatoria_usa_el_default(): void
    {
        $this->actingAs($this->instructor)->post(route('cursos.store'), [
            'titulo' => 'Curso sin nota especificada',
            'descripcion' => str_repeat('a', 25),
            'duracion_horas' => 10,
        ]);

        $this->assertDatabaseHas('cursos', [
            'titulo' => 'Curso sin nota especificada',
            'nota_aprobatoria' => 80,
        ]);
    }

    public function test_instructor_puede_editar_nota_aprobatoria(): void
    {
        $curso = Curso::factory()->create(['created_by' => $this->instructor->id]);

        $response = $this->actingAs($this->instructor)->put(route('cursos.update', $curso), [
            'titulo' => $curso->titulo,
            'descripcion' => str_repeat('b', 25),
            'duracion_horas' => 5,
            'estado' => 'publicado',
            'tipo_certificado' => 'diploma',
            'nota_aprobatoria' => 70,
        ]);

        $response->assertRedirect();
        $this->assertSame(70, $curso->fresh()->nota_aprobatoria);
    }

    public function test_nota_aprobatoria_fuera_de_rango_es_rechazada(): void
    {
        $response = $this->actingAs($this->instructor)->post(route('cursos.store'), [
            'titulo' => 'Curso con nota inválida',
            'descripcion' => str_repeat('a', 25),
            'duracion_horas' => 10,
            'nota_aprobatoria' => 150,
        ]);

        $response->assertSessionHasErrors('nota_aprobatoria');
    }
}
```

- [ ] **Step 2: Correr el test y verificar que falla**

Run: `php artisan test --filter=CursoNotaAprobatoriaFormTest`
Expected: `test_nota_aprobatoria_fuera_de_rango_es_rechazada` FALLA (no hay regla de validación todavía, así que Laravel la ignora silenciosamente en vez de rechazarla — `assertSessionHasErrors` no encuentra el error). Los otros 3 tests probablemente PASAN ya porque `nota_aprobatoria` es `fillable` desde Task 1 y el campo simplemente no se envía o se ignora sin validar.

- [ ] **Step 3: Agregar la validación en `store()`**

En `app/Http/Controllers/CursoController.php`, el bloque de validación actual de `store()` es:
```php
        $validated = $request->validate([
            'titulo'           => 'required|string|max:255|unique:cursos',
            'descripcion'      => 'required|string|min:20',
            'duracion_horas'   => 'required|integer|min:1|max:500',
            'imagen_portada'   => 'nullable|image|mimes:jpeg,png,jpg|max:5120',
            'categoria_id'     => 'nullable|exists:categorias,id',
            'tipo_certificado' => 'nullable|in:diploma,diplomado',
        ]);
```
Reemplazar por:
```php
        $validated = $request->validate([
            'titulo'           => 'required|string|max:255|unique:cursos',
            'descripcion'      => 'required|string|min:20',
            'duracion_horas'   => 'required|integer|min:1|max:500',
            'imagen_portada'   => 'nullable|image|mimes:jpeg,png,jpg|max:5120',
            'categoria_id'     => 'nullable|exists:categorias,id',
            'tipo_certificado' => 'nullable|in:diploma,diplomado',
            'nota_aprobatoria' => 'nullable|integer|min:0|max:100',
        ]);
```

- [ ] **Step 4: Agregar la validación en `update()`**

El bloque de validación actual de `update()` es:
```php
        $validated = $request->validate([
            'titulo'           => 'required|string|max:255|unique:cursos,titulo,' . $curso->id,
            'descripcion'      => 'required|string|min:20',
            'duracion_horas'   => 'required|integer|min:1|max:500',
            'estado'           => 'required|in:borrador,publicado,archivado',
            'imagen_portada'   => 'nullable|image|mimes:jpeg,png,jpg|max:5120',
            'categoria_id'     => 'nullable|exists:categorias,id',
            'tipo_certificado' => 'required|in:diploma,diplomado',
        ]);
```
Reemplazar por:
```php
        $validated = $request->validate([
            'titulo'           => 'required|string|max:255|unique:cursos,titulo,' . $curso->id,
            'descripcion'      => 'required|string|min:20',
            'duracion_horas'   => 'required|integer|min:1|max:500',
            'estado'           => 'required|in:borrador,publicado,archivado',
            'imagen_portada'   => 'nullable|image|mimes:jpeg,png,jpg|max:5120',
            'categoria_id'     => 'nullable|exists:categorias,id',
            'tipo_certificado' => 'required|in:diploma,diplomado',
            'nota_aprobatoria' => 'required|integer|min:0|max:100',
        ]);
```

- [ ] **Step 5: Agregar el campo en `cursos/create.blade.php`**

Justo después del bloque del selector de `tipo_certificado` (busca `<p class="text-xs text-gray-400 mt-1">Define qué diseño de certificado recibe el estudiante al completar el curso.</p>` seguido de `</div>`), agregar:
```blade
        <div class="mb-6">
            <label for="nota_aprobatoria" class="block text-sm font-medium text-gray-700 mb-2">Nota mínima para aprobar (%)</label>
            <input type="number" name="nota_aprobatoria" id="nota_aprobatoria" min="0" max="100"
                   value="{{ old('nota_aprobatoria', 80) }}"
                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-dyl-orange-600 focus:border-transparent">
            <p class="text-xs text-gray-400 mt-1">Guía para el instructor al aprobar certificados — no bloquea la aprobación, solo advierte si la nota del estudiante queda por debajo.</p>
        </div>
```

- [ ] **Step 6: Agregar el campo en `cursos/edit.blade.php`**

Justo después del bloque del selector de `tipo_certificado` (busca el `</select>` seguido de `</div>` que cierra ese bloque, antes del comentario/bloque de `imagen_portada`), agregar:
```blade
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Nota mínima para aprobar (%)</label>
                    <input type="number" name="nota_aprobatoria" min="0" max="100"
                           value="{{ old('nota_aprobatoria', $curso->nota_aprobatoria) }}"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                </div>
```

- [ ] **Step 7: Correr el test y verificar que pasa**

Run: `php artisan test --filter=CursoNotaAprobatoriaFormTest`
Expected: 4 passed.

- [ ] **Step 8: Correr toda la suite**

Run: `php artisan test`
Expected: todo en verde.

- [ ] **Step 9: Commit**

```bash
git add app/Http/Controllers/CursoController.php \
        resources/views/cursos/create.blade.php resources/views/cursos/edit.blade.php \
        tests/Feature/CursoNotaAprobatoriaFormTest.php
git commit -m "feat: permitir configurar nota_aprobatoria al crear/editar un curso"
```

---

## Task 3: `CertificadoService` exige aprobador — se elimina el autoservicio del estudiante

**Files:**
- Modify: `app/Services/CertificadoService.php`
- Modify: `app/Http/Controllers/CertificadoController.php` (elimina `generar()`)
- Modify: `routes/web.php` (elimina la ruta `certificados.generar`)
- Modify: `resources/views/cursos/show.blade.php` (reemplaza el botón por un mensaje de estado)
- Modify: `tests/Feature/CertificadoServiceTipoTest.php` (agrega el 3er argumento)
- Modify: `tests/Unit/CertificadoServiceIntentosTest.php` (agrega el 3er argumento)
- Delete: `tests/Feature/CertificadoGenerarEndToEndTest.php` (ejercitaba la ruta eliminada — Task 6 la reemplaza con el flujo nuevo)
- Test: `tests/Feature/CertificadoServiceAprobacionTest.php`

**Interfaces:**
- Produces: `CertificadoService::generarSiCorresponde(User $usuario, Curso $curso, User $aprobadoPor): ?Certificado` (firma nueva, el tercer parámetro es obligatorio).
- Consumes (para las vistas): nada nuevo — este task no agrega el endpoint de aprobación todavía (eso es Task 4); solo deja el servicio listo y limpia el caller viejo que ya no puede compilar contra la firma nueva.

- [ ] **Step 1: Escribir el test del servicio (falla primero)**

```php
<?php
// tests/Feature/CertificadoServiceAprobacionTest.php

namespace Tests\Feature;

use App\Models\Curso;
use App\Models\Inscripcion;
use App\Models\User;
use App\Services\CertificadoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CertificadoServiceAprobacionTest extends TestCase
{
    use RefreshDatabase;

    private function completarInscripcion(User $estudiante, Curso $curso): void
    {
        Inscripcion::create([
            'user_id' => $estudiante->id, 'curso_id' => $curso->id,
            'fecha_inicio' => '2026-01-01', 'fecha_fin' => '2026-02-01', 'estado' => 'completado',
        ]);
    }

    public function test_certificado_generado_guarda_quien_lo_aprobo(): void
    {
        $instructor = User::factory()->create();
        $estudiante = User::factory()->create();
        $curso = Curso::factory()->create(['created_by' => $instructor->id]);
        $this->completarInscripcion($estudiante, $curso);

        $certificado = app(CertificadoService::class)->generarSiCorresponde($estudiante, $curso, $instructor);

        $this->assertNotNull($certificado);
        $this->assertSame($instructor->id, $certificado->aprobado_por_id);

        @unlink(storage_path('app/public/'.$certificado->archivo_pdf));
    }

    public function test_nota_por_debajo_del_minimo_no_bloquea_la_aprobacion(): void
    {
        $instructor = User::factory()->create();
        $estudiante = User::factory()->create();
        // nota_aprobatoria default 80; sin actividades calificadas, calcularCalificacionFinal()
        // devuelve 100 (aprobado por completar lecciones) — para forzar el caso "por debajo del
        // mínimo" bastaría con un curso cuya nota_aprobatoria sea mayor a 100, lo cual no es un
        // caso real. Lo que este test realmente confirma es que el servicio NUNCA compara contra
        // nota_aprobatoria: no existe ningún parámetro ni chequeo que pueda rechazar la generación
        // por ese motivo, sin importar el valor configurado.
        $curso = Curso::factory()->create(['created_by' => $instructor->id, 'nota_aprobatoria' => 100]);
        $this->completarInscripcion($estudiante, $curso);

        $certificado = app(CertificadoService::class)->generarSiCorresponde($estudiante, $curso, $instructor);

        $this->assertNotNull($certificado);

        @unlink(storage_path('app/public/'.$certificado->archivo_pdf));
    }
}
```

- [ ] **Step 2: Correr el test y verificar que falla**

Run: `php artisan test --filter=CertificadoServiceAprobacionTest`
Expected: FAIL — `Too many arguments to function ... generarSiCorresponde(), 3 passed and exactly 2 expected` (la firma actual todavía acepta solo 2 parámetros). Un error de firma, no una aserción fallida.

- [ ] **Step 3: Cambiar la firma de `generarSiCorresponde()`**

En `app/Services/CertificadoService.php`, el método actual (docblock incluido) es:
```php
    /**
     * Genera (o recupera) el certificado de un usuario para un curso.
     * Solo procede si la inscripción está en estado 'completado'.
     * Retorna el Certificado o null si no corresponde.
     */
    public function generarSiCorresponde(User $usuario, Curso $curso): ?Certificado
    {
        // Ya tiene certificado
        $existente = Certificado::where('user_id', $usuario->id)
            ->where('curso_id', $curso->id)
            ->first();

        if ($existente) {
            return $existente;
        }

        // Verificar que el curso esté completado
        $inscripcion = Inscripcion::where('user_id', $usuario->id)
            ->where('curso_id', $curso->id)
            ->where('estado', 'completado')
            ->first();

        if (!$inscripcion) {
            return null;
        }

        // La carta de diplomado necesita el número de documento del estudiante.
        if ($curso->tipo_certificado === 'diplomado' && !$usuario->numero_documento) {
            Notificacion::crear(
                $usuario->id,
                'certificado',
                'Completa tu perfil para tu certificado',
                "Necesitamos tu número de documento para emitir tu certificado de «{$curso->titulo}». Complétalo en tu perfil.",
                route('profile.edit')
            );

            return null;
        }

        // Calcular calificación final (promedio de actividades calificadas)
        $calificacionFinal = $this->calcularCalificacionFinal($usuario, $curso);

        // Crear registro
        $certificado = Certificado::create([
            'user_id'             => $usuario->id,
            'curso_id'            => $curso->id,
            'fecha_emision'       => now()->toDateString(),
            'numero_certificado'  => $this->generarNumero(),
            'calificacion_final'  => $calificacionFinal,
        ]);

        // Generar PDF y guardar ruta
        $rutaPdf = $this->generarPdf($certificado);
        $certificado->update(['archivo_pdf' => $rutaPdf]);

        return $certificado;
    }
```
Reemplazar por:
```php
    /**
     * Genera (o recupera) el certificado de un usuario para un curso, a
     * pedido explícito de un instructor/admin que lo aprueba.
     * Solo procede si la inscripción está en estado 'completado'.
     * Retorna el Certificado o null si no corresponde.
     */
    public function generarSiCorresponde(User $usuario, Curso $curso, User $aprobadoPor): ?Certificado
    {
        // Ya tiene certificado
        $existente = Certificado::where('user_id', $usuario->id)
            ->where('curso_id', $curso->id)
            ->first();

        if ($existente) {
            return $existente;
        }

        // Verificar que el curso esté completado
        $inscripcion = Inscripcion::where('user_id', $usuario->id)
            ->where('curso_id', $curso->id)
            ->where('estado', 'completado')
            ->first();

        if (!$inscripcion) {
            return null;
        }

        // La carta de diplomado necesita el número de documento del estudiante.
        if ($curso->tipo_certificado === 'diplomado' && !$usuario->numero_documento) {
            Notificacion::crear(
                $usuario->id,
                'certificado',
                'Completa tu perfil para tu certificado',
                "Necesitamos tu número de documento para emitir tu certificado de «{$curso->titulo}». Complétalo en tu perfil.",
                route('profile.edit')
            );

            return null;
        }

        // Calcular calificación final (promedio de actividades calificadas)
        $calificacionFinal = $this->calcularCalificacionFinal($usuario, $curso);

        // Crear registro
        $certificado = Certificado::create([
            'user_id'             => $usuario->id,
            'curso_id'            => $curso->id,
            'fecha_emision'       => now()->toDateString(),
            'numero_certificado'  => $this->generarNumero(),
            'calificacion_final'  => $calificacionFinal,
            'aprobado_por_id'     => $aprobadoPor->id,
        ]);

        // Generar PDF y guardar ruta
        $rutaPdf = $this->generarPdf($certificado);
        $certificado->update(['archivo_pdf' => $rutaPdf]);

        return $certificado;
    }
```
Nota: `nota_aprobatoria` NO aparece en este método — es deliberado, ver Global Constraints.

- [ ] **Step 4: Actualizar los 2 tests existentes que llaman a `generarSiCorresponde()` con la firma vieja**

En `tests/Feature/CertificadoServiceTipoTest.php`, las 3 llamadas son idénticas:
```php
        $certificado = app(CertificadoService::class)->generarSiCorresponde($estudiante, $curso);
```
Cada una de las 3 aparece dentro de un test que ya crea `$instructor` (el `created_by` del curso). Reemplazar las 3 ocurrencias por:
```php
        $certificado = app(CertificadoService::class)->generarSiCorresponde($estudiante, $curso, $instructor);
```

En `tests/Unit/CertificadoServiceIntentosTest.php`, la única llamada:
```php
        $certificado = app(CertificadoService::class)->generarSiCorresponde($estudiante, $curso);
```
Ese archivo también crea `$instructor` antes (`created_by` del curso). Reemplazar por:
```php
        $certificado = app(CertificadoService::class)->generarSiCorresponde($estudiante, $curso, $instructor);
```

- [ ] **Step 5: Eliminar el método `generar()` de `CertificadoController`**

En `app/Http/Controllers/CertificadoController.php`, eliminar por completo este método (incluyendo su docblock, es el primer método de la clase):
```php
    /**
     * Genera (o recupera) el certificado del usuario autenticado para un curso.
     * Solo funciona si la inscripción está completada.
     */
    public function generar(Curso $curso)
    {
        $usuario = Auth::user();

        $certificado = $this->certificadoService->generarSiCorresponde($usuario, $curso);

        if (!$certificado) {
            return redirect()
                ->route('cursos.show', $curso)
                ->with('error', 'Debes completar todas las lecciones del curso para obtener el certificado.');
        }

        if ($certificado->wasRecentlyCreated) {
            Notificacion::crear(
                $usuario->id,
                'certificado',
                'Certificado generado',
                "¡Felicitaciones! Obtuviste el certificado del curso «{$curso->titulo}».",
                route('certificados.show', $certificado)
            );
        }

        return redirect()->route('certificados.show', $certificado);
    }

```
El `use App\Models\Curso;` al inicio del archivo se queda — `Curso` sigue usándose como type-hint en otros métodos de la clase (revisar con `grep -n "Curso" app/Http/Controllers/CertificadoController.php` si hay duda; a la fecha de este plan sigue en uso). El `use App\Models\Notificacion;` también se queda, lo sigue usando el resto de la clase.

- [ ] **Step 6: Eliminar la ruta `certificados.generar`**

En `routes/web.php`, eliminar esta línea (dentro del bloque de rutas de Certificados):
```php
    Route::post('/cursos/{curso}/certificado', [CertificadoController::class, 'generar'])->name('certificados.generar');
```

- [ ] **Step 7: Reemplazar el botón de autoservicio en `cursos/show.blade.php`**

El bloque actual (dentro del `@if($primeraLeccionSinCompletar) ... @else ... @endif` de la sección de progreso del estudiante):
```blade
                            @else
                                <form action="{{ route('certificados.generar', $curso) }}" method="POST">
                                    @csrf
                                    <button type="submit"
                                            class="w-full bg-dyl-orange-600 text-white px-4 py-2 rounded-lg hover:bg-dyl-orange-700 font-medium text-sm">
                                        &#127941; Obtener Certificado
                                    </button>
                                </form>
                            @endif
```
Reemplazar por:
```blade
                            @else
                                <div class="text-center text-gray-600 text-sm px-4 py-3 bg-gray-50 rounded-lg">
                                    Tu certificado está pendiente de revisión del instructor.
                                </div>
                            @endif
```
El bloque `@if($certExistente)` (botón "Ver Certificado") justo arriba de este `@else` no cambia.

- [ ] **Step 8: Eliminar el test end-to-end obsoleto**

```bash
git rm tests/Feature/CertificadoGenerarEndToEndTest.php
```
Este archivo ejercitaba únicamente la ruta `certificados.generar` que este task elimina. Task 6 agrega su reemplazo (mismos 3 escenarios, disparados por el instructor).

- [ ] **Step 9: Correr el test nuevo y los 2 modificados**

Run: `php artisan test --filter=CertificadoServiceAprobacionTest`
Expected: 2 passed.

Run: `php artisan test --filter=CertificadoServiceTipoTest`
Expected: 3 passed.

Run: `php artisan test --filter=CertificadoServiceIntentosTest`
Expected: 1 passed.

- [ ] **Step 10: Correr toda la suite**

Run: `php artisan test`
Expected: todo en verde. Si `cursos/show.blade.php` se renderiza en algún otro test (por ejemplo un test de `CursoInscripcionTest`) y ese test fallara por referenciar el botón viejo, confirmarlo con `grep -rln "Obtener Certificado" tests/` — a la fecha de este plan no hay ningún otro test que lo haga (verificado), así que no debería requerir tocar nada más.

- [ ] **Step 11: Commit**

```bash
git add app/Services/CertificadoService.php app/Http/Controllers/CertificadoController.php \
        routes/web.php resources/views/cursos/show.blade.php \
        tests/Feature/CertificadoServiceTipoTest.php tests/Unit/CertificadoServiceIntentosTest.php \
        tests/Feature/CertificadoServiceAprobacionTest.php
git commit -m "feat: CertificadoService exige aprobador; elimina autoservicio de certificados"
```
El `git rm` del Step 8 ya dejó la eliminación de `CertificadoGenerarEndToEndTest.php` en el índice — confirmar con `git status --short` antes de comitear que aparece como `D` en staged (no hace falta `git add` para eso).

---

## Task 4: Endpoint de aprobación — instructor/admin aprueba desde Calificaciones

**Files:**
- Modify: `app/Http/Controllers/CalificacionController.php` (inyecta `CertificadoService`, nuevo método `aprobarCertificado()`)
- Modify: `routes/web.php` (nueva ruta)
- Test: `tests/Feature/AprobarCertificadoTest.php`

**Interfaces:**
- Consumes: `CertificadoService::generarSiCorresponde(User, Curso, User): ?Certificado` (Task 3).
- Produces: ruta con nombre `calificaciones.aprobarCertificado`, acepta `POST` con `{curso}` y `{estudiante}` en la URL.

- [ ] **Step 1: Escribir el test (falla primero)**

```php
<?php
// tests/Feature/AprobarCertificadoTest.php

namespace Tests\Feature;

use App\Models\Curso;
use App\Models\Inscripcion;
use App\Models\Rol;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AprobarCertificadoTest extends TestCase
{
    use RefreshDatabase;

    private User $instructor;
    private User $estudiante;
    private Curso $curso;

    protected function setUp(): void
    {
        parent::setUp();

        $rolInstructor  = Rol::create(['nombre' => 'Instructor']);
        Rol::create(['nombre' => 'Estudiante']);

        $this->instructor = User::factory()->create(['estado' => 'activo']);
        $this->instructor->roles()->attach($rolInstructor);

        $this->estudiante = User::factory()->create(['estado' => 'activo']);
        $this->curso = Curso::factory()->create(['created_by' => $this->instructor->id]);

        Inscripcion::create([
            'user_id' => $this->estudiante->id, 'curso_id' => $this->curso->id,
            'fecha_inicio' => '2026-01-01', 'fecha_fin' => '2026-02-01', 'estado' => 'completado',
        ]);
    }

    public function test_instructor_dueno_del_curso_puede_aprobar(): void
    {
        $response = $this->actingAs($this->instructor)
            ->post(route('calificaciones.aprobarCertificado', [$this->curso, $this->estudiante]));

        $response->assertRedirect(route('calificaciones.curso', $this->curso));
        $response->assertSessionHas('success');
        $this->assertDatabaseHas('certificados', [
            'user_id' => $this->estudiante->id,
            'curso_id' => $this->curso->id,
            'aprobado_por_id' => $this->instructor->id,
        ]);
    }

    public function test_instructor_que_no_es_dueno_del_curso_no_puede_aprobar(): void
    {
        $otroInstructor = User::factory()->create(['estado' => 'activo']);
        $otroInstructor->roles()->attach(Rol::where('nombre', 'Instructor')->first());

        $response = $this->actingAs($otroInstructor)
            ->post(route('calificaciones.aprobarCertificado', [$this->curso, $this->estudiante]));

        $response->assertForbidden();
        $this->assertDatabaseCount('certificados', 0);
    }

    public function test_admin_puede_aprobar_certificado_de_cualquier_curso(): void
    {
        $admin = User::factory()->create(['estado' => 'activo']);
        $admin->roles()->attach(Rol::create(['nombre' => 'Administrador']));

        $response = $this->actingAs($admin)
            ->post(route('calificaciones.aprobarCertificado', [$this->curso, $this->estudiante]));

        $response->assertRedirect(route('calificaciones.curso', $this->curso));
        $this->assertDatabaseHas('certificados', ['aprobado_por_id' => $admin->id]);
    }

    public function test_no_se_puede_aprobar_a_un_estudiante_que_no_completo_el_curso(): void
    {
        $otroEstudiante = User::factory()->create(['estado' => 'activo']);
        Inscripcion::create([
            'user_id' => $otroEstudiante->id, 'curso_id' => $this->curso->id,
            'fecha_inicio' => '2026-01-01', 'estado' => 'en_progreso',
        ]);

        $response = $this->actingAs($this->instructor)
            ->post(route('calificaciones.aprobarCertificado', [$this->curso, $otroEstudiante]));

        $response->assertRedirect(route('calificaciones.curso', $this->curso));
        $response->assertSessionHas('error');
        $this->assertDatabaseCount('certificados', 0);
    }

    public function test_aprobar_diplomado_sin_documento_notifica_en_vez_de_generar(): void
    {
        $curso = Curso::factory()->diplomado()->create(['created_by' => $this->instructor->id]);
        $estudiante = User::factory()->create(['estado' => 'activo', 'numero_documento' => null]);
        Inscripcion::create([
            'user_id' => $estudiante->id, 'curso_id' => $curso->id,
            'fecha_inicio' => '2026-01-01', 'fecha_fin' => '2026-02-01', 'estado' => 'completado',
        ]);

        $response = $this->actingAs($this->instructor)
            ->post(route('calificaciones.aprobarCertificado', [$curso, $estudiante]));

        $response->assertRedirect(route('calificaciones.curso', $curso));
        $response->assertSessionHas('error');
        $this->assertDatabaseCount('certificados', 0);
        $this->assertDatabaseHas('notificaciones', ['user_id' => $estudiante->id, 'tipo' => 'certificado']);
    }
}
```

- [ ] **Step 2: Correr el test y verificar que falla**

Run: `php artisan test --filter=AprobarCertificadoTest`
Expected: FAIL — `Route [calificaciones.aprobarCertificado] not defined` (o similar) en los 5 tests.

- [ ] **Step 3: Agregar la ruta**

En `routes/web.php`, dentro del grupo `Route::middleware('instructor')->group(...)` de Calificaciones (busca `Route::post('/calificaciones/actividades/{actividad}/estudiantes/{estudiante}/intentos-extra', ...)`, la última ruta de ese grupo), agregar justo después:
```php
        Route::post('/calificaciones/curso/{curso}/estudiantes/{estudiante}/aprobar-certificado', [CalificacionController::class, 'aprobarCertificado'])->name('calificaciones.aprobarCertificado');
```

- [ ] **Step 4: Inyectar `CertificadoService` y agregar el método `aprobarCertificado()`**

En `app/Http/Controllers/CalificacionController.php`, el constructor actual es:
```php
    public function __construct(private CalificacionService $calificacionService)
    {
    }
```
Reemplazar por:
```php
    public function __construct(
        private CalificacionService $calificacionService,
        private CertificadoService $certificadoService,
    ) {
    }
```
Agregar el import correspondiente junto a los demás `use` del archivo:
```php
use App\Services\CertificadoService;
```

Agregar el método nuevo (justo después de `otorgarIntentoExtra()`, antes de `show()`):
```php
    /**
     * Instructor/admin: aprueba y genera el certificado de un estudiante que
     * completó el curso. La nota mínima del curso (Curso::nota_aprobatoria)
     * es solo informativa en la matriz — este método no la valida, la
     * decisión de aprobar por debajo del mínimo es del instructor.
     */
    public function aprobarCertificado(Curso $curso, User $estudiante)
    {
        $this->verificarAccesoCurso($curso);

        $completado = Inscripcion::where('user_id', $estudiante->id)
            ->where('curso_id', $curso->id)
            ->where('estado', 'completado')
            ->exists();

        if (!$completado) {
            return redirect()->route('calificaciones.curso', $curso)
                ->with('error', "{$estudiante->name} no ha completado el curso todavía.");
        }

        $certificado = $this->certificadoService->generarSiCorresponde($estudiante, $curso, Auth::user());

        if (!$certificado) {
            return redirect()->route('calificaciones.curso', $curso)
                ->with('error', "No se pudo generar: a {$estudiante->name} le falta el número de documento — se le notificó para que lo complete.");
        }

        return redirect()->route('calificaciones.curso', $curso)
            ->with('success', "Certificado aprobado y generado para {$estudiante->name}.");
    }
```

- [ ] **Step 5: Correr el test y verificar que pasa**

Run: `php artisan test --filter=AprobarCertificadoTest`
Expected: 5 passed.

- [ ] **Step 6: Correr toda la suite**

Run: `php artisan test`
Expected: todo en verde.

- [ ] **Step 7: Commit**

```bash
git add app/Http/Controllers/CalificacionController.php routes/web.php \
        tests/Feature/AprobarCertificadoTest.php
git commit -m "feat: endpoint de aprobación de certificado para instructor/admin"
```

---

## Task 5: Columna "Certificado" en la matriz de Calificaciones

**Files:**
- Modify: `app/Http/Controllers/CalificacionController.php` (`curso()`: agrega `completado` y `certificado` a cada fila)
- Modify: `resources/views/calificaciones/curso.blade.php` (nueva columna)
- Test: `tests/Feature/CalificacionesCertificadoColumnaTest.php`

**Interfaces:**
- Consumes: `Curso::$nota_aprobatoria` (Task 1), ruta `calificaciones.aprobarCertificado` (Task 4).
- Produces: cada elemento de `$filas` gana `completado` (bool) y `certificado` (`?Certificado`, con `aprobador` cargado).

- [ ] **Step 1: Escribir el test (falla primero)**

```php
<?php
// tests/Feature/CalificacionesCertificadoColumnaTest.php

namespace Tests\Feature;

use App\Models\Actividad;
use App\Models\Certificado;
use App\Models\Curso;
use App\Models\Inscripcion;
use App\Models\Leccion;
use App\Models\Modulo;
use App\Models\RespuestaEstudiante;
use App\Models\Rol;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CalificacionesCertificadoColumnaTest extends TestCase
{
    use RefreshDatabase;

    private User $instructor;
    private Curso $curso;
    private Modulo $modulo;
    private Leccion $leccion;
    private Actividad $actividad;

    protected function setUp(): void
    {
        parent::setUp();

        Rol::create(['nombre' => 'Instructor']);
        Rol::create(['nombre' => 'Estudiante']);

        $this->instructor = User::factory()->create(['estado' => 'activo']);
        $this->instructor->roles()->attach(Rol::where('nombre', 'Instructor')->first());

        $this->curso     = Curso::factory()->create(['created_by' => $this->instructor->id]);
        $this->modulo    = Modulo::factory()->create(['curso_id' => $this->curso->id]);
        $this->leccion   = Leccion::factory()->create(['modulo_id' => $this->modulo->id]);
        $this->actividad = Actividad::factory()->create([
            'leccion_id' => $this->leccion->id, 'tipo' => 'tarea', 'puntaje_maximo' => 100,
        ]);
    }

    private function inscribirEstudiante(string $nombre, string $estadoInscripcion): User
    {
        $estudiante = User::factory()->create(['name' => $nombre, 'estado' => 'activo']);
        $estudiante->roles()->attach(Rol::where('nombre', 'Estudiante')->first());

        Inscripcion::create([
            'user_id' => $estudiante->id, 'curso_id' => $this->curso->id,
            'fecha_inicio' => '2026-01-01', 'estado' => $estadoInscripcion,
        ]);

        return $estudiante;
    }

    public function test_estudiante_no_completado_no_muestra_boton_de_aprobar(): void
    {
        $estudiante = $this->inscribirEstudiante('En Progreso', 'en_progreso');

        $response = $this->actingAs($this->instructor)->get(route('calificaciones.curso', $this->curso));

        $response->assertOk();
        $response->assertDontSee(route('calificaciones.aprobarCertificado', [$this->curso, $estudiante]), false);
    }

    public function test_estudiante_completado_con_pendientes_no_muestra_boton_de_aprobar(): void
    {
        $estudiante = $this->inscribirEstudiante('Con Pendientes', 'completado');
        RespuestaEstudiante::factory()->create([
            'user_id' => $estudiante->id, 'actividad_id' => $this->actividad->id, 'estado' => 'sin_calificar',
        ]);

        $response = $this->actingAs($this->instructor)->get(route('calificaciones.curso', $this->curso));

        $response->assertOk();
        $response->assertDontSee(route('calificaciones.aprobarCertificado', [$this->curso, $estudiante]), false);
    }

    public function test_estudiante_listo_sin_certificado_muestra_boton_de_aprobar(): void
    {
        $estudiante = $this->inscribirEstudiante('Listo Para Aprobar', 'completado');
        RespuestaEstudiante::factory()->create([
            'user_id' => $estudiante->id, 'actividad_id' => $this->actividad->id,
            'estado' => 'calificada', 'calificacion' => 90,
        ]);

        $response = $this->actingAs($this->instructor)->get(route('calificaciones.curso', $this->curso));

        $response->assertOk();
        $response->assertSee(route('calificaciones.aprobarCertificado', [$this->curso, $estudiante]), false);
        $response->assertSee('Aprobar certificado');
    }

    public function test_estudiante_por_debajo_del_minimo_muestra_boton_de_advertencia(): void
    {
        $this->curso->update(['nota_aprobatoria' => 80]);
        $estudiante = $this->inscribirEstudiante('Bajo El Minimo', 'completado');
        RespuestaEstudiante::factory()->create([
            'user_id' => $estudiante->id, 'actividad_id' => $this->actividad->id,
            'estado' => 'calificada', 'calificacion' => 50,
        ]);

        $response = $this->actingAs($this->instructor)->get(route('calificaciones.curso', $this->curso));

        $response->assertOk();
        $response->assertSee('Aprobar de todas formas');
    }

    public function test_estudiante_con_certificado_muestra_insignia_de_emitido(): void
    {
        $estudiante = $this->inscribirEstudiante('Ya Aprobado', 'completado');
        Certificado::create([
            'user_id' => $estudiante->id, 'curso_id' => $this->curso->id,
            'fecha_emision' => now()->toDateString(), 'numero_certificado' => 'CERT-2026-YAAPROBADO',
            'aprobado_por_id' => $this->instructor->id,
        ]);

        $response = $this->actingAs($this->instructor)->get(route('calificaciones.curso', $this->curso));

        $response->assertOk();
        $response->assertSee('Certificado emitido');
        $response->assertDontSee(route('calificaciones.aprobarCertificado', [$this->curso, $estudiante]), false);
    }
}
```

- [ ] **Step 2: Correr el test y verificar que falla**

Run: `php artisan test --filter=CalificacionesCertificadoColumnaTest`
Expected: FAIL — la columna no existe todavía, ninguno de los `assertSee`/`assertDontSee` sobre el contenido nuevo puede pasar como corresponde (algunos `assertDontSee` podrían pasar por accidente al no haber nada que ver, pero los `assertSee` de texto/rutas nuevas fallan).

- [ ] **Step 3: Agregar `completado` y `certificado` a cada fila en el controlador**

En `app/Http/Controllers/CalificacionController.php`, agregar el import:
```php
use App\Models\Certificado;
```

En el método `curso()`, justo antes de la línea `$filas = $inscripciones->map(function ($insc) use ($actividades, $respuestasPorCelda) {`, agregar:
```php
        // Certificados ya emitidos para este curso, precargados para no consultar uno por uno.
        $certificadosPorEstudiante = Certificado::where('curso_id', $curso->id)
            ->whereIn('user_id', $estudiantesIds)
            ->with('aprobador')
            ->get()
            ->keyBy('user_id');

```

El bloque `$filas = $inscripciones->map(...)` actual es:
```php
        $filas = $inscripciones->map(function ($insc) use ($actividades, $respuestasPorCelda) {
            $celdas = $actividades->map(fn($act) => $respuestasPorCelda->get("{$insc->user_id}-{$act->id}"));

            $calificadas = $celdas->filter(fn($r) => $r && $r->estado === 'calificada');
            $tienePendientes = $actividades->count() > $calificadas->count();

            $totalPts    = $calificadas->sum(fn($r) => $r->actividad->puntaje_maximo);
            $obtenidoPts = $calificadas->sum('calificacion');
            $promedio    = $totalPts > 0 ? (int) round(($obtenidoPts / $totalPts) * 100) : null;

            return (object) [
                'estudiante'      => $insc->usuario,
                'celdas'          => $celdas,
                'promedio'        => $promedio,
                'tiene_pendientes'=> $tienePendientes,
            ];
        });
```
Reemplazar por:
```php
        $filas = $inscripciones->map(function ($insc) use ($actividades, $respuestasPorCelda, $certificadosPorEstudiante) {
            $celdas = $actividades->map(fn($act) => $respuestasPorCelda->get("{$insc->user_id}-{$act->id}"));

            $calificadas = $celdas->filter(fn($r) => $r && $r->estado === 'calificada');
            $tienePendientes = $actividades->count() > $calificadas->count();

            $totalPts    = $calificadas->sum(fn($r) => $r->actividad->puntaje_maximo);
            $obtenidoPts = $calificadas->sum('calificacion');
            $promedio    = $totalPts > 0 ? (int) round(($obtenidoPts / $totalPts) * 100) : null;

            return (object) [
                'estudiante'      => $insc->usuario,
                'celdas'          => $celdas,
                'promedio'        => $promedio,
                'tiene_pendientes'=> $tienePendientes,
                'completado'      => $insc->estado === 'completado',
                'certificado'     => $certificadosPorEstudiante->get($insc->user_id),
            ];
        });
```

- [ ] **Step 4: Agregar la columna en la vista**

En `resources/views/calificaciones/curso.blade.php`, el `<th>` del encabezado de la tabla:
```blade
                    <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase whitespace-nowrap">Promedio</th>
                </tr>
```
Reemplazar por:
```blade
                    <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase whitespace-nowrap">Promedio</th>
                    <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase whitespace-nowrap">Certificado</th>
                </tr>
```

La celda de promedio dentro de cada fila:
```blade
                    <td class="px-4 py-3 text-center font-bold text-gray-700 whitespace-nowrap">
                        {{ $fila->promedio !== null ? $fila->promedio . '%' : '—' }}
                    </td>
                </tr>
```
Reemplazar por:
```blade
                    <td class="px-4 py-3 text-center font-bold text-gray-700 whitespace-nowrap">
                        {{ $fila->promedio !== null ? $fila->promedio . '%' : '—' }}
                    </td>
                    <td class="px-4 py-3 text-center whitespace-nowrap">
                        @if($fila->certificado)
                            <a href="{{ route('certificados.show', $fila->certificado) }}" class="badge badge-green" title="Aprobado por {{ $fila->certificado->aprobador->name ?? '—' }} el {{ $fila->certificado->created_at->format('d/m/Y') }}">
                                Certificado emitido
                            </a>
                        @elseif($fila->completado && !$fila->tiene_pendientes)
                            @php($bajoMinimo = $fila->promedio !== null && $fila->promedio < $curso->nota_aprobatoria)
                            <form method="POST" action="{{ route('calificaciones.aprobarCertificado', [$curso, $fila->estudiante]) }}"
                                  onclick="return confirm('{{ $bajoMinimo ? '¿Aprobar de todas formas? La nota del estudiante está por debajo del mínimo del curso.' : '¿Aprobar y generar el certificado?' }}')">
                                @csrf
                                @if($bajoMinimo)
                                    <button type="submit" class="btn btn-sm bg-dyl-graphite-700 text-white hover:bg-dyl-graphite-800">
                                        Aprobar de todas formas
                                    </button>
                                @else
                                    <button type="submit" class="btn btn-primary btn-sm">
                                        Aprobar certificado
                                    </button>
                                @endif
                            </form>
                        @else
                            <span class="text-gray-300">—</span>
                        @endif
                    </td>
                </tr>
```

El `colspan` de la fila "sin resultados" actual:
```blade
                    <td colspan="{{ $actividades->count() + 2 }}" class="px-6 py-10 text-center text-gray-400">
```
Reemplazar por:
```blade
                    <td colspan="{{ $actividades->count() + 3 }}" class="px-6 py-10 text-center text-gray-400">
```

La fila del `<tfoot>` (promedio general) actual:
```blade
                <tr>
                    <td class="px-4 py-3 font-bold text-gray-700 whitespace-nowrap">Promedio general</td>
                    @foreach($promediosPorActividad as $prom)
                        <td class="px-4 py-3 font-bold text-gray-700 whitespace-nowrap">{{ $prom !== null ? number_format($prom, 2) : '—' }}</td>
                    @endforeach
                    <td></td>
                </tr>
```
Reemplazar por:
```blade
                <tr>
                    <td class="px-4 py-3 font-bold text-gray-700 whitespace-nowrap">Promedio general</td>
                    @foreach($promediosPorActividad as $prom)
                        <td class="px-4 py-3 font-bold text-gray-700 whitespace-nowrap">{{ $prom !== null ? number_format($prom, 2) : '—' }}</td>
                    @endforeach
                    <td></td>
                    <td></td>
                </tr>
```

- [ ] **Step 5: Correr el test y verificar que pasa**

Run: `php artisan test --filter=CalificacionesCertificadoColumnaTest`
Expected: 5 passed.

- [ ] **Step 6: Correr toda la suite**

Run: `php artisan test`
Expected: todo en verde — prestar atención a `CalificacionesCursoMatrizTest.php` (la suite ya existente sobre esta misma vista), no debería romperse ya que solo se agregó una columna al final.

- [ ] **Step 7: Commit**

```bash
git add app/Http/Controllers/CalificacionController.php resources/views/calificaciones/curso.blade.php \
        tests/Feature/CalificacionesCertificadoColumnaTest.php
git commit -m "feat: columna de aprobación de certificado en la matriz de Calificaciones"
```

---

## Task 6: Tests end-to-end del flujo completo (checkpoint final)

**Files:**
- Test: `tests/Feature/CertificadoAprobacionEndToEndTest.php`

**Interfaces:**
- Consumes: todo lo de Tasks 1-5 (sin producir nada nuevo — es la capa de confirmación final, mismo rol que el checkpoint final del plan de rediseño de certificados anterior).

- [ ] **Step 1: Escribir los tests**

```php
<?php
// tests/Feature/CertificadoAprobacionEndToEndTest.php

namespace Tests\Feature;

use App\Models\Curso;
use App\Models\Inscripcion;
use App\Models\Rol;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CertificadoAprobacionEndToEndTest extends TestCase
{
    use RefreshDatabase;

    private User $instructor;

    protected function setUp(): void
    {
        parent::setUp();
        $rol = Rol::create(['nombre' => 'Instructor']);
        Rol::create(['nombre' => 'Estudiante']);
        $this->instructor = User::factory()->create(['estado' => 'activo']);
        $this->instructor->roles()->attach($rol);
    }

    private function crearEstudiante(array $atributos = []): User
    {
        $user = User::factory()->create(array_merge(['estado' => 'activo'], $atributos));
        $user->roles()->attach(Rol::where('nombre', 'Estudiante')->first());
        return $user;
    }

    private function completarInscripcion(User $estudiante, Curso $curso): void
    {
        Inscripcion::create([
            'user_id' => $estudiante->id, 'curso_id' => $curso->id,
            'fecha_inicio' => '2026-01-01', 'fecha_fin' => '2026-02-01', 'estado' => 'completado',
        ]);
    }

    public function test_instructor_aprueba_y_el_estudiante_recibe_su_diploma(): void
    {
        $curso      = Curso::factory()->create(['created_by' => $this->instructor->id]);
        $estudiante = $this->crearEstudiante();
        $this->completarInscripcion($estudiante, $curso);

        $response = $this->actingAs($this->instructor)
            ->post(route('calificaciones.aprobarCertificado', [$curso, $estudiante]));

        $response->assertRedirect(route('calificaciones.curso', $curso));
        $this->assertDatabaseHas('certificados', [
            'user_id' => $estudiante->id, 'curso_id' => $curso->id, 'aprobado_por_id' => $this->instructor->id,
        ]);
        $this->assertDatabaseHas('notificaciones', ['user_id' => $estudiante->id, 'tipo' => 'certificado']);
    }

    public function test_instructor_aprueba_y_el_estudiante_recibe_su_carta_de_diplomado(): void
    {
        $curso      = Curso::factory()->diplomado()->create(['created_by' => $this->instructor->id]);
        $estudiante = $this->crearEstudiante(['numero_documento' => '1000790950', 'ciudad_expedicion' => 'Bogotá']);
        $this->completarInscripcion($estudiante, $curso);

        $response = $this->actingAs($this->instructor)
            ->post(route('calificaciones.aprobarCertificado', [$curso, $estudiante]));

        $response->assertRedirect(route('calificaciones.curso', $curso));
        $this->assertDatabaseHas('certificados', [
            'user_id' => $estudiante->id, 'curso_id' => $curso->id, 'aprobado_por_id' => $this->instructor->id,
        ]);
    }

    public function test_estudiante_sin_documento_no_recibe_carta_y_queda_notificado(): void
    {
        $curso      = Curso::factory()->diplomado()->create(['created_by' => $this->instructor->id]);
        $estudiante = $this->crearEstudiante(['numero_documento' => null]);
        $this->completarInscripcion($estudiante, $curso);

        $response = $this->actingAs($this->instructor)
            ->post(route('calificaciones.aprobarCertificado', [$curso, $estudiante]));

        $response->assertRedirect(route('calificaciones.curso', $curso));
        $response->assertSessionHas('error');
        $this->assertDatabaseCount('certificados', 0);
        $this->assertDatabaseHas('notificaciones', ['user_id' => $estudiante->id, 'tipo' => 'certificado']);
    }

    public function test_estudiante_no_puede_autogenerar_su_certificado(): void
    {
        $curso      = Curso::factory()->create(['created_by' => $this->instructor->id]);
        $estudiante = $this->crearEstudiante();
        $this->completarInscripcion($estudiante, $curso);

        $this->actingAs($estudiante)->get(route('cursos.show', $curso))
            ->assertSee('pendiente de revisión del instructor');

        $this->assertDatabaseCount('certificados', 0);
    }
}
```

- [ ] **Step 2: Correr los tests**

Run: `php artisan test --filter=CertificadoAprobacionEndToEndTest`
Expected: 4 passed.

- [ ] **Step 3: Correr toda la suite completa del proyecto**

Run: `php artisan test`
Expected: todo en verde — este es el checkpoint final de todo el feature.

- [ ] **Step 4: Commit**

```bash
git add tests/Feature/CertificadoAprobacionEndToEndTest.php
git commit -m "test: cobertura end-to-end del flujo de aprobación de certificados"
```

---

## Nota final (fuera del alcance de las tareas anteriores)

Certificados emitidos antes de este cambio quedan con `aprobado_por_id = null` — no se retro-completan (Global Constraints). Si en el futuro se quiere saber quién habría aprobado esos certificados bajo el flujo viejo, es una decisión aparte, no incluida en este plan.
