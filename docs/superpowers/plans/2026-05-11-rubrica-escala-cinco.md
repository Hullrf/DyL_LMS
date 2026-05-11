# Rúbrica de Calificación y Escala 0-5.0

> **Para agentes:** USA `superpowers:subagent-driven-development` o `superpowers:executing-plans`. Steps usan `- [ ]` para tracking.

**Goal:** Cambiar la escala de calificación de 100 a 5.0 en todas las actividades, y agregar un sistema de rúbrica opcional para tareas con entrega de archivo.

**Architecture:** 3 nuevas tablas (criterios_rubrica, niveles_criterio, selecciones_rubrica), cambios de tipo en columnas existentes (integer→decimal), nuevo RubricaController + RubricaImportService, builder Alpine.js en actividades/edit, tabla visible al estudiante en actividades/show, interfaz de calificación por rúbrica en calificaciones/show.

**Tech Stack:** Laravel 12, PHP 8.2, MySQL, Alpine.js, maatwebsite/excel (ya instalado)

---

## Archivos a crear/modificar

**Nuevos:**
- `database/migrations/2026_05_11_100000_change_calificacion_scale.php`
- `database/migrations/2026_05_11_100001_create_criterios_rubrica_table.php`
- `database/migrations/2026_05_11_100002_create_niveles_criterio_table.php`
- `database/migrations/2026_05_11_100003_create_selecciones_rubrica_table.php`
- `app/Models/CriterioRubrica.php`
- `app/Models/NivelCriterio.php`
- `app/Models/SeleccionRubrica.php`
- `app/Http/Controllers/RubricaController.php`
- `app/Services/RubricaImportService.php`
- `app/Exports/EjemploRubricaExport.php`
- `tests/Feature/RubricaTest.php`

**Modificados:**
- `app/Models/Actividad.php`
- `app/Models/RespuestaEstudiante.php`
- `app/Services/CalificacionService.php`
- `app/Http/Controllers/CalificacionController.php`
- `app/Http/Controllers/ActividadController.php`
- `routes/web.php`
- `resources/views/actividades/edit.blade.php`
- `resources/views/actividades/show.blade.php`
- `resources/views/calificaciones/show.blade.php`
- `resources/views/calificaciones/index.blade.php`
- `resources/views/calificaciones/mis-calificaciones.blade.php`

---

## Task 1: Migraciones de escala y tablas de rúbrica

**Files:**
- Create: `database/migrations/2026_05_11_100000_change_calificacion_scale.php`
- Create: `database/migrations/2026_05_11_100001_create_criterios_rubrica_table.php`
- Create: `database/migrations/2026_05_11_100002_create_niveles_criterio_table.php`
- Create: `database/migrations/2026_05_11_100003_create_selecciones_rubrica_table.php`

- [ ] Crear migración de cambio de escala:

```php
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 1. actividades: puntaje_maximo integer → decimal(5,2) + usa_rubrica
        Schema::table('actividades', function (Blueprint $table) {
            $table->decimal('puntaje_maximo_nuevo', 5, 2)->default(5.00)->after('orden');
            $table->boolean('usa_rubrica')->default(false)->after('puntaje_maximo_nuevo');
        });
        // Convertir valores existentes: 100 → 5.00
        DB::statement('UPDATE actividades SET puntaje_maximo_nuevo = 5.00');
        Schema::table('actividades', function (Blueprint $table) {
            $table->dropColumn('puntaje_maximo');
        });
        Schema::table('actividades', function (Blueprint $table) {
            $table->renameColumn('puntaje_maximo_nuevo', 'puntaje_maximo');
        });

        // 2. preguntas: puntaje integer → decimal(5,2)
        Schema::table('preguntas', function (Blueprint $table) {
            $table->decimal('puntaje_nuevo', 5, 2)->default(1.00)->after('tipo');
        });
        DB::statement('UPDATE preguntas SET puntaje_nuevo = puntaje');
        Schema::table('preguntas', function (Blueprint $table) {
            $table->dropColumn('puntaje');
        });
        Schema::table('preguntas', function (Blueprint $table) {
            $table->renameColumn('puntaje_nuevo', 'puntaje');
        });

        // 3. respuestas_estudiantes: calificacion integer → decimal(5,2)
        // Convertir proporcionalmente: old/100*5
        Schema::table('respuestas_estudiantes', function (Blueprint $table) {
            $table->decimal('calificacion_nueva', 5, 2)->nullable()->after('respuesta');
        });
        DB::statement('UPDATE respuestas_estudiantes SET calificacion_nueva = ROUND(calificacion / 100 * 5, 2) WHERE calificacion IS NOT NULL');
        Schema::table('respuestas_estudiantes', function (Blueprint $table) {
            $table->dropColumn('calificacion');
        });
        Schema::table('respuestas_estudiantes', function (Blueprint $table) {
            $table->renameColumn('calificacion_nueva', 'calificacion');
        });
    }

    public function down(): void
    {
        // Revertir (aproximación)
        Schema::table('actividades', function (Blueprint $table) {
            $table->dropColumn(['usa_rubrica']);
            $table->integer('puntaje_maximo_old')->default(100)->after('orden');
        });
        DB::statement('UPDATE actividades SET puntaje_maximo_old = 100');
        Schema::table('actividades', function (Blueprint $table) {
            $table->dropColumn('puntaje_maximo');
            $table->renameColumn('puntaje_maximo_old', 'puntaje_maximo');
        });

        Schema::table('respuestas_estudiantes', function (Blueprint $table) {
            $table->integer('calificacion_old')->nullable()->after('respuesta');
        });
        DB::statement('UPDATE respuestas_estudiantes SET calificacion_old = ROUND(calificacion / 5 * 100) WHERE calificacion IS NOT NULL');
        Schema::table('respuestas_estudiantes', function (Blueprint $table) {
            $table->dropColumn('calificacion');
            $table->renameColumn('calificacion_old', 'calificacion');
        });

        Schema::table('preguntas', function (Blueprint $table) {
            $table->integer('puntaje_old')->default(10)->after('tipo');
        });
        DB::statement('UPDATE preguntas SET puntaje_old = ROUND(puntaje)');
        Schema::table('preguntas', function (Blueprint $table) {
            $table->dropColumn('puntaje');
            $table->renameColumn('puntaje_old', 'puntaje');
        });
    }
};
```

- [ ] Crear migración `criterios_rubrica`:

```php
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('criterios_rubrica', function (Blueprint $table) {
            $table->id();
            $table->foreignId('actividad_id')->constrained('actividades')->onDelete('cascade');
            $table->string('nombre');
            $table->integer('orden')->default(0);
            $table->timestamps();
            $table->index('actividad_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('criterios_rubrica');
    }
};
```

- [ ] Crear migración `niveles_criterio`:

```php
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('niveles_criterio', function (Blueprint $table) {
            $table->id();
            $table->foreignId('criterio_id')
                  ->constrained('criterios_rubrica')->onDelete('cascade');
            $table->longText('descripcion');
            $table->decimal('puntos', 5, 2);
            $table->integer('orden')->default(0);
            $table->timestamps();
            $table->index('criterio_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('niveles_criterio');
    }
};
```

- [ ] Crear migración `selecciones_rubrica`:

```php
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('selecciones_rubrica', function (Blueprint $table) {
            $table->id();
            $table->foreignId('respuesta_estudiante_id')
                  ->constrained('respuestas_estudiantes')->onDelete('cascade');
            $table->foreignId('criterio_id')
                  ->constrained('criterios_rubrica')->onDelete('cascade');
            $table->foreignId('nivel_criterio_id')
                  ->constrained('niveles_criterio')->onDelete('cascade');
            $table->timestamps();
            $table->unique(['respuesta_estudiante_id', 'criterio_id'], 'unique_seleccion');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('selecciones_rubrica');
    }
};
```

- [ ] Ejecutar migraciones:

```bash
cd "C:\xampp\htdocs\LMS DyL\lms-dyl-quality"
php artisan migrate
```

Expected: 4 migraciones ejecutadas sin errores.

- [ ] Commit:

```bash
git add database/migrations/2026_05_11_10000*
git commit -m "feat: migrations for 0-5 scale and rubric tables"
```

---

## Task 2: Modelos nuevos y actualizados

**Files:**
- Create: `app/Models/CriterioRubrica.php`
- Create: `app/Models/NivelCriterio.php`
- Create: `app/Models/SeleccionRubrica.php`
- Modify: `app/Models/Actividad.php`
- Modify: `app/Models/RespuestaEstudiante.php`

- [ ] Crear `app/Models/CriterioRubrica.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CriterioRubrica extends Model
{
    protected $table    = 'criterios_rubrica';
    protected $fillable = ['actividad_id', 'nombre', 'orden'];

    public function actividad(): BelongsTo
    {
        return $this->belongsTo(Actividad::class);
    }

    public function niveles(): HasMany
    {
        return $this->hasMany(NivelCriterio::class, 'criterio_id')->orderBy('orden');
    }

    public function puntajeMaximo(): float
    {
        return (float) $this->niveles()->max('puntos');
    }
}
```

- [ ] Crear `app/Models/NivelCriterio.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NivelCriterio extends Model
{
    protected $table    = 'niveles_criterio';
    protected $fillable = ['criterio_id', 'descripcion', 'puntos', 'orden'];

    protected $casts = ['puntos' => 'decimal:2'];

    public function criterio(): BelongsTo
    {
        return $this->belongsTo(CriterioRubrica::class, 'criterio_id');
    }
}
```

- [ ] Crear `app/Models/SeleccionRubrica.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SeleccionRubrica extends Model
{
    protected $table    = 'selecciones_rubrica';
    protected $fillable = ['respuesta_estudiante_id', 'criterio_id', 'nivel_criterio_id'];

    public function respuesta(): BelongsTo
    {
        return $this->belongsTo(RespuestaEstudiante::class, 'respuesta_estudiante_id');
    }

    public function criterio(): BelongsTo
    {
        return $this->belongsTo(CriterioRubrica::class, 'criterio_id');
    }

    public function nivel(): BelongsTo
    {
        return $this->belongsTo(NivelCriterio::class, 'nivel_criterio_id');
    }
}
```

- [ ] Actualizar `app/Models/Actividad.php` — agregar usa_rubrica en fillable, casts, y relaciones:

Localizar `protected $fillable` (línea 20) y reemplazar:
```php
protected $fillable = [
    'leccion_id', 'tipo', 'titulo', 'descripcion',
    'orden', 'puntaje_maximo', 'duracion_minutos', 'es_obligatoria',
    'fecha_apertura', 'fecha_cierre', 'usa_rubrica',
];

protected $casts = [
    'fecha_apertura' => 'datetime',
    'fecha_cierre'   => 'datetime',
    'es_obligatoria' => 'boolean',
    'usa_rubrica'    => 'boolean',
    'puntaje_maximo' => 'decimal:2',
];
```

Agregar estos métodos antes del cierre de la clase:
```php
public function criteriosRubrica(): HasMany
{
    return $this->hasMany(CriterioRubrica::class)->orderBy('orden');
}

public function puntajeRubrica(): float
{
    return (float) $this->criteriosRubrica()
        ->with('niveles')
        ->get()
        ->sum(fn($c) => $c->niveles->max('puntos') ?? 0);
}
```

También agregar `use Illuminate\Database\Eloquent\Relations\HasMany;` si no está ya en los imports.

- [ ] Actualizar `app/Models/RespuestaEstudiante.php` — agregar cast decimal y relación:

En `$casts`, agregar:
```php
'calificacion' => 'decimal:2',
```

Agregar relación antes del cierre de la clase:
```php
public function seleccionesRubrica(): HasMany
{
    return $this->hasMany(SeleccionRubrica::class, 'respuesta_estudiante_id');
}
```

Agregar import:
```php
use Illuminate\Database\Eloquent\Relations\HasMany;
```

- [ ] Commit:

```bash
git add app/Models/
git commit -m "feat: CriterioRubrica, NivelCriterio, SeleccionRubrica models + update Actividad/RespuestaEstudiante"
```

---

## Task 3: CalificacionService — escala decimal y método de rúbrica

**Files:**
- Modify: `app/Services/CalificacionService.php`

- [ ] Reemplazar el contenido completo del archivo:

```php
<?php

namespace App\Services;

use App\Models\Actividad;
use App\Models\NivelCriterio;
use App\Models\RespuestaEstudiante;

class CalificacionService
{
    public function tienePreguntasCortas(Actividad $actividad): bool
    {
        return $actividad->preguntas()->where('tipo', 'respuesta_corta')->exists();
    }

    /**
     * Auto-califica un cuestionario. Retorna float en escala 0-puntaje_maximo.
     *
     * Formato JSON de respuestas:
     *   - Opción única / V-F:   { "pregunta_id": "opcion_id" }
     *   - Selección múltiple:   { "pregunta_id": ["id1", "id2"] }
     *   - Respuesta corta:      { "pregunta_id": "texto libre" }
     *
     * $decisionesCortas: [ pregunta_id => true|false ]
     */
    public function calcularCuestionario(
        Actividad $actividad,
        string $respuestaJson,
        array $decisionesCortas = []
    ): float {
        $respuestas = json_decode($respuestaJson, true);
        if (!is_array($respuestas)) {
            return 0.0;
        }

        $totalPuntaje    = 0.0;
        $puntajeObtenido = 0.0;

        foreach ($actividad->preguntas()->with('opciones')->get() as $pregunta) {
            $totalPuntaje += (float) $pregunta->puntaje;
            $respuesta     = $respuestas[$pregunta->id] ?? null;

            if ($respuesta === null) {
                continue;
            }

            if ($pregunta->tipo === 'respuesta_corta') {
                if ($decisionesCortas[$pregunta->id] ?? false) {
                    $puntajeObtenido += (float) $pregunta->puntaje;
                }

            } elseif ($pregunta->seleccion_multiple) {
                $idsCorrectas = $pregunta->opciones
                    ->where('es_correcta', true)
                    ->pluck('id')
                    ->map(fn($id) => (string) $id);

                $nCorrectas = $idsCorrectas->count();
                if ($nCorrectas === 0) continue;

                $seleccionadas = collect(is_array($respuesta) ? $respuesta : [$respuesta])
                    ->map(fn($id) => (string) $id);

                $acertadas = $seleccionadas->intersect($idsCorrectas)->count();
                $puntajeObtenido += ($acertadas / $nCorrectas) * (float) $pregunta->puntaje;

            } else {
                $opcionCorrecta = $pregunta->opciones->firstWhere('es_correcta', true);
                if ($opcionCorrecta && (string) $opcionCorrecta->id === (string) $respuesta) {
                    $puntajeObtenido += (float) $pregunta->puntaje;
                }
            }
        }

        if ($totalPuntaje == 0) {
            return 0.0;
        }

        return round(($puntajeObtenido / $totalPuntaje) * (float) $actividad->puntaje_maximo, 2);
    }

    /**
     * Califica manualmente (ensayo, tarea, practica). Acepta float.
     */
    public function calificarManual(RespuestaEstudiante $respuesta, float $calificacion, ?string $feedback): void
    {
        $respuesta->update([
            'calificacion'       => $calificacion,
            'feedback'           => $feedback,
            'estado'             => 'calificada',
            'fecha_calificacion' => now(),
        ]);
    }

    /**
     * Califica una tarea usando rúbrica.
     * $selecciones: [criterio_id => nivel_criterio_id]
     */
    public function calificarConRubrica(
        RespuestaEstudiante $respuesta,
        array $selecciones,
        ?string $feedback
    ): float {
        // Persistir selecciones
        foreach ($selecciones as $criterioId => $nivelId) {
            $respuesta->seleccionesRubrica()->updateOrCreate(
                ['criterio_id' => (int) $criterioId],
                ['nivel_criterio_id' => (int) $nivelId]
            );
        }

        // Calcular nota sumando puntos de niveles seleccionados
        $nivelIds     = array_map('intval', array_values($selecciones));
        $calificacion = round(
            (float) NivelCriterio::whereIn('id', $nivelIds)->sum('puntos'),
            2
        );

        $this->calificarManual($respuesta, $calificacion, $feedback);

        return $calificacion;
    }
}
```

- [ ] Verificar que los tests existentes de CalificacionService siguen pasando:

```bash
cd "C:\xampp\htdocs\LMS DyL\lms-dyl-quality"
php artisan test tests/Unit/CalificacionServiceTest.php
```

Expected: todos los tests pasan (los int que retornaba ahora son float, los assertions deben seguir siendo numéricamente iguales).

- [ ] Si algún test falla por comparación `===` entre int y float, actualizar la assertion:

En `tests/Unit/CalificacionServiceTest.php`, cambiar cualquier `assertSame(int, ...)` por `assertEquals(int, ...)`.

- [ ] Commit:

```bash
git add app/Services/CalificacionService.php tests/Unit/CalificacionServiceTest.php
git commit -m "feat: CalificacionService float scale + calificarConRubrica method"
```

---

## Task 4: EjemploRubricaExport y RubricaImportService

**Files:**
- Create: `app/Exports/EjemploRubricaExport.php`
- Create: `app/Services/RubricaImportService.php`

- [ ] Crear `app/Exports/EjemploRubricaExport.php`:

```php
<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Color;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class EjemploRubricaSheet implements FromArray, WithTitle, WithStyles, ShouldAutoSize
{
    public function array(): array
    {
        return [
            ['Criterio', 'Nivel 1', 'Nivel 2', 'Nivel 3', 'Nivel 4'],
            [
                'Planteamiento del Problema y Justificación',
                "El problema no está claramente descrito, o no se relaciona con una necesidad real. La justificación es inexistente.\n\n0 puntos",
                "El problema se describe de forma general, pero le falta claridad. La justificación es básica.\n\n0.33 puntos",
                "El problema está claramente descrito y se evidencia su relevancia. La justificación presenta argumentos sólidos.\n\n0.8 puntos",
                "El problema está planteado con total claridad, demostrando una comprensión profunda. La justificación es convincente.\n\n1.0 puntos",
            ],
            [
                'Formulación de Pregunta y Objetivos',
                "La pregunta de investigación es vaga o inexistente. Los objetivos no están definidos.\n\n0 puntos",
                "La pregunta existe pero es ambigua. Los objetivos son generales y poco medibles.\n\n0.25 puntos",
                "La pregunta es clara y delimita el problema. Los objetivos son específicos y alcanzables.\n\n0.5 puntos",
                "La pregunta es precisa, específica y verificable. Los objetivos están perfectamente articulados.\n\n1.0 puntos",
            ],
        ];
    }

    public function title(): string
    {
        return 'Rúbrica';
    }

    public function styles(Worksheet $sheet): void
    {
        // Fila de encabezados
        $sheet->getStyle('A1:E1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1E3A5F']],
        ]);

        // Puntos en verde en cada celda de nivel (última línea)
        foreach (range(2, 3) as $row) {
            foreach (['B', 'C', 'D', 'E'] as $col) {
                $sheet->getStyle("{$col}{$row}")->getAlignment()
                    ->setWrapText(true)
                    ->setVertical(Alignment::VERTICAL_TOP);
            }
        }

        // Columna A: bold
        $sheet->getStyle('A2:A3')->getFont()->setBold(true);

        // Alto de filas
        $sheet->getRowDimension(2)->setRowHeight(120);
        $sheet->getRowDimension(3)->setRowHeight(120);
    }
}

class EjemploInstruccionesSheet implements FromArray, WithTitle
{
    public function array(): array
    {
        return [
            ['INSTRUCCIONES PARA USAR ESTA PLANTILLA'],
            [''],
            ['ESTRUCTURA:'],
            ['- Columna A: Nombre del criterio de evaluación'],
            ['- Columnas B en adelante: Un nivel por columna (de peor a mejor)'],
            ['- En cada celda de nivel: escribe la descripción y en la ÚLTIMA LÍNEA el puntaje así:'],
            ['  "0.5 puntos"  o  "0.5 pts"  o simplemente  "0.5"'],
            [''],
            ['REGLAS:'],
            ['- Puedes tener los niveles que quieras (mín. 1, típicamente 4)'],
            ['- Los puntos de cada nivel deben ir de menor a mayor (de izquierda a derecha)'],
            ['- La suma de los puntos máximos de todos los criterios = nota máxima del trabajo'],
            ['- No cambies el nombre de esta pestaña ni de la pestaña "Rúbrica"'],
            [''],
            ['EJEMPLO de celda de nivel:'],
            ['El problema está claramente descrito y se evidencia su relevancia.'],
            ['La justificación presenta argumentos sólidos.'],
            [''],
            ['0.8 puntos'],
        ];
    }

    public function title(): string
    {
        return 'Instrucciones';
    }
}

class EjemploRubricaExport implements WithMultipleSheets
{
    public function sheets(): array
    {
        return [
            new EjemploRubricaSheet(),
            new EjemploInstruccionesSheet(),
        ];
    }
}
```

- [ ] Crear `app/Services/RubricaImportService.php`:

```php
<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\Concerns\ToArray;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class RubricaImportReader implements ToArray
{
    private array $rows = [];

    public function array(array $array): void
    {
        $this->rows = $array;
    }

    public function getRows(): array
    {
        return $this->rows;
    }
}

class RubricaImportService
{
    /**
     * Parsea un archivo Excel y retorna la estructura de criterios/niveles.
     * Row 0 = encabezados (ignorada). Row 1+ = criterios.
     * Col 0 = nombre del criterio. Col 1+ = niveles.
     *
     * Cada celda de nivel: texto de descripción + última línea con puntos.
     * Ejemplo última línea: "0.8 puntos", "0.8 pts", "0.8"
     *
     * Retorna:
     * [
     *   ['nombre' => '...', 'niveles' => [
     *       ['descripcion' => '...', 'puntos' => 0.8],
     *       ...
     *   ]],
     *   ...
     * ]
     */
    public function parsear(UploadedFile $archivo): array
    {
        $reader = new RubricaImportReader();
        Excel::import($reader, $archivo);

        $rows     = $reader->getRows();
        $criterios = [];

        // Ignorar primera fila (encabezados)
        foreach (array_slice($rows, 1) as $row) {
            if (empty($row[0])) continue;

            $nombre  = trim((string) $row[0]);
            $niveles = [];

            for ($i = 1; $i < count($row); $i++) {
                $celda = trim((string) ($row[$i] ?? ''));
                if ($celda === '') continue;

                $puntos      = $this->extraerPuntos($celda);
                $descripcion = $this->extraerDescripcion($celda);

                if ($descripcion !== '' || $puntos > 0) {
                    $niveles[] = [
                        'descripcion' => $descripcion,
                        'puntos'      => $puntos,
                    ];
                }
            }

            if ($nombre !== '' && count($niveles) > 0) {
                $criterios[] = ['nombre' => $nombre, 'niveles' => $niveles];
            }
        }

        return $criterios;
    }

    private function extraerPuntos(string $celda): float
    {
        $lineas = array_map('trim', explode("\n", $celda));
        $lineas = array_filter($lineas);

        foreach (array_reverse($lineas) as $linea) {
            if (preg_match('/^(\d+(?:[.,]\d+)?)\s*(?:puntos?|pts?)?$/iu', $linea, $m)) {
                return round((float) str_replace(',', '.', $m[1]), 2);
            }
        }

        return 0.0;
    }

    private function extraerDescripcion(string $celda): string
    {
        $lineas = array_map('trim', explode("\n", $celda));

        // Eliminar última línea si es solo puntos
        $ultima = end($lineas);
        if (preg_match('/^(\d+(?:[.,]\d+)?)\s*(?:puntos?|pts?)?$/iu', $ultima)) {
            array_pop($lineas);
        }

        // Eliminar líneas vacías al final
        while (!empty($lineas) && end($lineas) === '') {
            array_pop($lineas);
        }

        return implode("\n", $lineas);
    }
}
```

- [ ] Commit:

```bash
git add app/Exports/EjemploRubricaExport.php app/Services/RubricaImportService.php
git commit -m "feat: EjemploRubricaExport and RubricaImportService"
```

---

## Task 5: RubricaController

**Files:**
- Create: `app/Http/Controllers/RubricaController.php`

- [ ] Crear el controller:

```php
<?php

namespace App\Http\Controllers;

use App\Exports\EjemploRubricaExport;
use App\Models\Actividad;
use App\Services\RubricaImportService;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class RubricaController extends Controller
{
    public function __construct(private RubricaImportService $importService) {}

    /**
     * Guarda o reemplaza la rúbrica de una actividad.
     * Recibe: usa_rubrica (bool) + criterios (array)
     */
    public function store(Request $request, Actividad $actividad)
    {
        $this->authorize('update', $actividad->leccion->modulo->curso);

        $request->validate([
            'usa_rubrica'                           => 'required|boolean',
            'criterios'                             => 'nullable|array',
            'criterios.*.nombre'                    => 'required_with:criterios|string|max:255',
            'criterios.*.niveles'                   => 'required_with:criterios|array|min:1',
            'criterios.*.niveles.*.descripcion'     => 'required|string',
            'criterios.*.niveles.*.puntos'          => 'required|numeric|min:0|max:99.99',
        ]);

        $actividad->update(['usa_rubrica' => $request->boolean('usa_rubrica')]);

        // Eliminar criterios anteriores (cascade elimina niveles)
        $actividad->criteriosRubrica()->delete();

        if ($request->boolean('usa_rubrica') && $request->criterios) {
            foreach ($request->criterios as $i => $criterioData) {
                $criterio = $actividad->criteriosRubrica()->create([
                    'nombre' => $criterioData['nombre'],
                    'orden'  => $i,
                ]);

                foreach ($criterioData['niveles'] as $j => $nivelData) {
                    $criterio->niveles()->create([
                        'descripcion' => $nivelData['descripcion'],
                        'puntos'      => $nivelData['puntos'],
                        'orden'       => $j,
                    ]);
                }
            }

            // Actualizar puntaje_maximo = suma de nivel máximo de cada criterio
            $puntajeMax = $actividad->puntajeRubrica();
            $actividad->update(['puntaje_maximo' => $puntajeMax ?: 5.00]);
        }

        return redirect()
            ->route('actividades.edit', $actividad)
            ->with('success', 'Rúbrica guardada correctamente.');
    }

    /**
     * Descarga el archivo Excel de ejemplo.
     */
    public function ejemplo()
    {
        return Excel::download(new EjemploRubricaExport(), 'ejemplo-rubrica.xlsx');
    }

    /**
     * Parsea un Excel subido y retorna los criterios como JSON.
     * NO guarda en BD — el frontend los carga en el builder para revisión.
     */
    public function importar(Request $request, Actividad $actividad)
    {
        $this->authorize('update', $actividad->leccion->modulo->curso);

        $request->validate([
            'archivo' => 'required|file|mimes:xlsx,xls,csv|max:5120',
        ]);

        try {
            $criterios = $this->importService->parsear($request->file('archivo'));

            if (empty($criterios)) {
                return response()->json([
                    'error' => 'No se encontraron criterios en el archivo. Verifica que usas la plantilla correcta.',
                ], 422);
            }

            return response()->json(['criterios' => $criterios]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Error al leer el archivo: ' . $e->getMessage(),
            ], 422);
        }
    }
}
```

- [ ] Commit:

```bash
git add app/Http/Controllers/RubricaController.php
git commit -m "feat: RubricaController (store, ejemplo, importar)"
```

---

## Task 6: Rutas + ActividadController.edit

**Files:**
- Modify: `routes/web.php`
- Modify: `app/Http/Controllers/ActividadController.php`

- [ ] Agregar rutas en `routes/web.php` dentro del grupo `auth` (antes del bloque de Admin):

```php
// Rúbrica
Route::middleware('instructor')->group(function () {
    Route::post('/actividades/{actividad}/rubrica', [\App\Http\Controllers\RubricaController::class, 'store'])
        ->name('rubrica.store');
    Route::post('/rubrica/importar/{actividad}', [\App\Http\Controllers\RubricaController::class, 'importar'])
        ->name('rubrica.importar');
});
Route::get('/rubrica/ejemplo', [\App\Http\Controllers\RubricaController::class, 'ejemplo'])
    ->name('rubrica.ejemplo')
    ->middleware('auth');
```

- [ ] En `app/Http/Controllers/ActividadController.php`, actualizar el método `edit()`. Localizar:

```php
public function edit(Actividad $actividad)
{
    $this->authorize('update', $actividad->leccion->modulo->curso);
    $preguntas = $actividad->preguntas()->with('opciones')->get();
    return view('actividades.edit', compact('actividad', 'preguntas'));
}
```

Reemplazar por:

```php
public function edit(Actividad $actividad)
{
    $this->authorize('update', $actividad->leccion->modulo->curso);
    $preguntas        = $actividad->preguntas()->with('opciones')->get();
    $criteriosRubrica = $actividad->criteriosRubrica()->with('niveles')->get();
    return view('actividades.edit', compact('actividad', 'preguntas', 'criteriosRubrica'));
}
```

- [ ] Commit:

```bash
git add routes/web.php app/Http/Controllers/ActividadController.php
git commit -m "feat: rubrica routes + pass criteriosRubrica to actividades.edit"
```

---

## Task 7: CalificacionController — validación decimal + guardarRubrica

**Files:**
- Modify: `app/Http/Controllers/CalificacionController.php`

- [ ] En el método `update()`, cambiar la validación de calificación de `integer` a `numeric`:

Localizar (línea ~64):
```php
'calificacion' => "required|integer|min:0|max:{$actividad->puntaje_maximo}",
```
Reemplazar por:
```php
'calificacion' => "required|numeric|min:0|max:{$actividad->puntaje_maximo}",
```

- [ ] En `calificarManual()` del mismo controller, el servicio ya acepta float — verificar que la llamada no hace cast a int:

Localizar:
```php
$this->calificacionService->calificarManual(
    $respuesta,
    $validated['calificacion'],
    $validated['feedback'] ?? null
);
```

Reemplazar por:
```php
$this->calificacionService->calificarManual(
    $respuesta,
    (float) $validated['calificacion'],
    $validated['feedback'] ?? null
);
```

- [ ] Agregar el método `guardarRubrica()` antes del método `misCalificaciones()`:

```php
/**
 * Instructor: califica una tarea usando rúbrica.
 * POST calificaciones/{respuesta}/rubrica
 */
public function guardarRubrica(Request $request, RespuestaEstudiante $respuesta)
{
    $this->verificarAcceso($respuesta);

    $actividad  = $respuesta->actividad->load('criteriosRubrica');
    $criterioIds = $actividad->criteriosRubrica->pluck('id')->toArray();

    $request->validate([
        'selecciones'   => 'required|array',
        'selecciones.*' => 'required|integer|exists:niveles_criterio,id',
        'feedback'      => 'nullable|string|max:2000',
    ]);

    // Verificar que todos los criterios tienen selección
    foreach ($criterioIds as $criterioId) {
        if (!array_key_exists($criterioId, $request->selecciones)) {
            return back()->withErrors([
                'selecciones' => 'Debes seleccionar un nivel para cada criterio de la rúbrica.',
            ]);
        }
    }

    $calificacion = $this->calificacionService->calificarConRubrica(
        $respuesta,
        $request->selecciones,
        $request->feedback
    );

    return redirect()->route('calificaciones.index')
        ->with('success', "Calificación guardada: {$calificacion} / {$actividad->puntaje_maximo} pts.");
}
```

- [ ] Agregar ruta `calificaciones.rubrica` en `routes/web.php` junto a las otras rutas de calificaciones:

```php
Route::post('/calificaciones/{respuesta}/rubrica',
    [\App\Http\Controllers\CalificacionController::class, 'guardarRubrica'])
    ->name('calificaciones.rubrica')
    ->middleware(['auth', 'instructor']);
```

- [ ] Ejecutar tests existentes:

```bash
php artisan test
```

Expected: todos los tests pasan.

- [ ] Commit:

```bash
git add app/Http/Controllers/CalificacionController.php routes/web.php
git commit -m "feat: CalificacionController decimal validation + guardarRubrica"
```

---

## Task 8: Vista actividades/edit.blade.php — constructor de rúbrica

**Files:**
- Modify: `resources/views/actividades/edit.blade.php`

- [ ] Localizar al final del archivo el bloque `@else` que corresponde a ensayo/tarea/practica (línea ~436). Reemplazar la sección `@else ... @endif` del tipo de actividad:

```blade
        @else
            {{-- Para ensayo, tarea, practica --}}
            <div class="space-y-6">

                {{-- Instrucciones y respuestas recibidas (existente) --}}
                <div class="card p-8">
                    <h3 class="text-lg font-bold text-gray-900 mb-2">Instrucciones para estudiantes</h3>
                    <p class="text-gray-600 mb-4">
                        Esta actividad de tipo <strong>{{ $actividad->tipo }}</strong> requiere calificación manual por parte del instructor.
                    </p>
                    @if($actividad->descripcion)
                    <div class="bg-gray-50 rounded-lg p-4 text-gray-700 text-sm">
                        {{ $actividad->descripcion }}
                    </div>
                    @endif
                    <div class="mt-6 border-t pt-4">
                        <h4 class="font-medium text-gray-900 mb-2">Respuestas recibidas</h4>
                        @php $respuestas = $actividad->respuestas()->with('usuario')->latest()->get(); @endphp
                        @forelse($respuestas as $resp)
                        <div class="border border-gray-200 rounded-lg p-4 mb-3">
                            <div class="flex justify-between items-start mb-2">
                                <span class="font-medium text-gray-800">{{ $resp->usuario->name }}</span>
                                <span class="badge
                                    @if($resp->estado === 'calificada') badge-green
                                    @elseif($resp->estado === 'en_revision') badge-yellow
                                    @else badge-gray @endif">
                                    {{ str_replace('_', ' ', $resp->estado) }}
                                </span>
                            </div>
                            <p class="text-sm text-gray-600 line-clamp-3">{{ $resp->respuesta }}</p>
                            @if($resp->calificacion !== null)
                                <p class="text-sm font-medium text-green-600 mt-1">
                                    Calificación: {{ number_format($resp->calificacion, 2) }} / {{ $actividad->puntaje_maximo }}
                                </p>
                            @endif
                        </div>
                        @empty
                            <p class="text-gray-500 text-sm">Aún no hay respuestas.</p>
                        @endforelse
                    </div>
                </div>

                {{-- ===== CONSTRUCTOR DE RÚBRICA (solo para tarea) ===== --}}
                @if($actividad->tipo === 'tarea')
                @php
                    $criteriosIniciales = $criteriosRubrica->map(fn($c) => [
                        'nombre'  => $c->nombre,
                        'niveles' => $c->niveles->map(fn($n) => [
                            'descripcion' => $n->descripcion,
                            'puntos'      => (string) $n->puntos,
                        ])->values()->toArray(),
                    ])->values()->toArray();
                @endphp

                <div class="card p-6"
                     x-data="{
                        usaRubrica: {{ $actividad->usa_rubrica ? 'true' : 'false' }},
                        criterios: {{ json_encode($criteriosIniciales) }},
                        importError: '',
                        importando: false,
                        modalImport: false,

                        get totalPuntos() {
                            return this.criterios.reduce((sum, c) => {
                                const max = Math.max(...c.niveles.map(n => parseFloat(n.puntos) || 0), 0);
                                return sum + max;
                            }, 0).toFixed(2);
                        },

                        agregarCriterio() {
                            this.criterios.push({ nombre: '', niveles: [{ descripcion: '', puntos: '' }] });
                        },

                        eliminarCriterio(i) {
                            this.criterios.splice(i, 1);
                        },

                        agregarNivel(ci) {
                            this.criterios[ci].niveles.push({ descripcion: '', puntos: '' });
                        },

                        eliminarNivel(ci, ni) {
                            if (this.criterios[ci].niveles.length > 1) {
                                this.criterios[ci].niveles.splice(ni, 1);
                            }
                        },

                        async importarArchivo(event) {
                            const file = event.target.files[0];
                            if (!file) return;
                            this.importando  = true;
                            this.importError = '';

                            const fd = new FormData();
                            fd.append('archivo', file);
                            fd.append('_token', document.querySelector('meta[name=csrf-token]').content);

                            try {
                                const res  = await fetch('{{ route('rubrica.importar', $actividad) }}', { method: 'POST', body: fd });
                                const data = await res.json();
                                if (!res.ok) {
                                    this.importError = data.error || 'Error al importar.';
                                } else {
                                    this.criterios   = data.criterios;
                                    this.usaRubrica  = true;
                                    this.modalImport = false;
                                }
                            } catch (e) {
                                this.importError = 'Error de conexión.';
                            } finally {
                                this.importando = false;
                                event.target.value = '';
                            }
                        }
                     }">

                    {{-- Encabezado --}}
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-bold text-gray-900">Rúbrica de evaluación</h3>
                        <label class="flex items-center gap-2 cursor-pointer">
                            <span class="text-sm text-gray-600">Activar rúbrica</span>
                            <div class="relative">
                                <input type="checkbox" x-model="usaRubrica" class="sr-only peer">
                                <div class="w-10 h-6 bg-gray-200 peer-checked:bg-dyl-blue rounded-full transition-colors"></div>
                                <div class="absolute top-1 left-1 w-4 h-4 bg-white rounded-full shadow transition-transform peer-checked:translate-x-4"></div>
                            </div>
                        </label>
                    </div>

                    <div x-show="usaRubrica" x-cloak class="space-y-4">

                        {{-- Botones de acción --}}
                        <div class="flex gap-3 flex-wrap">
                            <button type="button" @click="agregarCriterio()"
                                    class="btn-outline btn-sm flex items-center gap-1">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                                </svg>
                                Agregar criterio
                            </button>
                            <button type="button" @click="modalImport = true"
                                    class="btn-outline btn-sm flex items-center gap-1">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
                                </svg>
                                Importar desde Excel
                            </button>
                        </div>

                        {{-- Lista de criterios --}}
                        <div class="space-y-4">
                            <template x-for="(criterio, ci) in criterios" :key="ci">
                                <div class="border border-gray-200 rounded-xl p-4 bg-gray-50">
                                    <div class="flex items-start gap-3 mb-3">
                                        <div class="flex-1">
                                            <label class="form-label text-xs">Nombre del criterio</label>
                                            <input type="text" x-model="criterio.nombre"
                                                   placeholder="Ej: Planteamiento del Problema"
                                                   class="form-input">
                                        </div>
                                        <button type="button" @click="eliminarCriterio(ci)"
                                                class="mt-5 text-red-400 hover:text-red-600 transition-colors"
                                                title="Eliminar criterio">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                            </svg>
                                        </button>
                                    </div>

                                    {{-- Niveles del criterio --}}
                                    <div class="space-y-2 mb-3">
                                        <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Niveles (de menor a mayor)</p>
                                        <template x-for="(nivel, ni) in criterio.niveles" :key="ni">
                                            <div class="flex gap-2 items-start bg-white border border-gray-200 rounded-lg p-3">
                                                <span class="text-xs text-gray-400 mt-2 w-5 shrink-0" x-text="ni + 1 + '.'"></span>
                                                <textarea x-model="nivel.descripcion"
                                                          placeholder="Descripción del nivel..."
                                                          rows="2"
                                                          class="form-input flex-1 text-sm resize-none"></textarea>
                                                <div class="w-24 shrink-0">
                                                    <label class="text-xs text-gray-400 block mb-1">Puntos</label>
                                                    <input type="number" x-model="nivel.puntos"
                                                           step="0.01" min="0" max="99.99"
                                                           placeholder="0.00"
                                                           class="form-input text-sm text-center">
                                                </div>
                                                <button type="button" @click="eliminarNivel(ci, ni)"
                                                        x-show="criterio.niveles.length > 1"
                                                        class="mt-1 text-gray-300 hover:text-red-400 transition-colors">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                                    </svg>
                                                </button>
                                            </div>
                                        </template>
                                    </div>
                                    <button type="button" @click="agregarNivel(ci)"
                                            class="text-xs text-blue-600 hover:text-blue-800 flex items-center gap-1">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                                        </svg>
                                        + Nivel
                                    </button>
                                </div>
                            </template>

                            <div x-show="criterios.length === 0" class="text-center py-6 text-gray-400 text-sm border border-dashed border-gray-300 rounded-xl">
                                Sin criterios. Agrega uno arriba o importa desde Excel.
                            </div>
                        </div>

                        {{-- Total de puntos --}}
                        <div class="flex items-center justify-between px-4 py-3 bg-blue-50 border border-blue-200 rounded-xl">
                            <span class="text-sm font-medium text-blue-800">Puntaje máximo calculado:</span>
                            <span class="text-xl font-bold text-blue-700" x-text="totalPuntos + ' / 5.00'"></span>
                        </div>

                        {{-- Formulario oculto para guardar rúbrica --}}
                        <form :action="'{{ route('rubrica.store', '') }}/' + {{ $actividad->id }}" method="POST" id="form-rubrica">
                            @csrf
                            <input type="hidden" name="usa_rubrica" :value="usaRubrica ? '1' : '0'">
                            <input type="hidden" name="criterios_json" :value="JSON.stringify(criterios)">
                            <button type="submit"
                                    @click.prevent="
                                        document.querySelector('[name=criterios_json]').value = JSON.stringify(criterios);
                                        $el.closest('form').submit();
                                    "
                                    class="btn-primary w-full">
                                Guardar rúbrica
                            </button>
                        </form>
                    </div>

                    {{-- Si rubrica desactivada, botón para desactivar explícitamente --}}
                    <div x-show="!usaRubrica && {{ $actividad->usa_rubrica ? 'true' : 'false' }}" x-cloak>
                        <form action="{{ route('rubrica.store', $actividad) }}" method="POST">
                            @csrf
                            <input type="hidden" name="usa_rubrica" value="0">
                            <button type="submit" class="btn-outline btn-sm w-full mt-3">
                                Confirmar desactivación de rúbrica
                            </button>
                        </form>
                    </div>

                    {{-- Modal de importación --}}
                    <div x-show="modalImport" x-cloak
                         class="fixed inset-0 z-50 flex items-center justify-center bg-black/50"
                         @click.self="modalImport = false">
                        <div class="bg-white rounded-2xl shadow-xl p-8 max-w-md w-full mx-4">
                            <h3 class="text-lg font-bold text-gray-900 mb-4">Importar rúbrica desde Excel</h3>

                            <div class="mb-4 p-4 bg-blue-50 border border-blue-200 rounded-xl text-sm text-blue-800">
                                <p class="font-semibold mb-1">¿Cómo debe ir el archivo?</p>
                                <p>Columna A: nombre del criterio<br>
                                Columnas B en adelante: un nivel por columna<br>
                                Última línea de cada celda: el puntaje (ej: <strong>0.8 puntos</strong>)</p>
                            </div>

                            <a href="{{ route('rubrica.ejemplo') }}"
                               class="btn-outline w-full mb-4 flex items-center justify-center gap-2">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                                </svg>
                                Descargar archivo de ejemplo
                            </a>

                            <div>
                                <label class="form-label">Subir mi archivo (.xlsx)</label>
                                <input type="file" accept=".xlsx,.xls,.csv"
                                       @change="importarArchivo($event)"
                                       class="w-full text-sm text-gray-600 file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-blue-50 file:text-blue-700">
                            </div>

                            <p x-show="importando" class="text-sm text-blue-600 mt-2">Procesando archivo...</p>
                            <p x-show="importError" x-text="importError" class="text-sm text-red-600 mt-2"></p>

                            <button type="button" @click="modalImport = false"
                                    class="btn-outline w-full mt-4">Cancelar</button>
                        </div>
                    </div>

                </div>
                @endif

            </div>
        @endif
```

**Nota:** también hay que corregir el `RubricaController::store()` para que acepte los criterios desde JSON. El formulario envía `criterios_json` (string JSON). Agrega este parsing en `RubricaController::store()` antes de la validación:

```php
// Parsear criterios desde JSON si viene como string
if ($request->has('criterios_json')) {
    $criteriosDecoded = json_decode($request->criterios_json, true);
    $request->merge(['criterios' => $criteriosDecoded]);
}
```

- [ ] Commit:

```bash
git add resources/views/actividades/edit.blade.php app/Http/Controllers/RubricaController.php
git commit -m "feat: rubric builder in actividades/edit with Alpine.js + import modal"
```

---

## Task 9: Vista actividades/show.blade.php — tabla rúbrica para estudiante

**Files:**
- Modify: `resources/views/actividades/show.blade.php`
- Modify: `app/Http/Controllers/ActividadController.php`

- [ ] En `ActividadController::show()`, cargar criterios y selecciones si hay respuesta:

```php
public function show(Actividad $actividad)
{
    $this->authorize('view', $actividad->leccion->modulo->curso);
    $respuesta = $actividad->respuestas()
        ->where('user_id', auth()->id())
        ->with('seleccionesRubrica')
        ->latest()
        ->first();

    $criteriosRubrica = $actividad->usa_rubrica
        ? $actividad->criteriosRubrica()->with('niveles')->get()
        : collect();

    // Mapa criterio_id → nivel_criterio_id (selecciones del instructor)
    $seleccionesMap = $respuesta
        ? $respuesta->seleccionesRubrica->pluck('nivel_criterio_id', 'criterio_id')
        : collect();

    return view('actividades.show', compact('actividad', 'respuesta', 'criteriosRubrica', 'seleccionesMap'));
}
```

- [ ] En `resources/views/actividades/show.blade.php`, localizar la sección `{{-- Resultado si ya respondió --}}` y agregar ANTES de ella la tabla de rúbrica:

```blade
{{-- Tabla de rúbrica (solo si usa_rubrica y es tarea) --}}
@if($actividad->usa_rubrica && $criteriosRubrica->isNotEmpty())
<div class="bg-white rounded-xl shadow-sm border border-gray-200 mb-6 overflow-hidden">
    <div class="px-6 py-4 border-b border-gray-100 bg-gray-50">
        <h2 class="font-bold text-gray-900 flex items-center gap-2">
            <svg class="w-5 h-5 text-dyl-blue" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
            </svg>
            Criterios de calificación
        </h2>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="bg-gray-50 border-b border-gray-200">
                    <th class="text-left px-4 py-3 font-semibold text-gray-700 w-48">Criterio</th>
                    @foreach($criteriosRubrica->first()->niveles as $nivel)
                    <th class="text-center px-3 py-3 font-semibold text-gray-500 text-xs">
                        Nivel {{ $loop->iteration }}<br>
                        <span class="text-green-600 font-bold">{{ number_format($nivel->puntos, 2) }} pts</span>
                    </th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach($criteriosRubrica as $criterio)
                <tr class="border-b border-gray-100 {{ $loop->even ? 'bg-gray-50/50' : '' }}">
                    <td class="px-4 py-4 font-medium text-gray-800 align-top">
                        {{ $criterio->nombre }}
                    </td>
                    @foreach($criterio->niveles as $nivel)
                    @php $estaSeleccionado = $seleccionesMap->get($criterio->id) == $nivel->id; @endphp
                    <td class="px-3 py-4 align-top text-xs text-gray-600 leading-relaxed
                        {{ $estaSeleccionado ? 'bg-green-50 border-l-2 border-green-400' : '' }}">
                        @if($estaSeleccionado)
                            <span class="inline-block mb-1 text-green-600 font-semibold text-[10px] uppercase tracking-wide">✓ Nivel obtenido</span><br>
                        @endif
                        {{ $nivel->descripcion }}
                        <span class="block mt-2 font-bold {{ $estaSeleccionado ? 'text-green-600' : 'text-gray-400' }}">
                            {{ number_format($nivel->puntos, 2) }} puntos
                        </span>
                    </td>
                    @endforeach
                </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr class="bg-gray-50">
                    <td class="px-4 py-3 font-semibold text-gray-700">Puntaje máximo</td>
                    @foreach($criteriosRubrica->first()->niveles as $nivel)
                    <td class="px-3 py-3 text-center"></td>
                    @endforeach
                </tr>
            </tfoot>
        </table>
    </div>
    <div class="px-6 py-3 bg-gray-50 border-t border-gray-100 text-right text-sm text-gray-500">
        Nota máxima: <strong class="text-gray-800">{{ number_format($actividad->puntaje_maximo, 2) }} pts</strong>
    </div>
</div>
@endif
```

- [ ] Commit:

```bash
git add resources/views/actividades/show.blade.php app/Http/Controllers/ActividadController.php
git commit -m "feat: rubric table visible to students in actividades/show"
```

---

## Task 10: Vista calificaciones/show.blade.php — interfaz de calificación con rúbrica

**Files:**
- Modify: `resources/views/calificaciones/show.blade.php`
- Modify: `app/Http/Controllers/CalificacionController.php` (método show)

- [ ] En `CalificacionController::show()`, cargar criterios y selecciones existentes:

```php
public function show(RespuestaEstudiante $respuesta)
{
    $this->verificarAcceso($respuesta);
    $respuesta->load(['usuario', 'actividad.leccion.modulo.curso', 'seleccionesRubrica']);
    $criteriosRubrica = $respuesta->actividad->usa_rubrica
        ? $respuesta->actividad->criteriosRubrica()->with('niveles')->get()
        : collect();
    $seleccionesActuales = $respuesta->seleccionesRubrica->pluck('nivel_criterio_id', 'criterio_id');
    return view('calificaciones.show', compact('respuesta', 'criteriosRubrica', 'seleccionesActuales'));
}
```

- [ ] En `resources/views/calificaciones/show.blade.php`, reemplazar el bloque `{{-- Formulario de calificación --}}` (div que empieza en línea ~50):

```blade
        {{-- Formulario de calificación --}}
        <div class="bg-white rounded-lg shadow p-6">
            <h2 class="text-lg font-bold text-gray-900 mb-4">Calificación</h2>

            @if($errors->any())
                <div class="bg-red-50 border border-red-200 text-red-700 rounded-lg p-3 mb-4 text-sm">
                    @foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach
                </div>
            @endif

            @if($respuesta->actividad->usa_rubrica && $criteriosRubrica->isNotEmpty())
            {{-- ===== FORMULARIO CON RÚBRICA ===== --}}
            @php
                $nivelPuntos = $criteriosRubrica
                    ->flatMap(fn($c) => $c->niveles)
                    ->pluck('puntos', 'id')
                    ->map(fn($p) => (float) $p);
                $seleccionesJson = $seleccionesActuales->toJson();
            @endphp

            <form action="{{ route('calificaciones.rubrica', $respuesta) }}" method="POST"
                  x-data="{
                    selecciones: {{ $seleccionesJson }},
                    nivelPuntos: {{ $nivelPuntos->toJson() }},
                    totalCriterios: {{ $criteriosRubrica->count() }},

                    get totalSeleccionado() {
                        return Object.values(this.selecciones)
                            .reduce((sum, id) => sum + (this.nivelPuntos[id] || 0), 0)
                            .toFixed(2);
                    },

                    get todosSeleccionados() {
                        return Object.keys(this.selecciones).length >= this.totalCriterios;
                    }
                  }">
                @csrf

                <div class="space-y-4 mb-5">
                    @foreach($criteriosRubrica as $criterio)
                    <div class="border border-gray-200 rounded-xl overflow-hidden">
                        <div class="px-4 py-2 bg-gray-50 border-b border-gray-200">
                            <p class="font-semibold text-gray-800 text-sm">{{ $criterio->nombre }}</p>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-{{ min($criterio->niveles->count(), 4) }} divide-y sm:divide-y-0 sm:divide-x divide-gray-100">
                            @foreach($criterio->niveles->sortBy('orden') as $nivel)
                            <label class="cursor-pointer p-3 hover:bg-blue-50 transition-colors"
                                   :class="selecciones[{{ $criterio->id }}] == {{ $nivel->id }} ? 'bg-blue-50 ring-2 ring-inset ring-dyl-blue' : ''">
                                <input type="radio"
                                       name="selecciones[{{ $criterio->id }}]"
                                       value="{{ $nivel->id }}"
                                       x-model="selecciones[{{ $criterio->id }}]"
                                       class="sr-only">
                                <p class="text-xs text-gray-600 leading-relaxed mb-2">{{ $nivel->descripcion }}</p>
                                <p class="text-sm font-bold text-green-600">{{ number_format($nivel->puntos, 2) }} pts</p>
                            </label>
                            @endforeach
                        </div>
                    </div>
                    @endforeach
                </div>

                {{-- Contador en tiempo real --}}
                <div class="flex items-center justify-between px-4 py-3 bg-blue-50 border border-blue-200 rounded-xl mb-4">
                    <span class="text-sm font-medium text-blue-800">Calificación actual:</span>
                    <span class="text-2xl font-bold text-blue-700">
                        <span x-text="totalSeleccionado"></span>
                        <span class="text-base font-normal text-blue-500"> / {{ number_format($respuesta->actividad->puntaje_maximo, 2) }}</span>
                    </span>
                </div>

                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        Retroalimentación <span class="text-gray-400 font-normal">(opcional)</span>
                    </label>
                    <textarea name="feedback" rows="5"
                              placeholder="Comentarios para el estudiante..."
                              class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 resize-none">{{ old('feedback', $respuesta->feedback) }}</textarea>
                </div>

                <button type="submit"
                        :disabled="!todosSeleccionados"
                        :class="todosSeleccionados ? 'bg-blue-600 hover:bg-blue-700' : 'bg-gray-300 cursor-not-allowed'"
                        class="w-full text-white py-2.5 rounded-lg font-medium transition-colors">
                    <span x-show="todosSeleccionados">Guardar Calificación</span>
                    <span x-show="!todosSeleccionados">Selecciona un nivel por cada criterio</span>
                </button>
            </form>

            @else
            {{-- ===== FORMULARIO MANUAL (sin rúbrica) ===== --}}
            <form action="{{ route('calificaciones.update', $respuesta) }}" method="POST" class="space-y-5">
                @csrf @method('PUT')

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        Calificación (0 – {{ number_format($respuesta->actividad->puntaje_maximo, 2) }})
                    </label>
                    <div class="flex items-center gap-3">
                        <input type="number"
                               name="calificacion"
                               min="0"
                               max="{{ $respuesta->actividad->puntaje_maximo }}"
                               step="0.01"
                               value="{{ old('calificacion', $respuesta->calificacion) }}"
                               class="w-28 border border-gray-300 rounded-lg px-3 py-2 text-center text-2xl font-bold focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                               required>
                        <span class="text-gray-400 text-lg">/ {{ number_format($respuesta->actividad->puntaje_maximo, 2) }}</span>
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        Retroalimentación <span class="text-gray-400 font-normal">(opcional)</span>
                    </label>
                    <textarea name="feedback" rows="7"
                              placeholder="Escribe comentarios para el estudiante..."
                              class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 resize-none">{{ old('feedback', $respuesta->feedback) }}</textarea>
                </div>

                <button type="submit"
                        class="w-full bg-blue-600 text-white py-2.5 rounded-lg hover:bg-blue-700 font-medium transition-colors">
                    Guardar Calificación
                </button>
            </form>
            @endif

            @if($respuesta->fecha_calificacion)
                <p class="text-xs text-gray-400 text-center mt-3">
                    Última calificación: {{ $respuesta->fecha_calificacion->format('d/m/Y H:i') }}
                </p>
            @endif
        </div>
```

- [ ] Commit:

```bash
git add resources/views/calificaciones/show.blade.php app/Http/Controllers/CalificacionController.php
git commit -m "feat: rubric grading UI in calificaciones/show with live grade calculator"
```

---

## Task 11: Actualizar display decimal en vistas restantes

**Files:**
- Modify: `resources/views/calificaciones/index.blade.php`
- Modify: `resources/views/calificaciones/mis-calificaciones.blade.php`
- Modify: `resources/views/actividades/edit.blade.php` (el input de puntaje_maximo)

- [ ] En `calificaciones/index.blade.php`, buscar cualquier lugar donde se muestre la calificación y agregar `number_format(..., 2)`:

```bash
grep -n "calificacion" "C:\xampp\htdocs\LMS DyL\lms-dyl-quality\resources\views\calificaciones\index.blade.php"
```

Cambiar patrones como `$resp->calificacion` por `number_format($resp->calificacion, 2)` y `$resp->actividad->puntaje_maximo` por `number_format($resp->actividad->puntaje_maximo, 2)`.

- [ ] En `calificaciones/mis-calificaciones.blade.php`, hacer el mismo reemplazo de `number_format` para calificacion y puntaje_maximo.

- [ ] En `actividades/edit.blade.php`, el input de puntaje_maximo (línea ~41) — cambiar `min="1"` a `min="0.01"` y agregar `step="0.01"` para que acepte decimales:

```blade
<input type="number" name="puntaje_maximo" 
       value="{{ old('puntaje_maximo', $actividad->puntaje_maximo) }}" 
       min="0.01" step="0.01"
       class="form-input" required>
```

- [ ] Ejecutar todos los tests:

```bash
php artisan test
```

Expected: todos los tests pasan.

- [ ] Compilar assets:

```bash
npm run build
```

Expected: sin errores.

- [ ] Commit:

```bash
git add resources/views/calificaciones/ resources/views/actividades/edit.blade.php
git commit -m "fix: number_format decimal display in calificaciones views"
```

---

## Task 12: Tests

**Files:**
- Create: `tests/Feature/RubricaTest.php`

- [ ] Crear el test:

```php
<?php

namespace Tests\Feature;

use App\Models\Actividad;
use App\Models\CriterioRubrica;
use App\Models\Curso;
use App\Models\Inscripcion;
use App\Models\Leccion;
use App\Models\Modulo;
use App\Models\NivelCriterio;
use App\Models\Rol;
use App\Models\RespuestaEstudiante;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RubricaTest extends TestCase
{
    use RefreshDatabase;

    private User $instructor;
    private User $estudiante;
    private Actividad $actividad;

    protected function setUp(): void
    {
        parent::setUp();

        $rolInstructor = Rol::create(['nombre' => 'Instructor']);
        $rolEstudiante = Rol::create(['nombre' => 'Estudiante']);
        Rol::create(['nombre' => 'Administrador']);

        $this->instructor = User::factory()->create(['estado' => 'activo']);
        $this->instructor->roles()->attach($rolInstructor);

        $this->estudiante = User::factory()->create(['estado' => 'activo']);
        $this->estudiante->roles()->attach($rolEstudiante);

        $curso = Curso::factory()->create(['created_by' => $this->instructor->id, 'estado' => 'publicado']);
        $modulo = Modulo::factory()->create(['curso_id' => $curso->id]);
        $leccion = Leccion::factory()->create(['modulo_id' => $modulo->id]);

        $this->actividad = Actividad::factory()->create([
            'leccion_id'     => $leccion->id,
            'tipo'           => 'tarea',
            'puntaje_maximo' => 5.00,
            'usa_rubrica'    => false,
        ]);

        Inscripcion::create([
            'user_id'     => $this->estudiante->id,
            'curso_id'    => $curso->id,
            'fecha_inicio' => now(),
            'estado'      => 'en_progreso',
        ]);
    }

    public function test_instructor_puede_guardar_rubrica(): void
    {
        $response = $this->actingAs($this->instructor)->post(
            route('rubrica.store', $this->actividad),
            [
                'usa_rubrica'   => '1',
                'criterios_json' => json_encode([
                    [
                        'nombre'  => 'Planteamiento',
                        'niveles' => [
                            ['descripcion' => 'Deficiente', 'puntos' => '0'],
                            ['descripcion' => 'Aceptable',  'puntos' => '0.5'],
                            ['descripcion' => 'Excelente',  'puntos' => '1.0'],
                        ],
                    ],
                    [
                        'nombre'  => 'Metodología',
                        'niveles' => [
                            ['descripcion' => 'Deficiente', 'puntos' => '0'],
                            ['descripcion' => 'Excelente',  'puntos' => '1.5'],
                        ],
                    ],
                ]),
            ]
        );

        $response->assertRedirect(route('actividades.edit', $this->actividad));
        $this->assertDatabaseHas('criterios_rubrica', ['actividad_id' => $this->actividad->id, 'nombre' => 'Planteamiento']);
        $this->assertDatabaseHas('niveles_criterio', ['descripcion' => 'Excelente', 'puntos' => 1.0]);
        $this->actividad->refresh();
        $this->assertTrue($this->actividad->usa_rubrica);
        $this->assertEquals(2.5, (float) $this->actividad->puntaje_maximo); // 1.0 + 1.5
    }

    public function test_rubrica_visible_para_estudiante(): void
    {
        $this->actividad->update(['usa_rubrica' => true]);
        $criterio = CriterioRubrica::create(['actividad_id' => $this->actividad->id, 'nombre' => 'Criterio Test', 'orden' => 0]);
        NivelCriterio::create(['criterio_id' => $criterio->id, 'descripcion' => 'Nivel bajo', 'puntos' => 0, 'orden' => 0]);
        NivelCriterio::create(['criterio_id' => $criterio->id, 'descripcion' => 'Nivel alto', 'puntos' => 1.0, 'orden' => 1]);

        $response = $this->actingAs($this->estudiante)->get(route('actividades.show', $this->actividad));

        $response->assertOk();
        $response->assertSee('Criterio Test');
        $response->assertSee('Nivel bajo');
        $response->assertSee('1.00');
    }

    public function test_instructor_puede_calificar_con_rubrica(): void
    {
        $this->actividad->update(['usa_rubrica' => true, 'puntaje_maximo' => 2.5]);

        $criterio1 = CriterioRubrica::create(['actividad_id' => $this->actividad->id, 'nombre' => 'C1', 'orden' => 0]);
        $nivel1a   = NivelCriterio::create(['criterio_id' => $criterio1->id, 'descripcion' => 'Bajo',  'puntos' => 0,   'orden' => 0]);
        $nivel1b   = NivelCriterio::create(['criterio_id' => $criterio1->id, 'descripcion' => 'Alto',  'puntos' => 1.0, 'orden' => 1]);

        $criterio2 = CriterioRubrica::create(['actividad_id' => $this->actividad->id, 'nombre' => 'C2', 'orden' => 1]);
        $nivel2a   = NivelCriterio::create(['criterio_id' => $criterio2->id, 'descripcion' => 'Bajo',  'puntos' => 0,   'orden' => 0]);
        $nivel2b   = NivelCriterio::create(['criterio_id' => $criterio2->id, 'descripcion' => 'Alto',  'puntos' => 1.5, 'orden' => 1]);

        $respuesta = RespuestaEstudiante::create([
            'user_id'     => $this->estudiante->id,
            'actividad_id' => $this->actividad->id,
            'respuesta'   => 'Mi entrega',
            'estado'      => 'sin_calificar',
            'fecha_envio' => now(),
        ]);

        $response = $this->actingAs($this->instructor)->post(
            route('calificaciones.rubrica', $respuesta),
            [
                'selecciones' => [
                    $criterio1->id => $nivel1b->id,   // 1.0
                    $criterio2->id => $nivel2b->id,   // 1.5
                ],
                'feedback' => 'Buen trabajo',
            ]
        );

        $response->assertRedirect(route('calificaciones.index'));
        $respuesta->refresh();
        $this->assertEquals(2.5, (float) $respuesta->calificacion);
        $this->assertEquals('calificada', $respuesta->estado);
        $this->assertDatabaseHas('selecciones_rubrica', [
            'respuesta_estudiante_id' => $respuesta->id,
            'criterio_id'             => $criterio1->id,
            'nivel_criterio_id'       => $nivel1b->id,
        ]);
    }

    public function test_no_puede_calificar_rubrica_sin_todos_los_criterios(): void
    {
        $this->actividad->update(['usa_rubrica' => true]);
        $criterio1 = CriterioRubrica::create(['actividad_id' => $this->actividad->id, 'nombre' => 'C1', 'orden' => 0]);
        $nivel1b   = NivelCriterio::create(['criterio_id' => $criterio1->id, 'descripcion' => 'Alto', 'puntos' => 1.0, 'orden' => 0]);
        $criterio2 = CriterioRubrica::create(['actividad_id' => $this->actividad->id, 'nombre' => 'C2', 'orden' => 1]);
        NivelCriterio::create(['criterio_id' => $criterio2->id, 'descripcion' => 'Alto', 'puntos' => 1.5, 'orden' => 0]);

        $respuesta = RespuestaEstudiante::create([
            'user_id' => $this->estudiante->id, 'actividad_id' => $this->actividad->id,
            'respuesta' => 'Entrega', 'estado' => 'sin_calificar', 'fecha_envio' => now(),
        ]);

        // Solo selecciona 1 de 2 criterios
        $response = $this->actingAs($this->instructor)->post(
            route('calificaciones.rubrica', $respuesta),
            ['selecciones' => [$criterio1->id => $nivel1b->id]]
        );

        $response->assertRedirect(); // back with error
        $respuesta->refresh();
        $this->assertNull($respuesta->calificacion);
    }

    public function test_calificacion_manual_acepta_decimales(): void
    {
        $this->actividad->update(['puntaje_maximo' => 5.00]);

        $respuesta = RespuestaEstudiante::create([
            'user_id' => $this->estudiante->id, 'actividad_id' => $this->actividad->id,
            'respuesta' => 'Texto', 'estado' => 'sin_calificar', 'fecha_envio' => now(),
        ]);

        $response = $this->actingAs($this->instructor)->put(
            route('calificaciones.update', $respuesta),
            ['calificacion' => '3.5', 'feedback' => 'OK']
        );

        $response->assertRedirect(route('calificaciones.index'));
        $respuesta->refresh();
        $this->assertEquals(3.5, (float) $respuesta->calificacion);
    }
}
```

- [ ] Ejecutar los tests nuevos:

```bash
php artisan test tests/Feature/RubricaTest.php
```

Expected: 4 tests, todos en verde.

- [ ] Ejecutar suite completa:

```bash
php artisan test
```

Expected: todos los tests pasan.

- [ ] Commit final:

```bash
git add tests/Feature/RubricaTest.php
git commit -m "test: RubricaTest - store, student view, rubric grading, manual decimal"
git push origin master
```

---

## Verificación manual (en http://localhost:8002)

- [ ] Login como instructor → editar una tarea → activar toggle rúbrica → agregar 2 criterios con 4 niveles cada uno → guardar → verificar que puntaje_maximo se actualiza
- [ ] Descargar el archivo de ejemplo desde el modal de importación
- [ ] Importar un archivo Excel con rúbrica → verificar que los criterios se cargan en el builder
- [ ] Login como estudiante → ver la actividad → verificar que aparece la tabla de rúbrica
- [ ] Login como instructor → calificaciones pendientes → abrir la respuesta de la tarea → seleccionar niveles → guardar → verificar nota = suma de puntos
- [ ] Verificar que la nota se muestra como `X.XX / 5.00` en mis-calificaciones
