# Rediseño de Certificados (Diploma + Carta de Diplomado) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Reemplazar el certificado PDF genérico actual por el diseño real de marca de D&L Quality Consulting (diploma horizontal), y agregar un segundo formato (carta vertical de diplomado tipo "certificación bancaria"), eligiendo cuál se genera según un campo nuevo en el curso.

**Architecture:** Dos plantillas Blade → PDF (mismo motor mPDF, mismo `CertificadoService`, mismo flujo de descarga/verificación pública que ya existe). `Curso.tipo_certificado` decide cuál plantilla se usa. `User.numero_documento`/`ciudad_expedicion` son datos nuevos del perfil que ambas plantillas consumen. Sin librerías nuevas, sin segundo flujo de generación.

**Tech Stack:** Laravel 12, PHP 8.2, mPDF (`mpdf/mpdf`, ya instalado), Blade, MySQL/SQLite (tests).

**Spec:** `docs/superpowers/specs/2026-08-24-rediseno-certificados-diploma-diplomado-design.md`

## Global Constraints

- Ambos formatos de certificado se generan como PDF — no se introduce generación de `.docx` real ni ninguna librería nueva (`phpoffice/phpword` u otra).
- `tipo_certificado` en `cursos` tiene default `diploma` — todos los cursos existentes siguen usando ese formato sin necesitar migración de datos ni acción manual.
- `numero_documento` y `ciudad_expedicion` en `users` son **opcionales** (`nullable`) — no rompen usuarios existentes ni el registro.
- Si falta `numero_documento` al intentar generar un certificado `diplomado`, el sistema **no genera el PDF con el campo vacío**: notifica al estudiante (`Notificacion::crear(...)`) y no crea el `Certificado`.
- El nombre/cargo del firmante queda fijo por plantilla (no configurable por curso): diploma → "Sandra Marcela Fajardo" / "Directora de Formación"; carta → "Sandra Marcela Fajardo Valero" / "Coordinadora de formación empresarial" — tal como aparecen en los documentos de referencia.
- Assets de imagen (logo, firma) se extraen de `context/Certificados/Certificado Diplomado_Darcy Carolina Cruz Guayacan.docx` (archivo ya versionado en el repo) — no se piden imágenes nuevas al usuario.
- Cada tarea termina en verde (`php artisan test`) antes de comitear.

---

## Task 1: Migraciones y modelos — datos nuevos de usuario y curso

**Files:**
- Create: `database/migrations/2026_08_24_000001_add_documento_to_users_table.php`
- Create: `database/migrations/2026_08_24_000002_add_tipo_certificado_to_cursos_table.php`
- Modify: `app/Models/User.php` (agregar a `$fillable`)
- Modify: `app/Models/Curso.php` (agregar a `$fillable`)
- Modify: `database/factories/CursoFactory.php` (agregar estado `diplomado()`)
- Test: `tests/Unit/CertificadoDatosNuevosTest.php`

**Interfaces:**
- Produces: `User::$numero_documento` (string|null), `User::$ciudad_expedicion` (string|null), `Curso::$tipo_certificado` (string, `'diploma'|'diplomado'`, default `'diploma'`), `Curso::factory()->diplomado()`.

- [ ] **Step 1: Crear la migración de `users`**

```php
<?php
// database/migrations/2026_08_24_000001_add_documento_to_users_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('numero_documento', 30)->nullable()->after('empresa');
            $table->string('ciudad_expedicion', 100)->nullable()->after('numero_documento');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['numero_documento', 'ciudad_expedicion']);
        });
    }
};
```

- [ ] **Step 2: Crear la migración de `cursos`**

```php
<?php
// database/migrations/2026_08_24_000002_add_tipo_certificado_to_cursos_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cursos', function (Blueprint $table) {
            $table->enum('tipo_certificado', ['diploma', 'diplomado'])
                ->default('diploma')
                ->after('categoria_id');
        });
    }

    public function down(): void
    {
        Schema::table('cursos', function (Blueprint $table) {
            $table->dropColumn('tipo_certificado');
        });
    }
};
```

- [ ] **Step 3: Correr las migraciones y verificar que no fallan**

Run: `php artisan migrate`
Expected: ambas migraciones aparecen como `Ran` sin error. Si algo falla, revisar el nombre exacto de la tabla/columna con `php artisan migrate:status` antes de reintentar.

- [ ] **Step 4: Agregar los campos nuevos a `$fillable` en los modelos**

En `app/Models/User.php`, cambiar:
```php
    protected $fillable = [
        'name', 'email', 'password', 'empresa', 'estado',
        'two_factor_secret', 'two_factor_enabled',
    ];
```
por:
```php
    protected $fillable = [
        'name', 'email', 'password', 'empresa', 'estado',
        'numero_documento', 'ciudad_expedicion',
        'two_factor_secret', 'two_factor_enabled',
    ];
```

En `app/Models/Curso.php`, cambiar:
```php
    protected $fillable = [
        'titulo', 'descripcion', 'duracion_horas',
        'imagen_portada', 'estado', 'created_by', 'orden', 'categoria_id',
    ];
```
por:
```php
    protected $fillable = [
        'titulo', 'descripcion', 'duracion_horas',
        'imagen_portada', 'estado', 'created_by', 'orden', 'categoria_id',
        'tipo_certificado',
    ];
```

- [ ] **Step 5: Agregar el estado `diplomado()` a `CursoFactory`**

En `database/factories/CursoFactory.php`, después del método `publicado()`, agregar:
```php
    public function diplomado(): static
    {
        return $this->state(['tipo_certificado' => 'diplomado']);
    }
```

- [ ] **Step 6: Escribir el test que confirma los campos nuevos**

```php
<?php
// tests/Unit/CertificadoDatosNuevosTest.php

namespace Tests\Unit;

use App\Models\Curso;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CertificadoDatosNuevosTest extends TestCase
{
    use RefreshDatabase;

    public function test_curso_tiene_tipo_certificado_diploma_por_defecto(): void
    {
        $curso = Curso::factory()->create();

        $this->assertSame('diploma', $curso->tipo_certificado);
    }

    public function test_curso_factory_diplomado_marca_el_tipo_correcto(): void
    {
        $curso = Curso::factory()->diplomado()->create();

        $this->assertSame('diplomado', $curso->tipo_certificado);
    }

    public function test_usuario_puede_guardar_numero_documento_y_ciudad_expedicion(): void
    {
        $user = User::factory()->create([
            'numero_documento'   => '1000790950',
            'ciudad_expedicion'  => 'Bogotá',
        ]);

        $user->refresh();

        $this->assertSame('1000790950', $user->numero_documento);
        $this->assertSame('Bogotá', $user->ciudad_expedicion);
    }

    public function test_usuario_sin_numero_documento_no_falla(): void
    {
        $user = User::factory()->create();

        $this->assertNull($user->numero_documento);
        $this->assertNull($user->ciudad_expedicion);
    }
}
```

- [ ] **Step 7: Correr el test y verificar que pasa**

Run: `php artisan test --filter=CertificadoDatosNuevosTest`
Expected: 4 passed.

- [ ] **Step 8: Commit**

```bash
git add database/migrations/2026_08_24_000001_add_documento_to_users_table.php \
        database/migrations/2026_08_24_000002_add_tipo_certificado_to_cursos_table.php \
        app/Models/User.php app/Models/Curso.php \
        database/factories/CursoFactory.php \
        tests/Unit/CertificadoDatosNuevosTest.php
git commit -m "feat: agregar numero_documento/ciudad_expedicion a usuarios y tipo_certificado a cursos"
```

---

## Task 2: Helper `NumeroEnPalabras` — día del mes en letras

La carta de diplomado necesita "veintinueve (29)" en vez de solo "29". El rango es fijo (1-31), así que se resuelve con un array estático, sin librería externa.

**Files:**
- Create: `app/Support/NumeroEnPalabras.php`
- Test: `tests/Unit/NumeroEnPalabrasTest.php`

**Interfaces:**
- Produces: `App\Support\NumeroEnPalabras::dia(int $dia): string` — recibe un entero 1-31, devuelve el nombre en español minúsculas ("uno", "veintinueve", "treinta y uno"). Lanza `\InvalidArgumentException` fuera de ese rango.

- [ ] **Step 1: Escribir el test (falla primero)**

```php
<?php
// tests/Unit/NumeroEnPalabrasTest.php

namespace Tests\Unit;

use App\Support\NumeroEnPalabras;
use PHPUnit\Framework\TestCase;

class NumeroEnPalabrasTest extends TestCase
{
    public function test_dia_uno(): void
    {
        $this->assertSame('uno', NumeroEnPalabras::dia(1));
    }

    public function test_dia_veintinueve(): void
    {
        $this->assertSame('veintinueve', NumeroEnPalabras::dia(29));
    }

    public function test_dia_treinta_y_uno(): void
    {
        $this->assertSame('treinta y uno', NumeroEnPalabras::dia(31));
    }

    public function test_dia_dieciseis(): void
    {
        $this->assertSame('dieciséis', NumeroEnPalabras::dia(16));
    }

    public function test_dia_fuera_de_rango_lanza_excepcion(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        NumeroEnPalabras::dia(32);
    }

    public function test_dia_cero_lanza_excepcion(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        NumeroEnPalabras::dia(0);
    }
}
```

- [ ] **Step 2: Correr el test y verificar que falla**

Run: `php artisan test --filter=NumeroEnPalabrasTest`
Expected: FAIL — "Class App\Support\NumeroEnPalabras not found".

- [ ] **Step 3: Implementar el helper**

```php
<?php
// app/Support/NumeroEnPalabras.php

namespace App\Support;

class NumeroEnPalabras
{
    private const DIAS = [
        1 => 'uno', 2 => 'dos', 3 => 'tres', 4 => 'cuatro', 5 => 'cinco',
        6 => 'seis', 7 => 'siete', 8 => 'ocho', 9 => 'nueve', 10 => 'diez',
        11 => 'once', 12 => 'doce', 13 => 'trece', 14 => 'catorce', 15 => 'quince',
        16 => 'dieciséis', 17 => 'diecisiete', 18 => 'dieciocho', 19 => 'diecinueve', 20 => 'veinte',
        21 => 'veintiuno', 22 => 'veintidós', 23 => 'veintitrés', 24 => 'veinticuatro', 25 => 'veinticinco',
        26 => 'veintiséis', 27 => 'veintisiete', 28 => 'veintiocho', 29 => 'veintinueve', 30 => 'treinta',
        31 => 'treinta y uno',
    ];

    /**
     * Convierte un día del mes (1-31) a su nombre en español, tal como se
     * usa en el texto de las cartas de diplomado ("a los veintinueve (29) días...").
     */
    public static function dia(int $dia): string
    {
        if (!isset(self::DIAS[$dia])) {
            throw new \InvalidArgumentException("Día fuera de rango: {$dia}. Debe estar entre 1 y 31.");
        }

        return self::DIAS[$dia];
    }
}
```

- [ ] **Step 4: Correr el test y verificar que pasa**

Run: `php artisan test --filter=NumeroEnPalabrasTest`
Expected: 6 passed.

- [ ] **Step 5: Commit**

```bash
git add app/Support/NumeroEnPalabras.php tests/Unit/NumeroEnPalabrasTest.php
git commit -m "feat: agregar helper NumeroEnPalabras para fechas de la carta de diplomado"
```

---

## Task 3: Perfil — capturar número de documento y ciudad de expedición

**Files:**
- Modify: `app/Http/Requests/ProfileUpdateRequest.php:17-31`
- Modify: `resources/views/profile/partials/update-profile-information-form.blade.php`
- Test: `tests/Feature/ProfileDocumentoTest.php`

**Interfaces:**
- Consumes: `App\Models\User` (de Task 1: `numero_documento`, `ciudad_expedicion` ya en `$fillable`).
- Produces: nada que otras tareas consuman directamente — el resto de tareas leen `$usuario->numero_documento`/`ciudad_expedicion` directo del modelo.

- [ ] **Step 1: Escribir el test (falla primero)**

```php
<?php
// tests/Feature/ProfileDocumentoTest.php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileDocumentoTest extends TestCase
{
    use RefreshDatabase;

    public function test_usuario_puede_guardar_numero_documento_y_ciudad_desde_su_perfil(): void
    {
        $user = User::factory()->create(['estado' => 'activo']);

        $response = $this->actingAs($user)->patch(route('profile.update'), [
            'name'              => $user->name,
            'email'             => $user->email,
            'numero_documento'  => '1000790950',
            'ciudad_expedicion' => 'Bogotá',
        ]);

        $response->assertRedirect(route('profile.edit'));
        $user->refresh();
        $this->assertSame('1000790950', $user->numero_documento);
        $this->assertSame('Bogotá', $user->ciudad_expedicion);
    }

    public function test_numero_documento_y_ciudad_son_opcionales(): void
    {
        $user = User::factory()->create(['estado' => 'activo']);

        $response = $this->actingAs($user)->patch(route('profile.update'), [
            'name'  => $user->name,
            'email' => $user->email,
        ]);

        $response->assertRedirect(route('profile.edit'));
        $user->refresh();
        $this->assertNull($user->numero_documento);
    }

    public function test_pantalla_de_perfil_muestra_los_campos_nuevos(): void
    {
        $user = User::factory()->create(['estado' => 'activo', 'numero_documento' => '999888']);

        $response = $this->actingAs($user)->get(route('profile.edit'));

        $response->assertOk();
        $response->assertSee('numero_documento', false);
        $response->assertSee('999888');
    }
}
```

- [ ] **Step 2: Correr el test y verificar que falla**

Run: `php artisan test --filter=ProfileDocumentoTest`
Expected: FAIL en la primera aserción — `numero_documento` no está en las reglas de validación, así que `ProfileUpdateRequest` lo descarta silenciosamente (`$request->validated()` no lo incluye) y queda `null` en vez de `'1000790950'`.

- [ ] **Step 3: Agregar las reglas de validación**

En `app/Http/Requests/ProfileUpdateRequest.php`, cambiar:
```php
    public function rules(): array
    {
        return [
            'name'    => ['required', 'string', 'max:255'],
            'empresa' => ['nullable', 'string', 'max:255'],
            'email'   => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique(User::class)->ignore($this->user()->id),
            ],
        ];
    }
```
por:
```php
    public function rules(): array
    {
        return [
            'name'              => ['required', 'string', 'max:255'],
            'empresa'           => ['nullable', 'string', 'max:255'],
            'numero_documento'  => ['nullable', 'string', 'max:30'],
            'ciudad_expedicion' => ['nullable', 'string', 'max:100'],
            'email'             => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique(User::class)->ignore($this->user()->id),
            ],
        ];
    }
```

- [ ] **Step 4: Agregar los campos al formulario**

En `resources/views/profile/partials/update-profile-information-form.blade.php`, cambiar:
```blade
            @error('empresa')
                <p class="mt-1 text-sm text-dyl-graphite-900 font-semibold">{{ $message }}</p>
            @enderror
        </div>

        {{-- Email --}}
```
por (se insertan los dos campos nuevos entre el cierre del bloque de Empresa y el comentario de Email):
```blade
            @error('empresa')
                <p class="mt-1 text-sm text-dyl-graphite-900 font-semibold">{{ $message }}</p>
            @enderror
        </div>

        {{-- Número de documento --}}
        <div>
            <label for="numero_documento" class="form-label">Número de documento (C.C.)</label>
            <input
                id="numero_documento"
                name="numero_documento"
                type="text"
                class="form-input mt-1 w-full"
                value="{{ old('numero_documento', $user->numero_documento) }}"
                placeholder="Opcional — necesario para certificados de diplomado"
            />
            @error('numero_documento')
                <p class="mt-1 text-sm text-dyl-graphite-900 font-semibold">{{ $message }}</p>
            @enderror
        </div>

        {{-- Ciudad de expedición --}}
        <div>
            <label for="ciudad_expedicion" class="form-label">Ciudad de expedición del documento</label>
            <input
                id="ciudad_expedicion"
                name="ciudad_expedicion"
                type="text"
                class="form-input mt-1 w-full"
                value="{{ old('ciudad_expedicion', $user->ciudad_expedicion) }}"
                placeholder="Opcional"
            />
            @error('ciudad_expedicion')
                <p class="mt-1 text-sm text-dyl-graphite-900 font-semibold">{{ $message }}</p>
            @enderror
        </div>

        {{-- Email --}}
```
(el `{{-- Email --}}` ya existía — queda repetido al final del bloque nuevo solo para marcar dónde reconecta con el resto del archivo, que no cambia.)

- [ ] **Step 5: Correr el test y verificar que pasa**

Run: `php artisan test --filter=ProfileDocumentoTest`
Expected: 3 passed.

- [ ] **Step 6: Correr toda la suite para confirmar que nada más se rompió**

Run: `php artisan test`
Expected: todo en verde (ningún test existente referencia el markup del formulario de perfil).

- [ ] **Step 7: Commit**

```bash
git add app/Http/Requests/ProfileUpdateRequest.php \
        resources/views/profile/partials/update-profile-information-form.blade.php \
        tests/Feature/ProfileDocumentoTest.php
git commit -m "feat: capturar numero_documento y ciudad_expedicion en el perfil"
```

---

## Task 4: Curso — campo "Tipo de certificado" en crear/editar

**Files:**
- Modify: `app/Http/Controllers/CursoController.php:53-59` (store) y `:162-169` (update)
- Modify: `resources/views/cursos/create.blade.php:27-30`
- Modify: `resources/views/cursos/edit.blade.php:42-45`
- Test: `tests/Feature/CursoTipoCertificadoTest.php`

**Interfaces:**
- Consumes: `Curso::$tipo_certificado` (de Task 1).

- [ ] **Step 1: Escribir el test (falla primero)**

```php
<?php
// tests/Feature/CursoTipoCertificadoTest.php

namespace Tests\Feature;

use App\Models\Curso;
use App\Models\Rol;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CursoTipoCertificadoTest extends TestCase
{
    use RefreshDatabase;

    private function crearInstructor(): User
    {
        $rol = Rol::factory()->create(['nombre' => 'Instructor']);
        $user = User::factory()->create(['estado' => 'activo']);
        $user->roles()->attach($rol);
        return $user;
    }

    public function test_crear_curso_con_tipo_certificado_diplomado(): void
    {
        $instructor = $this->crearInstructor();

        $response = $this->actingAs($instructor)->post(route('cursos.store'), [
            'titulo'           => 'Diplomado en Calidad',
            'descripcion'      => str_repeat('Contenido de prueba. ', 3),
            'duracion_horas'   => 120,
            'tipo_certificado' => 'diplomado',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('cursos', [
            'titulo'           => 'Diplomado en Calidad',
            'tipo_certificado' => 'diplomado',
        ]);
    }

    public function test_crear_curso_sin_especificar_tipo_certificado_usa_diploma(): void
    {
        $instructor = $this->crearInstructor();

        $response = $this->actingAs($instructor)->post(route('cursos.store'), [
            'titulo'         => 'Curso Corto',
            'descripcion'    => str_repeat('Contenido de prueba. ', 3),
            'duracion_horas' => 8,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('cursos', [
            'titulo'           => 'Curso Corto',
            'tipo_certificado' => 'diploma',
        ]);
    }

    public function test_editar_curso_puede_cambiar_tipo_certificado(): void
    {
        $instructor = $this->crearInstructor();
        $curso = Curso::factory()->create(['created_by' => $instructor->id]);

        $response = $this->actingAs($instructor)->put(route('cursos.update', $curso), [
            'titulo'           => $curso->titulo,
            'descripcion'      => str_repeat('Contenido de prueba. ', 3),
            'duracion_horas'   => $curso->duracion_horas,
            'estado'           => 'publicado',
            'tipo_certificado' => 'diplomado',
        ]);

        $response->assertRedirect();
        $this->assertSame('diplomado', $curso->fresh()->tipo_certificado);
    }

    public function test_formulario_de_crear_curso_incluye_el_selector(): void
    {
        $instructor = $this->crearInstructor();

        $response = $this->actingAs($instructor)->get(route('cursos.create'));

        $response->assertOk();
        $response->assertSee('name="tipo_certificado"', false);
        $response->assertSee('diplomado', false);
    }
}
```

- [ ] **Step 2: Correr el test y verificar que falla**

Run: `php artisan test --filter=CursoTipoCertificadoTest`
Expected: la primera prueba falla porque `tipo_certificado` no está en las reglas de `store()`, así que `Curso::create($validated)` nunca lo recibe (queda en el default `diploma` de la migración, no en `'diplomado'`).

- [ ] **Step 3: Agregar la validación en `store()`**

En `app/Http/Controllers/CursoController.php`, cambiar:
```php
        $validated = $request->validate([
            'titulo'         => 'required|string|max:255|unique:cursos',
            'descripcion'    => 'required|string|min:20',
            'duracion_horas' => 'required|integer|min:1|max:500',
            'imagen_portada' => 'nullable|image|mimes:jpeg,png,jpg|max:5120',
            'categoria_id'   => 'nullable|exists:categorias,id',
        ]);
```
por:
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

- [ ] **Step 4: Agregar la validación en `update()`**

En el mismo archivo, cambiar:
```php
        $validated = $request->validate([
            'titulo'         => 'required|string|max:255|unique:cursos,titulo,' . $curso->id,
            'descripcion'    => 'required|string|min:20',
            'duracion_horas' => 'required|integer|min:1|max:500',
            'estado'         => 'required|in:borrador,publicado,archivado',
            'imagen_portada' => 'nullable|image|mimes:jpeg,png,jpg|max:5120',
            'categoria_id'   => 'nullable|exists:categorias,id',
        ]);
```
por:
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

Nota: en `store()` es `nullable` (si no se envía, la migración pone el default `diploma`); en `update()` es `required` porque el formulario de edición siempre lo va a enviar con un valor seleccionado (ver Step 6).

- [ ] **Step 5: Agregar el selector en `cursos/create.blade.php`**

En `resources/views/cursos/create.blade.php`, después del bloque de Categoría, cambiar:
```blade
        <div class="mb-6">
            <label for="categoria_id" class="block text-sm font-medium text-gray-700 mb-2">Categoría</label>
            <x-categoria-selector :categorias="$categorias" :selected-id="old('categoria_id')" />
        </div>
        <div class="mb-6" x-data="{ errorPortada: '' }">
```
por:
```blade
        <div class="mb-6">
            <label for="categoria_id" class="block text-sm font-medium text-gray-700 mb-2">Categoría</label>
            <x-categoria-selector :categorias="$categorias" :selected-id="old('categoria_id')" />
        </div>
        <div class="mb-6">
            <label for="tipo_certificado" class="block text-sm font-medium text-gray-700 mb-2">Tipo de certificado</label>
            <select name="tipo_certificado" id="tipo_certificado"
                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-dyl-orange-600 focus:border-transparent">
                <option value="diploma" {{ old('tipo_certificado', 'diploma') === 'diploma' ? 'selected' : '' }}>Diploma (horizontal)</option>
                <option value="diplomado" {{ old('tipo_certificado') === 'diplomado' ? 'selected' : '' }}>Diplomado — carta formal (vertical)</option>
            </select>
            <p class="text-xs text-gray-400 mt-1">Define qué diseño de certificado recibe el estudiante al completar el curso.</p>
        </div>
        <div class="mb-6" x-data="{ errorPortada: '' }">
```

- [ ] **Step 6: Agregar el selector en `cursos/edit.blade.php`**

En `resources/views/cursos/edit.blade.php`, después del bloque de Categoría, cambiar:
```blade
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Categoría</label>
                    <x-categoria-selector :categorias="$categorias" :selected-id="old('categoria_id', $curso->categoria_id)" />
                </div>
                <div class="mb-4" x-data="{ errorPortada: '' }">
```
por:
```blade
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Categoría</label>
                    <x-categoria-selector :categorias="$categorias" :selected-id="old('categoria_id', $curso->categoria_id)" />
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Tipo de certificado</label>
                    <select name="tipo_certificado" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                        <option value="diploma" @selected(old('tipo_certificado', $curso->tipo_certificado) === 'diploma')>Diploma (horizontal)</option>
                        <option value="diplomado" @selected(old('tipo_certificado', $curso->tipo_certificado) === 'diplomado')>Diplomado — carta formal (vertical)</option>
                    </select>
                </div>
                <div class="mb-4" x-data="{ errorPortada: '' }">
```

- [ ] **Step 7: Correr el test y verificar que pasa**

Run: `php artisan test --filter=CursoTipoCertificadoTest`
Expected: 4 passed.

- [ ] **Step 8: Correr toda la suite**

Run: `php artisan test`
Expected: todo en verde.

- [ ] **Step 9: Commit**

```bash
git add app/Http/Controllers/CursoController.php \
        resources/views/cursos/create.blade.php resources/views/cursos/edit.blade.php \
        tests/Feature/CursoTipoCertificadoTest.php
git commit -m "feat: selector de tipo de certificado en crear/editar curso"
```

---

## Task 5: Assets de marca + rediseño de `certificados/plantilla-pdf.blade.php` (diploma)

Extrae el logo circular real y la firma manuscrita del `.docx` de referencia (ya está en el repo), y rediseña la plantilla del diploma horizontal para que coincida con `context/Certificados/Certificado Auditor_Darcy Carolina Cruz Guayacan.pdf`.

**Files:**
- Create: `public/images/certificados/logo-circulos.jpg`
- Create: `public/images/certificados/firma-sandra-fajardo.jpg`
- Modify: `resources/views/certificados/plantilla-pdf.blade.php` (reescritura completa)
- Test: `tests/Feature/CertificadoPlantillaDiplomaTest.php`

**Interfaces:**
- Consumes: `Certificado` con `usuario` (`name`, `numero_documento`) y `curso` (`titulo`, `duracion_horas`) cargados; variable de vista `fecha_finalizacion` (string ya formateada, la pasa `CertificadoService` en Task 7 — por ahora en este task se usa `$certificado->fecha_emision` directo para poder probar la plantilla de forma aislada).
- Produces: la vista `certificados.plantilla-pdf` sigue esperando una variable `$certificado` (sin cambios de contrato con quien la invoca).

- [ ] **Step 1: Extraer los assets del `.docx` de referencia**

El `.docx` es un archivo ZIP. Extraer las dos imágenes que necesitamos:

Run:
```bash
mkdir -p public/images/certificados
cd /tmp && rm -rf docx_extract && mkdir docx_extract && cd docx_extract
unzip -o "/c/xampp/htdocs/LMS_DyL/lms-dyl-quality/context/Certificados/Certificado Diplomado_Darcy Carolina Cruz Guayacan.docx" -d .
cp word/media/image1.jpeg "/c/xampp/htdocs/LMS_DyL/lms-dyl-quality/public/images/certificados/logo-circulos.jpg"
cp word/media/image2.jpeg "/c/xampp/htdocs/LMS_DyL/lms-dyl-quality/public/images/certificados/firma-sandra-fajardo.jpg"
```
Expected: `public/images/certificados/logo-circulos.jpg` (~58KB, el círculo multicolor con "Nit. 900282703-3") y `public/images/certificados/firma-sandra-fajardo.jpg` (~8KB, la firma manuscrita) existen.

- [ ] **Step 2: Escribir el test (falla primero)**

```php
<?php
// tests/Feature/CertificadoPlantillaDiplomaTest.php

namespace Tests\Feature;

use App\Models\Certificado;
use App\Models\Curso;
use App\Models\User;
use App\Services\CertificadoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CertificadoPlantillaDiplomaTest extends TestCase
{
    use RefreshDatabase;

    public function test_genera_pdf_de_diploma_sin_errores(): void
    {
        Storage::fake('public');

        $instructor = User::factory()->create();
        $estudiante = User::factory()->create(['name' => 'Darcy Carolina Cruz Guayacán', 'numero_documento' => '1000790950']);
        $curso      = Curso::factory()->create(['created_by' => $instructor->id, 'titulo' => 'Auditor Interno ISO 9001:2015', 'duracion_horas' => 24]);

        $certificado = Certificado::create([
            'user_id'            => $estudiante->id,
            'curso_id'           => $curso->id,
            'fecha_emision'      => now()->toDateString(),
            'numero_certificado' => 'CERT-2026-TEST0001',
            'calificacion_final' => 95,
        ]);
        $certificado->load(['usuario', 'curso.creador']);

        $ruta = app(CertificadoService::class)->generarPdf($certificado);

        Storage::disk('public')->assertExists($ruta);
    }
}
```

- [ ] **Step 3: Correr el test y confirmar que pasa con la plantilla actual**

Run: `php artisan test --filter=CertificadoPlantillaDiplomaTest`
Expected: PASS (la plantilla actual ya genera el PDF sin errores — este test es la base para verificar que el rediseño del Step 4 no rompe la generación, no para verificar el contenido visual, que no se puede aserter automáticamente en un PDF).

- [ ] **Step 4: Reescribir `certificados/plantilla-pdf.blade.php`**

Reemplazar el archivo completo:

```blade
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            width: 297mm;
            height: 210mm;
            font-family: Helvetica, Arial, sans-serif;
            background: #fff;
            color: #1F2937;
            position: relative;
            overflow: hidden;
        }

        .encabezado {
            position: absolute;
            top: 14mm;
            left: 14mm;
            display: flex;
            align-items: flex-start;
            gap: 8mm;
        }

        .logo-circulos {
            width: 32mm;
        }

        .wordmark {
            padding-top: 4mm;
        }
        .wordmark .dl {
            font-size: 26pt;
            font-weight: bold;
            color: #1F2937;
            letter-spacing: 1px;
        }
        .wordmark .dl .amp { color: #16A34A; }
        .wordmark .sub {
            font-size: 10pt;
            letter-spacing: 4px;
            color: #4B5563;
            text-transform: uppercase;
        }

        .decoracion {
            position: absolute;
            top: -40mm;
            right: -40mm;
            width: 160mm;
            opacity: 0.12;
        }

        .contenido {
            position: absolute;
            top: 50mm;
            left: 20mm;
            right: 55mm;
        }

        .hace-constar {
            font-size: 13pt;
            color: #374151;
            margin-bottom: 8mm;
        }

        .nombre-estudiante {
            font-size: 24pt;
            font-weight: bold;
            color: #1F2937;
            text-transform: uppercase;
            margin-bottom: 2mm;
        }

        .cedula {
            font-size: 11pt;
            color: #4B5563;
            margin-bottom: 10mm;
        }

        .texto-completo {
            font-size: 12pt;
            color: #374151;
            margin-bottom: 4mm;
        }

        .nombre-curso {
            font-size: 20pt;
            font-weight: bold;
            color: #16A34A;
            text-transform: uppercase;
            margin-bottom: 10mm;
        }

        .datos-fila {
            display: flex;
            gap: 20mm;
            font-size: 11pt;
            color: #374151;
            margin-bottom: 16mm;
        }
        .datos-fila strong { color: #1F2937; }

        .firma-bloque {
            width: 70mm;
        }
        .firma-img {
            width: 40mm;
            margin-bottom: -3mm;
        }
        .firma-linea {
            border-top: 1px solid #9CA3AF;
            margin-bottom: 1.5mm;
        }
        .firma-nombre {
            font-size: 10pt;
            font-weight: bold;
            color: #1F2937;
        }
        .firma-cargo {
            font-size: 9pt;
            color: #6B7280;
        }

        .pie {
            position: absolute;
            bottom: 10mm;
            left: 20mm;
            right: 20mm;
            font-size: 9pt;
            color: #16A34A;
            font-weight: bold;
        }
        .pie .contacto {
            font-size: 8pt;
            color: #6B7280;
            font-weight: normal;
        }
        .pie .contacto a { color: #6B7280; text-decoration: none; }
    </style>
</head>
<body>

<img class="decoracion" src="{{ public_path('images/certificados/logo-circulos.jpg') }}">

<div class="encabezado">
    <img class="logo-circulos" src="{{ public_path('images/certificados/logo-circulos.jpg') }}">
    <div class="wordmark">
        <div class="dl">D<span class="amp">&amp;</span>L</div>
        <div class="sub">Quality Consulting</div>
    </div>
</div>

<div class="contenido">
    <p class="hace-constar">Hace Constar Que:</p>

    <p class="nombre-estudiante">{{ $certificado->usuario->name }}</p>
    @if($certificado->usuario->numero_documento)
        <p class="cedula">C.C. {{ $certificado->usuario->numero_documento }}</p>
    @endif

    <p class="texto-completo">Completó con éxito la formación y evaluación de</p>
    <p class="nombre-curso">{{ $certificado->curso->titulo }}</p>

    <div class="datos-fila">
        <span>Fecha Finalización: <strong>{{ \Carbon\Carbon::parse($certificado->fecha_emision)->format('Y/m/d') }}</strong></span>
        <span>Intensidad: <strong>{{ $certificado->curso->duracion_horas }} horas</strong></span>
    </div>

    <div class="firma-bloque">
        <img class="firma-img" src="{{ public_path('images/certificados/firma-sandra-fajardo.jpg') }}">
        <div class="firma-linea"></div>
        <p class="firma-nombre">Sandra Marcela Fajardo</p>
        <p class="firma-cargo">Directora de Formación</p>
    </div>
</div>

<div class="pie">
    <div>www.dylqualityconsulting.com</div>
    <div class="contacto">contacto.dylltda@gmail.com &middot; 310 349 1201 &middot; Calle 143 No. 46-55 &middot; N° {{ $certificado->numero_certificado }}</div>
</div>

</body>
</html>
```

Nota: se usa `public_path(...)` en el `src` (no `asset()`/URL) porque mPDF corre en el servidor y necesita una ruta de archivo local que pueda leer directamente — no una URL que tendría que descargar por red.

- [ ] **Step 5: Correr el test de nuevo**

Run: `php artisan test --filter=CertificadoPlantillaDiplomaTest`
Expected: PASS — la plantilla nueva genera el PDF sin errores (mismas aserciones que el Step 3, ahora contra el diseño rediseñado).

- [ ] **Step 6: Verificación visual manual**

Este paso no es automatizable — abrir el PDF generado y compararlo con `context/Certificados/Certificado Auditor_Darcy Carolina Cruz Guayacan.pdf`. Para generarlo y abrirlo:
```bash
php artisan tinker --execute="
\$c = App\Models\Certificado::first() ?? App\Models\Certificado::factory()->create();
\$c->load(['usuario','curso.creador']);
\$ruta = app(App\Services\CertificadoService::class)->generarPdf(\$c);
echo storage_path('app/public/'.\$ruta);
"
```
Abrir el archivo resultante y confirmar que el layout es razonablemente fiel (logo, "Hace Constar Que:", nombre+cédula, curso en verde, fecha+intensidad, firma, pie). Ajustes finos de posición/tamaño son aceptables en este paso sin volver a los steps anteriores.

- [ ] **Step 7: Commit**

```bash
git add public/images/certificados/logo-circulos.jpg public/images/certificados/firma-sandra-fajardo.jpg \
        resources/views/certificados/plantilla-pdf.blade.php \
        tests/Feature/CertificadoPlantillaDiplomaTest.php
git commit -m "feat: rediseñar plantilla de diploma con la marca real de D&L Quality Consulting"
```

---

## Task 6: Nueva `certificados/plantilla-carta.blade.php` (diplomado)

**Files:**
- Create: `resources/views/certificados/plantilla-carta.blade.php`
- Test: `tests/Feature/CertificadoPlantillaCartaTest.php`

**Interfaces:**
- Consumes: `App\Support\NumeroEnPalabras::dia()` (Task 2), assets `public/images/certificados/logo-circulos.jpg` y `firma-sandra-fajardo.jpg` (Task 5, ya extraídos — se reutilizan, no se vuelven a copiar).
- Produces: la vista `certificados.plantilla-carta` espera `$certificado` (con `usuario`, `curso` cargados) e `$inscripcion` (objeto `Inscripcion` con `fecha_inicio`/`fecha_fin` del estudiante en ese curso) — esta segunda variable es nueva, la pasa `CertificadoService` en Task 7.

- [ ] **Step 1: Escribir el test (falla primero)**

```php
<?php
// tests/Feature/CertificadoPlantillaCartaTest.php

namespace Tests\Feature;

use App\Models\Certificado;
use App\Models\Curso;
use App\Models\Inscripcion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CertificadoPlantillaCartaTest extends TestCase
{
    use RefreshDatabase;

    public function test_la_plantilla_renderiza_el_parrafo_completo(): void
    {
        $instructor = User::factory()->create();
        $estudiante = User::factory()->create([
            'name'              => 'Darcy Carolina Cruz Guayacán',
            'numero_documento'  => '1000790950',
            'ciudad_expedicion' => 'Bogotá',
        ]);
        $curso = Curso::factory()->diplomado()->create([
            'created_by'     => $instructor->id,
            'titulo'         => 'Sistema de Gestión de la Calidad (ISO 9001:2015)',
            'duracion_horas' => 120,
        ]);

        $certificado = Certificado::create([
            'user_id'            => $estudiante->id,
            'curso_id'           => $curso->id,
            'fecha_emision'      => '2026-07-29',
            'numero_certificado' => 'CERT-2026-TEST0002',
            'calificacion_final' => 100,
        ]);
        $certificado->load(['usuario', 'curso']);

        $inscripcion = Inscripcion::create([
            'user_id'      => $estudiante->id,
            'curso_id'     => $curso->id,
            'fecha_inicio' => '2026-03-28',
            'fecha_fin'    => '2026-07-28',
            'estado'       => 'completado',
        ]);

        $html = view('certificados.plantilla-carta', compact('certificado', 'inscripcion'))->render();

        $this->assertStringContainsString('DARCY CAROLINA CRUZ GUAYACÁN', mb_strtoupper($html));
        $this->assertStringContainsString('1000790950', $html);
        $this->assertStringContainsString('Bogotá', $html);
        $this->assertStringContainsString('SISTEMA DE GESTIÓN DE LA CALIDAD (ISO 9001:2015)', mb_strtoupper($html));
        $this->assertStringContainsString('120', $html);
        $this->assertStringContainsString('veintinueve', $html);
        $this->assertStringContainsString('Julio', $html);
        $this->assertStringContainsString('2026', $html);
        $this->assertStringNotContainsString('100%', $html); // no muestra calificación
    }
}
```

- [ ] **Step 2: Correr el test y verificar que falla**

Run: `php artisan test --filter=CertificadoPlantillaCartaTest`
Expected: FAIL — "View [certificados.plantilla-carta] not found".

- [ ] **Step 3: Crear la plantilla**

```blade
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: Helvetica, Arial, sans-serif;
            color: #1F2937;
            font-size: 12pt;
            line-height: 1.7;
        }

        .encabezado {
            display: flex;
            align-items: center;
            gap: 6mm;
            margin-bottom: 14mm;
        }
        .encabezado img { width: 22mm; }
        .encabezado .wordmark .dl {
            font-size: 16pt;
            font-weight: bold;
            color: #1F2937;
        }
        .encabezado .wordmark .dl .amp { color: #16A34A; }
        .encabezado .wordmark .sub {
            font-size: 7pt;
            letter-spacing: 2px;
            color: #4B5563;
            text-transform: uppercase;
        }

        .parrafo {
            text-align: justify;
            margin-bottom: 14mm;
        }
        .parrafo strong { color: #1F2937; }

        .despedida {
            margin-bottom: 4mm;
        }

        .firma-bloque {
            margin-top: 10mm;
        }
        .firma-bloque img {
            width: 35mm;
            margin-bottom: -2mm;
        }
        .firma-linea {
            border-top: 1px solid #9CA3AF;
            width: 60mm;
            margin-bottom: 1.5mm;
        }
        .firma-nombre {
            font-weight: bold;
            font-size: 11pt;
        }
        .firma-cargo, .firma-empresa {
            font-size: 10pt;
            color: #4B5563;
        }

        .pie {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: #F97316;
            color: #fff;
            padding: 4mm 10mm;
            font-size: 9pt;
            display: flex;
            justify-content: space-between;
        }
        .pie strong { font-weight: bold; }
    </style>
</head>
<body>

<div class="encabezado">
    <img src="{{ public_path('images/certificados/logo-circulos.jpg') }}">
    <div class="wordmark">
        <div class="dl">D<span class="amp">&amp;</span>L</div>
        <div class="sub">Quality Consulting</div>
    </div>
</div>

@php
    $fechaFin = \Carbon\Carbon::parse($certificado->fecha_emision);
    $diaTexto = \App\Support\NumeroEnPalabras::dia((int) $fechaFin->day);
    $mesTexto = \Illuminate\Support\Str::ucfirst($fechaFin->locale('es')->isoFormat('MMMM'));
@endphp

<p class="parrafo">
    EL PROCESO DE FORMACIÓN DE LA ORGANIZACIÓN D&amp;L QUALITY CONSULTING LTDA. HACE CONSTAR
    Que {{ $certificado->usuario->name }},
    quien se identifica con cédula de ciudadanía número
    <strong>{{ $certificado->usuario->numero_documento }}</strong>
    @if($certificado->usuario->ciudad_expedicion)
        de {{ $certificado->usuario->ciudad_expedicion }},
    @endif
    culminó exitosamente todos los contenidos académicos y aprobó satisfactoriamente la prueba
    de conocimiento del <strong>DIPLOMADO EN {{ mb_strtoupper($certificado->curso->titulo) }}</strong>,
    realizado entre el {{ \Carbon\Carbon::parse($inscripcion->fecha_inicio)->format('d/m/Y') }}
    y el {{ \Carbon\Carbon::parse($inscripcion->fecha_fin)->format('d/m/Y') }}
    con una intensidad de <strong>{{ $certificado->curso->duracion_horas }} horas</strong>.
    Se expide a solicitud de la o el interesado a los {{ $diaTexto }} ({{ $fechaFin->day }})
    días del mes de {{ $mesTexto }} del año {{ $fechaFin->year }} en la ciudad de Bogotá D.C.
</p>

<p class="despedida">Atentamente,</p>

<div class="firma-bloque">
    <img src="{{ public_path('images/certificados/firma-sandra-fajardo.jpg') }}">
    <div class="firma-linea"></div>
    <p class="firma-nombre">Sandra Marcela Fajardo Valero</p>
    <p class="firma-cargo">Coordinadora de formación empresarial</p>
    <p class="firma-empresa">D&amp;L QUALITY CONSULTING LTDA</p>
</div>

<div class="pie">
    <span><strong>Contacto:</strong> +57 305 442 2705</span>
    <span><strong>Horario:</strong> L-V 8:00 am - 5:00 pm</span>
    <span><strong>Email:</strong> contacto@dylqualityconsulting.com</span>
</div>

</body>
</html>
```

- [ ] **Step 4: Correr el test y verificar que pasa**

Run: `php artisan test --filter=CertificadoPlantillaCartaTest`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add resources/views/certificados/plantilla-carta.blade.php tests/Feature/CertificadoPlantillaCartaTest.php
git commit -m "feat: agregar plantilla-carta.blade.php para certificados de diplomado"
```

---

## Task 7: `CertificadoService` — selección de plantilla, orientación y dato faltante

**Files:**
- Modify: `app/Services/CertificadoService.php`
- Test: `tests/Feature/CertificadoServiceTipoTest.php`

**Interfaces:**
- Consumes: `Curso::$tipo_certificado` (Task 1), vistas `certificados.plantilla-pdf` (Task 5) y `certificados.plantilla-carta` (Task 6, espera `$certificado` + `$inscripcion`).
- Produces: `CertificadoService::generarSiCorresponde()` sigue devolviendo `?Certificado` (contrato sin cambios para `CertificadoController::generar()`, que ya maneja `null`); `generarPdf()` sigue devolviendo `string` (ruta relativa).

- [ ] **Step 1: Escribir el test (falla primero)**

```php
<?php
// tests/Feature/CertificadoServiceTipoTest.php

namespace Tests\Feature;

use App\Models\Actividad;
use App\Models\Curso;
use App\Models\Inscripcion;
use App\Models\Leccion;
use App\Models\Modulo;
use App\Models\Notificacion;
use App\Models\User;
use App\Services\CertificadoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CertificadoServiceTipoTest extends TestCase
{
    use RefreshDatabase;

    private function completarInscripcion(User $estudiante, Curso $curso): void
    {
        Inscripcion::create([
            'user_id' => $estudiante->id, 'curso_id' => $curso->id,
            'fecha_inicio' => '2026-01-01', 'fecha_fin' => '2026-02-01', 'estado' => 'completado',
        ]);
    }

    public function test_curso_diploma_genera_certificado_normalmente(): void
    {
        Storage::fake('public');

        $instructor = User::factory()->create();
        $estudiante = User::factory()->create(['numero_documento' => null]); // sin documento, no debería importarle a un diploma
        $curso      = Curso::factory()->create(['created_by' => $instructor->id]); // tipo_certificado = diploma por defecto
        $this->completarInscripcion($estudiante, $curso);

        $certificado = app(CertificadoService::class)->generarSiCorresponde($estudiante, $curso);

        $this->assertNotNull($certificado);
        Storage::disk('public')->assertExists($certificado->archivo_pdf);
    }

    public function test_curso_diplomado_con_documento_genera_certificado(): void
    {
        Storage::fake('public');

        $instructor = User::factory()->create();
        $estudiante = User::factory()->create(['numero_documento' => '1000790950']);
        $curso      = Curso::factory()->diplomado()->create(['created_by' => $instructor->id]);
        $this->completarInscripcion($estudiante, $curso);

        $certificado = app(CertificadoService::class)->generarSiCorresponde($estudiante, $curso);

        $this->assertNotNull($certificado);
        Storage::disk('public')->assertExists($certificado->archivo_pdf);
    }

    public function test_curso_diplomado_sin_documento_no_genera_y_notifica(): void
    {
        Storage::fake('public');

        $instructor = User::factory()->create();
        $estudiante = User::factory()->create(['numero_documento' => null]);
        $curso      = Curso::factory()->diplomado()->create(['created_by' => $instructor->id]);
        $this->completarInscripcion($estudiante, $curso);

        $certificado = app(CertificadoService::class)->generarSiCorresponde($estudiante, $curso);

        $this->assertNull($certificado);
        $this->assertDatabaseCount('certificados', 0);
        $this->assertDatabaseHas('notificaciones', [
            'user_id' => $estudiante->id,
            'tipo'    => 'certificado',
        ]);
    }
}
```

- [ ] **Step 2: Correr el test y verificar el estado inicial**

Run: `php artisan test --filter=CertificadoServiceTipoTest`
Expected: `test_curso_diploma_genera_certificado_normalmente` y `test_curso_diplomado_con_documento_genera_certificado` PASAN ya (la plantilla-pdf actual no distingue tipo, así que "diplomado" también genera con el diseño de diploma). `test_curso_diplomado_sin_documento_no_genera_y_notifica` FALLA — hoy el servicio genera el certificado igual, sin chequear `numero_documento`.

- [ ] **Step 3: Modificar `generarSiCorresponde()` y `generarPdf()`**

En `app/Services/CertificadoService.php`, agregar el import de `Notificacion` arriba:
```php
use App\Models\Certificado;
use App\Models\Curso;
use App\Models\Inscripcion;
use App\Models\Notificacion;
use App\Models\User;
use Illuminate\Support\Str;
use Mpdf\Mpdf;
use Mpdf\Config\ConfigVariables;
use Mpdf\Config\FontVariables;
```

Cambiar el método `generarSiCorresponde()` completo:
```php
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

Cambiar el método `generarPdf()` completo:
```php
    public function generarPdf(Certificado $certificado): string
    {
        $certificado->load(['usuario', 'curso.creador']);

        $esDiplomado = $certificado->curso->tipo_certificado === 'diplomado';

        if ($esDiplomado) {
            $inscripcion = Inscripcion::where('user_id', $certificado->user_id)
                ->where('curso_id', $certificado->curso_id)
                ->first();

            $html = view('certificados.plantilla-carta', compact('certificado', 'inscripcion'))->render();
            $mpdfConfig = [
                'mode'          => 'utf-8',
                'format'        => 'A4',
                'orientation'   => 'P',
                'margin_top'    => 20,
                'margin_right'  => 20,
                'margin_bottom' => 25,
                'margin_left'   => 20,
                'tempDir'       => storage_path('app/tmp'),
            ];
        } else {
            $html = view('certificados.plantilla-pdf', compact('certificado'))->render();
            $mpdfConfig = [
                'mode'          => 'utf-8',
                'format'        => 'A4-L',
                'orientation'   => 'L',
                'margin_top'    => 0,
                'margin_right'  => 0,
                'margin_bottom' => 0,
                'margin_left'   => 0,
                'tempDir'       => storage_path('app/tmp'),
            ];
        }

        $mpdf = new Mpdf($mpdfConfig);
        $mpdf->WriteHTML($html);

        $año           = date('Y');
        $nombreArchivo = 'certificado-' . $certificado->numero_certificado . '.pdf';
        $directorio    = storage_path("app/public/certificados/{$año}");

        if (!is_dir($directorio)) {
            mkdir($directorio, 0755, true);
        }

        $mpdf->Output("{$directorio}/{$nombreArchivo}", 'F');

        return "certificados/{$año}/{$nombreArchivo}";
    }
```

- [ ] **Step 4: Correr el test y verificar que pasa**

Run: `php artisan test --filter=CertificadoServiceTipoTest`
Expected: 3 passed.

- [ ] **Step 5: Correr toda la suite**

Run: `php artisan test`
Expected: todo en verde, incluyendo `CertificadoServiceIntentosTest` (que genera certificados de cursos `diploma` por defecto — no debería verse afectado).

- [ ] **Step 6: Commit**

```bash
git add app/Services/CertificadoService.php tests/Feature/CertificadoServiceTipoTest.php
git commit -m "feat: CertificadoService elige plantilla por tipo_certificado y valida numero_documento"
```

---

## Task 8: `certificados/show.blade.php` — preview real del PDF

La vista actual reimplementa el diseño del diploma a mano en Tailwind (duplicando `plantilla-pdf.blade.php` en HTML aparte) — quedaría desactualizada frente al rediseño y no tiene sentido para el formato de carta. Se reemplaza por un `<iframe>` que muestra el PDF real ya generado, válido para ambos tipos sin duplicar ningún diseño.

**Files:**
- Modify: `resources/views/certificados/show.blade.php` (reemplaza el bloque de preview, líneas ~20-66 de la versión actual)
- Test: `tests/Feature/CertificadoShowPreviewTest.php`

**Interfaces:**
- Consumes: `Certificado::$archivo_pdf` (ya existente, sin cambios).

- [ ] **Step 1: Escribir el test (falla primero)**

```php
<?php
// tests/Feature/CertificadoShowPreviewTest.php

namespace Tests\Feature;

use App\Models\Certificado;
use App\Models\Curso;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CertificadoShowPreviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_la_vista_muestra_un_iframe_con_el_pdf_real(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('certificados/2026/certificado-CERT-TEST.pdf', 'contenido-fake');

        $instructor = User::factory()->create();
        $estudiante = User::factory()->create();
        $curso      = Curso::factory()->create(['created_by' => $instructor->id]);

        $certificado = Certificado::create([
            'user_id'            => $estudiante->id,
            'curso_id'           => $curso->id,
            'fecha_emision'      => now()->toDateString(),
            'numero_certificado' => 'CERT-TEST',
            'archivo_pdf'        => 'certificados/2026/certificado-CERT-TEST.pdf',
        ]);

        $response = $this->actingAs($estudiante)->get(route('certificados.show', $certificado));

        $response->assertOk();
        $response->assertSee('<iframe', false);
        $response->assertSee(Storage::disk('public')->url($certificado->archivo_pdf), false);
    }
}
```

- [ ] **Step 2: Correr el test y verificar que falla**

Run: `php artisan test --filter=CertificadoShowPreviewTest`
Expected: FAIL — la vista actual no tiene ningún `<iframe>`.

- [ ] **Step 3: Reemplazar el bloque de preview**

En `resources/views/certificados/show.blade.php`, reemplazar todo el bloque que va desde el comentario `{{-- Tarjeta previsualización del certificado --}}` hasta su `</div>` de cierre (el `<div class="bg-white rounded-2xl shadow-lg ...">` completo, incluyendo la franja decorativa superior e inferior y el contenido Tailwind del mockup) por:

```blade
    {{-- Previsualización: el PDF real, no un mockup aparte --}}
    <div class="bg-white rounded-2xl shadow-lg overflow-hidden mb-6 border-2 border-dyl-orange-300">
        <iframe
            src="{{ Storage::disk('public')->url($certificado->archivo_pdf) }}"
            class="w-full"
            style="height: 80vh; border: none;"
            title="Certificado {{ $certificado->numero_certificado }}"
        ></iframe>
    </div>
```

Confirmar que el archivo tiene `use Illuminate\Support\Facades\Storage;` disponible en el contexto de la vista — Blade no necesita `use` para facades, `Storage::disk(...)` funciona directo por el alias global, igual que en el resto de vistas del proyecto (ver `cursos/edit.blade.php` que ya usa `Storage`-backed helpers de forma similar vía controlador; aquí se llama directo porque es una operación de solo lectura de URL, sin necesidad de pasar la variable desde el controlador).

- [ ] **Step 4: Correr el test y verificar que pasa**

Run: `php artisan test --filter=CertificadoShowPreviewTest`
Expected: PASS.

- [ ] **Step 5: Correr toda la suite**

Run: `php artisan test`
Expected: todo en verde.

- [ ] **Step 6: Commit**

```bash
git add resources/views/certificados/show.blade.php tests/Feature/CertificadoShowPreviewTest.php
git commit -m "fix: previsualizar el PDF real del certificado en vez de un mockup HTML duplicado"
```

---

## Task 9: Tests de integración end-to-end vía `CertificadoController`

Confirma que todo el flujo funciona junto, desde la ruta HTTP que un estudiante realmente usa (`certificados.generar`), para ambos tipos de certificado y el caso bloqueado.

**Files:**
- Test: `tests/Feature/CertificadoGenerarEndToEndTest.php`

**Interfaces:**
- Consumes: todo lo de Tasks 1-8 (sin producir nada nuevo — es la capa de confirmación final).

- [ ] **Step 1: Escribir los tests**

```php
<?php
// tests/Feature/CertificadoGenerarEndToEndTest.php

namespace Tests\Feature;

use App\Models\Curso;
use App\Models\Inscripcion;
use App\Models\Rol;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CertificadoGenerarEndToEndTest extends TestCase
{
    use RefreshDatabase;

    private function crearEstudiante(array $atributos = []): User
    {
        $rol = Rol::factory()->create(['nombre' => 'Estudiante']);
        $user = User::factory()->create(array_merge(['estado' => 'activo'], $atributos));
        $user->roles()->attach($rol);
        return $user;
    }

    private function completarInscripcion(User $estudiante, Curso $curso): void
    {
        Inscripcion::create([
            'user_id' => $estudiante->id, 'curso_id' => $curso->id,
            'fecha_inicio' => '2026-01-01', 'fecha_fin' => '2026-02-01', 'estado' => 'completado',
        ]);
    }

    public function test_estudiante_obtiene_diploma_via_ruta_generar(): void
    {
        $instructor = User::factory()->create();
        $curso      = Curso::factory()->create(['created_by' => $instructor->id]);
        $estudiante = $this->crearEstudiante();
        $this->completarInscripcion($estudiante, $curso);

        $response = $this->actingAs($estudiante)->post(route('certificados.generar', $curso));

        $response->assertRedirect();
        $this->assertDatabaseHas('certificados', ['user_id' => $estudiante->id, 'curso_id' => $curso->id]);
    }

    public function test_estudiante_obtiene_carta_de_diplomado_via_ruta_generar(): void
    {
        $instructor = User::factory()->create();
        $curso      = Curso::factory()->diplomado()->create(['created_by' => $instructor->id]);
        $estudiante = $this->crearEstudiante(['numero_documento' => '1000790950', 'ciudad_expedicion' => 'Bogotá']);
        $this->completarInscripcion($estudiante, $curso);

        $response = $this->actingAs($estudiante)->post(route('certificados.generar', $curso));

        $response->assertRedirect(route('certificados.show', \App\Models\Certificado::first()));
        $this->assertDatabaseHas('certificados', ['user_id' => $estudiante->id, 'curso_id' => $curso->id]);
    }

    public function test_estudiante_sin_documento_no_obtiene_carta_y_queda_notificado(): void
    {
        $instructor = User::factory()->create();
        $curso      = Curso::factory()->diplomado()->create(['created_by' => $instructor->id]);
        $estudiante = $this->crearEstudiante(['numero_documento' => null]);
        $this->completarInscripcion($estudiante, $curso);

        $response = $this->actingAs($estudiante)->post(route('certificados.generar', $curso));

        $response->assertRedirect(route('cursos.show', $curso));
        $this->assertDatabaseCount('certificados', 0);
        $this->assertDatabaseHas('notificaciones', ['user_id' => $estudiante->id, 'tipo' => 'certificado']);
    }
}
```

- [ ] **Step 2: Correr los tests**

Run: `php artisan test --filter=CertificadoGenerarEndToEndTest`
Expected: 3 passed.

- [ ] **Step 3: Correr toda la suite completa del proyecto**

Run: `php artisan test`
Expected: todo en verde — este es el checkpoint final de todo el feature.

- [ ] **Step 4: Commit**

```bash
git add tests/Feature/CertificadoGenerarEndToEndTest.php
git commit -m "test: cobertura end-to-end de generación de certificados (diploma, diplomado, dato faltante)"
```

---

## Nota final (fuera del alcance de las tareas anteriores)

Ningún certificado ya emitido antes de este cambio se regenera automáticamente — si el usuario quiere que certificados viejos adopten el nuevo diseño, es una decisión aparte (regenerar manualmente vía `CertificadoService::generarPdf()` para esos registros), no incluida en este plan.
