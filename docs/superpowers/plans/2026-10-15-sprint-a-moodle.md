# Sprint A (Moodle UCundinamarca) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Entregar el Sprint A de la spec: línea de tiempo de entregas (F1), progreso y tarjeta de curso (F3), convención ronda/actividad/semana (F5) y botón de ayuda (F8), evitando los fallos FE-1, FE-2, FE-4 y FE-5.

**Architecture:** Un servicio nuevo (`LineaDeTiempoService`) concentra la consulta "entregas próximas/atrasadas de un usuario" y la reutilizan la página `/linea-de-tiempo`, el dashboard del estudiante y las tarjetas de curso (`ResumenCursosService`). Los campos nuevos (`ronda`, `semana`, `numero_actividad`, `codigo`, `grupo`) son columnas nullable: nada existente se rompe. La ayuda lee de `config/lms.php`.

**Tech Stack:** Laravel 12, PHP 8.2, Blade + Tailwind + Alpine, PHPUnit 11 con SQLite en memoria (`php artisan test`).

**Spec:** `docs/SPEC_FUNCIONES_MOODLE.md` (secciones F1, F3, F5, F8 y FE-1…FE-6).

## Global Constraints

- Tests: solo `php artisan test` (SQLite en memoria). **Nunca** `migrate:fresh` ni `--env=testing` contra la BD local (`dyl_lms` real).
- Las migraciones nuevas son aditivas (columnas `nullable`, sin borrar ni renombrar nada).
- Textos de interfaz en español; nombres de tablas/columnas en español como el resto del repo.
- Autorización: el estudiante solo ve datos de sus propias inscripciones (RNF-3); rutas dentro del grupo `auth`.
- Consultas sin N+1 (RNF-2): `with(...)` y consultas agrupadas.
- Hora: se muestra **tal como está guardada** (igual que el resto de la app; `config/app.php` usa `UTC` y los formularios guardan la hora escrita). No convertir de zona en Sprint A; ver "Decisiones" abajo.
- Estilo: clases Tailwind del repo (`dyl-orange-600`, `dyl-graphite-*`), `alert alert-*`, componentes Blade existentes.
- Commits pequeños en español, terminan con `Co-Authored-By: Claude Sonnet 5.5 <noreply@anthropic.com>`.

## Decisiones tomadas (ajustes respecto a la spec)

1. **RNF-1 (hora de Bogotá):** `config/app.php` está en `UTC` y las fechas de actividad se guardan como las escribió el instructor. Convertir mostraría horas corridas 5 h. Sprint A no convierte; si se quiere, se decide aparte y se migra todo junto.
2. **"Nota actual" en la tarjeta (FE-5):** depende de la ponderación (F4, Sprint B). En Sprint A la tarjeta muestra progreso y próxima entrega; la nota se agrega en Sprint B.
3. **Prefijo `R#-A#-S#`:** el trigger `creating` de `Actividad` extrae el prefijo de títulos nuevos y lo guarda en los campos; así cubre el formulario, el importador de cursos y futuros caminos sin tocar cada uno.
4. **Marcar como hecha:** ya existe para actividades sin nota (`actividades.completar`). Falta poder **desmarcar** (Task 6).

## Review Focus

Entradas/condiciones que la spec implica y ninguna prueba "obvia" cubriría; cada una tiene su test en la tarea indicada:

1. Título con prefijo parecido pero inválido (`R0-A1-S1 x`, `R2-A3 x`, solo prefijo sin título) no debe romper ni vaciar el título → Task 1.
2. Actividad con `fecha_cierre` pero en curso no publicado o sin inscripción no debe aparecer → Task 3.
3. Actividad ya entregada, o marcada hecha, vencida: no es "atrasada" → Task 3.
4. Curso sin lecciones: tarjeta dice "Sin contenido aún", nunca "0 %" ni división por cero → Task 5.
5. Ninguna vista debe mostrar `{{` o `}}` literal ni líneas vacías cuando faltan correo/horario de soporte (FE-2) → Task 2.

---

## File Structure

| Archivo | Acción | Responsabilidad |
|---|---|---|
| `database/migrations/2026_10_16_000001_add_ronda_semana_to_actividades_table.php` | crear | columnas `ronda`, `semana`, `numero_actividad` |
| `database/migrations/2026_10_16_000002_add_codigo_grupo_to_cursos_table.php` | crear | columnas `codigo`, `grupo` (FE-1) |
| `app/Models/Actividad.php` | modificar | fillable, `prefijo()`, `tituloCompleto()`, hook `creating`, `estaCompletadaPor()` |
| `app/Models/Curso.php` | modificar | fillable `codigo`, `grupo`; `subtitulo()` |
| `app/Http/Controllers/ActividadController.php` | modificar | validar campos nuevos; `descompletar()` |
| `app/Services/LineaDeTiempoService.php` | crear | consulta de entregas próximas/atrasadas y próxima entrega por curso |
| `app/Services/ResumenCursosService.php` | crear | % de progreso y próxima entrega por curso |
| `app/Http/Controllers/LineaDeTiempoController.php` | crear | página `/linea-de-tiempo` |
| `app/Http/Controllers/DashboardController.php` | modificar | pasa `proximas` al dashboard del estudiante |
| `app/Http/Controllers/CursoController.php` | modificar | `index` pasa resumen; `store/update` aceptan `codigo`, `grupo` |
| `config/lms.php` | crear | datos de soporte |
| `resources/views/linea-de-tiempo/index.blade.php` | crear | página completa |
| `resources/views/partials/linea-de-tiempo.blade.php` | crear | bloque reutilizable (dashboard y página) |
| `resources/views/partials/ayuda-flotante.blade.php` | crear | botón + panel de ayuda |
| `resources/views/layouts/app.blade.php` | modificar | incluye ayuda flotante |
| `resources/views/layouts/partials/sidebar.blade.php` | modificar | enlace "Línea de tiempo" |
| `resources/views/actividades/{create,edit,show}.blade.php` | modificar | campos y título con prefijo; botón desmarcar |
| `resources/views/cursos/{index,create,edit}.blade.php`, `dashboard/estudiante.blade.php` | modificar | tarjeta con progreso/próxima entrega; campos código/grupo |
| `routes/web.php` | modificar | `/linea-de-tiempo`, `actividades.descompletar` |
| `tests/Feature/ActividadRondaSemanaTest.php`, `AyudaFlotanteTest.php`, `LineaDeTiempoTest.php`, `CursosTarjetaResumenTest.php`, `ActividadDescompletarTest.php` | crear | tests de cada tarea |

Comando de test usado en todo el plan (desde la raíz del worktree): `php artisan test --filter=<Nombre>`.

---

### Task 1: Ronda / actividad / semana (F5)

**Files:**
- Create: `database/migrations/2026_10_16_000001_add_ronda_semana_to_actividades_table.php`
- Modify: `app/Models/Actividad.php` (fillable, casts, hook, métodos), `app/Http/Controllers/ActividadController.php` (store ~línea 29, update ~línea 159)
- Modify: `resources/views/actividades/create.blade.php` (antes del checkbox `es_obligatoria`, ~línea 106), `edit.blade.php` (antes del checkbox, ~línea 90), `show.blade.php:2` y `:27`
- Test: `tests/Feature/ActividadRondaSemanaTest.php`

**Interfaces:**
- Produces: columnas `actividades.ronda|semana|numero_actividad` (`unsignedTinyInteger` nullable); `Actividad::prefijo(): ?string` (ej. `"R2-A3-S9"`, solo partes presentes, `null` si no hay ninguna); `Actividad::tituloCompleto(): string` (`"R2-A3-S9 · Título"` o solo el título).
- Consumes: nada.

- [ ] **Step 1: Escribir el test que falla**

```php
<?php

namespace Tests\Feature;

use App\Models\Actividad;
use App\Models\Leccion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\ArmaCursoCompleto;
use Tests\TestCase;

class ActividadRondaSemanaTest extends TestCase
{
    use RefreshDatabase, ArmaCursoCompleto;

    public function test_prefijo_se_compone_con_las_partes_presentes(): void
    {
        $a = Actividad::factory()->make(['titulo' => 'Puentes', 'ronda' => 2, 'numero_actividad' => 3, 'semana' => 9]);
        $this->assertSame('R2-A3-S9', $a->prefijo());
        $this->assertSame('R2-A3-S9 · Puentes', $a->tituloCompleto());

        $b = Actividad::factory()->make(['titulo' => 'Solo semana', 'semana' => 4]);
        $this->assertSame('S4', $b->prefijo());

        $c = Actividad::factory()->make(['titulo' => 'Sin nada']);
        $this->assertNull($c->prefijo());
        $this->assertSame('Sin nada', $c->tituloCompleto());
    }

    public function test_crear_con_titulo_prefijado_rellena_campos_y_limpia_titulo(): void
    {
        $leccion = Leccion::factory()->create();
        $a = Actividad::factory()->create([
            'leccion_id' => $leccion->id,
            'titulo'     => 'R2-A2-S10 Miremos la seguridad',
        ]);

        $this->assertSame(2, $a->ronda);
        $this->assertSame(2, $a->numero_actividad);
        $this->assertSame(10, $a->semana);
        $this->assertSame('Miremos la seguridad', $a->titulo);
    }

    #[DataProvider('titulosNoPrefijados')]
    public function test_titulos_con_prefijo_invalido_se_dejan_intactos(string $titulo): void
    {
        $a = Actividad::factory()->create(['titulo' => $titulo]);

        $this->assertSame($titulo, $a->titulo);
        $this->assertNull($a->ronda);
        $this->assertNull($a->semana);
    }

    public static function titulosNoPrefijados(): array
    {
        return [
            'ronda cero'       => ['R0-A1-S1 Algo'],
            'falta semana'     => ['R2-A3 Algo'],
            'solo el prefijo'  => ['R2-A3-S9'],
            'semana fuera'     => ['R2-A3-S99 Algo'],
        ];
    }

    public function test_instructor_guarda_y_valida_los_campos_desde_el_formulario(): void
    {
        $instructor = $this->instructor();
        $curso      = $this->cursoCompleto($instructor);
        $leccion    = $curso->lecciones()->first();

        $this->actingAs($instructor)->post(route('actividades.store', $leccion), [
            'titulo' => 'Tarea', 'tipo' => 'tarea', 'puntaje_maximo' => 5,
            'ronda' => 2, 'numero_actividad' => 1, 'semana' => 10,
        ])->assertRedirect();

        $this->assertDatabaseHas('actividades', ['titulo' => 'Tarea', 'ronda' => 2, 'semana' => 10]);

        $this->actingAs($instructor)->post(route('actividades.store', $leccion), [
            'titulo' => 'Mala', 'tipo' => 'tarea', 'puntaje_maximo' => 5, 'ronda' => 10, 'semana' => 53,
        ])->assertSessionHasErrors(['ronda', 'semana']);
    }
}
```

- [ ] **Step 2: Ejecutar y verificar que falla**

Run: `php artisan test --filter=ActividadRondaSemanaTest`
Expected: FAIL (`Call to undefined method ...prefijo()` / columna `ronda` inexistente).

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
        Schema::table('actividades', function (Blueprint $table) {
            $table->unsignedTinyInteger('ronda')->nullable()->after('titulo');
            $table->unsignedTinyInteger('numero_actividad')->nullable()->after('ronda');
            $table->unsignedTinyInteger('semana')->nullable()->after('numero_actividad');
        });
    }

    public function down(): void
    {
        Schema::table('actividades', function (Blueprint $table) {
            $table->dropColumn(['ronda', 'numero_actividad', 'semana']);
        });
    }
};
```

- [ ] **Step 4: Modelo `Actividad`**

En `$fillable` agregar `'ronda', 'numero_actividad', 'semana'`. Antes de `estadoPlazo()` agregar:

```php
    protected static function booted(): void
    {
        // Títulos tipo "R2-A3-S9 Título" (importados o escritos a mano) pasan a campos propios.
        static::creating(function (self $a) {
            if ($a->ronda !== null || $a->semana !== null || $a->numero_actividad !== null) return;

            if (preg_match('/^\s*R([1-9])-A(\d{1,2})-S(\d{1,2})\s+(\S.*)$/u', (string) $a->titulo, $m)
                && (int) $m[2] >= 1 && (int) $m[3] >= 1 && (int) $m[3] <= 52) {
                $a->ronda            = (int) $m[1];
                $a->numero_actividad = (int) $m[2];
                $a->semana           = (int) $m[3];
                $a->titulo           = trim($m[4]);
            }
        });
    }

    /** "R2-A3-S9" con las partes presentes; null si no hay ninguna. */
    public function prefijo(): ?string
    {
        $partes = array_filter([
            $this->ronda            ? "R{$this->ronda}"            : null,
            $this->numero_actividad ? "A{$this->numero_actividad}" : null,
            $this->semana           ? "S{$this->semana}"           : null,
        ]);

        return $partes ? implode('-', $partes) : null;
    }

    public function tituloCompleto(): string
    {
        $p = $this->prefijo();
        return $p ? "{$p} · {$this->titulo}" : $this->titulo;
    }
```

- [ ] **Step 5: Validación en el controlador**

En `store()` (junto a `'duracion_minutos'`) y en `update()` agregar las tres reglas; ambos ya hacen `...$validated`, así que se guardan solas:

```php
            'ronda'            => 'nullable|integer|between:1,9',
            'numero_actividad' => 'nullable|integer|between:1,99',
            'semana'           => 'nullable|integer|between:1,52',
```

- [ ] **Step 6: Vistas.** En `create.blade.php` y `edit.blade.php`, justo antes del bloque del checkbox `es_obligatoria`, insertar (en `edit` usar `$actividad->ronda` etc. como segundo argumento de `old`):

```blade
<div class="grid grid-cols-3 gap-4 mb-6">
    <div>
        <label class="block text-sm font-medium text-gray-700 mb-2">Ronda</label>
        <input type="number" name="ronda" min="1" max="9" value="{{ old('ronda', $actividad->ronda ?? null) }}"
               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-dyl-orange-600">
    </div>
    <div>
        <label class="block text-sm font-medium text-gray-700 mb-2">N.º de actividad</label>
        <input type="number" name="numero_actividad" min="1" max="99" value="{{ old('numero_actividad', $actividad->numero_actividad ?? null) }}"
               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-dyl-orange-600">
    </div>
    <div>
        <label class="block text-sm font-medium text-gray-700 mb-2">Semana</label>
        <input type="number" name="semana" min="1" max="52" value="{{ old('semana', $actividad->semana ?? null) }}"
               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-dyl-orange-600">
    </div>
</div>
```

En `show.blade.php` línea 2 usar `$actividad->tituloCompleto()` en lugar de `$actividad->titulo`, y en la línea 27 el `<h1>` igual.

- [ ] **Step 7: Ejecutar y verificar que pasa**

Run: `php artisan test --filter=ActividadRondaSemanaTest`
Expected: PASS. Luego `php artisan test` completo: sin regresiones (CursoExport/Import usan `$fillable`, los campos nuevos viajan solos).

- [ ] **Step 8: Commit**

```bash
git add database/migrations app/Models/Actividad.php app/Http/Controllers/ActividadController.php resources/views/actividades tests/Feature/ActividadRondaSemanaTest.php
git commit -m "feat: campos ronda/actividad/semana en actividades (F5)"
```

---

### Task 2: Botón de ayuda y soporte (F8, FE-2)

**Files:**
- Create: `config/lms.php`, `resources/views/partials/ayuda-flotante.blade.php`
- Modify: `resources/views/layouts/app.blade.php` (justo antes del cierre `</body>`)
- Test: `tests/Feature/AyudaFlotanteTest.php`

**Interfaces:**
- Produces: `config('lms.soporte.correo|url|horario|faq_url')` (todos opcionales, `null` por defecto vía `env`).
- Consumes: nada.

- [ ] **Step 1: Test que falla**

```php
<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ArmaCursoCompleto;
use Tests\TestCase;

class AyudaFlotanteTest extends TestCase
{
    use RefreshDatabase, ArmaCursoCompleto;

    public function test_el_boton_aparece_en_el_dashboard_con_los_datos_configurados(): void
    {
        config(['lms.soporte' => [
            'correo' => 'soporte@dyl.test', 'url' => null,
            'horario' => 'Lunes a viernes, 8:00 am a 6:00 pm', 'faq_url' => null,
        ]]);

        $this->actingAs($this->estudiante())->get('/dashboard')
            ->assertOk()
            ->assertSee('Ayuda')
            ->assertSee('soporte@dyl.test')
            ->assertSee('Lunes a viernes, 8:00 am a 6:00 pm');
    }

    public function test_sin_datos_no_se_muestran_lineas_vacias_ni_variables_literales(): void
    {
        config(['lms.soporte' => ['correo' => null, 'url' => null, 'horario' => null, 'faq_url' => null]]);

        $html = $this->actingAs($this->estudiante())->get('/dashboard')->assertOk()->getContent();

        $this->assertStringNotContainsString('{{', $html);
        $this->assertStringNotContainsString('mailto:"', $html);
        $this->assertStringNotContainsString('Horario de atención:</', $html);
    }
}
```

- [ ] **Step 2: Verificar que falla**

Run: `php artisan test --filter=AyudaFlotanteTest` → FAIL (no aparece "Ayuda").

- [ ] **Step 3: `config/lms.php`**

```php
<?php

return [
    'soporte' => [
        'correo'  => env('LMS_SOPORTE_CORREO'),
        'url'     => env('LMS_SOPORTE_URL'),
        'horario' => env('LMS_SOPORTE_HORARIO'),
        'faq_url' => env('LMS_SOPORTE_FAQ_URL'),
    ],
];
```

- [ ] **Step 4: Parcial**

```blade
@php $s = config('lms.soporte'); @endphp
<div x-data="{ abierto: false }" class="fixed bottom-4 right-4 z-30 print:hidden">
    <div x-show="abierto" x-cloak @keydown.escape.window="abierto = false"
         role="dialog" aria-label="Ayuda y soporte"
         class="mb-2 w-72 rounded-xl bg-white shadow-xl border border-gray-200 p-4 text-sm text-gray-700">
        <p class="font-semibold text-gray-900 mb-2">¿Necesitas ayuda?</p>
        <ul class="space-y-1.5">
            @if(!empty($s['url']))
                <li><a href="{{ $s['url'] }}" target="_blank" rel="noopener" class="text-dyl-orange-700 hover:underline">Centro de soporte</a></li>
            @endif
            @if(!empty($s['correo']))
                <li><a href="mailto:{{ $s['correo'] }}" class="text-dyl-orange-700 hover:underline">{{ $s['correo'] }}</a></li>
            @endif
            @if(!empty($s['faq_url']))
                <li><a href="{{ $s['faq_url'] }}" target="_blank" rel="noopener" class="text-dyl-orange-700 hover:underline">Preguntas frecuentes</a></li>
            @endif
            @if(!empty($s['horario']))
                <li class="text-xs text-gray-500 pt-1">Horario de atención: {{ $s['horario'] }}</li>
            @endif
            @if(empty($s['url']) && empty($s['correo']) && empty($s['faq_url']) && empty($s['horario']))
                <li class="text-gray-500">Escribe a tu instructor desde Mensajes.</li>
            @endif
        </ul>
    </div>
    <button type="button" @click="abierto = !abierto" :aria-expanded="abierto"
            class="ml-auto flex items-center gap-1.5 rounded-full bg-dyl-orange-600 px-4 py-2 text-sm font-medium text-white shadow-lg hover:bg-dyl-orange-700 focus:outline-none focus:ring-2 focus:ring-dyl-orange-600 focus:ring-offset-2">
        <span aria-hidden="true">?</span> Ayuda
    </button>
</div>
```

- [ ] **Step 5: Incluir en el layout.** En `layouts/app.blade.php`, antes de `</body>` añadir `@include('partials.ayuda-flotante')`. (Abrir el archivo y localizar el cierre; no incluirlo en `layouts/guest.blade.php`.)

- [ ] **Step 6: Verificar que pasa**

Run: `php artisan test --filter=AyudaFlotanteTest` → PASS.

- [ ] **Step 7: Commit**

```bash
git add config/lms.php resources/views/partials/ayuda-flotante.blade.php resources/views/layouts/app.blade.php tests/Feature/AyudaFlotanteTest.php
git commit -m "feat: boton flotante de ayuda y soporte configurable (F8)"
```

---

### Task 3: `LineaDeTiempoService` (F1, núcleo)

**Files:**
- Create: `app/Services/LineaDeTiempoService.php`
- Test: `tests/Feature/LineaDeTiempoTest.php` (parte de servicio; la parte de página se añade en Task 4)

**Interfaces:**
- Produces:
  - `LineaDeTiempoService::para(User $user, int $dias = 7, ?int $cursoId = null, bool $incluirEntregadas = false, ?string $buscar = null, ?int $ronda = null): array{atrasadas: Collection, dias: Collection}`
    - Cada ítem es un `stdClass` con `actividad` (Actividad con `leccion.modulo.curso` cargados), `curso` (Curso), `estado` (`'entregada'|'en_progreso'|'pendiente'`), `accion` (`'Entregar'|'Responder'|'Continuar'|'Ver'`).
    - `dias` es una Collection **keyed por fecha `Y-m-d`** de listas de ítems, ordenada ascendente por fecha y hora de cierre.
  - `LineaDeTiempoService::proximaEntregaPorCurso(User $user): Collection` → `curso_id => Carbon` (fecha de cierre más cercana ≥ ahora de una actividad no entregada).
- Consumes: modelos `Actividad`, `RespuestaEstudiante`, `ProgresoActividad`, `IntentoEnProgreso`, `Inscripcion`.

- [ ] **Step 1: Test del servicio (falla)**

```php
<?php

namespace Tests\Feature;

use App\Models\Actividad;
use App\Models\Curso;
use App\Models\Inscripcion;
use App\Models\IntentoEnProgreso;
use App\Models\Leccion;
use App\Models\Modulo;
use App\Models\ProgresoActividad;
use App\Models\RespuestaEstudiante;
use App\Models\User;
use App\Services\LineaDeTiempoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ArmaCursoCompleto;
use Tests\TestCase;

class LineaDeTiempoTest extends TestCase
{
    use RefreshDatabase, ArmaCursoCompleto;

    private function actividadEn(Curso $curso, array $attrs = []): Actividad
    {
        $modulo  = Modulo::factory()->create(['curso_id' => $curso->id]);
        $leccion = Leccion::factory()->create(['modulo_id' => $modulo->id]);

        return Actividad::factory()->tarea()->create($attrs + ['leccion_id' => $leccion->id]);
    }

    private function inscrito(?Curso $curso = null): array
    {
        $user  = $this->estudiante();
        $curso = $curso ?? Curso::factory()->publicado()->create();
        Inscripcion::factory()->create(['user_id' => $user->id, 'curso_id' => $curso->id]);

        return [$user, $curso];
    }

    public function test_agrupa_por_dia_y_ordena_por_hora(): void
    {
        [$user, $curso] = $this->inscrito();
        $tarde  = $this->actividadEn($curso, ['titulo' => 'Tarde',  'fecha_cierre' => now()->addDays(2)->setTime(23, 59)]);
        $pronto = $this->actividadEn($curso, ['titulo' => 'Pronto', 'fecha_cierre' => now()->addDays(2)->setTime(18, 0)]);
        $otro   = $this->actividadEn($curso, ['titulo' => 'Otro',   'fecha_cierre' => now()->addDay()]);

        $r = app(LineaDeTiempoService::class)->para($user);

        $this->assertCount(2, $r['dias']);
        $this->assertSame(['Otro'], $r['dias']->first()->pluck('actividad.titulo')->all());
        $this->assertSame(['Pronto', 'Tarde'], $r['dias']->last()->pluck('actividad.titulo')->all());
    }

    public function test_excluye_fuera_de_ventana_sin_inscripcion_y_curso_no_publicado(): void
    {
        [$user, $curso] = $this->inscrito();
        $this->actividadEn($curso, ['titulo' => 'Lejana', 'fecha_cierre' => now()->addDays(20)]);
        $this->actividadEn($curso, ['titulo' => 'SinFecha', 'fecha_cierre' => null]);

        $ajeno = Curso::factory()->publicado()->create();
        $this->actividadEn($ajeno, ['titulo' => 'Ajena', 'fecha_cierre' => now()->addDay()]);

        $borrador = Curso::factory()->borrador()->create();
        Inscripcion::factory()->create(['user_id' => $user->id, 'curso_id' => $borrador->id]);
        $this->actividadEn($borrador, ['titulo' => 'Borrador', 'fecha_cierre' => now()->addDay()]);

        $r = app(LineaDeTiempoService::class)->para($user);

        $this->assertCount(0, $r['dias']);
        $this->assertCount(0, $r['atrasadas']);
        $this->assertCount(1, app(LineaDeTiempoService::class)->para($user, 30)['dias']);
    }

    public function test_entregadas_se_ocultan_por_defecto_y_no_son_atrasadas(): void
    {
        [$user, $curso] = $this->inscrito();
        $entregada = $this->actividadEn($curso, ['titulo' => 'Entregada', 'fecha_cierre' => now()->addDay()]);
        $hechaVencida = $this->actividadEn($curso, ['titulo' => 'HechaVencida', 'fecha_cierre' => now()->subDay()]);
        $vencida = $this->actividadEn($curso, ['titulo' => 'Vencida', 'fecha_cierre' => now()->subDay()]);

        RespuestaEstudiante::factory()->create(['user_id' => $user->id, 'actividad_id' => $entregada->id]);
        ProgresoActividad::create(['user_id' => $user->id, 'actividad_id' => $hechaVencida->id, 'completado' => true, 'fecha_completado' => now()]);

        $svc = app(LineaDeTiempoService::class);
        $r   = $svc->para($user);

        $this->assertCount(0, $r['dias']);
        $this->assertSame(['Vencida'], $r['atrasadas']->pluck('actividad.titulo')->all());

        $todas = $svc->para($user, 7, null, true);
        $this->assertSame('entregada', $todas['dias']->first()->first()->estado);
        $this->assertSame('Ver', $todas['dias']->first()->first()->accion);
    }

    public function test_acciones_segun_estado_y_tipo(): void
    {
        [$user, $curso] = $this->inscrito();
        $tarea = $this->actividadEn($curso, ['fecha_cierre' => now()->addDay()]);
        $cuest = Actividad::factory()->cuestionario()->create([
            'leccion_id' => $tarea->leccion_id, 'fecha_cierre' => now()->addDays(2),
        ]);
        $enCurso = Actividad::factory()->cuestionario()->create([
            'leccion_id' => $tarea->leccion_id, 'fecha_cierre' => now()->addDays(3),
        ]);
        IntentoEnProgreso::create(['user_id' => $user->id, 'actividad_id' => $enCurso->id, 'fecha_inicio' => now()]);

        $items = app(LineaDeTiempoService::class)->para($user)['dias']->flatten(1)->keyBy('actividad.id');

        $this->assertSame('Entregar',  $items[$tarea->id]->accion);
        $this->assertSame('Responder', $items[$cuest->id]->accion);
        $this->assertSame('Continuar', $items[$enCurso->id]->accion);
    }

    public function test_filtros_por_curso_texto_y_ronda(): void
    {
        [$user, $curso] = $this->inscrito();
        $otroCurso = Curso::factory()->publicado()->create();
        Inscripcion::factory()->create(['user_id' => $user->id, 'curso_id' => $otroCurso->id]);

        $this->actividadEn($curso,     ['titulo' => 'Infografia', 'ronda' => 2, 'fecha_cierre' => now()->addDay()]);
        $this->actividadEn($otroCurso, ['titulo' => 'Redes',      'ronda' => 3, 'fecha_cierre' => now()->addDay()]);

        $svc = app(LineaDeTiempoService::class);
        $titulos = fn($r) => $r['dias']->flatten(1)->pluck('actividad.titulo')->all();

        $this->assertSame(['Infografia'], $titulos($svc->para($user, 7, $curso->id)));
        $this->assertSame(['Redes'],      $titulos($svc->para($user, 7, null, false, 'red')));
        $this->assertSame(['Redes'],      $titulos($svc->para($user, 7, null, false, null, 3)));
    }

    public function test_proxima_entrega_por_curso(): void
    {
        [$user, $curso] = $this->inscrito();
        $this->actividadEn($curso, ['fecha_cierre' => now()->addDays(5)]);
        $cercana = $this->actividadEn($curso, ['fecha_cierre' => now()->addDays(2)]);
        $this->actividadEn($curso, ['fecha_cierre' => now()->subDay()]);

        $mapa = app(LineaDeTiempoService::class)->proximaEntregaPorCurso($user);

        $this->assertTrue($mapa[$curso->id]->equalTo($cercana->fecha_cierre));
    }
}
```

Nota: `RespuestaEstudiante::factory()` ya existe (`database/factories/RespuestaEstudianteFactory.php`); revisar que su `definition()` acepte `user_id`/`actividad_id` por override (lo hace por convención).

- [ ] **Step 2: Verificar que falla**

Run: `php artisan test --filter=LineaDeTiempoTest` → FAIL (clase `LineaDeTiempoService` no existe).

- [ ] **Step 3: Implementar el servicio**

```php
<?php

namespace App\Services;

use App\Models\Actividad;
use App\Models\IntentoEnProgreso;
use App\Models\ProgresoActividad;
use App\Models\RespuestaEstudiante;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class LineaDeTiempoService
{
    private const MAX_ATRASADAS = 50;

    /** @return array{atrasadas: Collection, dias: Collection} */
    public function para(
        User $user,
        int $dias = 7,
        ?int $cursoId = null,
        bool $incluirEntregadas = false,
        ?string $buscar = null,
        ?int $ronda = null,
    ): array {
        $entregadas = $this->idsEntregados($user);
        $enProgreso = IntentoEnProgreso::where('user_id', $user->id)->pluck('actividad_id')->flip();

        $base = fn() => $this->actividadesVisibles($user, $cursoId, $buscar, $ronda);

        $proximas = $base()
            ->whereBetween('fecha_cierre', [now(), now()->addDays($dias)->endOfDay()])
            ->orderBy('fecha_cierre')->get()
            ->map(fn($a) => $this->item($a, $entregadas, $enProgreso))
            ->when(!$incluirEntregadas, fn($c) => $c->reject(fn($i) => $i->estado === 'entregada'));

        $atrasadas = $base()
            ->where('fecha_cierre', '<', now())
            ->orderByDesc('fecha_cierre')->limit(self::MAX_ATRASADAS * 2)->get()
            ->map(fn($a) => $this->item($a, $entregadas, $enProgreso))
            ->reject(fn($i) => $i->estado === 'entregada')
            ->take(self::MAX_ATRASADAS)->values();

        return [
            'atrasadas' => $atrasadas,
            'dias'      => $proximas->groupBy(fn($i) => $i->actividad->fecha_cierre->format('Y-m-d'))->sortKeys(),
        ];
    }

    /** curso_id => fecha de cierre más cercana (futura) de una actividad aún no entregada. */
    public function proximaEntregaPorCurso(User $user): Collection
    {
        $entregadas = $this->idsEntregados($user);

        return $this->actividadesVisibles($user)
            ->where('fecha_cierre', '>=', now())
            ->orderBy('fecha_cierre')->get()
            ->reject(fn($a) => isset($entregadas[$a->id]))
            ->groupBy(fn($a) => $a->leccion->modulo->curso_id)
            ->map(fn($g) => $g->first()->fecha_cierre);
    }

    private function actividadesVisibles(User $user, ?int $cursoId = null, ?string $buscar = null, ?int $ronda = null): Builder
    {
        return Actividad::query()
            ->with('leccion.modulo.curso')
            ->whereNotNull('fecha_cierre')
            ->whereHas('leccion.modulo.curso', function (Builder $q) use ($user, $cursoId) {
                $q->where('estado', 'publicado')
                  ->whereHas('inscripciones', fn($i) => $i->where('user_id', $user->id))
                  ->when($cursoId, fn($q2) => $q2->where('cursos.id', $cursoId));
            })
            ->when($buscar, fn($q) => $q->where(fn($w) => $w
                ->where('titulo', 'like', "%{$buscar}%")->orWhere('tipo', 'like', "%{$buscar}%")))
            ->when($ronda, fn($q) => $q->where('ronda', $ronda));
    }

    /** Collection flip: actividad_id => índice (isset() para chequear). */
    private function idsEntregados(User $user): Collection
    {
        return RespuestaEstudiante::where('user_id', $user->id)->pluck('actividad_id')
            ->merge(ProgresoActividad::where('user_id', $user->id)->where('completado', true)->pluck('actividad_id'))
            ->flip();
    }

    private function item(Actividad $a, Collection $entregadas, Collection $enProgreso): \stdClass
    {
        $estado = isset($entregadas[$a->id]) ? 'entregada' : (isset($enProgreso[$a->id]) ? 'en_progreso' : 'pendiente');

        return (object) [
            'actividad' => $a,
            'curso'     => $a->leccion->modulo->curso,
            'estado'    => $estado,
            'accion'    => match (true) {
                $estado === 'entregada'   => 'Ver',
                $estado === 'en_progreso' => 'Continuar',
                in_array($a->tipo, Actividad::TIPOS_CON_ENTREGA_ARCHIVO, true) => 'Entregar',
                default                   => 'Responder',
            },
        ];
    }
}
```

- [ ] **Step 4: Verificar que pasa**

Run: `php artisan test --filter=LineaDeTiempoTest` → PASS (6 tests). Si `RespuestaEstudiante::factory()` exige campos extra, ajustar el `create([...])` del test, no el servicio.

- [ ] **Step 5: Commit**

```bash
git add app/Services/LineaDeTiempoService.php tests/Feature/LineaDeTiempoTest.php
git commit -m "feat: servicio de linea de tiempo de entregas (F1)"
```

---

### Task 4: Página, bloque de dashboard y enlace de menú (F1, FE-4)

**Files:**
- Create: `app/Http/Controllers/LineaDeTiempoController.php`, `resources/views/linea-de-tiempo/index.blade.php`, `resources/views/partials/linea-de-tiempo.blade.php`
- Modify: `routes/web.php` (grupo `auth`, junto a `/dashboard`), `app/Http/Controllers/DashboardController.php` (`dashboardEstudiante`), `resources/views/dashboard/estudiante.blade.php`, `resources/views/layouts/partials/sidebar.blade.php` (enlace tras "Cursos", solo estudiante)
- Test: ampliar `tests/Feature/LineaDeTiempoTest.php`

**Interfaces:**
- Consumes: `LineaDeTiempoService::para(...)` (Task 3).
- Produces: ruta `linea-de-tiempo.index` (`GET /linea-de-tiempo`, query: `dias` ∈ {7,30,180}, `curso`, `buscar`, `ronda`, `entregadas=1`); parcial `partials.linea-de-tiempo` que recibe `$lineaDeTiempo` (`['atrasadas','dias']`) y `$compacto` (bool).

- [ ] **Step 1: Tests de página (fallan).** Añadir a `LineaDeTiempoTest`:

```php
    public function test_pagina_muestra_entregas_atrasadas_y_accion(): void
    {
        [$user, $curso] = $this->inscrito();
        $this->actividadEn($curso, ['titulo' => 'Puentes invisibles', 'fecha_cierre' => now()->addDay()->setTime(18, 0)]);
        $this->actividadEn($curso, ['titulo' => 'Tarea vieja', 'fecha_cierre' => now()->subDays(2)]);

        $this->actingAs($user)->get(route('linea-de-tiempo.index'))
            ->assertOk()
            ->assertSee('Puentes invisibles')
            ->assertSee('18:00')
            ->assertSee('Entregar')
            ->assertSee('Atrasadas')
            ->assertSee('Tarea vieja');
    }

    public function test_pagina_vacia_muestra_mensaje(): void
    {
        [$user] = $this->inscrito();

        $this->actingAs($user)->get(route('linea-de-tiempo.index'))
            ->assertOk()->assertSee('No tienes entregas próximas');
    }

    public function test_dias_invalidos_caen_al_valor_por_defecto_y_requiere_login(): void
    {
        [$user] = $this->inscrito();

        $this->actingAs($user)->get(route('linea-de-tiempo.index', ['dias' => 'abc']))->assertOk();
        auth()->logout();
        $this->get(route('linea-de-tiempo.index'))->assertRedirect(route('login'));
    }

    public function test_dashboard_del_estudiante_incluye_el_bloque(): void
    {
        [$user, $curso] = $this->inscrito();
        $this->actividadEn($curso, ['titulo' => 'Vence pronto', 'fecha_cierre' => now()->addDay()]);

        $this->actingAs($user)->get('/dashboard')->assertOk()
            ->assertSee('Próximas entregas')->assertSee('Vence pronto');
    }
```

- [ ] **Step 2: Verificar que falla** — `php artisan test --filter=LineaDeTiempoTest` → FAIL (ruta no definida).

- [ ] **Step 3: Controlador**

```php
<?php

namespace App\Http\Controllers;

use App\Models\Inscripcion;
use App\Services\LineaDeTiempoService;
use Illuminate\Http\Request;

class LineaDeTiempoController extends Controller
{
    public function index(Request $request, LineaDeTiempoService $servicio)
    {
        $user = $request->user();
        $dias = in_array((int) $request->query('dias'), [7, 30, 180], true) ? (int) $request->query('dias') : 7;

        $lineaDeTiempo = $servicio->para(
            $user,
            $dias,
            $request->integer('curso') ?: null,
            $request->boolean('entregadas'),
            $request->filled('buscar') ? trim($request->query('buscar')) : null,
            $request->integer('ronda') ?: null,
        );

        $cursos = Inscripcion::with('curso:id,titulo')->where('user_id', $user->id)->get()
            ->pluck('curso')->filter()->sortBy('titulo');

        return view('linea-de-tiempo.index', compact('lineaDeTiempo', 'cursos', 'dias'));
    }
}
```

- [ ] **Step 4: Ruta.** En `routes/web.php`, dentro del grupo `auth` junto a `/dashboard`:

```php
    Route::get('/linea-de-tiempo', [\App\Http\Controllers\LineaDeTiempoController::class, 'index'])->name('linea-de-tiempo.index');
```

- [ ] **Step 5: Parcial reutilizable** `partials/linea-de-tiempo.blade.php`:

```blade
@php $compacto = $compacto ?? false; @endphp

@if($lineaDeTiempo['atrasadas']->isNotEmpty())
<div class="mb-6 rounded-xl border border-red-200 bg-red-50 p-4">
    <h3 class="font-semibold text-red-800 mb-2">Atrasadas ({{ $lineaDeTiempo['atrasadas']->count() }})</h3>
    <ul class="divide-y divide-red-100">
        @foreach($lineaDeTiempo['atrasadas'] as $i)
            @include('partials.linea-de-tiempo-item', ['i' => $i, 'conFecha' => true])
        @endforeach
    </ul>
</div>
@endif

@forelse($lineaDeTiempo['dias'] as $fecha => $items)
    <div class="mb-5">
        <h3 class="text-sm font-semibold text-gray-700 mb-2">
            {{ \Carbon\Carbon::parse($fecha)->locale('es')->isoFormat('dddd, D [de] MMMM [de] YYYY') }}
        </h3>
        <ul class="bg-white rounded-xl shadow divide-y divide-gray-100">
            @foreach($items as $i)
                @include('partials.linea-de-tiempo-item', ['i' => $i, 'conFecha' => false])
            @endforeach
        </ul>
    </div>
@empty
    @if($lineaDeTiempo['atrasadas']->isEmpty())
        <div class="bg-white rounded-xl shadow p-8 text-center text-gray-500">No tienes entregas próximas.</div>
    @endif
@endforelse
```

y `partials/linea-de-tiempo-item.blade.php`:

```blade
<li class="flex items-center gap-4 px-4 py-3">
    <span class="w-16 shrink-0 text-sm font-medium text-gray-800">
        {{ $i->actividad->fecha_cierre->format($conFecha ? 'd/m H:i' : 'H:i') }}
    </span>
    <div class="min-w-0 flex-1">
        <p class="truncate text-sm font-medium text-gray-900">{{ $i->actividad->tituloCompleto() }}</p>
        <p class="truncate text-xs text-gray-500">
            {{ ucfirst($i->actividad->tipo) }} · {{ $i->curso->titulo }}
        </p>
    </div>
    <a href="{{ route('actividades.show', $i->actividad) }}"
       class="shrink-0 rounded-lg px-3 py-1.5 text-xs font-medium
              {{ $i->estado === 'entregada' ? 'border border-gray-300 text-gray-700 hover:bg-gray-50' : 'bg-dyl-orange-600 text-white hover:bg-dyl-orange-700' }}">
        {{ $i->accion }}
    </a>
</li>
```

- [ ] **Step 6: Página** `linea-de-tiempo/index.blade.php` (usar `Breadcrumbs` solo si ya existe una entrada; si no, omitir la sección `breadcrumbs`):

```blade
@extends('layouts.app')
@section('title', 'Línea de tiempo - D&L Quality Campus')
@section('content')
<h1 class="text-3xl font-bold text-gray-900 mb-6">Línea de tiempo</h1>

<form method="GET" class="flex flex-wrap items-end gap-3 mb-6">
    <label class="text-sm text-gray-700">Ventana
        <select name="dias" class="mt-1 block rounded-lg border-gray-300 text-sm" onchange="this.form.submit()">
            @foreach([7 => '7 días', 30 => '30 días', 180 => '6 meses'] as $v => $t)
                <option value="{{ $v }}" @selected($dias === $v)>{{ $t }}</option>
            @endforeach
        </select>
    </label>
    <label class="text-sm text-gray-700">Curso
        <select name="curso" class="mt-1 block rounded-lg border-gray-300 text-sm" onchange="this.form.submit()">
            <option value="">Todos</option>
            @foreach($cursos as $c)
                <option value="{{ $c->id }}" @selected((int) request('curso') === $c->id)>{{ $c->titulo }}</option>
            @endforeach
        </select>
    </label>
    <label class="text-sm text-gray-700">Ronda
        <input type="number" name="ronda" min="1" max="9" value="{{ request('ronda') }}" class="mt-1 block w-20 rounded-lg border-gray-300 text-sm">
    </label>
    <label class="text-sm text-gray-700">Buscar
        <input type="search" name="buscar" value="{{ request('buscar') }}" placeholder="Nombre o tipo" class="mt-1 block rounded-lg border-gray-300 text-sm">
    </label>
    <label class="flex items-center gap-2 text-sm text-gray-700">
        <input type="checkbox" name="entregadas" value="1" @checked(request()->boolean('entregadas')) class="rounded" onchange="this.form.submit()">
        Mostrar entregadas
    </label>
    <button class="rounded-lg bg-gray-100 px-3 py-2 text-sm text-gray-700 hover:bg-gray-200">Filtrar</button>
</form>

@include('partials.linea-de-tiempo', ['lineaDeTiempo' => $lineaDeTiempo])
@endsection
```

- [ ] **Step 7: Dashboard y sidebar.** En `DashboardController::dashboardEstudiante` antes del `return`:

```php
        $lineaDeTiempo = app(\App\Services\LineaDeTiempoService::class)->para($user);

        return view('dashboard.estudiante', compact('cursos_inscritos', 'stats', 'lineaDeTiempo'));
```

En `dashboard/estudiante.blade.php`, antes de `<h2 ...>Mis Cursos</h2>`:

```blade
<div class="flex items-center justify-between mb-3">
    <h2 class="text-xl font-bold text-gray-900">Próximas entregas</h2>
    <a href="{{ route('linea-de-tiempo.index') }}" class="text-sm text-dyl-orange-700 hover:underline">Ver todo</a>
</div>
<div class="mb-8">@include('partials.linea-de-tiempo', ['lineaDeTiempo' => $lineaDeTiempo, 'compacto' => true])</div>
```

En `sidebar.blade.php`, justo después del enlace "Cursos", un enlace con el mismo markup (icono SVG de calendario simple) a `route('linea-de-tiempo.index')`, activo con `request()->routeIs('linea-de-tiempo.*')`, envuelto en `@if(auth()->user()->esEstudiante())`.

- [ ] **Step 8: Verificar que pasa** — `php artisan test --filter=LineaDeTiempoTest` y `--filter=SidebarNavigationTest` → PASS.

- [ ] **Step 9: Commit**

```bash
git add app routes resources tests
git commit -m "feat: pagina y bloque de dashboard de linea de tiempo (F1)"
```

---

### Task 5: Tarjeta de curso con título limpio, progreso y próxima entrega (F3, FE-1, FE-5)

**Files:**
- Create: `database/migrations/2026_10_16_000002_add_codigo_grupo_to_cursos_table.php`, `app/Services/ResumenCursosService.php`
- Modify: `app/Models/Curso.php` (fillable + `subtitulo()`), `app/Http/Controllers/CursoController.php` (`index`, reglas de `store`/`update`), `resources/views/cursos/index.blade.php` (tarjeta "Mis Cursos"), `resources/views/cursos/create.blade.php` y `edit.blade.php` (inputs `codigo`, `grupo`), `resources/views/dashboard/estudiante.blade.php` (tarjeta)
- Test: `tests/Feature/CursosTarjetaResumenTest.php`

**Interfaces:**
- Produces: `cursos.codigo` (string 50 null), `cursos.grupo` (string 50 null); `Curso::subtitulo(): ?string` (`"CAD612021730 · Grupo 703M"`, partes presentes); `ResumenCursosService::para(User $user, Collection $cursos): array` → `curso_id => ['porcentaje' => ?int, 'proxima' => ?Carbon]` (`porcentaje` es `null` si el curso no tiene lecciones).
- Consumes: `LineaDeTiempoService::proximaEntregaPorCurso(User)` (Task 3).

- [ ] **Step 1: Test que falla**

```php
<?php

namespace Tests\Feature;

use App\Models\Actividad;
use App\Models\Curso;
use App\Models\Inscripcion;
use App\Models\Leccion;
use App\Models\Modulo;
use App\Models\ProgresoLeccion;
use App\Services\ResumenCursosService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ArmaCursoCompleto;
use Tests\TestCase;

class CursosTarjetaResumenTest extends TestCase
{
    use RefreshDatabase, ArmaCursoCompleto;

    public function test_subtitulo_combina_codigo_y_grupo(): void
    {
        $this->assertSame('CAD1 · Grupo 703M', Curso::factory()->make(['codigo' => 'CAD1', 'grupo' => '703M'])->subtitulo());
        $this->assertSame('CAD1', Curso::factory()->make(['codigo' => 'CAD1'])->subtitulo());
        $this->assertNull(Curso::factory()->make()->subtitulo());
    }

    public function test_resumen_calcula_porcentaje_y_proxima_entrega(): void
    {
        $user  = $this->estudiante();
        $curso = Curso::factory()->publicado()->create();
        Inscripcion::factory()->create(['user_id' => $user->id, 'curso_id' => $curso->id]);
        $modulo = Modulo::factory()->create(['curso_id' => $curso->id]);
        [$l1, $l2, $l3, $l4] = Leccion::factory()->count(4)->create(['modulo_id' => $modulo->id])->all();
        ProgresoLeccion::create(['user_id' => $user->id, 'leccion_id' => $l1->id, 'completado' => true, 'fecha_completado' => now()]);
        Actividad::factory()->tarea()->create(['leccion_id' => $l2->id, 'fecha_cierre' => now()->addDays(3)]);

        $r = app(ResumenCursosService::class)->para($user, collect([$curso]));

        $this->assertSame(25, $r[$curso->id]['porcentaje']);
        $this->assertNotNull($r[$curso->id]['proxima']);
    }

    public function test_curso_sin_lecciones_dice_sin_contenido_y_no_divide_por_cero(): void
    {
        $user  = $this->estudiante();
        $curso = Curso::factory()->publicado()->create(['titulo' => 'Curso vacio', 'codigo' => 'CAD9', 'grupo' => '701M']);
        Inscripcion::factory()->create(['user_id' => $user->id, 'curso_id' => $curso->id]);

        $r = app(ResumenCursosService::class)->para($user, collect([$curso]));
        $this->assertNull($r[$curso->id]['porcentaje']);

        $this->actingAs($user)->get(route('cursos.index'))->assertOk()
            ->assertSee('Curso vacio')
            ->assertSee('CAD9 · Grupo 701M')
            ->assertSee('Sin contenido aún')
            ->assertDontSee('0%');
    }

    public function test_tarjeta_muestra_porcentaje_y_proxima_entrega(): void
    {
        $user  = $this->estudiante();
        $curso = Curso::factory()->publicado()->create();
        Inscripcion::factory()->create(['user_id' => $user->id, 'curso_id' => $curso->id]);
        $modulo = Modulo::factory()->create(['curso_id' => $curso->id]);
        $l = Leccion::factory()->count(2)->create(['modulo_id' => $modulo->id]);
        ProgresoLeccion::create(['user_id' => $user->id, 'leccion_id' => $l[0]->id, 'completado' => true, 'fecha_completado' => now()]);
        Actividad::factory()->tarea()->create(['leccion_id' => $l[1]->id, 'fecha_cierre' => now()->addDays(2)]);

        $this->actingAs($user)->get(route('cursos.index'))->assertOk()
            ->assertSee('50%')->assertSee('Próxima entrega');
    }
}
```

- [ ] **Step 2: Verificar que falla** — `php artisan test --filter=CursosTarjetaResumenTest` → FAIL.

- [ ] **Step 3: Migración** (misma estructura que Task 1):

```php
Schema::table('cursos', function (Blueprint $table) {
    $table->string('codigo', 50)->nullable()->after('titulo');
    $table->string('grupo', 50)->nullable()->after('codigo');
});
// down: dropColumn(['codigo', 'grupo'])
```

- [ ] **Step 4: Modelo `Curso`.** Agregar `'codigo', 'grupo'` a `$fillable` y:

```php
    /** "CAD612021730 · Grupo 703M" con las partes presentes (FE-1: metadatos fuera del título). */
    public function subtitulo(): ?string
    {
        $partes = array_filter([
            $this->codigo,
            $this->grupo ? "Grupo {$this->grupo}" : null,
        ]);

        return $partes ? implode(' · ', $partes) : null;
    }
```

- [ ] **Step 5: Servicio**

```php
<?php

namespace App\Services;

use App\Models\Curso;
use App\Models\ProgresoLeccion;
use App\Models\User;
use Illuminate\Support\Collection;

class ResumenCursosService
{
    public function __construct(private LineaDeTiempoService $lineaDeTiempo) {}

    /** @return array<int, array{porcentaje: ?int, proxima: ?\Illuminate\Support\Carbon}> */
    public function para(User $user, Collection $cursos): array
    {
        $ids = $cursos->pluck('id');

        $totales = Curso::whereIn('id', $ids)->withCount('lecciones')->pluck('lecciones_count', 'id');

        $completadas = ProgresoLeccion::where('user_id', $user->id)->where('completado', true)
            ->with('leccion.modulo:id,curso_id')->get()
            ->filter(fn($p) => $p->leccion?->modulo && $ids->contains($p->leccion->modulo->curso_id))
            ->countBy(fn($p) => $p->leccion->modulo->curso_id);

        $proximas = $this->lineaDeTiempo->proximaEntregaPorCurso($user);

        return $cursos->mapWithKeys(function ($curso) use ($totales, $completadas, $proximas) {
            $total = (int) ($totales[$curso->id] ?? 0);

            return [$curso->id => [
                'porcentaje' => $total > 0 ? (int) round((($completadas[$curso->id] ?? 0) / $total) * 100) : null,
                'proxima'    => $proximas[$curso->id] ?? null,
            ]];
        })->all();
    }
}
```

Verificar en el repo que `ProgresoLeccion` tiene la relación `leccion()` (si no existe, agregarla: `belongsTo(Leccion::class)`).

- [ ] **Step 6: Controlador.** `CursoController::index`: tras armar `$inscripciones`, añadir

```php
        $resumen = app(\App\Services\ResumenCursosService::class)
            ->para($user, $inscripciones->pluck('curso'));
```

y pasar `'resumen'` en `compact(...)`. En `store()` y `update()` agregar a las reglas `'codigo' => 'nullable|string|max:50', 'grupo' => 'nullable|string|max:50'`. En `DashboardController::dashboardEstudiante` pasar también `'resumen' => app(ResumenCursosService::class)->para($user, $cursos_inscritos)`.

- [ ] **Step 7: Vistas.** En la tarjeta de `cursos/index.blade.php` (sección "Mis Cursos") y en `dashboard/estudiante.blade.php`, bajo el `<h3>` del título:

```blade
@if($curso->subtitulo())
    <p class="text-xs text-gray-500 -mt-1 mb-2 truncate" title="{{ $curso->subtitulo() }}">{{ $curso->subtitulo() }}</p>
@endif
@php $res = $resumen[$curso->id] ?? ['porcentaje' => null, 'proxima' => null]; @endphp
@if($res['porcentaje'] === null)
    <p class="text-xs text-gray-400 mb-2">Sin contenido aún</p>
@else
    <div class="mb-2" role="progressbar" aria-valuenow="{{ $res['porcentaje'] }}" aria-valuemin="0" aria-valuemax="100">
        <div class="flex justify-between text-xs text-gray-500 mb-1"><span>Progreso</span><span>{{ $res['porcentaje'] }}%</span></div>
        <div class="h-1.5 rounded-full bg-gray-200"><div class="h-1.5 rounded-full bg-dyl-orange-600" style="width: {{ $res['porcentaje'] }}%"></div></div>
    </div>
@endif
@if($res['proxima'])
    <p class="text-xs text-gray-600 mb-3">Próxima entrega: <span class="font-medium">{{ $res['proxima']->format('d/m H:i') }}</span></p>
@endif
```

En `cursos/create.blade.php` y `edit.blade.php` agregar dos inputs de texto `codigo` y `grupo` (misma clase que el input `titulo`, `old('codigo', $curso->codigo ?? null)`), con ayuda "Opcional. Se muestra bajo el título del curso."

- [ ] **Step 8: Verificar que pasa** — `php artisan test --filter=CursosTarjetaResumenTest`, luego `php artisan test --filter=Curso` (cursos index/inscripción/plantilla siguen verdes).

- [ ] **Step 9: Commit**

```bash
git add app database resources tests
git commit -m "feat: tarjeta de curso con progreso, proxima entrega y subtitulo (F3)"
```

---

### Task 6: Desmarcar actividad como completada (F3)

**Files:**
- Modify: `app/Models/Actividad.php` (`descompletarPara`), `app/Http/Controllers/ActividadController.php` (`descompletar`), `routes/web.php` (junto a `actividades.completar`, línea ~85), `resources/views/actividades/show.blade.php` (~línea 420)
- Test: `tests/Feature/ActividadDescompletarTest.php`

**Interfaces:**
- Produces: `Actividad::descompletarPara(int $userId): void`; ruta `actividades.descompletar` (`POST /actividades/{actividad}/descompletar`).
- Consumes: `ProgresoActividad`, `ProgresoLeccion`, `Inscripcion` (ya usados por `completarPara`).

- [ ] **Step 1: Test que falla**

```php
<?php

namespace Tests\Feature;

use App\Models\Actividad;
use App\Models\Curso;
use App\Models\Inscripcion;
use App\Models\Leccion;
use App\Models\Modulo;
use App\Models\ProgresoActividad;
use App\Models\ProgresoLeccion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ArmaCursoCompleto;
use Tests\TestCase;

class ActividadDescompletarTest extends TestCase
{
    use RefreshDatabase, ArmaCursoCompleto;

    private function escenario(): array
    {
        $user  = $this->estudiante();
        $curso = Curso::factory()->publicado()->create();
        Inscripcion::factory()->create(['user_id' => $user->id, 'curso_id' => $curso->id]);
        $leccion   = Leccion::factory()->create(['modulo_id' => Modulo::factory()->create(['curso_id' => $curso->id])->id]);
        $actividad = Actividad::factory()->sinNota()->create(['leccion_id' => $leccion->id]);

        return [$user, $curso, $leccion, $actividad];
    }

    public function test_desmarcar_revierte_actividad_leccion_e_inscripcion(): void
    {
        [$user, $curso, $leccion, $actividad] = $this->escenario();
        $actividad->completarPara($user->id);

        $this->assertDatabaseHas('inscripciones', ['user_id' => $user->id, 'curso_id' => $curso->id, 'estado' => 'completado']);

        $this->actingAs($user)->post(route('actividades.descompletar', $actividad))
            ->assertRedirect(route('actividades.show', $actividad));

        $this->assertFalse(ProgresoActividad::where('user_id', $user->id)->where('actividad_id', $actividad->id)->value('completado'));
        $this->assertFalse((bool) ProgresoLeccion::where('user_id', $user->id)->where('leccion_id', $leccion->id)->value('completado'));
        $this->assertDatabaseHas('inscripciones', ['user_id' => $user->id, 'curso_id' => $curso->id, 'estado' => 'en_progreso', 'fecha_fin' => null]);
    }

    public function test_desmarcar_sin_haber_completado_no_falla(): void
    {
        [$user, , , $actividad] = $this->escenario();

        $this->actingAs($user)->post(route('actividades.descompletar', $actividad))->assertRedirect();
    }

    public function test_no_inscrito_no_puede_desmarcar(): void
    {
        [, , , $actividad] = $this->escenario();

        $this->actingAs($this->estudiante())->post(route('actividades.descompletar', $actividad))->assertForbidden();
    }

    public function test_actividades_con_nota_no_se_desmarcan(): void
    {
        [$user, , $leccion] = $this->escenario();
        $conNota = Actividad::factory()->tarea()->create(['leccion_id' => $leccion->id]);

        $this->actingAs($user)->post(route('actividades.descompletar', $conNota))->assertForbidden();
    }
}
```

- [ ] **Step 2: Verificar que falla** — `php artisan test --filter=ActividadDescompletarTest` → FAIL (ruta inexistente).

- [ ] **Step 3: Modelo.** Junto a `completarPara`:

```php
    /** Revierte completarPara: la actividad, su lección y el estado "completado" de la inscripción. */
    public function descompletarPara(int $userId): void
    {
        ProgresoActividad::where('user_id', $userId)->where('actividad_id', $this->id)
            ->update(['completado' => false, 'fecha_completado' => null]);

        $leccion = $this->leccion;
        ProgresoLeccion::where('user_id', $userId)->where('leccion_id', $leccion->id)
            ->update(['completado' => false, 'fecha_completado' => null]);

        Inscripcion::where('user_id', $userId)
            ->where('curso_id', $leccion->modulo->curso_id)
            ->where('estado', 'completado')
            ->update(['estado' => 'en_progreso', 'fecha_fin' => null]);
    }
```

- [ ] **Step 4: Controlador y ruta**

```php
    public function descompletar(Actividad $actividad)
    {
        $this->authorize('verContenido', $actividad->leccion->modulo->curso);
        abort_if($actividad->tieneCalificacion(), 403);

        $actividad->descompletarPara(auth()->id());

        return redirect()
            ->route('actividades.show', $actividad)
            ->with('success', 'Actividad desmarcada.');
    }
```

```php
    Route::post('/actividades/{actividad}/descompletar', [ActividadController::class, 'descompletar'])->name('actividades.descompletar');
```

- [ ] **Step 5: Vista.** En `actividades/show.blade.php`, el bloque `@elseif(!$actividad->tieneCalificacion())` (~línea 420) muestra siempre "Marcar como completada". Cambiarlo para que, si `$actividadCompletada`, muestre el formulario de `actividades.descompletar` con el botón secundario "Desmarcar como completada" (clase `border border-gray-300 text-gray-700 hover:bg-gray-50`, mismo tamaño) y, si no, el botón actual.

- [ ] **Step 6: Verificar que pasa** — `php artisan test --filter=ActividadDescompletarTest` y `php artisan test` completo → PASS sin regresiones.

- [ ] **Step 7: Commit**

```bash
git add app routes resources tests
git commit -m "feat: desmarcar actividad como completada (F3)"
```

---

## Verificación final del sprint

- [ ] `php artisan test` completo en verde (en el worktree; nunca `migrate:fresh` en la BD local).
- [ ] Revisión manual (`php artisan serve` con la BD local ya migrada con `php artisan migrate`): como estudiante abrir `/dashboard`, `/linea-de-tiempo`, `/cursos`, una actividad sin nota (marcar/desmarcar) y comprobar el botón de ayuda; como instructor crear una actividad con ronda/semana y un curso con código y grupo.
- [ ] Revisar que ninguna página muestre `{{`, `}}` ni bloques vacíos con datos faltantes.

## Self-Review (spec ↔ plan)

| Spec | Cubierto en | Nota |
|---|---|---|
| F1 línea de tiempo (CA 1-5) | Task 3 y 4 | CA1 agrupación/orden, CA2 entregadas ocultas, CA3 atrasadas, CA4 inscripción, CA5 mensaje vacío |
| F3 progreso/tarjeta/marcar hecha (CA 1-4) | Task 5 y 6 | "Nota actual" y orden/filtro de lista **diferidos**: nota → Sprint B (F4); buscador/orden de la lista de Mis cursos no incluido (YAGNI hasta que haya muchos cursos) |
| F5 ronda/semana (CA 1-3) | Task 1 | Filtro por ronda en línea de tiempo (Task 3/4); filtros por ronda/semana en vista de curso y calificaciones quedan para cuando exista F4 |
| F8 ayuda (CA 1-3) | Task 2 | CA3 (no tapar en móvil) por diseño: botón pequeño, panel cerrable con Esc |
| FE-1 | Task 5 | `codigo`/`grupo` fuera del título |
| FE-2 | Task 2 | líneas omitidas si falta el dato; test sin `{{` |
| FE-4 | Task 4 | mensaje de vacío |
| FE-5 | Task 5 | progreso + próxima entrega (nota → Sprint B) |
| RNF-1 | Decisión 1 | no convierte zona horaria |

Consistencia de nombres revisada: `LineaDeTiempoService::para/proximaEntregaPorCurso`, `ResumenCursosService::para`, `Actividad::prefijo/tituloCompleto/descompletarPara`, `Curso::subtitulo`, rutas `linea-de-tiempo.index` y `actividades.descompletar`.

## Fuera de este plan (Sprints siguientes, planes aparte)

- **Sprint B:** F4 ponderación y proyección (+ nota actual en tarjeta), F2 calendario/.ics, F6 ficha académica, F9 ajustes.
- **Sprint C:** F7 competencias, F10 seguimiento, F11 modo edición.
