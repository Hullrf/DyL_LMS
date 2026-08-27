# Backup y Restauración Manual de la BD — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Agregar una pantalla `/admin/backups` (solo Administrador) con dos acciones manuales: descargar un dump SQL completo de la base de datos de producción, y restaurar la base desde un archivo `.sql` previamente descargado.

**Architecture:** Un `BackupService` en PHP puro (sin depender de binarios `mysqldump`/`mysql`, confirmados ausentes en el contenedor de producción) hace el dump vía la librería `druidfi/mysqldump-php` y transmite el resultado directo como descarga HTTP (sin persistir nada en el servidor). La restauración parsea el `.sql` subido en sentencias individuales y las ejecuta una por una vía `DB::unprepared()`, reportando exactamente cuál sentencia falla si algo sale mal.

**Tech Stack:** Laravel 12, PHP 8.2, MySQL, paquete `druidfi/mysqldump-php:^2.0`, Alpine.js (ya usado en el proyecto) para el gate de confirmación en el formulario de restauración.

**Spec:** `docs/superpowers/specs/2026-08-27-backup-restauracion-bd-design.md`

## Global Constraints

- El contenedor de producción en Railway **no tiene `mysqldump` ni `mysql` CLI instalados** (verificado con `railway ssh`) — todo el dump/restore debe ser PHP puro, nunca `Process`/`shell_exec` hacia esos binarios.
- Ambas rutas exclusivamente bajo `middleware(['auth', 'admin'])`, prefijo `admin`, mismo patrón que `admin.usuarios.*` / `admin.auditoria.*` en `routes/web.php`.
- El archivo de backup **nunca se guarda en el servidor** (ni `storage/`, ni disco S3) — se transmite directo como respuesta HTTP.
- La restauración es una operación irreversible (DROP + CREATE + INSERT); no se promete rollback automático.
- No tocar la base de datos real (`dyl_lms` local ni producción) desde los tests automatizados — los tests de `BackupController` deben mockear `BackupService`, y la lógica de parseo de SQL (`dividirEnSentencias`) se testea sin ninguna conexión a base de datos.

---

### Task 1: `BackupService` — parseo de sentencias SQL (con tests unitarios)

**Files:**
- Create: `app/Services/BackupService.php`
- Test: `tests/Unit/BackupServiceTest.php`

**Interfaces:**
- Produces: `App\Services\BackupService::dividirEnSentencias(string $sql): array<string>` — método público, sin dependencias externas, usado internamente por `restaurarDesdeArchivo()` (Task 2) y testeado de forma aislada acá.

- [ ] **Step 1: Escribir el test que falla**

Crear `tests/Unit/BackupServiceTest.php`:

```php
<?php

namespace Tests\Unit;

use App\Services\BackupService;
use Tests\TestCase;

class BackupServiceTest extends TestCase
{
    private BackupService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new BackupService();
    }

    public function test_separa_dos_sentencias_simples(): void
    {
        $sql = "SET NAMES utf8;\nDROP TABLE IF EXISTS `users`;\n";

        $sentencias = $this->service->dividirEnSentencias($sql);

        $this->assertCount(2, $sentencias);
        $this->assertSame('SET NAMES utf8;', $sentencias[0]);
        $this->assertSame('DROP TABLE IF EXISTS `users`;', $sentencias[1]);
    }

    public function test_ignora_lineas_de_comentario(): void
    {
        $sql = "-- MySQL dump\n-- ------------\nDROP TABLE IF EXISTS `users`;\n";

        $sentencias = $this->service->dividirEnSentencias($sql);

        $this->assertCount(1, $sentencias);
        $this->assertSame('DROP TABLE IF EXISTS `users`;', $sentencias[0]);
    }

    public function test_soporta_create_table_multilinea_como_una_sola_sentencia(): void
    {
        $sql = "CREATE TABLE `users` (\n  `id` bigint NOT NULL,\n  `name` varchar(255) NOT NULL\n) ENGINE=InnoDB;\n";

        $sentencias = $this->service->dividirEnSentencias($sql);

        $this->assertCount(1, $sentencias);
        $this->assertStringContainsString('CREATE TABLE `users`', $sentencias[0]);
        $this->assertStringContainsString('`id` bigint NOT NULL', $sentencias[0]);
        $this->assertStringEndsWith(') ENGINE=InnoDB;', $sentencias[0]);
    }

    public function test_incluye_condicionales_de_mysql_como_sentencia_propia(): void
    {
        $sql = "/*!40101 SET NAMES utf8 */;\nDROP TABLE IF EXISTS `users`;\n";

        $sentencias = $this->service->dividirEnSentencias($sql);

        $this->assertCount(2, $sentencias);
        $this->assertSame('/*!40101 SET NAMES utf8 */;', $sentencias[0]);
    }

    public function test_ignora_lineas_vacias(): void
    {
        $sql = "DROP TABLE IF EXISTS `a`;\n\n\nDROP TABLE IF EXISTS `b`;\n";

        $sentencias = $this->service->dividirEnSentencias($sql);

        $this->assertCount(2, $sentencias);
    }

    public function test_sql_vacio_devuelve_array_vacio(): void
    {
        $this->assertSame([], $this->service->dividirEnSentencias(''));
    }
}
```

- [ ] **Step 2: Correr el test para confirmar que falla**

Run: `php artisan test --filter=BackupServiceTest`
Expected: FAIL — `Class "App\Services\BackupService" not found`

- [ ] **Step 3: Implementación mínima**

Crear `app/Services/BackupService.php`:

```php
<?php

namespace App\Services;

class BackupService
{
    /**
     * Separa un dump SQL en sentencias individuales ejecutables. Cada
     * sentencia termina con ";" seguido de salto de línea (formato que
     * produce druidfi/mysqldump-php, igual que mysqldump). Las líneas de
     * comentario puro (que empiezan con "--") se descartan; las líneas
     * condicionales de MySQL ("/*!...*\/;") SÍ son sentencias ejecutables
     * válidas y se conservan.
     *
     * @return array<int, string>
     */
    public function dividirEnSentencias(string $sql): array
    {
        $lineas = preg_split('/\r\n|\r|\n/', $sql);
        $sentenciaActual = '';
        $sentencias = [];

        foreach ($lineas as $linea) {
            $lineaSinEspacios = trim($linea);

            if ($lineaSinEspacios === '' || str_starts_with($lineaSinEspacios, '--')) {
                continue;
            }

            $sentenciaActual .= ($sentenciaActual === '' ? '' : "\n") . $linea;

            if (str_ends_with($lineaSinEspacios, ';')) {
                $sentencias[] = trim($sentenciaActual);
                $sentenciaActual = '';
            }
        }

        if (trim($sentenciaActual) !== '') {
            $sentencias[] = trim($sentenciaActual);
        }

        return $sentencias;
    }
}
```

- [ ] **Step 4: Correr el test para confirmar que pasa**

Run: `php artisan test --filter=BackupServiceTest`
Expected: PASS — 6 tests, todos verdes.

- [ ] **Step 5: Commit**

```bash
git add app/Services/BackupService.php tests/Unit/BackupServiceTest.php
git commit -m "feat: parseo de sentencias SQL para restauración de backups"
```

---

### Task 2: `BackupService` — crear dump y restaurar (integración con MySQL)

**Files:**
- Create: `composer.json` (modificado por `composer require`)
- Modify: `app/Services/BackupService.php`

**Interfaces:**
- Consumes: `App\Services\BackupService::dividirEnSentencias()` (Task 1)
- Produces:
  - `App\Services\BackupService::crearDump(string $destino): void` — usado por `BackupController::crear()` (Task 3)
  - `App\Services\BackupService::restaurarDesdeArchivo(string $rutaArchivo): int` — usado por `BackupController::restaurar()` (Task 3). Devuelve la cantidad de sentencias ejecutadas; lanza `\RuntimeException` con el número y mensaje de la sentencia que falló.

**Nota sobre testing:** estos dos métodos requieren una conexión MySQL real (la librería `Druidfi\Mysqldump\Mysqldump` no funciona contra SQLite, que es lo que usa la suite de tests vía `phpunit.xml`). No se agregan tests automatizados para ellos en este plan — se verifican manualmente en el Task 5 contra la base de datos local. La lógica no trivial (parseo de sentencias) ya quedó cubierta en el Task 1.

- [ ] **Step 1: Instalar la dependencia**

Run: `composer require druidfi/mysqldump-php:^2.0`
Expected: se agrega `"druidfi/mysqldump-php": "^2.0"` a `composer.json` y su entrada en `composer.lock`, sin conflictos de versión (el proyecto usa PHP `^8.2`, el paquete requiere `^8.1`).

- [ ] **Step 2: Agregar `crearDump()` y `restaurarDesdeArchivo()`**

Modificar `app/Services/BackupService.php` (agregar al inicio del archivo el `use`, y estos dos métodos dentro de la clase, junto a `dividirEnSentencias`):

```php
<?php

namespace App\Services;

use Druidfi\Mysqldump\Mysqldump;
use Illuminate\Support\Facades\DB;

class BackupService
{
    /**
     * Genera el dump completo de la base de datos actual y lo escribe en
     * $destino. $destino admite cualquier stream wrapper de PHP (fopen()),
     * por ejemplo 'php://output' para transmitirlo directo como descarga
     * sin tocar disco. Usa las mismas credenciales que ya tiene configurada
     * la aplicación (config/database.php) — no requiere configuración
     * adicional.
     */
    public function crearDump(string $destino): void
    {
        $conexion = config('database.default');
        $config   = config("database.connections.{$conexion}");

        $dsn = "mysql:host={$config['host']};port={$config['port']};dbname={$config['database']};charset={$config['charset']}";

        $dump = new Mysqldump($dsn, $config['username'], $config['password'], [
            'add-drop-table'     => true,
            'no-data'            => false,
            'single-transaction' => true,
            'lock-tables'        => false,
        ]);

        $dump->start($destino);
    }

    /**
     * Ejecuta un archivo .sql (generado por crearDump()) contra la base de
     * datos actual, sentencia por sentencia. Reemplaza por completo las
     * tablas incluidas en el archivo (los dumps de crearDump() llevan
     * DROP TABLE IF EXISTS antes de cada CREATE TABLE). No hay rollback
     * automático si una sentencia falla a mitad de camino: MySQL hace
     * commit implícito en cada DDL (CREATE/DROP TABLE), así que una
     * transacción envolvente no serviría para deshacerlo.
     *
     * @return int cantidad de sentencias ejecutadas con éxito
     * @throws \RuntimeException si el archivo no se puede leer, o si
     *         alguna sentencia falla (el mensaje incluye cuál).
     */
    public function restaurarDesdeArchivo(string $rutaArchivo): int
    {
        $contenido = file_get_contents($rutaArchivo);

        if ($contenido === false) {
            throw new \RuntimeException('No se pudo leer el archivo de backup.');
        }

        $sentencias = $this->dividirEnSentencias($contenido);
        $total      = count($sentencias);
        $ejecutadas = 0;

        foreach ($sentencias as $i => $sentencia) {
            try {
                DB::unprepared($sentencia);
                $ejecutadas++;
            } catch (\Throwable $e) {
                $numero = $i + 1;
                throw new \RuntimeException(
                    "Falló la sentencia {$numero} de {$total}: {$e->getMessage()}"
                );
            }
        }

        return $ejecutadas;
    }

    /**
     * Separa un dump SQL en sentencias individuales ejecutables. Cada
     * sentencia termina con ";" seguido de salto de línea (formato que
     * produce druidfi/mysqldump-php, igual que mysqldump). Las líneas de
     * comentario puro (que empiezan con "--") se descartan; las líneas
     * condicionales de MySQL ("/*!...*\/;") SÍ son sentencias ejecutables
     * válidas y se conservan.
     *
     * @return array<int, string>
     */
    public function dividirEnSentencias(string $sql): array
    {
        $lineas = preg_split('/\r\n|\r|\n/', $sql);
        $sentenciaActual = '';
        $sentencias = [];

        foreach ($lineas as $linea) {
            $lineaSinEspacios = trim($linea);

            if ($lineaSinEspacios === '' || str_starts_with($lineaSinEspacios, '--')) {
                continue;
            }

            $sentenciaActual .= ($sentenciaActual === '' ? '' : "\n") . $linea;

            if (str_ends_with($lineaSinEspacios, ';')) {
                $sentencias[] = trim($sentenciaActual);
                $sentenciaActual = '';
            }
        }

        if (trim($sentenciaActual) !== '') {
            $sentencias[] = trim($sentenciaActual);
        }

        return $sentencias;
    }
}
```

- [ ] **Step 3: Confirmar que la suite completa sigue verde**

Run: `php artisan test`
Expected: PASS — todos los tests existentes siguen pasando (esta clase todavía no la usa nadie más).

- [ ] **Step 4: Commit**

```bash
git add composer.json composer.lock app/Services/BackupService.php
git commit -m "feat: BackupService crea dump y restaura vía druidfi/mysqldump-php"
```

---

### Task 3: Rutas y `BackupController` (con tests de feature)

**Files:**
- Create: `app/Http/Controllers/Admin/BackupController.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/Admin/BackupControllerTest.php`

**Interfaces:**
- Consumes: `App\Services\BackupService::crearDump()`, `::restaurarDesdeArchivo()` (Task 2) — mockeado en los tests.
- Produces: rutas `admin.backups.index` (GET `/admin/backups`), `admin.backups.crear` (POST `/admin/backups/crear`), `admin.backups.restaurar` (POST `/admin/backups/restaurar`). Consumidas por la vista en el Task 4.

- [ ] **Step 1: Escribir los tests que fallan**

Crear `tests/Feature/Admin/BackupControllerTest.php`:

```php
<?php

namespace Tests\Feature\Admin;

use App\Models\Rol;
use App\Models\User;
use App\Services\BackupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class BackupControllerTest extends TestCase
{
    use RefreshDatabase;

    private function crearUsuario(string $rolNombre): User
    {
        $rol  = Rol::firstOrCreate(['nombre' => $rolNombre]);
        $user = User::factory()->create(['estado' => 'activo']);
        $user->roles()->attach($rol);
        return $user;
    }

    public function test_admin_puede_acceder_a_la_pantalla_de_backups(): void
    {
        $admin = $this->crearUsuario('Administrador');

        $response = $this->actingAs($admin)->get(route('admin.backups.index'));

        $response->assertOk();
        $response->assertViewIs('admin.backups.index');
    }

    public function test_instructor_no_puede_acceder_a_pantalla_de_backups(): void
    {
        $instructor = $this->crearUsuario('Instructor');

        $response = $this->actingAs($instructor)->get(route('admin.backups.index'));

        $response->assertForbidden();
    }

    public function test_crear_backup_devuelve_el_dump_como_descarga(): void
    {
        $admin = $this->crearUsuario('Administrador');

        $this->mock(BackupService::class, function ($mock) {
            $mock->shouldReceive('crearDump')
                ->once()
                ->with('php://output')
                ->andReturnUsing(function () {
                    echo "-- dump de prueba\nDROP TABLE IF EXISTS `x`;\n";
                });
        });

        $response = $this->actingAs($admin)->post(route('admin.backups.crear'));

        $response->assertOk();
        $response->assertHeader('content-disposition');
        $this->assertStringContainsString(
            'attachment',
            $response->headers->get('content-disposition')
        );
        $this->assertStringContainsString(
            'DROP TABLE IF EXISTS `x`;',
            $response->streamedContent()
        );
    }

    public function test_instructor_no_puede_crear_backup(): void
    {
        $instructor = $this->crearUsuario('Instructor');

        $response = $this->actingAs($instructor)->post(route('admin.backups.crear'));

        $response->assertForbidden();
    }

    public function test_restaurar_requiere_escribir_restaurar_exacto(): void
    {
        $admin = $this->crearUsuario('Administrador');

        $this->mock(BackupService::class, function ($mock) {
            $mock->shouldNotReceive('restaurarDesdeArchivo');
        });

        $response = $this->actingAs($admin)->post(route('admin.backups.restaurar'), [
            'confirmacion' => 'restaurar',
            'archivo'      => UploadedFile::fake()->create('backup.sql', 10),
        ]);

        $response->assertSessionHasErrors('confirmacion');
    }

    public function test_restaurar_rechaza_archivo_sin_extension_sql(): void
    {
        $admin = $this->crearUsuario('Administrador');

        $this->mock(BackupService::class, function ($mock) {
            $mock->shouldNotReceive('restaurarDesdeArchivo');
        });

        $response = $this->actingAs($admin)->post(route('admin.backups.restaurar'), [
            'confirmacion' => 'RESTAURAR',
            'archivo'      => UploadedFile::fake()->create('backup.txt', 10),
        ]);

        $response->assertSessionHasErrors('archivo');
    }

    public function test_restaurar_con_archivo_valido_llama_al_servicio_y_redirige(): void
    {
        $admin = $this->crearUsuario('Administrador');

        $this->mock(BackupService::class, function ($mock) {
            $mock->shouldReceive('restaurarDesdeArchivo')->once()->andReturn(42);
        });

        $response = $this->actingAs($admin)->post(route('admin.backups.restaurar'), [
            'confirmacion' => 'RESTAURAR',
            'archivo'      => UploadedFile::fake()->create('backup.sql', 10),
        ]);

        $response->assertRedirect(route('admin.backups.index'));
        $response->assertSessionHas('success');
    }

    public function test_instructor_no_puede_restaurar(): void
    {
        $instructor = $this->crearUsuario('Instructor');

        $response = $this->actingAs($instructor)->post(route('admin.backups.restaurar'), [
            'confirmacion' => 'RESTAURAR',
            'archivo'      => UploadedFile::fake()->create('backup.sql', 10),
        ]);

        $response->assertForbidden();
    }
}
```

- [ ] **Step 2: Correr los tests para confirmar que fallan**

Run: `php artisan test --filter=BackupControllerTest`
Expected: FAIL — `Route [admin.backups.index] not defined` (u error equivalente).

- [ ] **Step 3: Agregar las rutas**

En `routes/web.php`, dentro del grupo existente (buscar el bloque que empieza en `Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(function () {`), agregar antes del `});` de cierre de ese grupo:

```php
    Route::get('backups', [\App\Http\Controllers\Admin\BackupController::class, 'index'])
        ->name('backups.index');
    Route::post('backups/crear', [\App\Http\Controllers\Admin\BackupController::class, 'crear'])
        ->name('backups.crear');
    Route::post('backups/restaurar', [\App\Http\Controllers\Admin\BackupController::class, 'restaurar'])
        ->name('backups.restaurar');
```

El bloque completo queda:

```php
// Admin
Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::resource('usuarios', \App\Http\Controllers\Admin\UsuarioController::class)
        ->except(['show']);
    Route::resource('categorias', \App\Http\Controllers\Admin\CategoriaController::class)
        ->except(['show']);
    Route::get('auditoria', [\App\Http\Controllers\Admin\AuditoriaController::class, 'index'])
        ->name('auditoria.index');
    Route::get('backups', [\App\Http\Controllers\Admin\BackupController::class, 'index'])
        ->name('backups.index');
    Route::post('backups/crear', [\App\Http\Controllers\Admin\BackupController::class, 'crear'])
        ->name('backups.crear');
    Route::post('backups/restaurar', [\App\Http\Controllers\Admin\BackupController::class, 'restaurar'])
        ->name('backups.restaurar');
});
```

- [ ] **Step 4: Crear el controller**

Crear `app/Http/Controllers/Admin/BackupController.php`:

```php
<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\BackupService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BackupController extends Controller
{
    public function __construct(private BackupService $backupService)
    {
    }

    public function index()
    {
        return view('admin.backups.index');
    }

    public function crear(): StreamedResponse
    {
        $nombreArchivo = 'backup_dyl_lms_' . now()->format('Y-m-d_His') . '.sql';

        Log::info('Backup de base de datos creado', [
            'user_id'   => Auth::id(),
            'user_name' => Auth::user()->name,
        ]);

        return response()->streamDownload(function () {
            $this->backupService->crearDump('php://output');
        }, $nombreArchivo, [
            'Content-Type' => 'application/sql',
        ]);
    }

    public function restaurar(Request $request)
    {
        $request->validate([
            'confirmacion' => 'required|in:RESTAURAR',
            'archivo'      => 'required|file|extensions:sql|max:51200',
        ]);

        try {
            $ejecutadas = $this->backupService->restaurarDesdeArchivo(
                $request->file('archivo')->getRealPath()
            );
        } catch (\Throwable $e) {
            Log::error('Restauración de base de datos falló', [
                'user_id' => Auth::id(),
                'error'   => $e->getMessage(),
            ]);

            return back()->withErrors(['archivo' => 'La restauración falló: ' . $e->getMessage()]);
        }

        Log::warning('Restauración de base de datos ejecutada', [
            'user_id'    => Auth::id(),
            'user_name'  => Auth::user()->name,
            'archivo'    => $request->file('archivo')->getClientOriginalName(),
            'sentencias' => $ejecutadas,
        ]);

        return redirect()->route('admin.backups.index')
            ->with('success', "Base de datos restaurada correctamente ({$ejecutadas} sentencias ejecutadas).");
    }
}
```

- [ ] **Step 5: Crear la vista mínima (placeholder temporal, se completa en el Task 4)**

Crear `resources/views/admin/backups/index.blade.php` con contenido mínimo para que las rutas resuelvan (el Task 4 la reemplaza por completo):

```blade
@extends('layouts.app')
@section('content')
<div>Backups</div>
@endsection
```

- [ ] **Step 6: Correr los tests para confirmar que pasan**

Run: `php artisan test --filter=BackupControllerTest`
Expected: PASS — 8 tests, todos verdes.

- [ ] **Step 7: Correr la suite completa**

Run: `php artisan test`
Expected: PASS — sin regresiones sobre el resto del proyecto.

- [ ] **Step 8: Commit**

```bash
git add app/Http/Controllers/Admin/BackupController.php routes/web.php resources/views/admin/backups/index.blade.php tests/Feature/Admin/BackupControllerTest.php
git commit -m "feat: rutas y controller para crear/restaurar backups (admin)"
```

---

### Task 4: Vista completa, breadcrumb y enlace de navegación

**Files:**
- Modify: `resources/views/admin/backups/index.blade.php` (reemplaza el placeholder del Task 3)
- Modify: `routes/breadcrumbs.php`
- Modify: `resources/views/layouts/partials/sidebar.blade.php`
- Test: `tests/Feature/Admin/BackupControllerTest.php` (agrega casos)

**Interfaces:**
- Consumes: rutas `admin.backups.index`, `admin.backups.crear`, `admin.backups.restaurar` (Task 3).

- [ ] **Step 1: Escribir los tests que fallan**

Agregar estos dos métodos a `tests/Feature/Admin/BackupControllerTest.php` (dentro de la clase existente):

```php
    public function test_pantalla_de_backups_muestra_los_dos_formularios(): void
    {
        $admin = $this->crearUsuario('Administrador');

        $response = $this->actingAs($admin)->get(route('admin.backups.index'));

        $response->assertOk();
        $response->assertSee('Descargar backup ahora');
        $response->assertSee('Descargar backup de seguridad del estado actual');
        $response->assertSee('RESTAURAR', false);
    }

    public function test_sidebar_muestra_enlace_de_backups_para_admin(): void
    {
        $admin = $this->crearUsuario('Administrador');

        $response = $this->actingAs($admin)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee(route('admin.backups.index'), false);
    }
```

- [ ] **Step 2: Correr los tests para confirmar que fallan**

Run: `php artisan test --filter=BackupControllerTest`
Expected: FAIL — `test_pantalla_de_backups_muestra_los_dos_formularios` falla porque el placeholder no tiene ese texto; `test_sidebar_muestra_enlace_de_backups_para_admin` falla porque el link no existe todavía.

- [ ] **Step 3: Reemplazar la vista placeholder**

Reemplazar el contenido completo de `resources/views/admin/backups/index.blade.php`:

```blade
@extends('layouts.app')
@section('title', 'Backups — LMS DyL')
@section('breadcrumbs'){{ Breadcrumbs::render('admin.backups.index') }}@endsection
@section('content')
<div class="max-w-3xl mx-auto"
     x-data="{ descargoSeguridad: false, confirmacion: '', archivoSeleccionado: null }">
    <h1 class="text-2xl font-bold text-gray-900 mb-2">Backups de la base de datos</h1>
    <p class="text-gray-500 text-sm mb-6">
        Backup y restauración manual de la base de datos de producción. No hay backups automáticos: esto es
        exclusivamente para vos.
    </p>

    @if(session('success'))
        <div class="alert alert-success mb-6">{{ session('success') }}</div>
    @endif
    @error('archivo')
        <div class="alert alert-error mb-6">{{ $message }}</div>
    @enderror

    {{-- Crear backup --}}
    <div class="card p-6 mb-6">
        <h2 class="text-lg font-bold text-gray-900 mb-2">Crear backup</h2>
        <p class="text-sm text-gray-600 mb-4">
            Genera un dump completo de la base de datos actual y lo descarga directo a tu computadora.
            No se guarda ninguna copia en el servidor.
        </p>
        <form method="POST" action="{{ route('admin.backups.crear') }}">
            @csrf
            <button type="submit" class="btn-primary">Descargar backup ahora</button>
        </form>
    </div>

    {{-- Restaurar backup --}}
    <div class="card p-6 border-2 border-dyl-graphite-300">
        <h2 class="text-lg font-bold text-gray-900 mb-2">Restaurar backup</h2>
        <p class="text-sm text-gray-600 mb-4">
            Sube un archivo <code>.sql</code> descargado previamente para reemplazar por completo el contenido
            actual de la base de datos. <strong>Esta acción no se puede deshacer.</strong>
        </p>

        <div class="mb-4 p-4 bg-dyl-graphite-50 rounded-xl border border-dyl-graphite-200">
            <p class="text-sm text-gray-700 mb-3">
                Antes de restaurar, descarga un backup de seguridad del estado actual — si el archivo que vas
                a subir resulta ser el equivocado, vas a necesitarlo para volver atrás.
            </p>
            <form method="POST" action="{{ route('admin.backups.crear') }}"
                  @submit="descargoSeguridad = true">
                @csrf
                <button type="submit" class="btn-outline">
                    Descargar backup de seguridad del estado actual
                </button>
            </form>
            <p x-show="descargoSeguridad" x-cloak class="text-xs text-dyl-orange-700 mt-2 font-medium">
                ✓ Backup de seguridad descargado en esta sesión.
            </p>
        </div>

        <form method="POST" action="{{ route('admin.backups.restaurar') }}" enctype="multipart/form-data">
            @csrf
            <div class="mb-3">
                <label class="form-label">Archivo de backup (.sql)</label>
                <input type="file" name="archivo" accept=".sql" required class="form-input"
                       @change="archivoSeleccionado = $event.target.files[0]?.name ?? null">
            </div>
            <div class="mb-4">
                <label class="form-label">
                    Escribe <strong>RESTAURAR</strong> para confirmar
                </label>
                <input type="text" name="confirmacion" x-model="confirmacion"
                       :disabled="!descargoSeguridad"
                       placeholder="RESTAURAR"
                       class="form-input">
                @error('confirmacion')<p class="text-dyl-graphite-900 font-semibold text-sm mt-1">{{ $message }}</p>@enderror
            </div>
            <button type="submit"
                    :disabled="!descargoSeguridad || confirmacion !== 'RESTAURAR' || !archivoSeleccionado"
                    :class="(!descargoSeguridad || confirmacion !== 'RESTAURAR' || !archivoSeleccionado) ? 'bg-gray-300 cursor-not-allowed' : 'bg-dyl-orange-600 hover:bg-dyl-orange-700 cursor-pointer'"
                    class="text-white px-6 py-2 rounded-lg font-medium transition-colors">
                Restaurar base de datos
            </button>
        </form>
    </div>
</div>
@endsection
```

- [ ] **Step 4: Agregar el breadcrumb**

En `routes/breadcrumbs.php`, agregar después del bloque de `admin.auditoria.index`:

```php
Breadcrumbs::for('admin.backups.index', fn(Trail $t) =>
    $t->parent('dashboard')->push('Backups', route('admin.backups.index'))
);
```

- [ ] **Step 5: Agregar el enlace en el sidebar**

En `resources/views/layouts/partials/sidebar.blade.php`, dentro del bloque `@if(auth()->user()->esAdmin())`, agregar después del enlace de Auditoría (antes del `@endif` que cierra ese bloque):

```blade
            <a href="{{ route('admin.backups.index') }}" class="dyl-sb-link {{ request()->routeIs('admin.backups.*') ? 'active' : '' }}">
                <span class="dyl-sb-ic" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><ellipse cx="12" cy="5.5" rx="7" ry="2.5"/><path d="M5 5.5v6c0 1.4 3.1 2.5 7 2.5s7-1.1 7-2.5v-6"/><path d="M5 11.5v6c0 1.4 3.1 2.5 7 2.5s7-1.1 7-2.5v-6"/></svg>
                </span>
                <span class="dyl-sb-label">Backups</span>
            </a>
```

- [ ] **Step 6: Correr los tests para confirmar que pasan**

Run: `php artisan test --filter=BackupControllerTest`
Expected: PASS — 10 tests, todos verdes.

- [ ] **Step 7: Correr la suite completa**

Run: `php artisan test`
Expected: PASS — sin regresiones (en particular, revisar `SidebarNavigationTest` sigue verde).

- [ ] **Step 8: Commit**

```bash
git add resources/views/admin/backups/index.blade.php routes/breadcrumbs.php resources/views/layouts/partials/sidebar.blade.php tests/Feature/Admin/BackupControllerTest.php
git commit -m "feat: pantalla de backups completa, breadcrumb y enlace en sidebar"
```

---

### Task 5: Verificación manual end-to-end (local) y cierre

Los métodos `crearDump()` y `restaurarDesdeArchivo()` de `BackupService` no tienen cobertura automatizada (requieren MySQL real, no SQLite). Esta verificación manual contra la base de datos **local** (`dyl_lms`, ya desechable — se recrea con `php artisan db:seed`) es la prueba real de que el flujo completo funciona.

**Files:** ninguno (verificación manual, sin cambios de código).

- [ ] **Step 1: Confirmar que la app local corre contra MySQL real**

Run: `php artisan tinker --execute="echo config('database.default');"`
Expected: `mysql` (no `sqlite`) — confirma que estamos apuntando a la base de datos local real, no a la de test.

- [ ] **Step 2: Loguearse como admin y descargar un backup**

En el navegador: login con `admin@dyl-quality.test` / `password123` → `/admin/backups` → clic en "Descargar backup ahora".
Expected: se descarga un archivo `backup_dyl_lms_....sql`. Abrirlo y confirmar que contiene `DROP TABLE IF EXISTS` y `INSERT INTO` para tablas conocidas (`users`, `cursos`, etc.).

- [ ] **Step 3: Hacer un cambio de prueba en la base**

Run: `php artisan tinker --execute="App\Models\Curso::first()->update(['titulo' => 'CAMBIO DE PRUEBA BACKUP']);"`
Expected: sin errores.

- [ ] **Step 4: Restaurar el backup del Step 2**

En el navegador: en la sección "Restaurar backup", clic en "Descargar backup de seguridad del estado actual" (habilita el formulario), subir el archivo del Step 2, escribir `RESTAURAR`, enviar.
Expected: mensaje de éxito "Base de datos restaurada correctamente (N sentencias ejecutadas)".

- [ ] **Step 5: Confirmar que el cambio de prueba desapareció**

Run: `php artisan tinker --execute="echo App\Models\Curso::first()->titulo;"`
Expected: el título original (NO "CAMBIO DE PRUEBA BACKUP") — confirma que la restauración efectivamente revirtió el cambio.

- [ ] **Step 6: Re-sembrar por si el backup restaurado dejó datos desactualizados**

Run: `php artisan db:seed`
Expected: cuentas y cursos demo recreados/actualizados sin error (usa `firstOrCreate`/`updateOrCreate`, es seguro correrlo de nuevo).

- [ ] **Step 7: Correr la suite completa una última vez**

Run: `php artisan test`
Expected: PASS — todos los tests verdes.

- [ ] **Step 8: Push y abrir el PR**

```bash
git push -u origin feature/backup-restauracion-bd
gh pr create --base master --head feature/backup-restauracion-bd \
  --title "feat: backup y restauración manual de la base de datos (admin)" \
  --body "Ver docs/superpowers/specs/2026-08-27-backup-restauracion-bd-design.md. Verificado manualmente end-to-end contra MySQL local (crear backup, modificar dato, restaurar, confirmar reversión)."
```
