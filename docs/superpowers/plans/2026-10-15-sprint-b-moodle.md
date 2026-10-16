# Sprint B (Moodle UCundinamarca) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Entregar el Sprint B de la spec: ponderación de actividades con nota actual y proyección (F4), calendario mensual con eventos personales y exportación `.ics` (F2), ficha académica del curso (F6) y avisos automáticos de vencimiento (F9), evitando FE-2, FE-3, FE-4 y FE-5.

**Architecture:** Un único `NotaCursoService` calcula la nota de un estudiante en un curso (modo *ponderado* o *por puntos*) y lo reutilizan "Mis calificaciones", la tarjeta de curso y el certificado, de modo que el estudiante vea la misma nota en todas partes. `CalendarioService` arma una lista uniforme de eventos (actividades + personales) sobre `LineaDeTiempoService`; la página mensual y el `.ics` consumen esa misma lista. La ficha académica vive en tablas propias (`curso_fichas`, `curso_bibliografias`) con su propia pantalla de edición. Los avisos de vencimiento son un comando artisan idempotente programado cada hora.

**Tech Stack:** Laravel 12, PHP 8.2, Blade + Tailwind + Alpine, PHPUnit 11 con SQLite en memoria (`php artisan test`).

**Spec:** `docs/SPEC_FUNCIONES_MOODLE.md` (secciones F2, F4, F6, F9 y FE-2…FE-6). Plan anterior: `docs/superpowers/plans/2026-10-15-sprint-a-moodle.md` (ya mergeado).

## Global Constraints

- Tests: solo `php artisan test` (SQLite en memoria). **Nunca** `migrate:fresh` ni `--env=testing` contra la BD local (`dyl_lms` real).
- Migraciones aditivas (columnas/tablas nuevas, nullable donde aplique); `down()` funcional.
- Textos de interfaz en español; tablas/columnas en español como el resto del repo.
- Autorización (RNF-3): el estudiante solo ve datos propios; edición de curso solo dueño o admin (`CursoPolicy::update`), rutas de instructor dentro del grupo `middleware('instructor')`.
- Consultas sin N+1 (RNF-2); listas acotadas.
- Hora: se muestra **tal como está guardada** (igual que el resto de la app, que corre en UTC). En `.ics` se emiten horas *flotantes* (sin `Z`), coherente con eso.
- Escala: las notas se guardan en `respuestas_estudiantes.calificacion` (0–`actividades.puntaje_maximo`); el curso tiene `nota_aprobatoria` entera 0–100 (%). Todo el cálculo de nota se expresa en %.
- Escapar siempre con `{{ }}` en Blade; nunca mostrar `{{` o `}}` literales ni encabezados/etiquetas vacías cuando falte un dato (FE-2).
- Estilo: clases Tailwind del repo (`dyl-orange-600`, `dyl-graphite-*`), `alert alert-*`, patrón de vistas existente.
- Worktree nuevo: `composer install` real (no junction a `vendor/`), copiar `.env.example` a `.env` + `php artisan key:generate`, y `public/build` (copiar o `npm run build`). Ya está resuelto en el worktree actual.
- Commits pequeños en español, terminan con `Co-Authored-By: Claude Sonnet 5.5 <noreply@anthropic.com>`.

## Decisiones tomadas (la spec dejaba preguntas abiertas)

1. **Ponderación opcional con respaldo.** Si *todas* las actividades calificables del curso tienen `ponderacion` y suman 100 → modo `ponderado`. En cualquier otro caso → modo `por_puntos` (peso = `puntaje_maximo` / suma de puntajes), que es **exactamente** lo que hoy hace el certificado. Así nada cambia hasta que un instructor configure las ponderaciones. (La spec decía "reparto igual"; se descarta porque cambiaría las notas de cursos existentes.)
2. **Calendario: vencimientos/aperturas de actividades + eventos personales.** No hay eventos de curso creados por el instructor (no existe el modelo; Sprint C si se pide).
3. **Eventos personales: crear, listar y borrar.** Editar = borrar y crear de nuevo (YAGNI hasta que lo pidan).
4. **Soporte (F8):** ya configurable por `LMS_SOPORTE_*` desde el Sprint A.
5. **Nomenclatura R#-A#-S#:** ya implementada como campos (Sprint A); se usa `tituloCompleto()` también en el calendario.
6. **F9 acotado.** "Marcar todas leídas", contador en la campana y aviso al calificar **ya existen**. Solo falta el aviso de vencimiento a 24 h. "Nueva actividad publicada" se descarta: las actividades no tienen estado borrador/publicado, y avisar al crear spamearía a los inscritos mientras el instructor arma el curso.
7. **FE-3:** la ficha académica va como sección plegable al final del contenido del curso (no hay bloque "próximas fechas" por curso; la línea de tiempo global ya existe).
8. **Programación del comando (F9):** el comando queda registrado en el scheduler de Laravel, pero Railway arranca con `artisan serve` y **no corre `schedule:run`**. Para que avise en producción hace falta un cron del servicio (ver "Requiere acción de ops" al final). No se toca `railway.toml` en este plan.

## Review Focus

Condiciones que la spec implica y ningún test "obvio" cubriría; cada una tiene su test en la tarea indicada:

1. Ponderaciones: todas nulas, algunas nulas, suma ≠ 100, actividad sin nota (lectura) o eliminada (soft delete), curso sin actividades calificables (sin división por cero) → Task 1 y 2.
2. Calendario: `?mes=` basura o en forma de arreglo, evento a las 00:00 del primer día y 23:59 del último, cuadrícula que cruza el cambio de año → Task 5.
3. `.ics`: títulos con coma, punto y coma, salto de línea y tildes; líneas plegadas sin partir caracteres multibyte (≤ 75 bytes); token inválido o revocado → 404 → Task 6.
4. Ficha vacía: ni `{{` ni encabezados vacíos; "resultados específicos" con líneas en blanco; bibliografía con 0 filas → Task 7.
5. Vencimientos: ejecutar dos veces no duplica; entregada, abandonada, curso no publicado, ya vencida y fuera de las 24 h no avisan → Task 8.

---

## File Structure

| Archivo | Acción | Responsabilidad |
|---|---|---|
| `tests/Concerns/ArmaActividades.php` | crear | helpers de test: curso inscrito, actividad en curso, calificar |
| `database/migrations/2026_10_17_000001_add_ponderacion_to_actividades_table.php` | crear | `actividades.ponderacion` |
| `app/Services/NotaCursoService.php` | crear | nota del estudiante en un curso, aporte por actividad, proyección |
| `app/Models/Curso.php` | modificar | `actividadesCalificables()`, `ficha()`, `bibliografias()`, `fichaFaltantes()` |
| `app/Models/Actividad.php` | modificar | `ponderacion` en `$fillable`/`$casts` |
| `app/Http/Controllers/PonderacionController.php` | crear | edición de ponderaciones por curso |
| `resources/views/cursos/ponderaciones.blade.php` | crear | pantalla de ponderaciones con suma en vivo |
| `app/Http/Controllers/CalificacionController.php` | modificar | `misCalificaciones` pasa notas por curso |
| `resources/views/calificaciones/partials/resumen-nota-curso.blade.php` | crear | "Cómo se calcula tu nota" + proyección |
| `app/Services/ResumenCursosService.php`, `resources/views/cursos/_resumen.blade.php` | modificar | nota actual en la tarjeta |
| `app/Services/CertificadoService.php` | modificar | la nota final sale de `NotaCursoService` |
| `database/migrations/2026_10_17_000002_create_eventos_personales_table.php` | crear | eventos personales |
| `app/Models/EventoPersonal.php`, `app/Models/User.php` | crear/modificar | modelo y relación |
| `app/Services/LineaDeTiempoService.php` | modificar | `actividadesEnRango()` |
| `app/Services/CalendarioService.php` | crear | lista uniforme de eventos |
| `app/Http/Controllers/CalendarioController.php`, `resources/views/calendario/index.blade.php` | crear | página mensual, filtro y eventos personales |
| `database/migrations/2026_10_17_000003_add_calendario_token_to_users_table.php` | crear | token de suscripción |
| `app/Services/IcsService.php`, `app/Http/Controllers/CalendarioIcsController.php` | crear | generación y entrega de `.ics` |
| `database/migrations/2026_10_17_000004_create_curso_fichas_tables.php` | crear | `curso_fichas`, `curso_bibliografias` |
| `app/Models/CursoFicha.php`, `app/Models/CursoBibliografia.php` | crear | modelos de la ficha |
| `app/Http/Controllers/FichaController.php`, `resources/views/cursos/ficha.blade.php`, `resources/views/cursos/partials/ficha.blade.php` | crear | edición y visualización |
| `app/Console/Commands/NotificarVencimientos.php`, `routes/console.php` | crear/modificar | aviso de vencimiento a 24 h |
| `routes/web.php`, `routes/breadcrumbs.php`, sidebar | modificar | rutas, migas y enlace "Calendario" |

Comando de test: `php artisan test --filter=<Nombre>` (desde la raíz del worktree).

---

### Task 1: `NotaCursoService` y columna `ponderacion` (F4, núcleo)

**Files:**
- Create: `tests/Concerns/ArmaActividades.php`, `database/migrations/2026_10_17_000001_add_ponderacion_to_actividades_table.php`, `app/Services/NotaCursoService.php`
- Modify: `app/Models/Actividad.php` (`$fillable`, `$casts`), `app/Models/Curso.php` (`actividadesCalificables()`)
- Test: `tests/Feature/NotaCursoServiceTest.php`

**Interfaces:**
- Produces: `actividades.ponderacion decimal(5,2) null`; `Curso::actividadesCalificables(): Illuminate\Support\Collection` (actividades con `tieneCalificacion()`, sin eliminadas, ordenadas por módulo→lección→actividad); `NotaCursoService::para(User $user, Curso $curso): array` con claves: `filas` (Collection de objetos `{actividad, peso, nota, max, pct, aporta, estado}` donde `estado` ∈ `calificada|en_revision|pendiente`, `nota/pct/aporta` son `null` si no está calificada), `modo` (`ponderado|por_puntos`), `acumulado` (float, puntos sobre 100), `peso_calificado` (float), `peso_total` (float), `promedio_a_la_fecha` (`?int`, % sobre lo ya calificado), `aprobatoria` (int), `requerido` (`?float`: % mínimo necesario en lo pendiente; `null` si no hay pendientes; `0.0` si ya alcanzó), `alcanzable` (bool).
- Test helpers (trait `Tests\Concerns\ArmaActividades`): `cursoInscrito(User $estudiante, array $cursoAttrs = []): Curso`, `actividadEn(Curso $curso, array $attrs = []): Actividad`, `calificar(User $u, Actividad $a, float $nota): RespuestaEstudiante`.

- [ ] **Step 1: Helpers de test** `tests/Concerns/ArmaActividades.php`

```php
<?php

namespace Tests\Concerns;

use App\Models\Actividad;
use App\Models\Curso;
use App\Models\Inscripcion;
use App\Models\Leccion;
use App\Models\Modulo;
use App\Models\RespuestaEstudiante;
use App\Models\User;

trait ArmaActividades
{
    protected function cursoInscrito(User $estudiante, array $cursoAttrs = []): Curso
    {
        $curso = Curso::factory()->publicado()->create($cursoAttrs);
        Inscripcion::factory()->create(['user_id' => $estudiante->id, 'curso_id' => $curso->id]);

        return $curso;
    }

    protected function actividadEn(Curso $curso, array $attrs = []): Actividad
    {
        $modulo  = Modulo::factory()->create(['curso_id' => $curso->id]);
        $leccion = Leccion::factory()->create(['modulo_id' => $modulo->id]);

        return Actividad::factory()->tarea()->create($attrs + ['leccion_id' => $leccion->id]);
    }

    protected function calificar(User $u, Actividad $a, float $nota): RespuestaEstudiante
    {
        return RespuestaEstudiante::factory()->calificada()->create([
            'user_id' => $u->id, 'actividad_id' => $a->id, 'calificacion' => $nota,
        ]);
    }
}
```

- [ ] **Step 2: Test que falla** `tests/Feature/NotaCursoServiceTest.php`

```php
<?php

namespace Tests\Feature;

use App\Models\Actividad;
use App\Models\RespuestaEstudiante;
use App\Services\NotaCursoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ArmaActividades;
use Tests\Concerns\ArmaCursoCompleto;
use Tests\TestCase;

class NotaCursoServiceTest extends TestCase
{
    use RefreshDatabase, ArmaCursoCompleto, ArmaActividades;

    private function servicio(): NotaCursoService
    {
        return app(NotaCursoService::class);
    }

    public function test_modo_ponderado_cuando_todas_tienen_peso_y_suman_100(): void
    {
        $u = $this->estudiante();
        $curso = $this->cursoInscrito($u, ['nota_aprobatoria' => 70]);
        $a1 = $this->actividadEn($curso, ['puntaje_maximo' => 5, 'ponderacion' => 80]);
        $a2 = $this->actividadEn($curso, ['puntaje_maximo' => 5, 'ponderacion' => 20]);
        $this->calificar($u, $a1, 0);
        $this->calificar($u, $a2, 5);

        $r = $this->servicio()->para($u, $curso);

        $this->assertSame('ponderado', $r['modo']);
        $this->assertEquals(20.0, $r['acumulado']);
        $this->assertSame(20, $r['promedio_a_la_fecha']); // por puntos habria dado 50
        $this->assertEquals([80.0, 20.0], $r['filas']->pluck('peso')->all());
    }

    public function test_modo_por_puntos_si_alguna_ponderacion_falta_o_no_suma_100(): void
    {
        $u = $this->estudiante();
        $curso = $this->cursoInscrito($u);
        $a1 = $this->actividadEn($curso, ['puntaje_maximo' => 5, 'ponderacion' => 50]);
        $a2 = $this->actividadEn($curso, ['puntaje_maximo' => 15, 'ponderacion' => null]);
        $this->calificar($u, $a1, 5);
        $this->calificar($u, $a2, 0);

        $r = $this->servicio()->para($u, $curso);

        $this->assertSame('por_puntos', $r['modo']);
        $this->assertEquals([25.0, 75.0], $r['filas']->pluck('peso')->all());
        $this->assertSame(25, $r['promedio_a_la_fecha']);

        $a2->update(['ponderacion' => 40]); // suman 90
        $this->assertSame('por_puntos', $this->servicio()->para($u, $curso)['modo']);
    }

    public function test_pendientes_y_en_revision_no_cuentan_como_cero(): void
    {
        $u = $this->estudiante();
        $curso = $this->cursoInscrito($u);
        $a1 = $this->actividadEn($curso, ['puntaje_maximo' => 5]);
        $a2 = $this->actividadEn($curso, ['puntaje_maximo' => 5]);
        $a3 = $this->actividadEn($curso, ['puntaje_maximo' => 5]);
        $this->calificar($u, $a1, 5);
        RespuestaEstudiante::factory()->create(['user_id' => $u->id, 'actividad_id' => $a2->id, 'estado' => 'en_revision']);

        $r = $this->servicio()->para($u, $curso);
        $estados = $r['filas']->pluck('estado', 'actividad.id');

        $this->assertSame('calificada', $estados[$a1->id]);
        $this->assertSame('en_revision', $estados[$a2->id]);
        $this->assertSame('pendiente', $estados[$a3->id]);
        $this->assertNull($r['filas']->firstWhere('actividad.id', $a3->id)->nota);
        $this->assertSame(100, $r['promedio_a_la_fecha']);
    }

    public function test_proyeccion_de_nota_requerida(): void
    {
        $u = $this->estudiante();
        $curso = $this->cursoInscrito($u, ['nota_aprobatoria' => 70]);
        $acts = collect(range(1, 4))->map(fn() => $this->actividadEn($curso, ['puntaje_maximo' => 5, 'ponderacion' => 25]));
        $this->calificar($u, $acts[0], 5);
        $this->calificar($u, $acts[1], 3);

        $r = $this->servicio()->para($u, $curso);

        $this->assertEquals(40.0, $r['acumulado']);   // 25 + 15
        $this->assertEquals(60.0, $r['requerido']);   // faltan 30 sobre 50 de peso pendiente
        $this->assertTrue($r['alcanzable']);
    }

    public function test_proyeccion_ya_alcanzada_y_no_alcanzable(): void
    {
        $u = $this->estudiante();
        $curso = $this->cursoInscrito($u, ['nota_aprobatoria' => 50]);
        $a1 = $this->actividadEn($curso, ['puntaje_maximo' => 5, 'ponderacion' => 60]);
        $a2 = $this->actividadEn($curso, ['puntaje_maximo' => 5, 'ponderacion' => 40]);
        $this->calificar($u, $a1, 5); // aporta 60 >= 50

        $r = $this->servicio()->para($u, $curso);
        $this->assertEquals(0.0, $r['requerido']);
        $this->assertTrue($r['alcanzable']);

        // Caso no alcanzable: aprobatoria 100 y se sacó 0 en lo que pesa 60 -> faltan 100 sobre 40 de peso pendiente
        $u2 = $this->estudiante();
        $curso2 = $this->cursoInscrito($u2, ['nota_aprobatoria' => 100]);
        $b1 = $this->actividadEn($curso2, ['puntaje_maximo' => 5, 'ponderacion' => 60]);
        $this->actividadEn($curso2, ['puntaje_maximo' => 5, 'ponderacion' => 40]);
        $this->calificar($u2, $b1, 0);

        $r2 = $this->servicio()->para($u2, $curso2);
        $this->assertEquals(250.0, $r2['requerido']);
        $this->assertFalse($r2['alcanzable']);
    }

    public function test_curso_sin_actividades_calificables_no_divide_por_cero(): void
    {
        $u = $this->estudiante();
        $curso = $this->cursoInscrito($u);
        $this->actividadEn($curso, ['tipo' => 'lectura', 'puntaje_maximo' => null]);

        $r = $this->servicio()->para($u, $curso);

        $this->assertCount(0, $r['filas']);
        $this->assertNull($r['promedio_a_la_fecha']);
        $this->assertNull($r['requerido']);
    }

    public function test_actividad_eliminada_no_cuenta(): void
    {
        $u = $this->estudiante();
        $curso = $this->cursoInscrito($u);
        $a1 = $this->actividadEn($curso, ['puntaje_maximo' => 5]);
        $a2 = $this->actividadEn($curso, ['puntaje_maximo' => 5]);
        $a2->delete();

        $this->assertCount(1, $this->servicio()->para($u, $curso)['filas']);
    }
}
```

- [ ] **Step 3: Verificar que falla** — `php artisan test --filter=NotaCursoServiceTest` → FAIL (clase inexistente). Ajustar el test (no el servicio) solo si `Actividad::factory()->tarea()` o el estado `lectura` exigen otros campos.

- [ ] **Step 4: Migración**

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
            $table->decimal('ponderacion', 5, 2)->nullable()->after('puntaje_maximo');
        });
    }

    public function down(): void
    {
        Schema::table('actividades', function (Blueprint $table) {
            $table->dropColumn('ponderacion');
        });
    }
};
```

- [ ] **Step 5: Modelos.** En `Actividad::$fillable` agregar `'ponderacion'` y en `$casts` `'ponderacion' => 'decimal:2'`. En `Curso` agregar (importar `App\Models\Actividad` si no está, y `Illuminate\Support\Collection`):

```php
    /** Actividades con nota (sin las eliminadas), ordenadas por módulo, lección y actividad. */
    public function actividadesCalificables(): Collection
    {
        return Actividad::whereHas('leccion.modulo', fn($q) => $q->where('curso_id', $this->id))
            ->with('leccion.modulo')
            ->get()
            ->filter(fn($a) => $a->tieneCalificacion())
            ->sortBy(fn($a) => [$a->leccion->modulo->orden, $a->leccion->orden, $a->orden])
            ->values();
    }
```

- [ ] **Step 6: Servicio**

```php
<?php

namespace App\Services;

use App\Models\Curso;
use App\Models\RespuestaEstudiante;
use App\Models\User;
use Illuminate\Support\Collection;

class NotaCursoService
{
    public function __construct(private CalificacionService $calificaciones) {}

    public function para(User $user, Curso $curso): array
    {
        $actividades = $curso->actividadesCalificables();
        $ids         = $actividades->pluck('id');

        $respuestas = RespuestaEstudiante::where('user_id', $user->id)
            ->whereIn('actividad_id', $ids)->with('actividad')->get();

        $oficiales = $this->calificaciones
            ->respuestasOficiales($respuestas->where('estado', 'calificada'))
            ->keyBy('actividad_id');
        $conEnvio = $respuestas->pluck('actividad_id')->flip();

        $modo        = $this->esPonderado($actividades) ? 'ponderado' : 'por_puntos';
        $totalPuntos = (float) $actividades->sum(fn($a) => (float) $a->puntaje_maximo);

        $filas = $actividades->map(function ($a) use ($modo, $totalPuntos, $oficiales, $conEnvio) {
            $max  = (float) $a->puntaje_maximo;
            $peso = $modo === 'ponderado'
                ? (float) $a->ponderacion
                : ($totalPuntos > 0 ? $max / $totalPuntos * 100 : 0.0);

            $oficial = $oficiales->get($a->id);
            $nota    = $oficial ? (float) $oficial->calificacion : null;
            $pct     = $oficial && $max > 0 ? $nota / $max * 100 : ($oficial ? 0.0 : null);

            return (object) [
                'actividad' => $a,
                'peso'      => round($peso, 2),
                'nota'      => $nota,
                'max'       => $max,
                'pct'       => $pct,
                'aporta'    => $pct === null ? null : $peso * $pct / 100,
                'estado'    => $oficial ? 'calificada' : (isset($conEnvio[$a->id]) ? 'en_revision' : 'pendiente'),
            ];
        })->values();

        $calificadas   = $filas->where('estado', 'calificada');
        $acumulado     = (float) $calificadas->sum('aporta');
        $pesoCalif     = (float) $calificadas->sum('peso');
        $pesoTotal     = (float) $filas->sum('peso');
        $pesoPendiente = $pesoTotal - $pesoCalif;
        $aprobatoria   = (int) ($curso->nota_aprobatoria ?? 80);

        $requerido = null;
        if ($pesoPendiente > 0.0001) {
            $faltante  = $aprobatoria - $acumulado;
            $requerido = $faltante <= 0 ? 0.0 : round($faltante / $pesoPendiente * 100, 1);
        }

        return [
            'filas'               => $filas,
            'modo'                => $modo,
            'acumulado'           => round($acumulado, 2),
            'peso_calificado'     => round($pesoCalif, 2),
            'peso_total'          => round($pesoTotal, 2),
            'promedio_a_la_fecha' => $pesoCalif > 0 ? (int) round($acumulado / $pesoCalif * 100) : null,
            'aprobatoria'         => $aprobatoria,
            'requerido'           => $requerido,
            'alcanzable'          => $requerido === null || $requerido <= 100,
        ];
    }

    /** Todas con peso y suma 100 (tolerancia de centésimas). */
    private function esPonderado(Collection $actividades): bool
    {
        if ($actividades->isEmpty() || $actividades->contains(fn($a) => $a->ponderacion === null)) {
            return false;
        }

        return abs((float) $actividades->sum(fn($a) => (float) $a->ponderacion) - 100) < 0.01;
    }
}
```

- [ ] **Step 7: Verificar que pasa** — `php artisan test --filter=NotaCursoServiceTest` → PASS. Luego `php artisan test` completo en verde.

- [ ] **Step 8: Commit**

```bash
git add database/migrations app/Models app/Services/NotaCursoService.php tests/Concerns/ArmaActividades.php tests/Feature/NotaCursoServiceTest.php
git commit -m "feat: servicio de nota por curso con ponderacion y proyeccion (F4)"
```

---

### Task 2: Pantalla de ponderaciones del instructor (F4)

**Files:**
- Create: `app/Http/Controllers/PonderacionController.php`, `resources/views/cursos/ponderaciones.blade.php`
- Modify: `routes/web.php` (grupo `instructor`, junto a `cursos.edit`), `routes/breadcrumbs.php`, `resources/views/calificaciones/curso.blade.php` y `resources/views/cursos/edit.blade.php` (un enlace "Ponderaciones")
- Test: `tests/Feature/PonderacionesTest.php`

**Interfaces:**
- Consumes: `Curso::actividadesCalificables()` (Task 1), columna `actividades.ponderacion`.
- Produces: rutas `cursos.ponderaciones.edit` (`GET /cursos/{curso}/ponderaciones`) y `cursos.ponderaciones.update` (`PUT`), breadcrumb `cursos.ponderaciones`. El formulario envía `ponderaciones[<actividad_id>]`.

- [ ] **Step 1: Test que falla**

```php
<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ArmaActividades;
use Tests\Concerns\ArmaCursoCompleto;
use Tests\TestCase;

class PonderacionesTest extends TestCase
{
    use RefreshDatabase, ArmaCursoCompleto, ArmaActividades;

    private function escenario(): array
    {
        $inst  = $this->instructor();
        $curso = \App\Models\Curso::factory()->publicado()->create(['created_by' => $inst->id]);
        $a1 = $this->actividadEn($curso, ['puntaje_maximo' => 5]);
        $a2 = $this->actividadEn($curso, ['puntaje_maximo' => 5]);

        return [$inst, $curso, $a1, $a2];
    }

    public function test_guarda_cuando_suman_100(): void
    {
        [$inst, $curso, $a1, $a2] = $this->escenario();

        $this->actingAs($inst)->put(route('cursos.ponderaciones.update', $curso), [
            'ponderaciones' => [$a1->id => '60', $a2->id => '40'],
        ])->assertRedirect(route('cursos.ponderaciones.edit', $curso))->assertSessionHas('success');

        $this->assertEquals(60, $a1->fresh()->ponderacion);
        $this->assertEquals(40, $a2->fresh()->ponderacion);
    }

    public function test_rechaza_suma_distinta_de_100_o_pesos_incompletos(): void
    {
        [$inst, $curso, $a1, $a2] = $this->escenario();

        $this->actingAs($inst)->put(route('cursos.ponderaciones.update', $curso), [
            'ponderaciones' => [$a1->id => '50', $a2->id => '40'],
        ])->assertSessionHasErrors('ponderaciones');

        $this->actingAs($inst)->put(route('cursos.ponderaciones.update', $curso), [
            'ponderaciones' => [$a1->id => '100', $a2->id => ''],
        ])->assertSessionHasErrors('ponderaciones');

        $this->assertNull($a1->fresh()->ponderacion);
    }

    public function test_todo_vacio_limpia_las_ponderaciones(): void
    {
        [$inst, $curso, $a1, $a2] = $this->escenario();
        $a1->update(['ponderacion' => 70]);
        $a2->update(['ponderacion' => 30]);

        $this->actingAs($inst)->put(route('cursos.ponderaciones.update', $curso), [
            'ponderaciones' => [$a1->id => '', $a2->id => ''],
        ])->assertSessionHasNoErrors();

        $this->assertNull($a1->fresh()->ponderacion);
        $this->assertNull($a2->fresh()->ponderacion);
    }

    public function test_valores_fuera_de_rango_o_basura_se_rechazan_sin_500(): void
    {
        [$inst, $curso, $a1, $a2] = $this->escenario();

        $this->actingAs($inst)->put(route('cursos.ponderaciones.update', $curso), [
            'ponderaciones' => [$a1->id => '-5', $a2->id => 'abc'],
        ])->assertSessionHasErrors();

        $this->actingAs($inst)->put(route('cursos.ponderaciones.update', $curso), [
            'ponderaciones' => 'no-es-arreglo',
        ])->assertSessionHasErrors('ponderaciones');
    }

    public function test_otro_instructor_y_estudiante_no_pueden(): void
    {
        [$inst, $curso] = $this->escenario();

        $this->actingAs($this->instructor())->get(route('cursos.ponderaciones.edit', $curso))->assertForbidden();
        $this->actingAs($this->estudiante())->get(route('cursos.ponderaciones.edit', $curso))->assertForbidden();
    }

    public function test_la_pantalla_muestra_actividades_con_nota_y_ocultan_las_demas(): void
    {
        [$inst, $curso] = $this->escenario();
        $this->actividadEn($curso, ['tipo' => 'lectura', 'titulo' => 'Lectura sin nota', 'puntaje_maximo' => null]);

        $this->actingAs($inst)->get(route('cursos.ponderaciones.edit', $curso))
            ->assertOk()->assertSee('Ponderaciones')->assertDontSee('Lectura sin nota');
    }
}
```

- [ ] **Step 2: Verificar que falla** — `php artisan test --filter=PonderacionesTest` → FAIL (ruta no definida). Si `assertForbidden()` para el estudiante resulta ser otra respuesta (el middleware `instructor` puede redirigir), ajustar la aserción al comportamiento real documentado en `tests/Feature/AccesControlTest.php`.

- [ ] **Step 3: Controlador**

```php
<?php

namespace App\Http\Controllers;

use App\Models\Curso;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PonderacionController extends Controller
{
    public function edit(Curso $curso)
    {
        $this->authorize('update', $curso);
        $actividades = $curso->actividadesCalificables();

        return view('cursos.ponderaciones', compact('curso', 'actividades'));
    }

    public function update(Request $request, Curso $curso)
    {
        $this->authorize('update', $curso);
        $actividades = $curso->actividadesCalificables();

        $request->validate([
            'ponderaciones'   => 'required|array',
            'ponderaciones.*' => 'nullable|numeric|min:0|max:100',
        ]);

        $valores = $actividades->mapWithKeys(function ($a) use ($request) {
            $v = $request->input("ponderaciones.{$a->id}");
            return [$a->id => ($v === null || $v === '') ? null : round((float) $v, 2)];
        });

        $conValor = $valores->filter(fn($v) => $v !== null);

        if ($conValor->isNotEmpty()) {
            if ($conValor->count() !== $valores->count()) {
                return back()->withInput()->withErrors([
                    'ponderaciones' => 'Asigna un peso a todas las actividades con nota, o deja todas vacías para repartir por puntaje.',
                ]);
            }
            if (abs($conValor->sum() - 100) >= 0.01) {
                return back()->withInput()->withErrors([
                    'ponderaciones' => 'Las ponderaciones deben sumar 100 % (hoy suman ' . rtrim(rtrim(number_format($conValor->sum(), 2), '0'), '.') . ' %).',
                ]);
            }
        }

        DB::transaction(function () use ($actividades, $valores) {
            foreach ($actividades as $a) {
                $a->update(['ponderacion' => $valores[$a->id]]);
            }
        });

        return redirect()->route('cursos.ponderaciones.edit', $curso)->with('success', 'Ponderaciones guardadas.');
    }
}
```

- [ ] **Step 4: Rutas y migas.** En `routes/web.php`, dentro del grupo `middleware('instructor')` que contiene `cursos.edit`:

```php
        Route::get('/cursos/{curso}/ponderaciones', [\App\Http\Controllers\PonderacionController::class, 'edit'])->name('cursos.ponderaciones.edit');
        Route::put('/cursos/{curso}/ponderaciones', [\App\Http\Controllers\PonderacionController::class, 'update'])->name('cursos.ponderaciones.update');
```

En `routes/breadcrumbs.php` (después de `cursos.edit`):

```php
Breadcrumbs::for('cursos.ponderaciones', fn(Trail $t, $curso) =>
    $t->parent('cursos.show', $curso)->push('Ponderaciones')
);
```

- [ ] **Step 5: Vista** `resources/views/cursos/ponderaciones.blade.php`

```blade
@extends('layouts.app')
@section('title', 'Ponderaciones - ' . $curso->titulo)
@section('breadcrumbs'){{ Breadcrumbs::render('cursos.ponderaciones', $curso) }}@endsection
@section('content')
<h1 class="text-3xl font-bold text-gray-900 mb-2">Ponderaciones</h1>
<p class="text-sm text-gray-600 mb-6 max-w-2xl">
    Define cuánto pesa cada actividad con nota en la nota final de «{{ $curso->titulo }}».
    Deben sumar 100 %. Si las dejas todas vacías, el peso se reparte según el puntaje máximo de cada actividad.
    Cambiar las ponderaciones recalcula la nota de todos los estudiantes.
</p>

@if($actividades->isEmpty())
    <div class="bg-white rounded-xl shadow p-8 text-center text-gray-500">Este curso aún no tiene actividades con nota.</div>
@else
<form method="POST" action="{{ route('cursos.ponderaciones.update', $curso) }}"
      x-data="{
          vals: {{ Js::from($actividades->mapWithKeys(fn($a) => [$a->id => (string) old('ponderaciones.' . $a->id, $a->ponderacion !== null ? rtrim(rtrim(number_format($a->ponderacion, 2, '.', ''), '0'), '.') : '')])) }},
          get suma() { return Object.values(this.vals).reduce((s, v) => s + (parseFloat(v) || 0), 0); },
          limpiar() { Object.keys(this.vals).forEach(k => this.vals[k] = ''); }
      }">
    @csrf @method('PUT')
    @error('ponderaciones')<div class="alert alert-error mb-4" role="alert">{{ $message }}</div>@enderror

    <div class="bg-white rounded-xl shadow overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actividad</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Tipo</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Puntaje máx.</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Peso (%)</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @foreach($actividades as $a)
                <tr>
                    <td class="px-6 py-3 text-sm text-gray-900">{{ $a->tituloCompleto() }}</td>
                    <td class="px-6 py-3 text-sm text-gray-500">{{ ucfirst($a->tipo) }}</td>
                    <td class="px-6 py-3 text-sm text-right text-gray-700">{{ number_format($a->puntaje_maximo, 2) }}</td>
                    <td class="px-6 py-3 text-right">
                        <input type="number" step="0.01" min="0" max="100" name="ponderaciones[{{ $a->id }}]"
                               x-model="vals[{{ $a->id }}]" aria-label="Peso de {{ $a->titulo }}"
                               class="w-24 rounded-lg border-gray-300 text-right text-sm">
                    </td>
                </tr>
                @endforeach
            </tbody>
            <tfoot class="bg-gray-50">
                <tr>
                    <td colspan="3" class="px-6 py-3 text-sm font-semibold text-gray-700 text-right">Suma</td>
                    <td class="px-6 py-3 text-right text-sm font-bold"
                        :class="Math.abs(suma - 100) < 0.01 || suma === 0 ? 'text-dyl-orange-700' : 'text-red-600'"
                        x-text="suma.toFixed(2) + ' %'"></td>
                </tr>
            </tfoot>
        </table>
    </div>

    <div class="mt-4 flex gap-3">
        <button type="submit" class="rounded-lg bg-dyl-orange-600 px-5 py-2 text-sm font-medium text-white hover:bg-dyl-orange-700">Guardar</button>
        <button type="button" @click="limpiar()" class="rounded-lg border border-gray-300 px-5 py-2 text-sm text-gray-700 hover:bg-gray-50">Repartir por puntaje</button>
        <a href="{{ route('calificaciones.curso', $curso) }}" class="px-3 py-2 text-sm text-gray-500 hover:text-gray-700">Volver a calificaciones</a>
    </div>
</form>
@endif
@endsection
```

- [ ] **Step 6: Enlaces.** En `calificaciones/curso.blade.php` (junto al encabezado de la página) y en `cursos/edit.blade.php` (junto a los demás accesos del curso) añadir un enlace con el mismo estilo de los botones secundarios vecinos: `<a href="{{ route('cursos.ponderaciones.edit', $curso) }}" ...>Ponderaciones</a>`. Abrir cada archivo y ubicarlo al lado de un enlace/botón existente; no reorganizar el resto.

- [ ] **Step 7: Verificar que pasa** — `php artisan test --filter=PonderacionesTest` → PASS; `php artisan test --filter=Calificaciones` y `--filter=Curso` en verde.

- [ ] **Step 8: Commit**

```bash
git add app routes resources tests
git commit -m "feat: pantalla de ponderaciones por curso para el instructor (F4)"
```

---

### Task 3: "Cómo se calcula tu nota" con proyección en Mis calificaciones (F4)

**Files:**
- Create: `resources/views/calificaciones/partials/resumen-nota-curso.blade.php`
- Modify: `app/Http/Controllers/CalificacionController.php` (`misCalificaciones`, ~línea 421), `resources/views/calificaciones/mis-calificaciones.blade.php` (dentro de cada curso, justo antes de la tabla de respuestas)
- Test: `tests/Feature/MisCalificacionesPonderadasTest.php`

**Interfaces:**
- Consumes: `NotaCursoService::para(User, Curso): array` (Task 1).
- Produces: variable de vista `$notasPorCurso` (array `curso_id => resultado del servicio`).

- [ ] **Step 1: Test que falla**

```php
<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ArmaActividades;
use Tests\Concerns\ArmaCursoCompleto;
use Tests\TestCase;

class MisCalificacionesPonderadasTest extends TestCase
{
    use RefreshDatabase, ArmaCursoCompleto, ArmaActividades;

    public function test_muestra_peso_aporte_total_parcial_y_proyeccion(): void
    {
        $u = $this->estudiante();
        $curso = $this->cursoInscrito($u, ['nota_aprobatoria' => 70]);
        $acts = collect(range(1, 4))->map(fn($i) => $this->actividadEn($curso, ['titulo' => "Act $i", 'puntaje_maximo' => 5, 'ponderacion' => 25]));
        $this->calificar($u, $acts[0], 5);
        $this->calificar($u, $acts[1], 3);

        $this->actingAs($u)->get(route('calificaciones.mis'))->assertOk()
            ->assertSee('Cómo se calcula tu nota')
            ->assertSee('25')                                   // peso
            ->assertSee('parcial')
            ->assertSee('Necesitas al menos 60%');
    }

    public function test_actividades_pendientes_se_muestran_con_guion_no_con_cero(): void
    {
        $u = $this->estudiante();
        $curso = $this->cursoInscrito($u, ['nota_aprobatoria' => 70]);
        $a1 = $this->actividadEn($curso, ['titulo' => 'Hecha', 'puntaje_maximo' => 5]);
        $this->actividadEn($curso, ['titulo' => 'Pendiente X', 'puntaje_maximo' => 5]);
        $this->calificar($u, $a1, 5);

        $html = $this->actingAs($u)->get(route('calificaciones.mis'))->assertOk()->assertSee('Pendiente X')->getContent();

        $this->assertMatchesRegularExpression('/Pendiente X.*?—/s', $html);
    }

    public function test_mensaje_cuando_ya_alcanzo_y_cuando_no_es_alcanzable(): void
    {
        $u = $this->estudiante();
        $curso = $this->cursoInscrito($u, ['nota_aprobatoria' => 50]);
        $a1 = $this->actividadEn($curso, ['puntaje_maximo' => 5, 'ponderacion' => 60]);
        $this->actividadEn($curso, ['puntaje_maximo' => 5, 'ponderacion' => 40]);
        $this->calificar($u, $a1, 5);
        $this->actingAs($u)->get(route('calificaciones.mis'))->assertSee('Ya alcanzaste la nota mínima');

        $u2 = $this->estudiante();
        $curso2 = $this->cursoInscrito($u2, ['nota_aprobatoria' => 100]);
        $b1 = $this->actividadEn($curso2, ['puntaje_maximo' => 5, 'ponderacion' => 60]);
        $this->actividadEn($curso2, ['puntaje_maximo' => 5, 'ponderacion' => 40]);
        $this->calificar($u2, $b1, 0);
        $this->actingAs($u2)->get(route('calificaciones.mis'))->assertSee('ya no alcanzas la nota mínima');
    }

    public function test_curso_sin_actividades_con_nota_no_rompe_la_pagina(): void
    {
        $u = $this->estudiante();
        $curso = $this->cursoInscrito($u);
        $a = $this->actividadEn($curso, ['tipo' => 'ensayo', 'puntaje_maximo' => 5]);
        $a->update(['tipo' => 'lectura', 'puntaje_maximo' => null]);
        \App\Models\RespuestaEstudiante::factory()->create(['user_id' => $u->id, 'actividad_id' => $a->id, 'estado' => 'calificada', 'calificacion' => 0]);

        $this->actingAs($u)->get(route('calificaciones.mis'))->assertOk();
    }
}
```

- [ ] **Step 2: Verificar que falla** — `php artisan test --filter=MisCalificacionesPonderadasTest` → FAIL.

- [ ] **Step 3: Controlador.** En `misCalificaciones()`, antes del `return view(...)`:

```php
        $notasPorCurso = $porCurso->mapWithKeys(fn($g) => [
            $g->curso->id => app(\App\Services\NotaCursoService::class)->para(Auth::user(), $g->curso),
        ])->all();

        return view('calificaciones.mis-calificaciones', compact('respuestas', 'porCurso', 'oficialesIds', 'notasPorCurso'));
```

- [ ] **Step 4: Parcial** `calificaciones/partials/resumen-nota-curso.blade.php` (recibe `$nota`, el resultado del servicio):

```blade
@if($nota['filas']->isNotEmpty())
<details class="mb-4 rounded-lg border border-gray-200 bg-white">
    <summary class="cursor-pointer px-4 py-3 text-sm font-medium text-dyl-orange-700">Cómo se calcula tu nota</summary>
    <div class="px-4 pb-4">
        <p class="text-xs text-gray-500 mb-3">
            @if($nota['modo'] === 'ponderado')
                Cada actividad pesa lo que definió tu instructor.
            @else
                Cada actividad pesa según su puntaje máximo.
            @endif
        </p>
        <table class="min-w-full text-sm">
            <thead>
                <tr class="text-left text-xs uppercase text-gray-500">
                    <th class="py-1 pr-4">Actividad</th>
                    <th class="py-1 pr-4 text-right">Peso</th>
                    <th class="py-1 pr-4 text-right">Nota</th>
                    <th class="py-1 text-right">Aporta</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @foreach($nota['filas'] as $f)
                <tr>
                    <td class="py-1.5 pr-4 text-gray-900">{{ $f->actividad->tituloCompleto() }}</td>
                    <td class="py-1.5 pr-4 text-right text-gray-700">{{ rtrim(rtrim(number_format($f->peso, 2), '0'), '.') }}%</td>
                    <td class="py-1.5 pr-4 text-right text-gray-700">
                        @if($f->estado === 'calificada') {{ number_format($f->nota, 2) }} / {{ number_format($f->max, 2) }}
                        @elseif($f->estado === 'en_revision') En revisión
                        @else — @endif
                    </td>
                    <td class="py-1.5 text-right font-medium text-gray-900">
                        {{ $f->aporta === null ? '—' : number_format($f->aporta, 1) . '%' }}
                    </td>
                </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr class="border-t border-gray-200 font-semibold">
                    <td class="py-2 pr-4" colspan="3">
                        Total del curso
                        @if($nota['peso_calificado'] < $nota['peso_total'] - 0.01)
                            <span class="font-normal text-xs text-gray-500">(parcial: faltan actividades por calificar)</span>
                        @endif
                    </td>
                    <td class="py-2 text-right">{{ number_format($nota['acumulado'], 1) }} / 100</td>
                </tr>
            </tfoot>
        </table>

        @if($nota['requerido'] !== null)
            <p class="mt-3 rounded-lg px-3 py-2 text-sm {{ $nota['alcanzable'] ? 'bg-dyl-orange-50 text-dyl-orange-800' : 'bg-dyl-graphite-100 text-dyl-graphite-700' }}">
                @if($nota['requerido'] <= 0)
                    Ya alcanzaste la nota mínima ({{ $nota['aprobatoria'] }}%).
                @elseif($nota['alcanzable'])
                    Necesitas al menos {{ rtrim(rtrim(number_format($nota['requerido'], 1), '0'), '.') }}% en las actividades pendientes para llegar a la nota mínima ({{ $nota['aprobatoria'] }}%).
                @else
                    Con las actividades pendientes ya no alcanzas la nota mínima ({{ $nota['aprobatoria'] }}%). Habla con tu instructor.
                @endif
            </p>
        @endif
    </div>
</details>
@endif
```

- [ ] **Step 5: Incluirlo.** En `mis-calificaciones.blade.php`, dentro del bucle de cursos, justo antes de `<div class="bg-white rounded-lg shadow overflow-hidden">` de la tabla de respuestas:

```blade
    @isset($notasPorCurso[$grupo->curso->id])
        @include('calificaciones.partials.resumen-nota-curso', ['nota' => $notasPorCurso[$grupo->curso->id]])
    @endisset
```

(Abrir el archivo para confirmar el nombre de la variable del bucle; en el fragmento revisado es `$grupo`.)

- [ ] **Step 6: Verificar que pasa** — `php artisan test --filter=MisCalificacionesPonderadasTest` y `--filter=Calificaciones` → PASS.

- [ ] **Step 7: Commit**

```bash
git add app resources tests
git commit -m "feat: desglose de nota ponderada y proyeccion en mis calificaciones (F4)"
```

---

### Task 4: Nota actual en la tarjeta y certificado con la misma nota (F4, FE-5)

**Files:**
- Modify: `app/Services/ResumenCursosService.php`, `resources/views/cursos/_resumen.blade.php`, `app/Services/CertificadoService.php` (`calcularCalificacionFinal`, ~línea 172)
- Test: ampliar `tests/Feature/CursosTarjetaResumenTest.php`; crear `tests/Feature/CertificadoNotaPonderadaTest.php`

**Interfaces:**
- Consumes: `NotaCursoService::para()` (Task 1), claves `promedio_a_la_fecha`.
- Produces: `ResumenCursosService::para()` agrega la clave `nota` (`?int`) a cada curso.

- [ ] **Step 1: Tests que fallan.** En `CursosTarjetaResumenTest` agregar (usar el trait `ArmaActividades` y `use` correspondiente):

```php
    public function test_tarjeta_muestra_nota_actual_o_nada(): void
    {
        $user  = $this->estudiante();
        $curso = $this->cursoInscrito($user);
        $a1 = $this->actividadEn($curso, ['puntaje_maximo' => 5]);
        $this->actividadEn($curso, ['puntaje_maximo' => 5]);

        $this->actingAs($user)->get(route('cursos.index'))->assertOk()->assertDontSee('Nota actual');

        $this->calificar($user, $a1, 4);
        $r = app(ResumenCursosService::class)->para($user, collect([$curso]));
        $this->assertSame(80, $r[$curso->id]['nota']);

        $this->actingAs($user)->get(route('cursos.index'))->assertSee('Nota actual: 80%');
    }
```

`tests/Feature/CertificadoNotaPonderadaTest.php`: copiar el armado de `tests/Feature/CertificadoServiceAprobacionTest.php` (abrirlo y reutilizar su helper para aprobar/generar un certificado) y comprobar que con ponderaciones 80/20, notas 0 y 5 (ambas sobre 5) el `calificacion_final` guardado es `20`, mientras que sin ponderaciones (null) sería `50`:

```php
    public function test_calificacion_final_usa_ponderaciones(): void
    {
        // armado igual que CertificadoServiceAprobacionTest: curso con inscripcion completada,
        // actividades a1 (ponderacion 80) y a2 (ponderacion 20), puntaje_maximo 5 ambas,
        // calificar($u, $a1, 0); calificar($u, $a2, 5);
        // $certificado = app(CertificadoService::class)->generarSiCorresponde(<mismos argumentos que ese test>);
        $this->assertSame(20, (int) $certificado->calificacion_final);
    }

    public function test_calificacion_final_sin_ponderaciones_no_cambia(): void
    {
        // mismo armado con ponderacion null en ambas: 0/5 y 5/5 -> 50
        $this->assertSame(50, (int) $certificado->calificacion_final);
    }
```

(El implementador debe completar el armado leyendo `CertificadoServiceAprobacionTest`; no se debe cambiar ninguna aserción de los tests de certificado existentes.)

- [ ] **Step 2: Verificar que fallan** — `php artisan test --filter=CursosTarjetaResumenTest` y `--filter=CertificadoNotaPonderadaTest`.

- [ ] **Step 3: `ResumenCursosService`.** Inyectar `NotaCursoService` en el constructor junto a `LineaDeTiempoService`, y en el `mapWithKeys` devolver además la nota:

```php
    public function __construct(private LineaDeTiempoService $lineaDeTiempo, private NotaCursoService $notas) {}
    // ...
            return [$curso->id => [
                'porcentaje' => $total > 0 ? (int) round((($completadas[$curso->id] ?? 0) / $total) * 100) : null,
                'proxima'    => $proximas[$curso->id] ?? null,
                'nota'       => $this->notas->para($user, $curso)['promedio_a_la_fecha'],
            ]];
```

(El `use ($user)` debe agregarse al closure de `mapWithKeys`.)

- [ ] **Step 4: Parcial.** En `cursos/_resumen.blade.php`, tras la línea de "Próxima entrega":

```blade
@if(($res['nota'] ?? null) !== null)
    <p class="text-xs text-gray-600 mb-3">Nota actual: <span class="font-medium">{{ $res['nota'] }}%</span></p>
@endif
```

- [ ] **Step 5: Certificado.** Reemplazar el cuerpo de `CertificadoService::calcularCalificacionFinal` por:

```php
    private function calcularCalificacionFinal(User $usuario, Curso $curso): int
    {
        // Misma nota que ve el estudiante; sin nada calificado se aprueba por completar lecciones.
        return app(NotaCursoService::class)->para($usuario, $curso)['promedio_a_la_fecha'] ?? 100;
    }
```

Quitar la propiedad/`use` de `CalificacionService` del constructor **solo si** quedan sin uso en el archivo (verificar con búsqueda); si otro código del servicio la usa, dejarla.

- [ ] **Step 6: Verificar que pasa** — `php artisan test --filter=CursosTarjetaResumenTest`, `--filter=Certificado` y luego `php artisan test` completo. Cualquier test de certificado preexistente que falle indica que el modo `por_puntos` no replica el cálculo anterior: corregir el servicio, no el test.

- [ ] **Step 7: Commit**

```bash
git add app resources tests
git commit -m "feat: nota actual en la tarjeta y certificado con la misma nota ponderada (F4)"
```

---

### Task 5: Calendario mensual con eventos personales (F2)

**Files:**
- Create: `database/migrations/2026_10_17_000002_create_eventos_personales_table.php`, `app/Models/EventoPersonal.php`, `app/Services/CalendarioService.php`, `app/Http/Controllers/CalendarioController.php`, `resources/views/calendario/index.blade.php`
- Modify: `app/Models/User.php` (`eventosPersonales()`), `app/Services/LineaDeTiempoService.php` (`actividadesEnRango`), `routes/web.php` (grupo `auth`), `routes/breadcrumbs.php`, `resources/views/layouts/partials/sidebar.blade.php`
- Test: `tests/Feature/CalendarioTest.php`

**Interfaces:**
- Produces:
  - `LineaDeTiempoService::actividadesEnRango(User $user, Carbon $desde, Carbon $hasta, ?int $cursoId = null): Collection` (actividades visibles con `fecha_apertura` o `fecha_cierre` dentro del rango; requiere `fecha_cierre` no nula, igual que el resto del servicio).
  - `CalendarioService::eventos(User $user, Carbon $desde, Carbon $hasta, ?int $cursoId = null): Collection` de objetos `{uid: string, id: ?int, tipo: 'apertura'|'cierre'|'personal', inicia: Carbon, fin: Carbon, titulo: string, detalle: ?string, url: ?string}` ordenada por `inicia`. Con `$cursoId` no se incluyen eventos personales.
  - Rutas: `calendario.index` (`GET /calendario?mes=YYYY-MM&curso=ID`), `calendario.eventos.store` (`POST /calendario/eventos`), `calendario.eventos.destroy` (`DELETE /calendario/eventos/{evento}`).
  - Tabla `eventos_personales` (`id`, `user_id` fk cascade, `titulo` string 255, `inicia_en` datetime, `termina_en` datetime null, `descripcion` text null, timestamps).

- [ ] **Step 1: Test que falla**

```php
<?php

namespace Tests\Feature;

use App\Models\EventoPersonal;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ArmaActividades;
use Tests\Concerns\ArmaCursoCompleto;
use Tests\TestCase;

class CalendarioTest extends TestCase
{
    use RefreshDatabase, ArmaCursoCompleto, ArmaActividades;

    public function test_muestra_eventos_del_mes_y_oculta_los_de_otro_mes(): void
    {
        $u = $this->estudiante();
        $curso = $this->cursoInscrito($u);
        $this->actividadEn($curso, ['titulo' => 'De octubre', 'fecha_cierre' => '2026-10-16 23:59:00']);
        $this->actividadEn($curso, ['titulo' => 'De noviembre', 'fecha_cierre' => '2026-11-20 10:00:00']);

        $this->actingAs($u)->get(route('calendario.index', ['mes' => '2026-10']))->assertOk()
            ->assertSee('Vence: De octubre')->assertDontSee('De noviembre')->assertSee('octubre');
    }

    public function test_dia_con_cinco_eventos_los_muestra_todos(): void
    {
        $u = $this->estudiante();
        $curso = $this->cursoInscrito($u);
        foreach (range(1, 5) as $i) {
            $this->actividadEn($curso, ['titulo' => "Entrega $i", 'fecha_cierre' => "2026-10-16 1{$i}:00:00"]);
        }

        $html = $this->actingAs($u)->get(route('calendario.index', ['mes' => '2026-10']))->assertOk()->getContent();

        foreach (range(1, 5) as $i) {
            $this->assertStringContainsString("Vence: Entrega $i", $html);
        }
    }

    public function test_limites_del_mes_y_cambio_de_anio(): void
    {
        $u = $this->estudiante();
        $curso = $this->cursoInscrito($u);
        $this->actividadEn($curso, ['titulo' => 'Primero', 'fecha_cierre' => '2026-12-01 00:00:00']);
        $this->actividadEn($curso, ['titulo' => 'Ultimo', 'fecha_cierre' => '2026-12-31 23:59:00']);
        $this->actividadEn($curso, ['titulo' => 'Enero', 'fecha_cierre' => '2027-01-02 09:00:00']); // en la cuadricula de diciembre

        $this->actingAs($u)->get(route('calendario.index', ['mes' => '2026-12']))->assertOk()
            ->assertSee('Vence: Primero')->assertSee('Vence: Ultimo')->assertSee('Vence: Enero');
    }

    public function test_filtro_por_curso_oculta_otros_cursos_y_eventos_personales(): void
    {
        $u = $this->estudiante();
        $c1 = $this->cursoInscrito($u);
        $c2 = $this->cursoInscrito($u);
        $this->actividadEn($c1, ['titulo' => 'Del uno', 'fecha_cierre' => '2026-10-16 10:00:00']);
        $this->actividadEn($c2, ['titulo' => 'Del dos', 'fecha_cierre' => '2026-10-16 11:00:00']);
        EventoPersonal::create(['user_id' => $u->id, 'titulo' => 'Cita medica', 'inicia_en' => '2026-10-16 08:00:00']);

        $this->actingAs($u)->get(route('calendario.index', ['mes' => '2026-10', 'curso' => $c1->id]))->assertOk()
            ->assertSee('Del uno')->assertDontSee('Del dos')->assertDontSee('Cita medica');
    }

    public function test_parametros_basura_o_arreglo_no_provocan_500(): void
    {
        $u = $this->estudiante();

        $this->actingAs($u)->get('/calendario?mes=xx')->assertOk();
        $this->actingAs($u)->get('/calendario?mes[]=2026-10&curso[]=3')->assertOk();
        $this->actingAs($u)->get('/calendario?mes=2026-13')->assertOk();
    }

    public function test_crear_y_borrar_evento_personal_y_aislamiento_entre_usuarios(): void
    {
        $u = $this->estudiante();
        $otro = $this->estudiante();

        $this->actingAs($u)->post(route('calendario.eventos.store'), [
            'titulo' => 'Estudiar', 'inicia_en' => '2026-10-20 18:00', 'descripcion' => 'Repaso',
        ])->assertRedirect();
        $evento = EventoPersonal::firstOrFail();

        $this->actingAs($u)->get(route('calendario.index', ['mes' => '2026-10']))->assertSee('Estudiar');
        $this->actingAs($otro)->get(route('calendario.index', ['mes' => '2026-10']))->assertDontSee('Estudiar');
        $this->actingAs($otro)->delete(route('calendario.eventos.destroy', $evento))->assertForbidden();

        $this->actingAs($u)->delete(route('calendario.eventos.destroy', $evento))->assertRedirect();
        $this->assertDatabaseCount('eventos_personales', 0);
    }

    public function test_validacion_del_evento_personal(): void
    {
        $u = $this->estudiante();

        $this->actingAs($u)->post(route('calendario.eventos.store'), ['titulo' => '', 'inicia_en' => 'no-fecha'])
            ->assertSessionHasErrors(['titulo', 'inicia_en']);
        $this->actingAs($u)->post(route('calendario.eventos.store'), [
            'titulo' => 'X', 'inicia_en' => '2026-10-20 18:00', 'termina_en' => '2026-10-20 17:00',
        ])->assertSessionHasErrors('termina_en');
    }

    public function test_requiere_sesion(): void
    {
        $this->get(route('calendario.index'))->assertRedirect(route('login'));
    }
}
```

- [ ] **Step 2: Verificar que falla** — `php artisan test --filter=CalendarioTest` → FAIL (ruta no definida).

- [ ] **Step 3: Migración y modelos**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('eventos_personales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->string('titulo');
            $table->dateTime('inicia_en');
            $table->dateTime('termina_en')->nullable();
            $table->text('descripcion')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'inicia_en']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('eventos_personales');
    }
};
```

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventoPersonal extends Model
{
    protected $table = 'eventos_personales';
    protected $fillable = ['user_id', 'titulo', 'inicia_en', 'termina_en', 'descripcion'];
    protected $casts = ['inicia_en' => 'datetime', 'termina_en' => 'datetime'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
```

En `User` agregar `public function eventosPersonales(): HasMany { return $this->hasMany(EventoPersonal::class); }`.

- [ ] **Step 4: `LineaDeTiempoService::actividadesEnRango`** (método público junto a `proximaEntregaPorCurso`; `use Carbon\Carbon;`):

```php
    /** Actividades visibles cuya apertura o cierre cae en [desde, hasta]. */
    public function actividadesEnRango(User $user, Carbon $desde, Carbon $hasta, ?int $cursoId = null): Collection
    {
        return $this->actividadesVisibles($user, $cursoId)
            ->where(fn($q) => $q->whereBetween('fecha_cierre', [$desde, $hasta])
                ->orWhereBetween('fecha_apertura', [$desde, $hasta]))
            ->orderBy('fecha_cierre')
            ->get();
    }
```

- [ ] **Step 5: `CalendarioService`**

```php
<?php

namespace App\Services;

use App\Models\EventoPersonal;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class CalendarioService
{
    public function __construct(private LineaDeTiempoService $lineaDeTiempo) {}

    /** @return Collection<int, \stdClass> ordenada por inicio */
    public function eventos(User $user, Carbon $desde, Carbon $hasta, ?int $cursoId = null): Collection
    {
        $items = collect();

        foreach ($this->lineaDeTiempo->actividadesEnRango($user, $desde, $hasta, $cursoId) as $a) {
            $curso = $a->leccion->modulo->curso;

            foreach (['apertura' => 'Abre', 'cierre' => 'Vence'] as $tipo => $verbo) {
                $f = $a->{"fecha_{$tipo}"};
                if ($f && $f->between($desde, $hasta)) {
                    $items->push((object) [
                        'uid'     => "act-{$a->id}-{$tipo}",
                        'id'      => null,
                        'tipo'    => $tipo,
                        'inicia'  => $f,
                        'fin'     => $f->copy()->addMinutes(30),
                        'titulo'  => "{$verbo}: {$a->tituloCompleto()}",
                        'detalle' => $curso->titulo,
                        'url'     => route('actividades.show', $a),
                    ]);
                }
            }
        }

        if (!$cursoId) {
            EventoPersonal::where('user_id', $user->id)->whereBetween('inicia_en', [$desde, $hasta])->get()
                ->each(fn($e) => $items->push((object) [
                    'uid'     => "evt-{$e->id}",
                    'id'      => $e->id,
                    'tipo'    => 'personal',
                    'inicia'  => $e->inicia_en,
                    'fin'     => $e->termina_en ?? $e->inicia_en->copy()->addHour(),
                    'titulo'  => $e->titulo,
                    'detalle' => $e->descripcion,
                    'url'     => null,
                ]));
        }

        return $items->sortBy('inicia')->values();
    }
}
```

- [ ] **Step 6: Controlador**

```php
<?php

namespace App\Http\Controllers;

use App\Models\EventoPersonal;
use App\Models\Inscripcion;
use App\Services\CalendarioService;
use Carbon\Carbon;
use Illuminate\Http\Request;

class CalendarioController extends Controller
{
    public function index(Request $request, CalendarioService $calendario)
    {
        $user = $request->user();

        $m   = $request->query('mes');
        $mes = is_string($m) && preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $m)
            ? Carbon::createFromFormat('Y-m-d', $m . '-01')->startOfDay()
            : now()->startOfMonth();

        $c       = $request->query('curso');
        $cursoId = is_string($c) && ctype_digit($c) ? (int) $c : null;

        $desde = $mes->copy()->startOfMonth()->startOfWeek(Carbon::MONDAY);
        $hasta = $mes->copy()->endOfMonth()->endOfWeek(Carbon::SUNDAY);

        $porDia = $calendario->eventos($user, $desde, $hasta, $cursoId)
            ->groupBy(fn($e) => $e->inicia->format('Y-m-d'));

        $semanas = collect($desde->toPeriod($hasta))->chunk(7);

        $cursos = Inscripcion::with('curso:id,titulo,estado')->where('user_id', $user->id)
            ->where('estado', '!=', 'abandonado')->get()
            ->pluck('curso')->filter(fn($c) => $c && $c->estado === 'publicado')->sortBy('titulo');

        return view('calendario.index', [
            'mes' => $mes, 'semanas' => $semanas, 'porDia' => $porDia, 'cursos' => $cursos,
            'cursoId' => $cursoId, 'anterior' => $mes->copy()->subMonth()->format('Y-m'),
            'siguiente' => $mes->copy()->addMonth()->format('Y-m'),
        ]);
    }

    public function store(Request $request)
    {
        $datos = $request->validate([
            'titulo'      => 'required|string|max:255',
            'inicia_en'   => 'required|date',
            'termina_en'  => 'nullable|date|after_or_equal:inicia_en',
            'descripcion' => 'nullable|string|max:2000',
        ]);

        $evento = $request->user()->eventosPersonales()->create($datos);

        return redirect()->route('calendario.index', ['mes' => $evento->inicia_en->format('Y-m')])
            ->with('success', 'Evento agregado.');
    }

    public function destroy(Request $request, EventoPersonal $evento)
    {
        abort_unless($evento->user_id === $request->user()->id, 403);
        $mes = $evento->inicia_en->format('Y-m');
        $evento->delete();

        return redirect()->route('calendario.index', ['mes' => $mes])->with('success', 'Evento eliminado.');
    }
}
```

- [ ] **Step 7: Rutas, migas y menú.** En `routes/web.php` dentro del grupo `auth`, junto a `/linea-de-tiempo`:

```php
    Route::get('/calendario', [\App\Http\Controllers\CalendarioController::class, 'index'])->name('calendario.index');
    Route::post('/calendario/eventos', [\App\Http\Controllers\CalendarioController::class, 'store'])->name('calendario.eventos.store');
    Route::delete('/calendario/eventos/{evento}', [\App\Http\Controllers\CalendarioController::class, 'destroy'])->name('calendario.eventos.destroy');
```

`routes/breadcrumbs.php`: `Breadcrumbs::for('calendario.index', fn(Trail $t) => $t->parent('dashboard')->push('Calendario', route('calendario.index')));`

Sidebar: junto al enlace "Línea de tiempo" (solo `esEstudiante()`), agregar el enlace "Calendario" con el mismo markup y `request()->routeIs('calendario.*')` para el estado activo.

- [ ] **Step 8: Vista** `calendario/index.blade.php`

```blade
@extends('layouts.app')
@section('title', 'Calendario - D&L Quality Campus')
@section('breadcrumbs'){{ Breadcrumbs::render('calendario.index') }}@endsection
@section('content')
<div class="flex flex-wrap items-center justify-between gap-3 mb-4">
    <h1 class="text-3xl font-bold text-gray-900">Calendario</h1>
    <div class="flex items-center gap-2">
        <a href="{{ route('calendario.index', array_filter(['mes' => $anterior, 'curso' => $cursoId])) }}" class="rounded-lg border border-gray-300 px-3 py-1.5 text-sm hover:bg-gray-50" aria-label="Mes anterior">◄</a>
        <span class="w-40 text-center text-sm font-semibold capitalize">{{ $mes->locale('es')->isoFormat('MMMM YYYY') }}</span>
        <a href="{{ route('calendario.index', array_filter(['mes' => $siguiente, 'curso' => $cursoId])) }}" class="rounded-lg border border-gray-300 px-3 py-1.5 text-sm hover:bg-gray-50" aria-label="Mes siguiente">►</a>
    </div>
</div>

<form method="GET" class="flex flex-wrap items-end gap-3 mb-4">
    <input type="hidden" name="mes" value="{{ $mes->format('Y-m') }}">
    <label class="text-sm text-gray-700">Curso
        <select name="curso" class="mt-1 block rounded-lg border-gray-300 text-sm" onchange="this.form.submit()">
            <option value="">Todos los cursos</option>
            @foreach($cursos as $c)
                <option value="{{ $c->id }}" @selected($cursoId === $c->id)>{{ $c->titulo }}</option>
            @endforeach
        </select>
    </label>
</form>

<div class="overflow-x-auto">
<div class="grid min-w-[720px] grid-cols-7 gap-px rounded-xl bg-gray-200 shadow overflow-hidden text-sm">
    @foreach(['Lun','Mar','Mié','Jue','Vie','Sáb','Dom'] as $d)
        <div class="bg-gray-50 px-2 py-1.5 text-center text-xs font-medium text-gray-500">{{ $d }}</div>
    @endforeach
    @foreach($semanas as $semana)
        @foreach($semana as $dia)
            @php $eventos = $porDia[$dia->format('Y-m-d')] ?? collect(); @endphp
            <div class="min-h-24 bg-white p-1.5 {{ $dia->month !== $mes->month ? 'opacity-50' : '' }}">
                <p class="text-xs {{ $dia->isToday() ? 'font-bold text-dyl-orange-700' : 'text-gray-500' }}">{{ $dia->day }}</p>
                <ul class="mt-1 max-h-28 space-y-1 overflow-y-auto">
                    @foreach($eventos as $e)
                        <li class="rounded px-1.5 py-0.5 text-xs leading-tight
                            {{ $e->tipo === 'cierre' ? 'bg-dyl-orange-100 text-dyl-orange-800' : ($e->tipo === 'apertura' ? 'bg-dyl-graphite-100 text-dyl-graphite-700' : 'bg-blue-50 text-blue-800') }}">
                            <span class="font-medium">{{ $e->inicia->format('H:i') }}</span>
                            @if($e->url)
                                <a href="{{ $e->url }}" class="hover:underline" title="{{ $e->detalle }}">{{ $e->titulo }}</a>
                            @else
                                {{ $e->titulo }}
                                <form method="POST" action="{{ route('calendario.eventos.destroy', $e->id) }}" class="inline"
                                      onsubmit="return confirm('¿Eliminar este evento?')">
                                    @csrf @method('DELETE')
                                    <button class="ml-1 text-gray-400 hover:text-red-600" aria-label="Eliminar evento">×</button>
                                </form>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </div>
        @endforeach
    @endforeach
</div>
</div>

<details class="mt-6 rounded-xl border border-gray-200 bg-white p-4">
    <summary class="cursor-pointer text-sm font-medium text-dyl-orange-700">Nuevo evento personal</summary>
    <form method="POST" action="{{ route('calendario.eventos.store') }}" class="mt-3 grid gap-3 sm:grid-cols-2">
        @csrf
        <label class="text-sm text-gray-700 sm:col-span-2">Título
            <input type="text" name="titulo" maxlength="255" required value="{{ old('titulo') }}" class="mt-1 block w-full rounded-lg border-gray-300 text-sm">
        </label>
        <label class="text-sm text-gray-700">Inicia
            <input type="datetime-local" name="inicia_en" required value="{{ old('inicia_en') }}" class="mt-1 block w-full rounded-lg border-gray-300 text-sm">
        </label>
        <label class="text-sm text-gray-700">Termina (opcional)
            <input type="datetime-local" name="termina_en" value="{{ old('termina_en') }}" class="mt-1 block w-full rounded-lg border-gray-300 text-sm">
        </label>
        <label class="text-sm text-gray-700 sm:col-span-2">Descripción (opcional)
            <textarea name="descripcion" rows="2" maxlength="2000" class="mt-1 block w-full rounded-lg border-gray-300 text-sm">{{ old('descripcion') }}</textarea>
        </label>
        <div><button class="rounded-lg bg-dyl-orange-600 px-4 py-2 text-sm font-medium text-white hover:bg-dyl-orange-700">Agregar</button></div>
    </form>
</details>
@endsection
```

(El bloque de suscripción/`.ics` se agrega en la Task 6.)

- [ ] **Step 9: Verificar que pasa** — `php artisan test --filter=CalendarioTest`, `--filter=LineaDeTiempoTest` y `--filter=SidebarNavigationTest` → PASS.

- [ ] **Step 10: Commit**

```bash
git add database/migrations app routes resources tests
git commit -m "feat: calendario mensual con eventos personales (F2)"
```

---

### Task 6: Exportación `.ics` y suscripción por token (F2)

**Files:**
- Create: `database/migrations/2026_10_17_000003_add_calendario_token_to_users_table.php`, `app/Services/IcsService.php`, `app/Http/Controllers/CalendarioIcsController.php`
- Modify: `app/Models/User.php` (`$hidden` += `calendario_token`), `routes/web.php`, `resources/views/calendario/index.blade.php` (bloque de suscripción)
- Test: `tests/Unit/IcsServiceTest.php`, `tests/Feature/CalendarioIcsTest.php`

**Interfaces:**
- Consumes: `CalendarioService::eventos(...)` (Task 5; objetos `{uid, tipo, inicia, fin, titulo, detalle, url}`).
- Produces: `IcsService::generar(Collection $eventos, string $nombre): string`; rutas `calendario.exportar` (`GET /calendario/exportar.ics`, auth, descarga), `calendario.token.store` (`POST /calendario/token`, crea o regenera), `calendario.token.destroy` (`DELETE /calendario/token`, revoca), `calendario.feed` (`GET /calendario/feed/{token}.ics`, **pública**, 404 si el token no existe). Columna `users.calendario_token` (string 64, nullable, única).

- [ ] **Step 1: Tests que fallan.** `tests/Unit/IcsServiceTest.php`:

```php
<?php

namespace Tests\Unit;

use App\Services\IcsService;
use Carbon\Carbon;
use Tests\TestCase;

class IcsServiceTest extends TestCase
{
    private function evento(string $titulo, string $detalle = null): \stdClass
    {
        return (object) [
            'uid' => 'act-1-cierre', 'tipo' => 'cierre', 'titulo' => $titulo, 'detalle' => $detalle,
            'inicia' => Carbon::parse('2026-10-16 18:00:00'), 'fin' => Carbon::parse('2026-10-16 18:30:00'),
            'url' => 'https://lms.test/actividades/1',
        ];
    }

    public function test_estructura_basica_con_crlf_y_horas_flotantes(): void
    {
        $ics = (new IcsService)->generar(collect([$this->evento('Entrega final')]), 'Mi calendario');

        $this->assertStringStartsWith("BEGIN:VCALENDAR\r\n", $ics);
        $this->assertStringEndsWith("END:VCALENDAR\r\n", $ics);
        $this->assertStringContainsString("SUMMARY:Entrega final\r\n", $ics);
        $this->assertStringContainsString("DTSTART:20261016T180000\r\n", $ics);
        $this->assertStringContainsString("DTEND:20261016T183000\r\n", $ics);
        $this->assertStringContainsString("URL:https://lms.test/actividades/1\r\n", $ics);
        $this->assertStringNotContainsString("\n", str_replace("\r\n", '', $ics)); // ningun LF suelto
    }

    public function test_escapa_coma_punto_y_coma_barra_y_saltos_de_linea(): void
    {
        $ics = (new IcsService)->generar(collect([$this->evento('A, B; C \\ D', "linea1\nlinea2")]), 'X');

        $this->assertStringContainsString('SUMMARY:A\, B\; C \\\\ D', $ics);
        $this->assertStringContainsString('DESCRIPTION:linea1\nlinea2', $ics);
    }

    public function test_lineas_largas_se_pliegan_a_75_bytes_sin_partir_multibyte(): void
    {
        $titulo = str_repeat('áéíóú ñ 😀 ', 30);
        $ics = (new IcsService)->generar(collect([$this->evento($titulo)]), 'X');

        foreach (explode("\r\n", $ics) as $linea) {
            $this->assertLessThanOrEqual(75, strlen($linea));
            $this->assertTrue(mb_check_encoding($linea, 'UTF-8'), 'linea con UTF-8 roto: ' . $linea);
        }
        // al desplegar (quitar CRLF + espacio) se recupera el titulo completo
        $this->assertStringContainsString(rtrim($titulo), str_replace("\r\n ", '', $ics));
    }

    public function test_sin_eventos_sigue_siendo_un_calendario_valido(): void
    {
        $ics = (new IcsService)->generar(collect(), 'Vacio');

        $this->assertStringContainsString('BEGIN:VCALENDAR', $ics);
        $this->assertStringNotContainsString('BEGIN:VEVENT', $ics);
    }
}
```

`tests/Feature/CalendarioIcsTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\EventoPersonal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ArmaActividades;
use Tests\Concerns\ArmaCursoCompleto;
use Tests\TestCase;

class CalendarioIcsTest extends TestCase
{
    use RefreshDatabase, ArmaCursoCompleto, ArmaActividades;

    public function test_exportar_descarga_ics_con_mis_eventos_y_no_los_de_otros(): void
    {
        $u = $this->estudiante();
        $otro = $this->estudiante();
        $curso = $this->cursoInscrito($u);
        $this->actividadEn($curso, ['titulo' => 'Mi entrega', 'fecha_cierre' => now()->addDays(3)]);
        EventoPersonal::create(['user_id' => $u->id, 'titulo' => 'Mi cita', 'inicia_en' => now()->addDay()]);
        EventoPersonal::create(['user_id' => $otro->id, 'titulo' => 'Ajeno', 'inicia_en' => now()->addDay()]);

        $r = $this->actingAs($u)->get(route('calendario.exportar'))->assertOk();

        $this->assertStringContainsString('text/calendar', $r->headers->get('Content-Type'));
        $this->assertStringContainsString('Vence: Mi entrega', $r->getContent());
        $this->assertStringContainsString('Mi cita', $r->getContent());
        $this->assertStringNotContainsString('Ajeno', $r->getContent());
    }

    public function test_exportar_requiere_sesion(): void
    {
        $this->get(route('calendario.exportar'))->assertRedirect(route('login'));
    }

    public function test_token_crear_usar_regenerar_y_revocar(): void
    {
        $u = $this->estudiante();
        EventoPersonal::create(['user_id' => $u->id, 'titulo' => 'Evento feed', 'inicia_en' => now()->addDay()]);

        $this->actingAs($u)->post(route('calendario.token.store'))->assertRedirect();
        $t1 = $u->fresh()->calendario_token;
        $this->assertSame(64, strlen($t1));

        auth()->logout();
        $this->get(route('calendario.feed', $t1))->assertOk()->assertSee('Evento feed');

        $this->actingAs($u)->post(route('calendario.token.store'));
        $t2 = $u->fresh()->calendario_token;
        $this->assertNotSame($t1, $t2);
        auth()->logout();
        $this->get(route('calendario.feed', $t1))->assertNotFound();
        $this->get(route('calendario.feed', $t2))->assertOk();

        $this->actingAs($u)->delete(route('calendario.token.destroy'));
        auth()->logout();
        $this->get(route('calendario.feed', $t2))->assertNotFound();
    }

    public function test_token_inexistente_o_con_formato_invalido_da_404(): void
    {
        $this->get('/calendario/feed/' . str_repeat('a', 64) . '.ics')->assertNotFound();
        $this->get('/calendario/feed/corto.ics')->assertNotFound();
    }

    public function test_token_no_aparece_en_json_del_usuario(): void
    {
        $u = $this->estudiante();
        $u->forceFill(['calendario_token' => str_repeat('b', 64)])->save();

        $this->assertArrayNotHasKey('calendario_token', $u->fresh()->toArray());
    }
}
```

- [ ] **Step 2: Verificar que fallan** — `php artisan test --filter=IcsServiceTest` y `--filter=CalendarioIcsTest`.

- [ ] **Step 3: Migración**

```php
Schema::table('users', function (Blueprint $table) {
    $table->string('calendario_token', 64)->nullable()->unique()->after('remember_token');
});
// down(): $table->dropUnique(['calendario_token']); $table->dropColumn('calendario_token');
```

En `User::$hidden` agregar `'calendario_token'` (no va en `$fillable`; se asigna con `forceFill`).

- [ ] **Step 4: `IcsService`**

```php
<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Collection;

class IcsService
{
    /** @param Collection<int, \stdClass> $eventos objetos del CalendarioService */
    public function generar(Collection $eventos, string $nombre): string
    {
        $host  = parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'lms.local';
        $stamp = Carbon::now('UTC')->format('Ymd\THis\Z');

        $lineas = ['BEGIN:VCALENDAR', 'VERSION:2.0', 'PRODID:-//DyL Quality Campus//Calendario//ES',
            'CALSCALE:GREGORIAN', 'X-WR-CALNAME:' . $this->escapar($nombre)];

        foreach ($eventos as $e) {
            $lineas[] = 'BEGIN:VEVENT';
            $lineas[] = "UID:{$e->uid}@{$host}";
            $lineas[] = "DTSTAMP:{$stamp}";
            $lineas[] = 'DTSTART:' . $e->inicia->format('Ymd\THis');
            $lineas[] = 'DTEND:' . $e->fin->format('Ymd\THis');
            $lineas[] = 'SUMMARY:' . $this->escapar($e->titulo);
            if (!empty($e->detalle)) $lineas[] = 'DESCRIPTION:' . $this->escapar($e->detalle);
            if (!empty($e->url))     $lineas[] = 'URL:' . $e->url;
            $lineas[] = 'END:VEVENT';
        }
        $lineas[] = 'END:VCALENDAR';

        return implode("\r\n", array_map(fn($l) => $this->plegar($l), $lineas)) . "\r\n";
    }

    private function escapar(string $t): string
    {
        return str_replace(['\\', ';', ',', "\r\n", "\n", "\r"], ['\\\\', '\\;', '\\,', '\\n', '\\n', '\\n'], $t);
    }

    /** RFC 5545: líneas de máx. 75 bytes; la continuación empieza con un espacio. No parte caracteres UTF-8. */
    private function plegar(string $linea): string
    {
        $salida = $actual = '';
        $bytes  = 0;
        foreach (mb_str_split($linea) as $c) {
            $b = strlen($c);
            if ($bytes + $b > 75) {
                $salida .= $actual . "\r\n ";
                $actual = '';
                $bytes  = 1;
            }
            $actual .= $c;
            $bytes  += $b;
        }

        return $salida . $actual;
    }
}
```

- [ ] **Step 5: Controlador**

```php
<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\CalendarioService;
use App\Services\IcsService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CalendarioIcsController extends Controller
{
    public function descargar(Request $request, CalendarioService $cal, IcsService $ics)
    {
        return $this->respuesta($request->user(), $cal, $ics)
            ->header('Content-Disposition', 'attachment; filename="calendario.ics"');
    }

    public function feed(string $token, CalendarioService $cal, IcsService $ics)
    {
        $user = User::where('calendario_token', $token)->firstOrFail();

        return $this->respuesta($user, $cal, $ics);
    }

    public function guardarToken(Request $request)
    {
        $request->user()->forceFill(['calendario_token' => Str::random(64)])->save();

        return back()->with('success', 'Enlace de suscripción generado. El anterior dejó de funcionar.');
    }

    public function revocarToken(Request $request)
    {
        $request->user()->forceFill(['calendario_token' => null])->save();

        return back()->with('success', 'Enlace de suscripción revocado.');
    }

    private function respuesta(User $user, CalendarioService $cal, IcsService $ics)
    {
        $eventos = $cal->eventos($user, now()->subDays(60)->startOfDay(), now()->addDays(365)->endOfDay());

        return response($ics->generar($eventos, 'D&L Quality Campus - ' . $user->name))
            ->header('Content-Type', 'text/calendar; charset=utf-8');
    }
}
```

- [ ] **Step 6: Rutas.** Dentro del grupo `auth` (junto a las del calendario):

```php
    Route::get('/calendario/exportar.ics', [\App\Http\Controllers\CalendarioIcsController::class, 'descargar'])->name('calendario.exportar');
    Route::post('/calendario/token', [\App\Http\Controllers\CalendarioIcsController::class, 'guardarToken'])->name('calendario.token.store');
    Route::delete('/calendario/token', [\App\Http\Controllers\CalendarioIcsController::class, 'revocarToken'])->name('calendario.token.destroy');
```

Fuera de cualquier grupo `auth` (junto a `/verificar-certificado/{numero}`):

```php
Route::get('/calendario/feed/{token}.ics', [\App\Http\Controllers\CalendarioIcsController::class, 'feed'])
    ->where('token', '[A-Za-z0-9]{64}')->middleware('throttle:60,1')->name('calendario.feed');
```

- [ ] **Step 7: Bloque de suscripción.** En `calendario/index.blade.php`, antes de `@endsection`:

```blade
<div class="mt-6 rounded-xl border border-gray-200 bg-white p-4 text-sm">
    <p class="font-medium text-gray-900 mb-2">Llévalo a tu calendario</p>
    <div class="flex flex-wrap items-center gap-3">
        <a href="{{ route('calendario.exportar') }}" class="rounded-lg border border-gray-300 px-3 py-1.5 hover:bg-gray-50">Descargar .ics</a>
        @if(auth()->user()->calendario_token)
            <input type="text" readonly value="{{ route('calendario.feed', auth()->user()->calendario_token) }}"
                   onclick="this.select()" class="min-w-0 flex-1 rounded-lg border-gray-300 text-xs" aria-label="Enlace de suscripción">
            <form method="POST" action="{{ route('calendario.token.store') }}">@csrf
                <button class="rounded-lg border border-gray-300 px-3 py-1.5 hover:bg-gray-50">Regenerar</button></form>
            <form method="POST" action="{{ route('calendario.token.destroy') }}">@csrf @method('DELETE')
                <button class="rounded-lg border border-gray-300 px-3 py-1.5 text-red-600 hover:bg-red-50">Revocar</button></form>
        @else
            <form method="POST" action="{{ route('calendario.token.store') }}">@csrf
                <button class="rounded-lg border border-gray-300 px-3 py-1.5 hover:bg-gray-50">Crear enlace de suscripción</button></form>
        @endif
    </div>
    <p class="mt-2 text-xs text-gray-500">Quien tenga el enlace de suscripción puede ver tus fechas; regenéralo o revócalo si lo compartiste por error.</p>
</div>
```

- [ ] **Step 8: Verificar que pasa** — `php artisan test --filter=IcsServiceTest`, `--filter=CalendarioIcsTest`, `--filter=CalendarioTest`, luego suite completa.

- [ ] **Step 9: Commit**

```bash
git add database/migrations app routes resources tests
git commit -m "feat: exportar calendario .ics y suscripcion por token revocable (F2)"
```

---

### Task 7: Ficha académica del curso (F6, FE-2, FE-3)

**Files:**
- Create: `database/migrations/2026_10_17_000004_create_curso_fichas_tables.php`, `app/Models/CursoFicha.php`, `app/Models/CursoBibliografia.php`, `app/Http/Controllers/FichaController.php`, `resources/views/cursos/ficha.blade.php`, `resources/views/cursos/partials/ficha.blade.php`
- Modify: `app/Models/Curso.php` (relaciones y `fichaFaltantes()`), `app/Http/Controllers/CursoController.php` (`show` carga la ficha), `resources/views/cursos/show.blade.php` (incluye el parcial al final del contenido), `resources/views/cursos/edit.blade.php` (aviso de ficha incompleta + enlace), `routes/web.php`, `routes/breadcrumbs.php`
- Test: `tests/Feature/FichaCursoTest.php`

**Interfaces:**
- Produces: tablas `curso_fichas` (`curso_id` único; `justificacion`, `resultado_aprendizaje`, `prerrequisitos` text null; `resultados_especificos` json null; `creditos`, `semestre` unsignedTinyInteger null; `programa`, `facultad` string null) y `curso_bibliografias` (`curso_id`, `autor`, `anio` unsignedSmallInteger null, `titulo`, `editorial` null, `orden`); `Curso::ficha(): HasOne`, `Curso::bibliografias(): HasMany` (ordenada), `Curso::fichaFaltantes(): array<string>` (etiquetas legibles de lo que falta: Justificación, Resultado de aprendizaje, Créditos, Programa, Bibliografía); rutas `cursos.ficha.edit` (`GET /cursos/{curso}/ficha`) y `cursos.ficha.update` (`PUT`), ambas del grupo `instructor`.

- [ ] **Step 1: Test que falla**

```php
<?php

namespace Tests\Feature;

use App\Models\Curso;
use App\Models\CursoFicha;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ArmaActividades;
use Tests\Concerns\ArmaCursoCompleto;
use Tests\TestCase;

class FichaCursoTest extends TestCase
{
    use RefreshDatabase, ArmaCursoCompleto, ArmaActividades;

    private function datos(array $extra = []): array
    {
        return $extra + [
            'justificacion'          => 'Porque la seguridad importa.',
            'resultado_aprendizaje'  => 'Aplicar fundamentos de seguridad.',
            'resultados_especificos' => "Contrastar principios\n\n  \nImplementar politicas\n",
            'creditos' => 2, 'semestre' => 7, 'prerrequisitos' => 'Redes',
            'programa' => 'Ingeniería de Sistemas', 'facultad' => 'Ingeniería',
            'bibliografia' => [
                ['autor' => 'Carvajal', 'anio' => 2011, 'titulo' => 'Seguridad en bases de datos', 'editorial' => 'Revista'],
                ['autor' => 'Basta', 'anio' => '', 'titulo' => 'Database security', 'editorial' => ''],
            ],
        ];
    }

    public function test_instructor_guarda_ficha_y_bibliografia_y_el_estudiante_la_ve(): void
    {
        $inst = $this->instructor();
        $curso = Curso::factory()->publicado()->create(['created_by' => $inst->id]);

        $this->actingAs($inst)->put(route('cursos.ficha.update', $curso), $this->datos())
            ->assertRedirect(route('cursos.ficha.edit', $curso));

        $ficha = $curso->fresh()->ficha;
        $this->assertSame(['Contrastar principios', 'Implementar politicas'], $ficha->resultados_especificos);
        $this->assertCount(2, $curso->fresh()->bibliografias);

        $est = $this->estudiante();
        \App\Models\Inscripcion::factory()->create(['user_id' => $est->id, 'curso_id' => $curso->id]);
        $this->actingAs($est)->get(route('cursos.show', $curso))->assertOk()
            ->assertSee('Información del curso')->assertSee('Porque la seguridad importa.')
            ->assertSee('Carvajal')->assertSee('Ingeniería de Sistemas')->assertSee('mailto:' . $inst->email, false);
    }

    public function test_actualizar_reemplaza_la_bibliografia(): void
    {
        $inst = $this->instructor();
        $curso = Curso::factory()->publicado()->create(['created_by' => $inst->id]);
        $this->actingAs($inst)->put(route('cursos.ficha.update', $curso), $this->datos());
        $this->actingAs($inst)->put(route('cursos.ficha.update', $curso), $this->datos(['bibliografia' => [
            ['autor' => 'Solo uno', 'anio' => 2020, 'titulo' => 'Unico', 'editorial' => 'E'],
        ]]));

        $this->assertSame(['Solo uno'], $curso->fresh()->bibliografias->pluck('autor')->all());
    }

    public function test_ficha_vacia_no_muestra_seccion_ni_variables_literales(): void
    {
        $inst = $this->instructor();
        $curso = Curso::factory()->publicado()->create(['created_by' => $inst->id]);
        $est = $this->estudiante();
        \App\Models\Inscripcion::factory()->create(['user_id' => $est->id, 'curso_id' => $curso->id]);

        $html = $this->actingAs($est)->get(route('cursos.show', $curso))->assertOk()->getContent();

        $this->assertStringNotContainsString('Información del curso', $html);
        $this->assertStringNotContainsString('{{', $html);
        $this->assertStringNotContainsString('}}', $html);
    }

    public function test_ficha_parcial_oculta_bloques_vacios(): void
    {
        $inst = $this->instructor();
        $curso = Curso::factory()->publicado()->create(['created_by' => $inst->id]);
        CursoFicha::create(['curso_id' => $curso->id, 'justificacion' => 'Solo esto']);
        $est = $this->estudiante();
        \App\Models\Inscripcion::factory()->create(['user_id' => $est->id, 'curso_id' => $curso->id]);

        $this->actingAs($est)->get(route('cursos.show', $curso))->assertOk()
            ->assertSee('Solo esto')->assertDontSee('Bibliografía')->assertDontSee('Prerrequisitos');
    }

    public function test_validacion_y_permisos(): void
    {
        $inst = $this->instructor();
        $curso = Curso::factory()->publicado()->create(['created_by' => $inst->id]);

        $this->actingAs($inst)->put(route('cursos.ficha.update', $curso), $this->datos([
            'creditos' => 99, 'bibliografia' => [['autor' => '', 'titulo' => '']],
        ]))->assertSessionHasErrors(['creditos', 'bibliografia.0.autor', 'bibliografia.0.titulo']);

        $this->actingAs($this->instructor())->get(route('cursos.ficha.edit', $curso))->assertForbidden();
        $this->actingAs($this->estudiante())->get(route('cursos.ficha.edit', $curso))->assertForbidden();
    }

    public function test_aviso_de_ficha_incompleta_en_la_edicion_del_curso(): void
    {
        $inst = $this->instructor();
        $curso = Curso::factory()->publicado()->create(['created_by' => $inst->id]);

        $this->actingAs($inst)->get(route('cursos.edit', $curso))->assertOk()
            ->assertSee('Ficha académica incompleta')->assertSee('Justificación');

        $this->actingAs($inst)->put(route('cursos.ficha.update', $curso), $this->datos());
        $this->actingAs($inst)->get(route('cursos.edit', $curso))->assertDontSee('Ficha académica incompleta');
    }

    public function test_bloque_de_soporte_se_oculta_si_no_hay_datos(): void
    {
        config(['lms.soporte' => ['correo' => null, 'url' => null, 'horario' => null, 'faq_url' => null]]);
        $inst = $this->instructor();
        $curso = Curso::factory()->publicado()->create(['created_by' => $inst->id]);
        CursoFicha::create(['curso_id' => $curso->id, 'justificacion' => 'Algo']);

        $this->actingAs($inst)->get(route('cursos.show', $curso))->assertOk()->assertDontSee('Soporte técnico');
    }
}
```

- [ ] **Step 2: Verificar que falla** — `php artisan test --filter=FichaCursoTest`.

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
        Schema::create('curso_fichas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('curso_id')->unique()->constrained('cursos')->onDelete('cascade');
            $table->text('justificacion')->nullable();
            $table->text('resultado_aprendizaje')->nullable();
            $table->json('resultados_especificos')->nullable();
            $table->unsignedTinyInteger('creditos')->nullable();
            $table->unsignedTinyInteger('semestre')->nullable();
            $table->text('prerrequisitos')->nullable();
            $table->string('programa')->nullable();
            $table->string('facultad')->nullable();
            $table->timestamps();
        });

        Schema::create('curso_bibliografias', function (Blueprint $table) {
            $table->id();
            $table->foreignId('curso_id')->constrained('cursos')->onDelete('cascade');
            $table->string('autor');
            $table->unsignedSmallInteger('anio')->nullable();
            $table->string('titulo');
            $table->string('editorial')->nullable();
            $table->unsignedSmallInteger('orden')->default(0);
            $table->timestamps();

            $table->index('curso_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('curso_bibliografias');
        Schema::dropIfExists('curso_fichas');
    }
};
```

- [ ] **Step 4: Modelos**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CursoFicha extends Model
{
    protected $table = 'curso_fichas';
    protected $fillable = [
        'curso_id', 'justificacion', 'resultado_aprendizaje', 'resultados_especificos',
        'creditos', 'semestre', 'prerrequisitos', 'programa', 'facultad',
    ];
    protected $casts = ['resultados_especificos' => 'array'];

    public function curso(): BelongsTo
    {
        return $this->belongsTo(Curso::class);
    }
}
```

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CursoBibliografia extends Model
{
    protected $table = 'curso_bibliografias';
    protected $fillable = ['curso_id', 'autor', 'anio', 'titulo', 'editorial', 'orden'];

    public function curso(): BelongsTo
    {
        return $this->belongsTo(Curso::class);
    }
}
```

En `Curso` (importar `HasOne` si falta):

```php
    public function ficha(): HasOne
    {
        return $this->hasOne(CursoFicha::class);
    }

    public function bibliografias(): HasMany
    {
        return $this->hasMany(CursoBibliografia::class)->orderBy('orden');
    }

    /** Etiquetas de lo que falta para considerar completa la ficha académica. */
    public function fichaFaltantes(): array
    {
        $f = $this->ficha;
        $faltan = [];
        if (!$f || blank($f->justificacion))           $faltan[] = 'Justificación';
        if (!$f || blank($f->resultado_aprendizaje))   $faltan[] = 'Resultado de aprendizaje';
        if (!$f || $f->creditos === null)              $faltan[] = 'Créditos';
        if (!$f || blank($f->programa))                $faltan[] = 'Programa';
        if (!$this->bibliografias()->exists())         $faltan[] = 'Bibliografía';

        return $faltan;
    }
```

- [ ] **Step 5: Controlador**

```php
<?php

namespace App\Http\Controllers;

use App\Models\Curso;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FichaController extends Controller
{
    public function edit(Curso $curso)
    {
        $this->authorize('update', $curso);
        $curso->load('ficha', 'bibliografias');

        return view('cursos.ficha', compact('curso'));
    }

    public function update(Request $request, Curso $curso)
    {
        $this->authorize('update', $curso);

        $d = $request->validate([
            'justificacion'          => 'nullable|string|max:5000',
            'resultado_aprendizaje'  => 'nullable|string|max:2000',
            'resultados_especificos' => 'nullable|string|max:5000',
            'creditos'               => 'nullable|integer|min:1|max:20',
            'semestre'               => 'nullable|integer|min:1|max:20',
            'prerrequisitos'         => 'nullable|string|max:2000',
            'programa'               => 'nullable|string|max:255',
            'facultad'               => 'nullable|string|max:255',
            'bibliografia'           => 'nullable|array|max:50',
            'bibliografia.*.autor'     => 'required|string|max:255',
            'bibliografia.*.anio'      => 'nullable|integer|min:1900|max:2100',
            'bibliografia.*.titulo'    => 'required|string|max:500',
            'bibliografia.*.editorial' => 'nullable|string|max:255',
        ]);

        $especificos = collect(preg_split('/\R/u', (string) ($d['resultados_especificos'] ?? '')))
            ->map(fn($l) => trim($l))->filter()->values()->all();

        DB::transaction(function () use ($curso, $d, $especificos) {
            $curso->ficha()->updateOrCreate([], [
                'justificacion'          => $d['justificacion'] ?? null,
                'resultado_aprendizaje'  => $d['resultado_aprendizaje'] ?? null,
                'resultados_especificos' => $especificos ?: null,
                'creditos'               => $d['creditos'] ?? null,
                'semestre'               => $d['semestre'] ?? null,
                'prerrequisitos'         => $d['prerrequisitos'] ?? null,
                'programa'               => $d['programa'] ?? null,
                'facultad'               => $d['facultad'] ?? null,
            ]);

            $curso->bibliografias()->delete();
            foreach (array_values($d['bibliografia'] ?? []) as $i => $b) {
                $curso->bibliografias()->create([
                    'autor' => $b['autor'], 'anio' => $b['anio'] ?? null, 'titulo' => $b['titulo'],
                    'editorial' => $b['editorial'] ?? null, 'orden' => $i,
                ]);
            }
        });

        return redirect()->route('cursos.ficha.edit', $curso)->with('success', 'Ficha académica guardada.');
    }
}
```

- [ ] **Step 6: Rutas y migas.** Grupo `instructor` junto a `cursos.edit`:

```php
        Route::get('/cursos/{curso}/ficha', [\App\Http\Controllers\FichaController::class, 'edit'])->name('cursos.ficha.edit');
        Route::put('/cursos/{curso}/ficha', [\App\Http\Controllers\FichaController::class, 'update'])->name('cursos.ficha.update');
```

Breadcrumbs: `Breadcrumbs::for('cursos.ficha', fn(Trail $t, $curso) => $t->parent('cursos.show', $curso)->push('Ficha académica'));`

- [ ] **Step 7: Vista de edición** `cursos/ficha.blade.php` (formulario con campos de la tabla anterior; bibliografía dinámica con Alpine):

```blade
@extends('layouts.app')
@section('title', 'Ficha académica - ' . $curso->titulo)
@section('breadcrumbs'){{ Breadcrumbs::render('cursos.ficha', $curso) }}@endsection
@section('content')
@php $f = $curso->ficha; @endphp
<h1 class="text-3xl font-bold text-gray-900 mb-6">Ficha académica</h1>

<form method="POST" action="{{ route('cursos.ficha.update', $curso) }}" class="space-y-6 max-w-3xl"
      x-data="{ filas: {{ Js::from(old('bibliografia', $curso->bibliografias->map(fn($b) => ['autor' => $b->autor, 'anio' => $b->anio, 'titulo' => $b->titulo, 'editorial' => $b->editorial])->all())) }} }">
    @csrf @method('PUT')

    @foreach([
        ['justificacion', 'Justificación', 'textarea', $f?->justificacion],
        ['resultado_aprendizaje', 'Resultado esperado de aprendizaje', 'textarea', $f?->resultado_aprendizaje],
        ['prerrequisitos', 'Prerrequisitos', 'textarea', $f?->prerrequisitos],
    ] as [$campo, $etiqueta, $tipo, $valor])
        <label class="block text-sm font-medium text-gray-700">{{ $etiqueta }}
            <textarea name="{{ $campo }}" rows="3" class="mt-1 block w-full rounded-lg border-gray-300 text-sm">{{ old($campo, $valor) }}</textarea>
            @error($campo)<span class="text-xs text-red-600">{{ $message }}</span>@enderror
        </label>
    @endforeach

    <label class="block text-sm font-medium text-gray-700">Resultados específicos <span class="font-normal text-gray-500">(uno por línea)</span>
        <textarea name="resultados_especificos" rows="4" class="mt-1 block w-full rounded-lg border-gray-300 text-sm">{{ old('resultados_especificos', implode("\n", $f?->resultados_especificos ?? [])) }}</textarea>
    </label>

    <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
        @foreach([['creditos', 'Créditos', 'number', $f?->creditos], ['semestre', 'Semestre', 'number', $f?->semestre], ['programa', 'Programa', 'text', $f?->programa], ['facultad', 'Facultad', 'text', $f?->facultad]] as [$campo, $etiqueta, $tipo, $valor])
            <label class="block text-sm font-medium text-gray-700">{{ $etiqueta }}
                <input type="{{ $tipo }}" name="{{ $campo }}" value="{{ old($campo, $valor) }}" class="mt-1 block w-full rounded-lg border-gray-300 text-sm">
                @error($campo)<span class="text-xs text-red-600">{{ $message }}</span>@enderror
            </label>
        @endforeach
    </div>

    <fieldset>
        <legend class="text-sm font-medium text-gray-700 mb-2">Bibliografía</legend>
        <template x-for="(fila, i) in filas" :key="i">
            <div class="mb-2 grid grid-cols-12 gap-2">
                <input class="col-span-3 rounded-lg border-gray-300 text-sm" :name="`bibliografia[${i}][autor]`" x-model="fila.autor" placeholder="Autor" required>
                <input class="col-span-1 rounded-lg border-gray-300 text-sm" :name="`bibliografia[${i}][anio]`" x-model="fila.anio" placeholder="Año" type="number">
                <input class="col-span-4 rounded-lg border-gray-300 text-sm" :name="`bibliografia[${i}][titulo]`" x-model="fila.titulo" placeholder="Título" required>
                <input class="col-span-3 rounded-lg border-gray-300 text-sm" :name="`bibliografia[${i}][editorial]`" x-model="fila.editorial" placeholder="Editorial">
                <button type="button" class="col-span-1 text-red-600" @click="filas.splice(i, 1)" aria-label="Quitar">×</button>
            </div>
        </template>
        <button type="button" class="text-sm text-dyl-orange-700 hover:underline" @click="filas.push({autor: '', anio: '', titulo: '', editorial: ''})">+ Agregar referencia</button>
        @error('bibliografia.*.autor')<p class="text-xs text-red-600">{{ $message }}</p>@enderror
        @error('bibliografia.*.titulo')<p class="text-xs text-red-600">{{ $message }}</p>@enderror
    </fieldset>

    <button class="rounded-lg bg-dyl-orange-600 px-5 py-2 text-sm font-medium text-white hover:bg-dyl-orange-700">Guardar ficha</button>
</form>
@endsection
```

- [ ] **Step 8: Parcial de visualización** `cursos/partials/ficha.blade.php` (recibe `$curso` con `ficha` y `bibliografias` cargadas):

```blade
@php
    $f = $curso->ficha;
    $hayFicha = $f && (filled($f->justificacion) || filled($f->resultado_aprendizaje) || !empty($f->resultados_especificos)
        || $f->creditos !== null || $f->semestre !== null || filled($f->prerrequisitos) || filled($f->programa) || filled($f->facultad));
    $hayBiblio = $curso->bibliografias->isNotEmpty();
    $soporte = config('lms.soporte');
    $haySoporte = filled($soporte['correo'] ?? null) || filled($soporte['url'] ?? null) || filled($soporte['horario'] ?? null);
@endphp
@if($hayFicha || $hayBiblio)
<details class="mt-8 rounded-xl bg-white shadow">
    <summary class="cursor-pointer px-6 py-4 text-lg font-bold text-gray-900">Información del curso</summary>
    <div class="space-y-6 px-6 pb-6 text-sm text-gray-700">
        @if($f && filled($f->justificacion))
            <section><h3 class="font-semibold text-gray-900 mb-1">Justificación</h3><p class="whitespace-pre-line">{{ $f->justificacion }}</p></section>
        @endif
        @if($f && filled($f->resultado_aprendizaje))
            <section><h3 class="font-semibold text-gray-900 mb-1">Resultado esperado de aprendizaje</h3><p class="whitespace-pre-line">{{ $f->resultado_aprendizaje }}</p></section>
        @endif
        @if($f && !empty($f->resultados_especificos))
            <section><h3 class="font-semibold text-gray-900 mb-1">Resultados específicos</h3>
                <ul class="list-disc pl-5 space-y-1">@foreach($f->resultados_especificos as $r)<li>{{ $r }}</li>@endforeach</ul></section>
        @endif
        @if($f && ($f->creditos !== null || $f->semestre !== null || filled($f->prerrequisitos) || filled($f->programa) || filled($f->facultad)))
            <section><h3 class="font-semibold text-gray-900 mb-1">Identificación</h3>
                <dl class="grid grid-cols-1 gap-x-6 gap-y-1 sm:grid-cols-2">
                    @if($f->creditos !== null)<div><dt class="inline font-medium">Créditos:</dt> <dd class="inline">{{ $f->creditos }}</dd></div>@endif
                    @if($f->semestre !== null)<div><dt class="inline font-medium">Semestre:</dt> <dd class="inline">{{ $f->semestre }}</dd></div>@endif
                    @if(filled($f->programa))<div><dt class="inline font-medium">Programa:</dt> <dd class="inline">{{ $f->programa }}</dd></div>@endif
                    @if(filled($f->facultad))<div><dt class="inline font-medium">Facultad:</dt> <dd class="inline">{{ $f->facultad }}</dd></div>@endif
                    @if(filled($f->prerrequisitos))<div class="sm:col-span-2"><dt class="inline font-medium">Prerrequisitos:</dt> <dd class="inline">{{ $f->prerrequisitos }}</dd></div>@endif
                </dl></section>
        @endif
        @if($hayBiblio)
            <section><h3 class="font-semibold text-gray-900 mb-1">Bibliografía</h3>
                <table class="min-w-full text-left text-xs">
                    <thead class="text-gray-500"><tr><th class="py-1 pr-3">Autor</th><th class="py-1 pr-3">Año</th><th class="py-1 pr-3">Título</th><th class="py-1">Editorial</th></tr></thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach($curso->bibliografias as $b)
                            <tr><td class="py-1 pr-3">{{ $b->autor }}</td><td class="py-1 pr-3">{{ $b->anio ?? '—' }}</td><td class="py-1 pr-3">{{ $b->titulo }}</td><td class="py-1">{{ $b->editorial ?? '—' }}</td></tr>
                        @endforeach
                    </tbody>
                </table></section>
        @endif
        <section class="grid gap-4 border-t border-gray-100 pt-4 sm:grid-cols-2">
            <div><h3 class="font-semibold text-gray-900 mb-1">Contacte a su profesor</h3>
                <p>{{ $curso->creador->name }}@if(filled($curso->creador->email)) · <a class="text-dyl-orange-700 hover:underline" href="mailto:{{ $curso->creador->email }}">{{ $curso->creador->email }}</a>@endif</p></div>
            @if($haySoporte)
            <div><h3 class="font-semibold text-gray-900 mb-1">Soporte técnico</h3>
                @if(filled($soporte['correo'] ?? null))<p><a class="text-dyl-orange-700 hover:underline" href="mailto:{{ $soporte['correo'] }}">{{ $soporte['correo'] }}</a></p>@endif
                @if(filled($soporte['url'] ?? null))<p><a class="text-dyl-orange-700 hover:underline" href="{{ $soporte['url'] }}" target="_blank" rel="noopener">Centro de soporte</a></p>@endif
                @if(filled($soporte['horario'] ?? null))<p class="text-xs text-gray-500">Horario de atención: {{ $soporte['horario'] }}</p>@endif</div>
            @endif
        </section>
    </div>
</details>
@endif
```

- [ ] **Step 9: Mostrar y avisar.** En `cursos/show.blade.php`, como **último bloque** dentro de `@section('content')` (antes de `@endsection`): `@include('cursos.partials.ficha', ['curso' => $curso->loadMissing('ficha', 'bibliografias', 'creador')])`. En `cursos/edit.blade.php`, justo encima del primer formulario/panel del contenido:

```blade
@if(count($faltan = $curso->fichaFaltantes()))
    <div class="alert alert-warning mb-5" role="status">
        Ficha académica incompleta: falta {{ implode(', ', $faltan) }}.
        <a href="{{ route('cursos.ficha.edit', $curso) }}" class="font-medium underline">Completar ficha</a>
    </div>
@else
    <p class="mb-5 text-sm"><a href="{{ route('cursos.ficha.edit', $curso) }}" class="text-dyl-orange-700 hover:underline">Ficha académica</a></p>
@endif
```

(Si la clase `alert-warning` no existe en `resources/css/app.css`, usar `alert alert-error` o las clases de color vecinas; revisar el CSS antes.)

- [ ] **Step 10: Verificar que pasa** — `php artisan test --filter=FichaCursoTest`, `--filter=Curso`, y la suite completa.

- [ ] **Step 11: Commit**

```bash
git add database/migrations app routes resources tests
git commit -m "feat: ficha academica del curso con bibliografia y contacto (F6)"
```

---

### Task 8: Aviso automático de vencimiento a 24 h (F9)

**Files:**
- Create: `app/Console/Commands/NotificarVencimientos.php`
- Modify: `routes/console.php`
- Test: `tests/Feature/NotificarVencimientosTest.php`

**Interfaces:**
- Consumes: `Notificacion` (`tipo`, `data` JSON), `Inscripcion`, `RespuestaEstudiante`, `ProgresoActividad`.
- Produces: comando `notificaciones:vencimientos` (sin argumentos, imprime cuántos avisos creó); registro en el scheduler cada hora. Cada aviso es una `Notificacion` con `tipo = 'vencimiento'` y `data = ['url' => ..., 'actividad_id' => N]`; es idempotente por (usuario, actividad).

- [ ] **Step 1: Test que falla**

```php
<?php

namespace Tests\Feature;

use App\Models\Inscripcion;
use App\Models\Notificacion;
use App\Models\ProgresoActividad;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ArmaActividades;
use Tests\Concerns\ArmaCursoCompleto;
use Tests\TestCase;

class NotificarVencimientosTest extends TestCase
{
    use RefreshDatabase, ArmaCursoCompleto, ArmaActividades;

    private function correr(): void
    {
        $this->artisan('notificaciones:vencimientos')->assertSuccessful();
    }

    public function test_avisa_a_inscritos_con_entrega_pendiente_dentro_de_24_horas(): void
    {
        $u = $this->estudiante();
        $curso = $this->cursoInscrito($u);
        $a = $this->actividadEn($curso, ['titulo' => 'Vence manana', 'fecha_cierre' => now()->addHours(12)]);

        $this->correr();

        $n = Notificacion::where('user_id', $u->id)->where('tipo', 'vencimiento')->firstOrFail();
        $this->assertStringContainsString('Vence manana', $n->mensaje . $n->titulo);
        $this->assertSame($a->id, $n->data['actividad_id']);
        $this->assertSame(route('actividades.show', $a), $n->data['url']);
    }

    public function test_es_idempotente(): void
    {
        $u = $this->estudiante();
        $curso = $this->cursoInscrito($u);
        $this->actividadEn($curso, ['fecha_cierre' => now()->addHours(5)]);

        $this->correr();
        $this->correr();

        $this->assertSame(1, Notificacion::where('tipo', 'vencimiento')->count());
    }

    public function test_no_avisa_fuera_de_ventana_ni_a_quien_ya_entrego_ni_abandonados_ni_cursos_no_publicados(): void
    {
        $entrego = $this->estudiante();
        $curso = $this->cursoInscrito($entrego);
        $hecha = $this->actividadEn($curso, ['fecha_cierre' => now()->addHours(3)]);
        $this->calificar($entrego, $hecha, 5);

        $marcada = $this->estudiante();
        Inscripcion::factory()->create(['user_id' => $marcada->id, 'curso_id' => $curso->id]);
        ProgresoActividad::create(['user_id' => $marcada->id, 'actividad_id' => $hecha->id, 'completado' => true, 'fecha_completado' => now()]);

        $abandono = $this->estudiante();
        Inscripcion::factory()->create(['user_id' => $abandono->id, 'curso_id' => $curso->id, 'estado' => 'abandonado']);

        $this->actividadEn($curso, ['titulo' => 'Lejana', 'fecha_cierre' => now()->addHours(30)]);
        $this->actividadEn($curso, ['titulo' => 'Vencida', 'fecha_cierre' => now()->subHour()]);
        $this->actividadEn($curso, ['titulo' => 'Sin fecha', 'fecha_cierre' => null]);

        $borrador = \App\Models\Curso::factory()->borrador()->create();
        $enBorrador = $this->estudiante();
        Inscripcion::factory()->create(['user_id' => $enBorrador->id, 'curso_id' => $borrador->id]);
        $this->actividadEn($borrador, ['fecha_cierre' => now()->addHours(2)]);

        $this->correr();

        // solo `marcada` y `abandono` comparten la actividad `hecha`: marcada ya la hizo, abandono no cuenta
        // `entrego` entrego; nadie mas tiene pendiente algo dentro de 24 h en un curso publicado
        $this->assertSame(0, Notificacion::where('tipo', 'vencimiento')->count());
    }

    public function test_actividad_sin_inscritos_no_falla(): void
    {
        $curso = \App\Models\Curso::factory()->publicado()->create();
        $this->actividadEn($curso, ['fecha_cierre' => now()->addHours(2)]);

        $this->correr();

        $this->assertSame(0, Notificacion::count());
    }

    public function test_esta_registrado_en_el_scheduler(): void
    {
        $this->artisan('schedule:list')->expectsOutputToContain('notificaciones:vencimientos')->assertSuccessful();
    }
}
```

- [ ] **Step 2: Verificar que falla** — `php artisan test --filter=NotificarVencimientosTest` → FAIL (comando inexistente).

- [ ] **Step 3: Comando**

```php
<?php

namespace App\Console\Commands;

use App\Models\Actividad;
use App\Models\Inscripcion;
use App\Models\Notificacion;
use App\Models\ProgresoActividad;
use App\Models\RespuestaEstudiante;
use Illuminate\Console\Command;

class NotificarVencimientos extends Command
{
    protected $signature = 'notificaciones:vencimientos';
    protected $description = 'Avisa a los estudiantes con entregas que vencen en las próximas 24 horas (una sola vez por actividad)';

    public function handle(): int
    {
        $creadas = 0;

        $actividades = Actividad::with('leccion.modulo.curso')
            ->whereBetween('fecha_cierre', [now(), now()->addDay()])
            ->whereHas('leccion.modulo.curso', fn($q) => $q->where('estado', 'publicado'))
            ->get();

        foreach ($actividades as $a) {
            $curso = $a->leccion->modulo->curso;

            $inscritos = Inscripcion::where('curso_id', $curso->id)->where('estado', '!=', 'abandonado')->pluck('user_id');
            $yaEntregaron = RespuestaEstudiante::where('actividad_id', $a->id)->pluck('user_id')
                ->merge(ProgresoActividad::where('actividad_id', $a->id)->where('completado', true)->pluck('user_id'));
            $yaAvisados = Notificacion::where('tipo', 'vencimiento')->where('data->actividad_id', $a->id)->pluck('user_id');

            foreach ($inscritos->diff($yaEntregaron)->diff($yaAvisados) as $userId) {
                Notificacion::create([
                    'user_id' => $userId,
                    'tipo'    => 'vencimiento',
                    'titulo'  => 'Una entrega vence pronto',
                    'mensaje' => "«{$a->tituloCompleto()}» ({$curso->titulo}) vence el {$a->fecha_cierre->format('d/m H:i')}.",
                    'data'    => ['url' => route('actividades.show', $a), 'actividad_id' => $a->id],
                ]);
                $creadas++;
            }
        }

        $this->info("Avisos de vencimiento creados: {$creadas}");

        return self::SUCCESS;
    }
}
```

- [ ] **Step 4: Programación.** En `routes/console.php`:

```php
use Illuminate\Support\Facades\Schedule;

Schedule::command('notificaciones:vencimientos')->hourly()->withoutOverlapping();
```

- [ ] **Step 5: Verificar que pasa** — `php artisan test --filter=NotificarVencimientosTest` → PASS. Si `where('data->actividad_id', ...)` no funciona en SQLite por el cast, verificar la columna (`data` es `json`/`text`); como alternativa equivalente usar `->whereJsonContains('data->actividad_id', $a->id)`. Después `php artisan test` completo.

- [ ] **Step 6: Commit**

```bash
git add app routes tests
git commit -m "feat: aviso automatico de vencimiento a 24 horas (F9)"
```

---

## Verificación final del sprint

- [ ] `php artisan test` completo en verde (nunca `migrate:fresh` en la BD local).
- [ ] Revisión manual con la BD local migrada (`php artisan migrate`): como instructor definir ponderaciones (suma 100 y suma ≠ 100) y llenar la ficha de un curso; como estudiante ver "Mis calificaciones" (desglose y proyección), la tarjeta con "Nota actual", `/calendario` (crear/borrar evento, filtrar, descargar `.ics`, crear enlace y abrirlo en Google Calendar/Outlook) y la ficha en el curso; `php artisan notificaciones:vencimientos` con una actividad que vence mañana.
- [ ] Ninguna página muestra `{{`, `}}` ni encabezados vacíos con datos faltantes.

## Self-Review (spec ↔ plan)

| Spec | Cubierto en | Nota |
|---|---|---|
| F4 CA1 (8×12,5 %: nota 5 aporta 12,5 %) | Task 1, 3 | `aporta` = peso×pct |
| F4 CA2 (pendiente como "-", total parcial) | Task 3 | etiqueta "parcial" |
| F4 CA3 (proyección con test numérico) | Task 1 | 40 acumulado → 60 % requerido |
| F4 CA4 (suma ≠ 100 rechazada) | Task 2 | también pesos incompletos |
| F4 CA5 (aislamiento entre estudiantes) | Task 3 | solo `Auth::user()`; el servicio recibe el usuario explícito |
| F2 CA1–CA5 | Task 5, 6 | CA3 (importa en Google Calendar) verificado a mano; test de formato RFC 5545 |
| F6 CA1–CA4 | Task 7 | CA4 reinterpretado (Decisión 7) |
| F9 CA1 (job idempotente) | Task 8 | CA2/CA3 ya existen (aviso al calificar, "marcar todas") |
| FE-2, FE-3, FE-5 | Task 7, 7, 4 | |
| F4: nota actual en tarjeta (diferida del Sprint A) | Task 4 | |

Consistencia de nombres revisada: `NotaCursoService::para` (claves `filas`, `modo`, `acumulado`, `peso_calificado`, `peso_total`, `promedio_a_la_fecha`, `aprobatoria`, `requerido`, `alcanzable`), `Curso::actividadesCalificables`, `LineaDeTiempoService::actividadesEnRango`, `CalendarioService::eventos` (campos `uid`, `id`, `tipo`, `inicia`, `fin`, `titulo`, `detalle`, `url`), `IcsService::generar`, rutas `cursos.ponderaciones.*`, `calendario.*`, `cursos.ficha.*`.

## Requiere acción de ops (fuera del plan)

- **Scheduler en producción:** Railway arranca con `php artisan serve` y no ejecuta `schedule:run`; los avisos de vencimiento no saldrán hasta crear un cron/servicio que corra `php artisan schedule:run` cada minuto (o un proceso `schedule:work`). Cambiar `railway.toml` o crear ese servicio es un cambio de despliegue: decidirlo con el responsable antes de hacerlo.
- Correr `php artisan migrate` (4 migraciones nuevas) y, si no se hizo, definir `LMS_SOPORTE_*`.

## Fuera de este plan (Sprint C, plan aparte)

F7 competencias/REA, F10 panel de seguimiento del instructor, F11 modo edición; eventos de curso creados por el instructor; edición de eventos personales; notificación de "nueva actividad" (requeriría un estado publicada/borrador en actividades).
