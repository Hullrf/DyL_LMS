# Exportar / importar curso como plantilla — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Descargar un curso como ZIP (estructura + configuración + archivos) y cargarlo para crear un curso nuevo equivalente, sin datos de estudiantes.

**Architecture:** Dos servicios (`CursoExportService`, `CursoImportService`) dueños del formato; un controlador delgado (`CursoPlantillaController`) con dos rutas; botones en `cursos/edit` y `cursos/index`. El exportador serializa cada modelo con `$model->getFillable()` (menos FKs/archivos/fechas), así una columna nueva en `$fillable` viaja sola. La importación corre en una sola `DB::transaction` y limpia los archivos copiados si falla.

**Tech Stack:** Laravel 12, PHP 8.2 (`ext-zip` ya habilitada), `ZipArchive`, disco `public`, PHPUnit con sqlite en memoria.

**Spec:** `docs/superpowers/specs/2026-10-15-exportar-importar-curso-design.md`

## Global Constraints

- Todo acceso a archivos pasa por el facade `Storage::disk('public')` (así `Storage::fake('public')` funciona en tests). Nunca `storage_path()` directo.
- Importar SIEMPRE crea un curso nuevo: `estado = borrador`, `created_by` = quien importa, título + ` (importado)`; nunca sobrescribe.
- NO viajan: estudiantes, inscripciones, entregas, calificaciones, certificados, foros, anuncios, `fecha_apertura`, `fecha_cierre`.
- Categoría: se busca con `LOWER(TRIM(nombre))` (mismo criterio que `CategoriaController::store`); si no existe se crea con slug único.
- Mensajes al usuario en español.
- `cursos.titulo` es `UNIQUE` (incluye cursos con soft delete): usar `Curso::withTrashed()` al buscar un título libre.
- NO correr `migrate:fresh` contra la BD local (no existe `.env.testing`). Tests: `php artisan test` (sqlite in-memory).
- Los commits terminan con:
  ```
  Co-Authored-By: Claude Sonnet 5.5 <noreply@anthropic.com>
  Claude-Session: https://claude.ai/code/session_01RhrKPmLuMmkF4EEaGwHMkB
  ```
- El working tree tiene cambios ajenos sin commitear (`app/Services/CertificadoService.php`, `resources/views/certificados/plantilla-pdf.blade.php`, `context/`, `public/certificados/`, `public/fonts/`): NO tocarlos ni incluirlos. Usar siempre `git add <archivos explícitos>`.
- Rama de trabajo sugerida: `feature/exportar-importar-curso`, creada desde el commit que contiene la spec (`1ec8a53`). Al preparar un worktree nuevo correr `npm run build` antes de confiar en `php artisan test`.

## Review Focus

1. Imagen del editor referenciada dos veces en el HTML, o la misma en dos lecciones → se copia una sola vez y ambas referencias se reescriben (Task 2).
2. URL externa con `/storage/` en el HTML (otro host) → debe quedar intacta, no tratarse como archivo propio (Task 1).
3. Archivo referenciado en BD que ya no existe en disco → no aborta el export; queda en `advertencias` (Task 1).
4. ZIP con una entrada `../evil.txt` o ruta absoluta → rechazo total, nada se crea (Task 2).
5. Título ya importado antes (o en un curso con soft delete) → sufijo `(importado 2)`, nunca falla por `UNIQUE` (Task 2).
6. Fallo a mitad de la importación (dato inválido) → ni curso ni archivos huérfanos (Task 2).
7. El subir un archivo no-ZIP o sin `curso.json` desde la UI → mensaje de error, no un 500 (Task 3).

---

## File Structure

| Archivo | Responsabilidad |
|---|---|
| `app/Services/CursoExportService.php` (nuevo) | Construye el ZIP. Dueño del formato (`VERSION_FORMATO`, marcador `{{archivo:RUTA}}`). |
| `app/Services/CursoImportService.php` (nuevo) | Valida el ZIP y crea el curso en una transacción. |
| `app/Exceptions/CursoImportException.php` (nuevo) | Error con mensaje apto para mostrar al usuario. |
| `app/Http/Controllers/CursoPlantillaController.php` (nuevo) | `exportar(Curso)` e `importar(Request)`. |
| `routes/web.php` (modificar) | 2 rutas. |
| `resources/views/cursos/edit.blade.php`, `cursos/index.blade.php` (modificar) | Botones Exportar / Importar. |
| `tests/Concerns/ArmaCursoCompleto.php` (nuevo) | Trait de test que arma un curso con todo (compartido por 3 archivos de test). |
| `tests/Feature/CursoExportServiceTest.php`, `CursoImportServiceTest.php`, `CursoPlantillaControllerTest.php` (nuevos) | Pruebas. |

---

### Task 1: CursoExportService + fixture de tests

**Files:**
- Create: `tests/Concerns/ArmaCursoCompleto.php`
- Create: `app/Services/CursoExportService.php`
- Test: `tests/Feature/CursoExportServiceTest.php`

**Interfaces:**
- Produces:
  - `App\Services\CursoExportService::VERSION_FORMATO` (`int` = 1)
  - `App\Services\CursoExportService::MARCADOR` (`string` = `'{{archivo:%s}}'`, usar con `sprintf`)
  - `CursoExportService::exportar(Curso $curso): string` → ruta absoluta de un ZIP temporal (quien lo recibe lo borra).
  - Trait `Tests\Concerns\ArmaCursoCompleto` con `instructor(): User`, `admin(): User`, `estudiante(): User`, `cursoCompleto(User $dueno): Curso`.
- Formato de `curso.json`: `{version_formato, exportado_en, advertencias[], curso:{...atributos del curso, categoria:{nombre,color}|null, imagen_portada:"archivos/<ruta>"|null, modulos:[{...atributos, lecciones:[{...atributos, actividades:[{...atributos, preguntas:[{...atributos, imagen:"archivos/<ruta>"|null, opciones:[{...}]}], recursos:[{...atributos, archivo:"archivos/<ruta>"|null}], rubrica:[{nombre, orden, niveles:[{...}]}]}]}]}]}}`

- [ ] **Step 1: Crear el trait de fixture**

`tests/Concerns/ArmaCursoCompleto.php`:

```php
<?php

namespace Tests\Concerns;

use App\Models\Actividad;
use App\Models\Categoria;
use App\Models\CriterioRubrica;
use App\Models\Curso;
use App\Models\Leccion;
use App\Models\Modulo;
use App\Models\NivelCriterio;
use App\Models\Opcion;
use App\Models\Pregunta;
use App\Models\RecursoActividad;
use App\Models\Rol;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

trait ArmaCursoCompleto
{
    protected function usuarioConRol(string $rol): User
    {
        $r = Rol::firstOrCreate(['nombre' => $rol], ['descripcion' => $rol]);
        $u = User::factory()->create(['estado' => 'activo']);
        $u->roles()->attach($r);
        return $u;
    }

    protected function instructor(): User  { return $this->usuarioConRol('Instructor'); }
    protected function admin(): User       { return $this->usuarioConRol('Administrador'); }
    protected function estudiante(): User  { return $this->usuarioConRol('Estudiante'); }

    /** Curso con módulo, lección (con imagen del editor), actividad, pregunta, recurso y rúbrica. */
    protected function cursoCompleto(User $dueno): Curso
    {
        Storage::fake('public');
        Storage::disk('public')->put('editor/2026-10/foto.png', 'PNGDATA');
        Storage::disk('public')->put('cursos/origen/portada.jpg', 'PORTADA');
        Storage::disk('public')->put('cursos/origen/preguntas/p.png', 'PREG');
        Storage::disk('public')->put('cursos/origen/recursos/guia.pdf', 'PDFDATA');

        $categoria = Categoria::create(['nombre' => 'Calidad', 'slug' => 'calidad']);

        $curso = Curso::factory()->publicado()->diplomado()->create([
            'created_by'       => $dueno->id,
            'titulo'           => 'ISO 9001',
            'categoria_id'     => $categoria->id,
            'imagen_portada'   => 'cursos/origen/portada.jpg',
            'nota_aprobatoria' => 70,
        ]);

        $modulo  = Modulo::factory()->create(['curso_id' => $curso->id, 'orden' => 0, 'titulo' => 'Modulo 1']);
        $leccion = Leccion::factory()->create([
            'modulo_id'      => $modulo->id,
            'orden'          => 0,
            'contenido_html' => '<p>Hola</p>'
                . '<img src="' . asset('storage/editor/2026-10/foto.png') . '">'
                . '<img src="https://externo.test/storage/x.png">',
        ]);
        $actividad = Actividad::factory()->cuestionario()->create([
            'leccion_id'          => $leccion->id,
            'intentos_permitidos' => 3,
            'usa_rubrica'         => true,
            'fecha_apertura'      => now(),
            'fecha_cierre'        => now()->addDay(),
        ]);
        $pregunta = Pregunta::factory()->create([
            'actividad_id' => $actividad->id,
            'imagen_path'  => 'cursos/origen/preguntas/p.png',
            'orden'        => 0,
        ]);
        Opcion::factory()->correcta()->create(['pregunta_id' => $pregunta->id, 'orden' => 0, 'explicacion' => 'Porque si']);
        Opcion::factory()->create(['pregunta_id' => $pregunta->id, 'orden' => 1]);
        RecursoActividad::create([
            'actividad_id'            => $actividad->id,
            'tipo'                    => 'documento',
            'titulo'                  => 'Guia',
            'archivo_path'            => 'cursos/origen/recursos/guia.pdf',
            'archivo_nombre_original' => 'Guía ISO.pdf',
            'orden'                   => 0,
        ]);
        $criterio = CriterioRubrica::create(['actividad_id' => $actividad->id, 'nombre' => 'Claridad', 'orden' => 0]);
        NivelCriterio::create(['criterio_id' => $criterio->id, 'descripcion' => 'Excelente', 'puntos' => 5, 'orden' => 0]);

        return $curso;
    }
}
```

- [ ] **Step 2: Escribir los tests del exportador (fallan)**

`tests/Feature/CursoExportServiceTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Services\CursoExportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\ArmaCursoCompleto;
use Tests\TestCase;
use ZipArchive;

class CursoExportServiceTest extends TestCase
{
    use RefreshDatabase, ArmaCursoCompleto;

    /** @return array{0: array, 1: string[]} [json decodificado, nombres de entradas] */
    private function leerZip(string $ruta): array
    {
        $zip = new ZipArchive();
        $this->assertTrue($zip->open($ruta) === true);
        $json = json_decode($zip->getFromName('curso.json'), true);
        $nombres = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $nombres[] = $zip->getNameIndex($i);
        }
        $zip->close();
        return [$json, $nombres];
    }

    public function test_exporta_arbol_completo_y_archivos(): void
    {
        $curso = $this->cursoCompleto($this->instructor());

        $ruta = app(CursoExportService::class)->exportar($curso);
        [$json, $nombres] = $this->leerZip($ruta);
        @unlink($ruta);

        $this->assertSame(1, $json['version_formato']);
        $c = $json['curso'];
        $this->assertSame('ISO 9001', $c['titulo']);
        $this->assertSame('diplomado', $c['tipo_certificado']);
        $this->assertEquals(70, $c['nota_aprobatoria']);
        $this->assertSame(['nombre' => 'Calidad', 'color' => '#4F46E5'], $c['categoria']);
        $this->assertSame('archivos/cursos/origen/portada.jpg', $c['imagen_portada']);

        $leccion = $c['modulos'][0]['lecciones'][0];
        $this->assertStringContainsString('{{archivo:editor/2026-10/foto.png}}', $leccion['contenido_html']);
        $this->assertStringNotContainsString(asset('storage/editor'), $leccion['contenido_html']);
        // URL de otro host con /storage/ queda intacta
        $this->assertStringContainsString('https://externo.test/storage/x.png', $leccion['contenido_html']);

        $act = $leccion['actividades'][0];
        $this->assertSame(3, $act['intentos_permitidos']);
        $this->assertTrue($act['usa_rubrica']);
        $this->assertSame('archivos/cursos/origen/preguntas/p.png', $act['preguntas'][0]['imagen']);
        $this->assertCount(2, $act['preguntas'][0]['opciones']);
        $this->assertSame('archivos/cursos/origen/recursos/guia.pdf', $act['recursos'][0]['archivo']);
        $this->assertSame('Guía ISO.pdf', $act['recursos'][0]['archivo_nombre_original']);
        $this->assertSame('Claridad', $act['rubrica'][0]['nombre']);
        $this->assertEquals(5, $act['rubrica'][0]['niveles'][0]['puntos']);

        foreach (['archivos/editor/2026-10/foto.png', 'archivos/cursos/origen/portada.jpg',
                  'archivos/cursos/origen/preguntas/p.png', 'archivos/cursos/origen/recursos/guia.pdf'] as $e) {
            $this->assertContains($e, $nombres);
        }
    }

    public function test_no_exporta_ids_fks_ni_fechas_de_plazo(): void
    {
        $curso = $this->cursoCompleto($this->instructor());

        $ruta = app(CursoExportService::class)->exportar($curso);
        [$json] = $this->leerZip($ruta);
        @unlink($ruta);

        $texto = json_encode($json);
        foreach (['"id"', '"created_by"', '"curso_id"', '"modulo_id"', '"leccion_id"',
                  '"actividad_id"', '"pregunta_id"', '"criterio_id"', '"fecha_apertura"', '"fecha_cierre"'] as $clave) {
            $this->assertStringNotContainsString($clave . ':', $texto, "no debe exportar {$clave}");
        }
    }

    public function test_archivo_faltante_en_disco_no_aborta_y_queda_como_advertencia(): void
    {
        $curso = $this->cursoCompleto($this->instructor());
        Storage::disk('public')->delete('cursos/origen/recursos/guia.pdf');

        $ruta = app(CursoExportService::class)->exportar($curso);
        [$json, $nombres] = $this->leerZip($ruta);
        @unlink($ruta);

        $act = $json['curso']['modulos'][0]['lecciones'][0]['actividades'][0];
        $this->assertNull($act['recursos'][0]['archivo']);
        $this->assertNotEmpty($json['advertencias']);
        $this->assertStringContainsString('guia.pdf', $json['advertencias'][0]);
        $this->assertNotContains('archivos/cursos/origen/recursos/guia.pdf', $nombres);
    }
}
```

- [ ] **Step 3: Verificar que fallan**

Run: `php artisan test --filter=CursoExportServiceTest`
Expected: FAIL — `Class "App\Services\CursoExportService" not found`.

- [ ] **Step 4: Implementar el exportador**

`app/Services/CursoExportService.php`:

```php
<?php

namespace App\Services;

use App\Models\Actividad;
use App\Models\Curso;
use App\Models\Leccion;
use App\Models\Modulo;
use App\Models\Opcion;
use App\Models\Pregunta;
use App\Models\RecursoActividad;
use App\Models\CriterioRubrica;
use App\Models\NivelCriterio;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use ZipArchive;

/**
 * Exporta un curso como plantilla ZIP (curso.json + archivos/).
 * Es el único dueño del formato: la versión y el marcador de archivos viven aquí.
 */
class CursoExportService
{
    public const VERSION_FORMATO = 1;

    /** Marcador que reemplaza URLs de archivos propios dentro de HTML. */
    public const MARCADOR = '{{archivo:%s}}';

    /** Columnas que nunca viajan: llaves foráneas, rutas de archivo (van aparte) y plazos absolutos. */
    private const EXCLUIDOS = [
        'curso_id', 'modulo_id', 'leccion_id', 'actividad_id', 'pregunta_id', 'criterio_id',
        'created_by', 'categoria_id',
        'imagen_portada', 'imagen_path', 'archivo_path',
        'fecha_apertura', 'fecha_cierre',
    ];

    private ZipArchive $zip;

    /** @var array<string,string> ruta en disco => nombre de entrada en el ZIP */
    private array $archivos = [];

    /** @var string[] */
    private array $advertencias = [];

    /** @return string ruta absoluta de un ZIP temporal; quien lo recibe debe borrarlo */
    public function exportar(Curso $curso): string
    {
        $this->archivos = [];
        $this->advertencias = [];

        $curso->load([
            'categoria',
            'modulos.lecciones.actividades.preguntas.opciones',
            'modulos.lecciones.actividades.recursos',
            'modulos.lecciones.actividades.criteriosRubrica.niveles',
        ]);

        $ruta = tempnam(sys_get_temp_dir(), 'curso');
        $this->zip = new ZipArchive();
        $this->zip->open($ruta, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        $datos = $this->curso($curso);
        // Reescribe URLs de archivos propios dentro de cualquier texto (HTML de lecciones, descripciones...).
        array_walk_recursive($datos, function (&$valor) {
            if (is_string($valor)) {
                $valor = $this->reescribir($valor);
            }
        });

        $this->zip->addFromString('curso.json', json_encode([
            'version_formato' => self::VERSION_FORMATO,
            'exportado_en'    => now()->toIso8601String(),
            'advertencias'    => array_values(array_unique($this->advertencias)),
            'curso'           => $datos,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
        $this->zip->close();

        return $ruta;
    }

    private function curso(Curso $c): array
    {
        return $this->atributos($c) + [
            'categoria'      => $c->categoria ? ['nombre' => $c->categoria->nombre, 'color' => $c->categoria->color] : null,
            'imagen_portada' => $this->agregarArchivo($c->imagen_portada),
            'modulos'        => $c->modulos->map(fn (Modulo $m) => $this->atributos($m) + [
                'lecciones' => $m->lecciones->map(fn (Leccion $l) => $this->atributos($l) + [
                    'actividades' => $l->actividades->map(fn (Actividad $a) => $this->actividad($a))->all(),
                ])->all(),
            ])->all(),
        ];
    }

    private function actividad(Actividad $a): array
    {
        return $this->atributos($a) + [
            'preguntas' => $a->preguntas->map(fn (Pregunta $p) => $this->atributos($p) + [
                'imagen'   => $this->agregarArchivo($p->imagen_path),
                'opciones' => $p->opciones->map(fn (Opcion $o) => $this->atributos($o))->all(),
            ])->all(),
            'recursos'  => $a->recursos->map(fn (RecursoActividad $r) => $this->atributos($r) + [
                'archivo' => $this->agregarArchivo($r->archivo_path),
            ])->all(),
            'rubrica'   => $a->criteriosRubrica->map(fn (CriterioRubrica $c) => $this->atributos($c) + [
                'niveles' => $c->niveles->map(fn (NivelCriterio $n) => $this->atributos($n))->all(),
            ])->all(),
        ];
    }

    /** Atributos configurables del modelo: su $fillable menos FKs/archivos/plazos. */
    private function atributos(Model $modelo): array
    {
        return Arr::except($modelo->only($modelo->getFillable()), self::EXCLUIDOS);
    }

    /** Copia un archivo del disco `public` al ZIP. Devuelve el nombre de la entrada, o null si no se pudo. */
    private function agregarArchivo(?string $ruta): ?string
    {
        if (!$ruta) {
            return null;
        }
        if (isset($this->archivos[$ruta])) {
            return $this->archivos[$ruta];
        }
        $disco = Storage::disk('public');
        if (str_contains($ruta, '..') || !$disco->exists($ruta)) {
            $this->advertencias[] = "Archivo no encontrado, no se incluyó: {$ruta}";
            return null;
        }
        $entrada = 'archivos/' . $ruta;
        $this->zip->addFile($disco->path($ruta), $entrada);
        return $this->archivos[$ruta] = $entrada;
    }

    /** Cambia URLs de /storage/ de ESTA instalación por el marcador y mete el archivo al ZIP. */
    private function reescribir(string $texto): string
    {
        if (!str_contains($texto, '/storage/')) {
            return $texto;
        }
        $base = preg_quote(asset('storage') . '/', '#');

        return preg_replace_callback(
            '#(?:' . $base . '|(?<![\w:/.])/storage/)([^"\'\s)<>]+)#',
            function (array $m) {
                $ruta = urldecode($m[1]);
                return $this->agregarArchivo($ruta) ? sprintf(self::MARCADOR, $ruta) : $m[0];
            },
            $texto
        );
    }
}
```

- [ ] **Step 5: Verificar que pasan**

Run: `php artisan test --filter=CursoExportServiceTest`
Expected: PASS (3 tests). Si `test_no_exporta_ids...` falla porque aparece `"id":`, revisar que ningún modelo tenga `id` en `$fillable` (no debería).

- [ ] **Step 6: Commit**

```bash
git add tests/Concerns/ArmaCursoCompleto.php tests/Feature/CursoExportServiceTest.php app/Services/CursoExportService.php
git commit -m "feat: exportar curso como plantilla ZIP

Co-Authored-By: Claude Sonnet 5.5 <noreply@anthropic.com>
Claude-Session: https://claude.ai/code/session_01RhrKPmLuMmkF4EEaGwHMkB"
```

---

### Task 2: CursoImportService

**Files:**
- Create: `app/Exceptions/CursoImportException.php`
- Create: `app/Services/CursoImportService.php`
- Test: `tests/Feature/CursoImportServiceTest.php`

**Interfaces:**
- Consumes: `CursoExportService::VERSION_FORMATO`, `CursoExportService::MARCADOR`, `CursoExportService::exportar(Curso): string`; trait `ArmaCursoCompleto` (Task 1).
- Produces:
  - `App\Exceptions\CursoImportException extends \RuntimeException` (el mensaje se puede mostrar tal cual al usuario).
  - `CursoImportService::importar(string $rutaZip, User $usuario): Curso` → curso nuevo (borrador, dueño `$usuario`). Lanza `CursoImportException` ante cualquier fallo; en ese caso no queda curso ni archivos nuevos.

- [ ] **Step 1: Escribir los tests (fallan)**

`tests/Feature/CursoImportServiceTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Exceptions\CursoImportException;
use App\Models\Actividad;
use App\Models\Categoria;
use App\Models\Curso;
use App\Models\Leccion;
use App\Models\Modulo;
use App\Models\Opcion;
use App\Models\Pregunta;
use App\Models\RecursoActividad;
use App\Services\CursoExportService;
use App\Services\CursoImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\ArmaCursoCompleto;
use Tests\TestCase;
use ZipArchive;

class CursoImportServiceTest extends TestCase
{
    use RefreshDatabase, ArmaCursoCompleto;

    private function exportar(Curso $curso): string
    {
        return app(CursoExportService::class)->exportar($curso);
    }

    /** Arma un ZIP a mano: $json es el contenido de curso.json, $archivos [entrada => contenido]. */
    private function zipDe(array $json, array $archivos = []): string
    {
        $ruta = tempnam(sys_get_temp_dir(), 'zt');
        $zip = new ZipArchive();
        $zip->open($ruta, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('curso.json', json_encode($json));
        foreach ($archivos as $entrada => $contenido) {
            $zip->addFromString($entrada, $contenido);
        }
        $zip->close();
        return $ruta;
    }

    private function cursoMinimo(array $extra = []): array
    {
        return ['version_formato' => 1, 'curso' => array_merge([
            'titulo' => 'Mini', 'descripcion' => 'd', 'duracion_horas' => 1, 'modulos' => [],
        ], $extra)];
    }

    public function test_ciclo_completo_recrea_el_curso(): void
    {
        $origen = $this->cursoCompleto($this->instructor());
        $otro   = $this->instructor();

        $zip   = $this->exportar($origen);
        $nuevo = app(CursoImportService::class)->importar($zip, $otro);
        @unlink($zip);

        $this->assertNotSame($origen->id, $nuevo->id);
        $this->assertSame('ISO 9001 (importado)', $nuevo->titulo);
        $this->assertSame('borrador', $nuevo->estado);
        $this->assertSame($otro->id, $nuevo->created_by);
        $this->assertSame('diplomado', $nuevo->tipo_certificado);
        $this->assertEquals(70, $nuevo->nota_aprobatoria);
        $this->assertSame($origen->categoria_id, $nuevo->categoria_id);
        $this->assertSame(1, Categoria::count());

        $leccion = $nuevo->modulos()->first()->lecciones()->first();
        $this->assertTrue(preg_match('#<img src="[^"]*/storage/(cursos/[^"]+)">#', $leccion->contenido_html, $m) === 1);
        $this->assertSame('PNGDATA', Storage::disk('public')->get($m[1]));
        $this->assertStringContainsString('https://externo.test/storage/x.png', $leccion->contenido_html);
        $this->assertStringNotContainsString('{{archivo:', $leccion->contenido_html);

        $act = $leccion->actividades()->first();
        $this->assertSame(3, $act->intentos_permitidos);
        $this->assertTrue($act->usa_rubrica);
        $this->assertNull($act->fecha_apertura);
        $this->assertNull($act->fecha_cierre);

        $pregunta = $act->preguntas()->first();
        $this->assertSame('PREG', Storage::disk('public')->get($pregunta->imagen_path));
        $this->assertSame(2, $pregunta->opciones()->count());
        $this->assertSame('Porque si', $pregunta->opciones()->where('es_correcta', true)->first()->explicacion);

        $recurso = $act->recursos()->first();
        $this->assertSame('PDFDATA', Storage::disk('public')->get($recurso->archivo_path));
        $this->assertSame('Guía ISO.pdf', $recurso->archivo_nombre_original);

        $nivel = $act->criteriosRubrica()->first()->niveles()->first();
        $this->assertEquals(5, $nivel->puntos);

        $this->assertSame('PORTADA', Storage::disk('public')->get($nuevo->imagen_portada));

        // El original no se tocó
        $this->assertSame('ISO 9001', $origen->fresh()->titulo);
        $this->assertTrue(Storage::disk('public')->exists('cursos/origen/portada.jpg'));
    }

    public function test_todos_los_campos_configurables_se_copian(): void
    {
        $origen = $this->cursoCompleto($this->instructor());
        $zip    = $this->exportar($origen);
        $nuevo  = app(CursoImportService::class)->importar($zip, $this->instructor());
        @unlink($zip);

        $oL = $origen->modulos()->first()->lecciones()->first();
        $nL = $nuevo->modulos()->first()->lecciones()->first();
        $oA = $oL->actividades()->first();
        $nA = $nL->actividades()->first();
        $oP = $oA->preguntas()->first();
        $nP = $nA->preguntas()->first();

        $pares = [
            [$origen->modulos()->first(), $nuevo->modulos()->first(), ['curso_id']],
            [$oL, $nL, ['modulo_id', 'contenido_html']],
            [$oA, $nA, ['leccion_id', 'fecha_apertura', 'fecha_cierre']],
            [$oP, $nP, ['actividad_id', 'imagen_path']],
            [$oP->opciones()->first(), $nP->opciones()->first(), ['pregunta_id']],
            [$oA->recursos()->first(), $nA->recursos()->first(), ['actividad_id', 'archivo_path']],
        ];
        foreach ($pares as [$orig, $copia, $excluir]) {
            foreach ($orig->getFillable() as $col) {
                if (in_array($col, $excluir, true)) {
                    continue;
                }
                $this->assertEquals($orig->$col, $copia->$col, class_basename($orig) . ".{$col}");
            }
        }
    }

    public function test_la_misma_imagen_referenciada_dos_veces_se_copia_una_sola_vez(): void
    {
        $origen = $this->cursoCompleto($this->instructor());
        $modulo = $origen->modulos()->first();
        Leccion::factory()->create([
            'modulo_id'      => $modulo->id,
            'orden'          => 1,
            'contenido_html' => '<img src="' . asset('storage/editor/2026-10/foto.png') . '">',
        ]);
        $zip   = $this->exportar($origen);
        $antes = count(Storage::disk('public')->allFiles());
        $nuevo = app(CursoImportService::class)->importar($zip, $this->instructor());
        @unlink($zip);

        // 4 archivos distintos (portada, foto, pregunta, recurso) aunque la foto se use en 2 lecciones
        $this->assertCount($antes + 4, Storage::disk('public')->allFiles());
        $urls = $nuevo->modulos()->first()->lecciones()->get()
            ->map(fn ($l) => preg_match('#/storage/(cursos/[^"]+)"#', $l->contenido_html, $m) ? $m[1] : null);
        $this->assertSame($urls[0], $urls[1]);
    }

    public function test_importar_dos_veces_genera_titulos_distintos_y_respeta_soft_delete(): void
    {
        $origen = $this->cursoCompleto($this->instructor());
        $u      = $this->instructor();
        $zip    = $this->exportar($origen);

        $a = app(CursoImportService::class)->importar($zip, $u);
        $a->delete(); // soft delete: el UNIQUE sigue ocupado
        $b = app(CursoImportService::class)->importar($zip, $u);
        $c = app(CursoImportService::class)->importar($zip, $u);
        @unlink($zip);

        $this->assertSame('ISO 9001 (importado)', $a->titulo);
        $this->assertSame('ISO 9001 (importado 2)', $b->titulo);
        $this->assertSame('ISO 9001 (importado 3)', $c->titulo);
    }

    public function test_crea_categoria_si_no_existe(): void
    {
        $origen = $this->cursoCompleto($this->instructor());
        $zip    = $this->exportar($origen);
        Curso::query()->update(['categoria_id' => null]);
        Categoria::query()->delete();

        $nuevo = app(CursoImportService::class)->importar($zip, $this->instructor());
        @unlink($zip);

        $this->assertSame('Calidad', $nuevo->categoria->nombre);
        $this->assertSame('calidad', $nuevo->categoria->slug);
    }

    public function test_rechaza_zip_con_ruta_peligrosa(): void
    {
        $zip = $this->zipDe($this->cursoMinimo(), ['../evil.txt' => 'x']);

        try {
            app(CursoImportService::class)->importar($zip, $this->instructor());
            $this->fail('Debió rechazar el ZIP');
        } catch (CursoImportException $e) {
            $this->assertStringContainsString('rutas no permitidas', $e->getMessage());
        } finally {
            @unlink($zip);
        }
        $this->assertSame(0, Curso::count());
    }

    public function test_rechaza_archivo_que_no_es_zip(): void
    {
        $ruta = tempnam(sys_get_temp_dir(), 'tx');
        file_put_contents($ruta, 'esto no es un zip');

        $this->expectException(CursoImportException::class);
        try {
            app(CursoImportService::class)->importar($ruta, $this->instructor());
        } finally {
            @unlink($ruta);
        }
    }

    public function test_rechaza_zip_sin_curso_json(): void
    {
        $ruta = tempnam(sys_get_temp_dir(), 'zt');
        $zip = new ZipArchive();
        $zip->open($ruta, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('otro.txt', 'x');
        $zip->close();

        $this->expectException(CursoImportException::class);
        try {
            app(CursoImportService::class)->importar($ruta, $this->instructor());
        } finally {
            @unlink($ruta);
        }
    }

    public function test_rechaza_version_de_formato_no_soportada(): void
    {
        $json = $this->cursoMinimo();
        $json['version_formato'] = 99;
        $zip = $this->zipDe($json);

        try {
            app(CursoImportService::class)->importar($zip, $this->instructor());
            $this->fail('Debió rechazar la versión');
        } catch (CursoImportException $e) {
            $this->assertStringContainsString('versión', $e->getMessage());
        } finally {
            @unlink($zip);
        }
        $this->assertSame(0, Curso::count());
    }

    public function test_marcador_sin_archivo_en_el_zip_no_deja_nada(): void
    {
        Storage::fake('public');
        $json = $this->cursoMinimo([
            'imagen_portada' => 'archivos/portada.jpg',
            'modulos' => [['titulo' => 'M', 'lecciones' => [
                ['titulo' => 'L1', 'contenido_html' => '<img src="{{archivo:editor/a.png}}">', 'actividades' => []],
                ['titulo' => 'L2', 'contenido_html' => '<img src="{{archivo:editor/falta.png}}">', 'actividades' => []],
            ]]],
        ]);
        $zip = $this->zipDe($json, ['archivos/portada.jpg' => 'P', 'archivos/editor/a.png' => 'A']);

        try {
            app(CursoImportService::class)->importar($zip, $this->instructor());
            $this->fail('Debió fallar por archivo faltante');
        } catch (CursoImportException $e) {
            $this->assertStringContainsString('falta.png', $e->getMessage());
        } finally {
            @unlink($zip);
        }
        $this->assertSame(0, Curso::count());
        $this->assertSame([], Storage::disk('public')->allFiles(), 'no deben quedar archivos huérfanos');
    }

    public function test_fallo_a_mitad_hace_rollback_de_filas_y_archivos(): void
    {
        Storage::fake('public');
        $json = $this->cursoMinimo([
            'imagen_portada' => 'archivos/portada.jpg',
            'modulos' => [['titulo' => 'M', 'lecciones' => [
                ['titulo' => 'L', 'tipo' => 'valor-invalido', 'actividades' => []],
            ]]],
        ]);
        $zip = $this->zipDe($json, ['archivos/portada.jpg' => 'P']);

        try {
            app(CursoImportService::class)->importar($zip, $this->instructor());
            $this->fail('Debió fallar por dato inválido');
        } catch (CursoImportException $e) {
            $this->assertNotSame('', $e->getMessage());
        } finally {
            @unlink($zip);
        }
        $this->assertSame(0, Curso::withTrashed()->count());
        $this->assertSame(0, Modulo::count());
        $this->assertSame([], Storage::disk('public')->allFiles());
    }
}
```

Nota para el implementador: en `test_rechaza_zip_con_ruta_peligrosa`, si `ZipArchive::addFromString('../evil.txt', ...)` devuelve `false` (libzip lo normaliza), reemplazar la entrada por `'a/../../evil.txt'`; el objetivo es que el ZIP contenga un nombre con `..`.

- [ ] **Step 2: Verificar que fallan**

Run: `php artisan test --filter=CursoImportServiceTest`
Expected: FAIL — `Class "App\Services\CursoImportService" not found`.

- [ ] **Step 3: Crear la excepción**

`app/Exceptions/CursoImportException.php`:

```php
<?php

namespace App\Exceptions;

use RuntimeException;

/** Error de importación de curso con mensaje apto para mostrar al usuario. */
class CursoImportException extends RuntimeException
{
}
```

- [ ] **Step 4: Implementar el importador**

`app/Services/CursoImportService.php`:

```php
<?php

namespace App\Services;

use App\Exceptions\CursoImportException;
use App\Models\Actividad;
use App\Models\Categoria;
use App\Models\CriterioRubrica;
use App\Models\Curso;
use App\Models\Leccion;
use App\Models\Modulo;
use App\Models\NivelCriterio;
use App\Models\Opcion;
use App\Models\Pregunta;
use App\Models\RecursoActividad;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;
use ZipArchive;

/**
 * Importa una plantilla ZIP creada por CursoExportService.
 * Siempre crea un curso nuevo (borrador) dentro de una sola transacción.
 */
class CursoImportService
{
    private const MAX_ENTRADAS = 5000;
    private const MAX_BYTES    = 524288000; // 500 MB descomprimidos

    private ZipArchive $zip;
    private string $dir = '';

    /** @var array<string,string> entrada del ZIP => ruta nueva en el disco */
    private array $copiados = [];

    public function importar(string $rutaZip, User $usuario): Curso
    {
        $this->copiados = [];
        $this->zip = new ZipArchive();
        if ($this->zip->open($rutaZip, ZipArchive::RDONLY) !== true) {
            throw new CursoImportException('El archivo no es un ZIP válido.');
        }

        try {
            $this->validarEntradas();
            $datos = $this->leerJson();

            return DB::transaction(fn () => $this->crear($datos['curso'], $usuario));
        } catch (CursoImportException $e) {
            $this->limpiar();
            throw $e;
        } catch (Throwable $e) {
            $this->limpiar();
            report($e);
            throw new CursoImportException('No se pudo importar el curso: el archivo está incompleto o tiene datos inválidos.');
        } finally {
            $this->zip->close();
        }
    }

    private function validarEntradas(): void
    {
        if ($this->zip->numFiles > self::MAX_ENTRADAS) {
            throw new CursoImportException('El ZIP contiene demasiados archivos.');
        }
        $total = 0;
        for ($i = 0; $i < $this->zip->numFiles; $i++) {
            $stat   = $this->zip->statIndex($i);
            $nombre = $stat['name'];
            if (str_contains($nombre, '..') || str_contains($nombre, '\\')
                || str_starts_with($nombre, '/') || preg_match('/^[A-Za-z]:/', $nombre)) {
                throw new CursoImportException('El ZIP contiene rutas no permitidas.');
            }
            $total += $stat['size'];
        }
        if ($total > self::MAX_BYTES) {
            throw new CursoImportException('El contenido del ZIP es demasiado grande.');
        }
    }

    private function leerJson(): array
    {
        $crudo = $this->zip->getFromName('curso.json');
        if ($crudo === false) {
            throw new CursoImportException('El ZIP no contiene curso.json.');
        }
        $datos = json_decode($crudo, true);
        if (!is_array($datos) || !is_array($datos['curso'] ?? null) || !is_string($datos['curso']['titulo'] ?? null)) {
            throw new CursoImportException('curso.json es inválido.');
        }
        $version = $datos['version_formato'] ?? null;
        if (!is_int($version) || $version < 1 || $version > CursoExportService::VERSION_FORMATO) {
            throw new CursoImportException('La versión del formato del archivo no es compatible con esta instalación.');
        }
        return $datos;
    }

    private function crear(array $d, User $usuario): Curso
    {
        $titulo    = $this->tituloLibre($d['titulo']);
        $this->dir = 'cursos/' . Str::slug($titulo);

        // Resuelve marcadores {{archivo:...}} copiando cada archivo una sola vez.
        array_walk_recursive($d, function (&$valor) {
            if (is_string($valor) && str_contains($valor, '{{archivo:')) {
                $valor = $this->resolverMarcadores($valor);
            }
        });

        $curso = Curso::create($this->campos($d, Curso::class) + [
            'titulo'         => $titulo,
            'estado'         => 'borrador',
            'created_by'     => $usuario->id,
            'orden'          => (int) Curso::max('orden') + 1,
            'categoria_id'   => $this->categoria($d['categoria'] ?? null),
            'imagen_portada' => !empty($d['imagen_portada']) ? $this->copiar($d['imagen_portada']) : null,
        ]);

        foreach ((array) ($d['modulos'] ?? []) as $m) {
            $modulo = $curso->modulos()->create($this->campos($m, Modulo::class));
            foreach ((array) ($m['lecciones'] ?? []) as $l) {
                $leccion = $modulo->lecciones()->create($this->campos($l, Leccion::class));
                foreach ((array) ($l['actividades'] ?? []) as $a) {
                    $this->actividad($leccion, $a);
                }
            }
        }

        return $curso;
    }

    private function actividad(Leccion $leccion, array $a): void
    {
        $actividad = $leccion->actividades()->create($this->campos($a, Actividad::class));

        foreach ((array) ($a['preguntas'] ?? []) as $p) {
            $pregunta = $actividad->preguntas()->create($this->campos($p, Pregunta::class) + [
                'imagen_path' => !empty($p['imagen']) ? $this->copiar($p['imagen']) : null,
            ]);
            foreach ((array) ($p['opciones'] ?? []) as $o) {
                $pregunta->opciones()->create($this->campos($o, Opcion::class));
            }
        }
        foreach ((array) ($a['recursos'] ?? []) as $r) {
            $actividad->recursos()->create($this->campos($r, RecursoActividad::class) + [
                'archivo_path' => !empty($r['archivo']) ? $this->copiar($r['archivo']) : null,
            ]);
        }
        foreach ((array) ($a['rubrica'] ?? []) as $c) {
            $criterio = $actividad->criteriosRubrica()->create($this->campos($c, CriterioRubrica::class));
            foreach ((array) ($c['niveles'] ?? []) as $n) {
                $criterio->niveles()->create($this->campos($n, NivelCriterio::class));
            }
        }
    }

    /** Solo columnas fillable; nunca FKs, ids ni plazos absolutos que vengan en el JSON. */
    private function campos(array $datos, string $clase): array
    {
        /** @var Model $modelo */
        $modelo = new $clase();
        return Arr::except(Arr::only($datos, $modelo->getFillable()), [
            'curso_id', 'modulo_id', 'leccion_id', 'actividad_id', 'pregunta_id', 'criterio_id',
            'created_by', 'categoria_id', 'imagen_portada', 'imagen_path', 'archivo_path',
            'fecha_apertura', 'fecha_cierre',
        ]);
    }

    private function tituloLibre(string $titulo): string
    {
        $base   = Str::limit(trim($titulo), 230, '');
        $nuevo  = "{$base} (importado)";
        $n      = 2;
        while (Curso::withTrashed()->where('titulo', $nuevo)->exists()) {
            $nuevo = "{$base} (importado {$n})";
            $n++;
        }
        return $nuevo;
    }

    private function categoria(?array $c): ?int
    {
        $nombre = trim((string) ($c['nombre'] ?? ''));
        if ($nombre === '') {
            return null;
        }
        $existente = Categoria::whereRaw('LOWER(TRIM(nombre)) = ?', [mb_strtolower($nombre)])->first();
        if ($existente) {
            return $existente->id;
        }
        $slug = $base = Str::slug($nombre);
        $n = 2;
        while (Categoria::where('slug', $slug)->exists()) {
            $slug = "{$base}-{$n}";
            $n++;
        }
        $datos = ['nombre' => $nombre, 'slug' => $slug];
        if (isset($c['color']) && preg_match('/^#[0-9a-fA-F]{6}$/', $c['color'])) {
            $datos['color'] = $c['color'];
        }
        return Categoria::create($datos)->id;
    }

    private function resolverMarcadores(string $texto): string
    {
        return preg_replace_callback('/\{\{archivo:([^}]+)\}\}/', function (array $m) {
            $nueva = $this->copiar('archivos/' . $m[1]);
            return asset('storage/' . $nueva);
        }, $texto);
    }

    /** Copia una entrada del ZIP al disco `public` (una sola vez por entrada). Devuelve la ruta nueva. */
    private function copiar(string $entrada): string
    {
        if (isset($this->copiados[$entrada])) {
            return $this->copiados[$entrada];
        }
        if (!str_starts_with($entrada, 'archivos/')) {
            throw new CursoImportException("Referencia de archivo no válida: {$entrada}");
        }
        $flujo = $this->zip->getStream($entrada);
        if ($flujo === false) {
            throw new CursoImportException('Falta el archivo ' . basename($entrada) . ' dentro del ZIP.');
        }
        $nombre = preg_replace('/[^\w.\-]/u', '_', basename($entrada));
        $nueva  = $this->dir . '/' . (count($this->copiados) + 1) . '-' . $nombre;
        Storage::disk('public')->writeStream($nueva, $flujo);
        fclose($flujo);

        return $this->copiados[$entrada] = $nueva;
    }

    private function limpiar(): void
    {
        foreach ($this->copiados as $ruta) {
            Storage::disk('public')->delete($ruta);
        }
        $this->copiados = [];
    }
}
```

- [ ] **Step 5: Verificar que pasan**

Run: `php artisan test --filter=CursoImportServiceTest`
Expected: PASS (10 tests). Si `test_la_misma_imagen...` falla por el conteo de archivos, imprimir `allFiles()` antes/después: el criterio correcto es que la foto compartida produzca UN archivo nuevo.

- [ ] **Step 6: Ejecutar ambos archivos de test juntos**

Run: `php artisan test --filter="CursoExportServiceTest|CursoImportServiceTest"`
Expected: PASS (13 tests).

- [ ] **Step 7: Commit**

```bash
git add app/Exceptions/CursoImportException.php app/Services/CursoImportService.php tests/Feature/CursoImportServiceTest.php
git commit -m "feat: importar curso desde plantilla ZIP (siempre crea curso nuevo)

Co-Authored-By: Claude Sonnet 5.5 <noreply@anthropic.com>
Claude-Session: https://claude.ai/code/session_01RhrKPmLuMmkF4EEaGwHMkB"
```

---

### Task 3: Controlador, rutas y botones

**Files:**
- Create: `app/Http/Controllers/CursoPlantillaController.php`
- Modify: `routes/web.php` (grupo `instructor` de `cursos/crear` y grupo de `cursos/{curso}/editar`)
- Modify: `resources/views/cursos/edit.blade.php:5-8` (cabecera)
- Modify: `resources/views/cursos/index.blade.php:6-13` (cabecera)
- Test: `tests/Feature/CursoPlantillaControllerTest.php`

**Interfaces:**
- Consumes: `CursoExportService::exportar(Curso): string`, `CursoImportService::importar(string, User): Curso`, `CursoImportException`, trait `ArmaCursoCompleto`.
- Produces: rutas `cursos.exportar` (`GET /cursos/{curso}/exportar`) y `cursos.importar` (`POST /cursos/importar`).

- [ ] **Step 1: Escribir los tests (fallan)**

`tests/Feature/CursoPlantillaControllerTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\Curso;
use App\Services\CursoExportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\Concerns\ArmaCursoCompleto;
use Tests\TestCase;

class CursoPlantillaControllerTest extends TestCase
{
    use RefreshDatabase, ArmaCursoCompleto;

    private function zipSubido(Curso $curso): UploadedFile
    {
        $ruta = app(CursoExportService::class)->exportar($curso);
        return new UploadedFile($ruta, 'plantilla.zip', 'application/zip', null, true);
    }

    public function test_dueno_descarga_el_zip(): void
    {
        $dueno = $this->instructor();
        $curso = $this->cursoCompleto($dueno);

        $r = $this->actingAs($dueno)->get(route('cursos.exportar', $curso));

        $r->assertOk();
        $this->assertStringContainsString('curso-iso-9001.zip', $r->headers->get('content-disposition'));
    }

    public function test_admin_puede_exportar_cualquier_curso(): void
    {
        $curso = $this->cursoCompleto($this->instructor());
        $this->actingAs($this->admin())->get(route('cursos.exportar', $curso))->assertOk();
    }

    public function test_instructor_ajeno_y_estudiante_no_exportan(): void
    {
        $curso = $this->cursoCompleto($this->instructor());

        $this->actingAs($this->instructor())->get(route('cursos.exportar', $curso))->assertForbidden();
        $this->actingAs($this->estudiante())->get(route('cursos.exportar', $curso))->assertForbidden();
    }

    public function test_instructor_importa_y_va_a_editar_el_curso_nuevo(): void
    {
        $instructor = $this->instructor();
        $curso      = $this->cursoCompleto($instructor);

        $r = $this->actingAs($instructor)->post(route('cursos.importar'), ['archivo' => $this->zipSubido($curso)]);

        $nuevo = Curso::where('titulo', 'ISO 9001 (importado)')->firstOrFail();
        $r->assertRedirect(route('cursos.edit', $nuevo));
        $r->assertSessionHas('success');
        $this->assertSame($instructor->id, $nuevo->created_by);
    }

    public function test_estudiante_no_importa(): void
    {
        $curso = $this->cursoCompleto($this->instructor());

        $this->actingAs($this->estudiante())
            ->post(route('cursos.importar'), ['archivo' => $this->zipSubido($curso)])
            ->assertForbidden();
        $this->assertSame(1, Curso::count());
    }

    public function test_sin_archivo_vuelve_con_error(): void
    {
        $r = $this->actingAs($this->instructor())->from(route('cursos.index'))->post(route('cursos.importar'), []);

        $r->assertRedirect(route('cursos.index'));
        $r->assertSessionHas('error');
    }

    public function test_archivo_que_no_es_zip_vuelve_con_error_sin_crear_nada(): void
    {
        $falso = UploadedFile::fake()->createWithContent('basura.zip', 'esto no es un zip');

        $r = $this->actingAs($this->instructor())->from(route('cursos.index'))
            ->post(route('cursos.importar'), ['archivo' => $falso]);

        $r->assertRedirect(route('cursos.index'));
        $r->assertSessionHas('error');
        $this->assertSame(0, Curso::count());
    }

    public function test_los_botones_se_muestran_al_instructor(): void
    {
        $dueno = $this->instructor();
        $curso = $this->cursoCompleto($dueno);

        $this->actingAs($dueno)->get(route('cursos.edit', $curso))
            ->assertSee(route('cursos.exportar', $curso), false);
        $this->actingAs($dueno)->get(route('cursos.index'))
            ->assertSee(route('cursos.importar'), false);
    }
}
```

- [ ] **Step 2: Verificar que fallan**

Run: `php artisan test --filter=CursoPlantillaControllerTest`
Expected: FAIL — `Route [cursos.exportar] not defined`.

- [ ] **Step 3: Crear el controlador**

`app/Http/Controllers/CursoPlantillaController.php`:

```php
<?php

namespace App\Http\Controllers;

use App\Exceptions\CursoImportException;
use App\Models\Curso;
use App\Services\CursoExportService;
use App\Services\CursoImportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class CursoPlantillaController extends Controller
{
    public function exportar(Curso $curso, CursoExportService $servicio): BinaryFileResponse
    {
        $this->authorize('update', $curso);

        $ruta = $servicio->exportar($curso);

        return response()
            ->download($ruta, 'curso-' . Str::slug($curso->titulo) . '.zip', ['Content-Type' => 'application/zip'])
            ->deleteFileAfterSend(true);
    }

    public function importar(Request $request, CursoImportService $servicio): RedirectResponse
    {
        $this->authorize('create', Curso::class);

        $validador = Validator::make($request->all(), [
            'archivo' => 'required|file|mimes:zip|max:102400',
        ], [
            'archivo.required' => 'Selecciona el archivo ZIP del curso.',
            'archivo.mimes'    => 'El archivo debe ser un ZIP exportado desde la plataforma.',
            'archivo.max'      => 'El archivo supera el tamaño máximo de 100 MB.',
        ]);
        if ($validador->fails()) {
            return back()->with('error', $validador->errors()->first());
        }

        try {
            $curso = $servicio->importar($request->file('archivo')->getRealPath(), $request->user());
        } catch (CursoImportException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('cursos.edit', $curso)
            ->with('success', "Curso importado como borrador: {$curso->titulo}. Revísalo antes de publicarlo.");
    }
}
```

- [ ] **Step 4: Registrar las rutas**

En `routes/web.php`: importar la clase junto a los otros `use` (`use App\Http\Controllers\CursoPlantillaController;`), y

1. Dentro del primer grupo `middleware('instructor')` (el de `cursos/crear`), agregar ANTES de que exista cualquier ruta con `{curso}`:

```php
        Route::post('/cursos/importar', [CursoPlantillaController::class, 'importar'])->name('cursos.importar');
```

2. Dentro del grupo `middleware('instructor')` que tiene `cursos/{curso}/editar`, agregar:

```php
        Route::get('/cursos/{curso}/exportar', [CursoPlantillaController::class, 'exportar'])->name('cursos.exportar');
```

(`POST /cursos/importar` no choca con `{curso}` porque las rutas con parámetro son GET/PUT/DELETE; aun así queda en el bloque estático por consistencia con el comentario del archivo.)

- [ ] **Step 5: Botón Exportar en `cursos/edit.blade.php`**

Reemplazar el bloque de líneas 5-8:

```blade
<div class="flex items-center justify-between gap-3 mb-6">
    <h1 class="text-2xl font-bold text-gray-900">Editar Curso</h1>
    <a href="{{ route('cursos.show', $curso) }}" class="text-dyl-orange-600 hover:text-dyl-orange-700 text-sm">Ver curso &rarr;</a>
</div>
```

por:

```blade
<div class="flex items-center justify-between gap-3 mb-6">
    <h1 class="text-2xl font-bold text-gray-900">Editar Curso</h1>
    <div class="flex items-center gap-4">
        <a href="{{ route('cursos.exportar', $curso) }}"
           title="Descarga el curso (estructura, configuración y archivos) para respaldarlo o cargarlo en otra instalación"
           class="text-sm font-medium text-gray-700 hover:text-gray-900 border border-gray-300 rounded-lg px-3 py-1.5">
            Exportar curso
        </a>
        <a href="{{ route('cursos.show', $curso) }}" class="text-dyl-orange-600 hover:text-dyl-orange-700 text-sm">Ver curso &rarr;</a>
    </div>
</div>
```

- [ ] **Step 6: Botón Importar en `cursos/index.blade.php`**

Reemplazar el bloque de líneas 6-13 (cabecera con "+ Nuevo Curso"):

```blade
<div class="flex justify-between items-center mb-8">
    <h1 class="text-3xl font-bold text-gray-900">Cursos</h1>
    @if(auth()->user()->esInstructor() || auth()->user()->esAdmin())
        <a href="{{ route('cursos.create') }}"
           class="bg-dyl-orange-600 text-white px-5 py-2 rounded-lg hover:bg-dyl-orange-700 font-medium text-sm">
            + Nuevo Curso
        </a>
    @endif
</div>
```

por:

```blade
<div class="flex justify-between items-center mb-8">
    <h1 class="text-3xl font-bold text-gray-900">Cursos</h1>
    @if(auth()->user()->esInstructor() || auth()->user()->esAdmin())
        <div class="flex items-center gap-3">
            <form method="POST" action="{{ route('cursos.importar') }}" enctype="multipart/form-data">
                @csrf
                <label class="cursor-pointer border border-gray-300 text-gray-700 px-5 py-2 rounded-lg hover:bg-gray-50 font-medium text-sm inline-block"
                       title="Carga un curso exportado (.zip). Se crea como borrador.">
                    Importar curso
                    <input type="file" name="archivo" accept=".zip,application/zip" class="hidden"
                           onchange="if (this.files.length) this.form.submit()">
                </label>
            </form>
            <a href="{{ route('cursos.create') }}"
               class="bg-dyl-orange-600 text-white px-5 py-2 rounded-lg hover:bg-dyl-orange-700 font-medium text-sm">
                + Nuevo Curso
            </a>
        </div>
    @endif
</div>
```

- [ ] **Step 7: Verificar que pasan**

Run: `php artisan test --filter=CursoPlantillaControllerTest`
Expected: PASS (8 tests). Si `test_estudiante_no_importa` devuelve 403 pero `test_instructor_ajeno_y_estudiante_no_exportan` falla con otro código, revisar que `Rol 'Estudiante'` no tenga `esInstructor()` verdadero.

- [ ] **Step 8: Commit**

```bash
git add app/Http/Controllers/CursoPlantillaController.php routes/web.php resources/views/cursos/edit.blade.php resources/views/cursos/index.blade.php tests/Feature/CursoPlantillaControllerTest.php
git commit -m "feat: rutas y botones para exportar/importar curso

Co-Authored-By: Claude Sonnet 5.5 <noreply@anthropic.com>
Claude-Session: https://claude.ai/code/session_01RhrKPmLuMmkF4EEaGwHMkB"
```

---

### Task 4: Suite completa y verificación manual

**Files:** ninguno nuevo (solo corregir lo que rompa).

- [ ] **Step 1: Suite completa**

Run: `php artisan test`
Expected: toda la suite en verde (antes de este trabajo: 281/281 según la memoria del proyecto; ahora +21 tests). Si algún test preexistente falla, comprobar con `git stash` si ya fallaba antes de este trabajo antes de tocar nada.

- [ ] **Step 2: Verificación manual con un curso real (BD local `dyl_lms`, sin migrate:fresh)**

1. `php artisan serve` (o XAMPP), entrar como instructor, abrir un curso con imágenes en lecciones, cuestionario con imagen en una pregunta, recurso con documento y una rúbrica.
2. Editar curso → **Exportar curso** → se descarga `curso-<slug>.zip`. Abrirlo: debe tener `curso.json` y `archivos/`.
3. Cursos → **Importar curso** → elegir ese ZIP → redirige a editar el curso `"… (importado)"` en borrador.
4. Verificar a ojo: las imágenes de las lecciones se ven, el cuestionario conserva opciones correctas y explicaciones, el recurso se descarga con su nombre original, la rúbrica y los intentos están igual, y las actividades NO tienen plazos.
5. Subir un `.txt` renombrado a `.zip` → mensaje de error rojo, no se crea curso.

- [ ] **Step 3: Reportar al usuario** el resultado (tests y verificación manual) con evidencia; mencionar lo que quedó fuera de alcance (foros, anuncios, estudiantes, sobrescribir) y que el push a `origin` / deploy queda pendiente de su decisión.

---

## Self-Review

**Cobertura de la spec:**
- Formato ZIP + JSON anidado sin IDs → Task 1. Reescritura de URLs del editor → Task 1 (export) y Task 2 (import, `resolverMarcadores`).
- Archivos faltantes → advertencia → Task 1 (`test_archivo_faltante...`).
- Un solo curso nuevo, borrador, dueño, sufijo, categoría, fechas descartadas, rúbrica → Task 2.
- Transacción y limpieza → Task 2 (`test_fallo_a_mitad...`, `test_marcador_sin_archivo...`).
- Zip-slip, versión, no-ZIP, sin json → Task 2.
- Autorización (dueño/admin/ajeno/estudiante) → Task 3. UI → Task 3.
- Fuera de alcance respetado (nada de foros/anuncios/sobrescribir).

**Desviación consciente de la spec:** `fecha_apertura/fecha_cierre` se omiten ya en el EXPORT (además de ignorarse al importar); el efecto en el curso importado es el mismo y el archivo queda más limpio. El campo `orden` del curso viaja en el JSON pero la importación lo ignora (se pone al final).

**Consistencia de tipos:** `CursoExportService::exportar(Curso): string`, `CursoImportService::importar(string, User): Curso`, `CursoImportException` y las constantes `VERSION_FORMATO`/`MARCADOR` se usan igual en las 3 tareas. Claves JSON: `imagen_portada` (curso), `imagen` (pregunta), `archivo` (recurso), `rubrica`/`niveles` — iguales en exportador, importador y tests.

**Riesgo a vigilar en la ejecución:** el patrón de la URL del exportador asume que `asset('storage')` en la petición de exportación coincide con el host con que se guardaron las imágenes (si cambió el dominio, esas URLs viejas no se reconocen como propias y quedan tal cual; las relativas `/storage/...` sí). Es una limitación aceptada, no un bug.
