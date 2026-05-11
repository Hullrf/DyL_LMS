# Diseño: Rúbrica de Calificación y Escala 0-5.0

**Fecha:** 2026-05-11
**Proyecto:** LMS DyL Quality Consulting
**Estado:** Aprobado

---

## Resumen

Dos cambios relacionados:
1. **Escala global 0-5.0** para todas las actividades (cuestionarios, ensayos, tareas, prácticas).
2. **Sistema de rúbrica** opcional para actividades tipo *tarea* con entrega de archivo, donde el instructor define criterios de evaluación con niveles y puntos, y califica seleccionando el nivel alcanzado por cada criterio.

---

## 1. Modelo de Datos

### Tablas nuevas

```sql
-- Criterios de una rúbrica (uno por fila de la tabla)
CREATE TABLE criterios_rubrica (
    id           BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    actividad_id BIGINT UNSIGNED NOT NULL,
    nombre       VARCHAR(255) NOT NULL,
    orden        INT DEFAULT 0,
    created_at   TIMESTAMP,
    updated_at   TIMESTAMP,
    FOREIGN KEY (actividad_id) REFERENCES actividades(id) ON DELETE CASCADE,
    INDEX idx_actividad_id (actividad_id)
);

-- Niveles de cada criterio (columnas de la tabla)
CREATE TABLE niveles_criterio (
    id          BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    criterio_id BIGINT UNSIGNED NOT NULL,
    descripcion LONGTEXT NOT NULL,
    puntos      DECIMAL(4,2) NOT NULL,
    orden       INT DEFAULT 0,
    created_at  TIMESTAMP,
    updated_at  TIMESTAMP,
    FOREIGN KEY (criterio_id) REFERENCES criterios_rubrica(id) ON DELETE CASCADE,
    INDEX idx_criterio_id (criterio_id)
);

-- Selecciones del instructor al calificar
CREATE TABLE selecciones_rubrica (
    id                       BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    respuesta_estudiante_id  BIGINT UNSIGNED NOT NULL,
    criterio_id              BIGINT UNSIGNED NOT NULL,
    nivel_criterio_id        BIGINT UNSIGNED NOT NULL,
    created_at               TIMESTAMP,
    updated_at               TIMESTAMP,
    FOREIGN KEY (respuesta_estudiante_id) REFERENCES respuestas_estudiantes(id) ON DELETE CASCADE,
    FOREIGN KEY (criterio_id)             REFERENCES criterios_rubrica(id) ON DELETE CASCADE,
    FOREIGN KEY (nivel_criterio_id)       REFERENCES niveles_criterio(id) ON DELETE CASCADE,
    UNIQUE KEY unique_seleccion (respuesta_estudiante_id, criterio_id)
);
```

### Cambios en tablas existentes

| Tabla | Campo | Cambio |
|-------|-------|--------|
| `actividades` | `puntaje_maximo` | `INTEGER(100)` → `DECIMAL(4,2)` default `5.00` |
| `actividades` | `usa_rubrica` | Nueva columna `BOOLEAN` default `false` |
| `preguntas` | `puntaje` | `INTEGER(10)` → `DECIMAL(4,2)` default `1.00` |
| `respuestas_estudiantes` | `calificacion` | `INTEGER` → `DECIMAL(4,2)` |

**Migración de datos:** Al correr la migración, los registros existentes de `actividades.puntaje_maximo = 100` se actualizan a `5.00`. Las calificaciones existentes en `respuestas_estudiantes.calificacion` se convierten proporcionalmente (`valor_viejo / 100 * 5`).

---

## 2. Modelos Eloquent

### Nuevos modelos

**`App\Models\CriterioRubrica`**
- `belongsTo(Actividad)`
- `hasMany(NivelCriterio)` ordenados por `orden`

**`App\Models\NivelCriterio`**
- `belongsTo(CriterioRubrica)`

**`App\Models\SeleccionRubrica`**
- `belongsTo(RespuestaEstudiante)`
- `belongsTo(CriterioRubrica)`
- `belongsTo(NivelCriterio)`

### Cambios en modelos existentes

**`Actividad`**
- `hasMany(CriterioRubrica)` ordenados por `orden`
- Método `puntajeRubrica(): float` — suma el nivel máximo de cada criterio
- Cast `puntaje_maximo` → `decimal:2`, `usa_rubrica` → `boolean`

**`RespuestaEstudiante`**
- `hasMany(SeleccionRubrica)`
- Cast `calificacion` → `decimal:2`

---

## 3. Servicios

### `CalificacionService` — cambios

```php
// Retorna float en lugar de int
public function calcularCuestionario(...): float

// Acepta float en lugar de int
public function calificarManual(RespuestaEstudiante $r, float $calificacion, ?string $feedback): void

// Nuevo método para rúbrica
public function calificarConRubrica(
    RespuestaEstudiante $respuesta,
    array $selecciones,   // [criterio_id => nivel_criterio_id]
    ?string $feedback
): float                  // retorna la nota calculada
```

`calificarConRubrica` persiste en `selecciones_rubrica`, suma los `puntos` de cada nivel seleccionado y llama a `calificarManual` con el total.

### `RubricaImportService` — nuevo

```php
public function generarEjemplo(): \Maatwebsite\Excel\...|BinaryFileResponse
public function importar(UploadedFile $archivo): array  // retorna estructura de criterios/niveles
```

El parser lee cada fila del Excel. Por cada celda de nivel (columnas B en adelante), extrae los puntos de la **última línea** con el patrón `(\d+(?:\.\d+)?)\s*(puntos|pts)?` (case-insensitive). El resto del texto de la celda es la descripción.

---

## 4. Controladores y Rutas

### Rutas nuevas

```php
// Gestión de rúbrica (instructor)
Route::middleware(['auth', 'instructor'])->group(function () {
    Route::post('/actividades/{actividad}/rubrica',    [RubricaController::class, 'store'])->name('rubrica.store');
    Route::get('/rubrica/ejemplo',                     [RubricaController::class, 'ejemplo'])->name('rubrica.ejemplo');
    Route::post('/rubrica/importar/{actividad}',       [RubricaController::class, 'importar'])->name('rubrica.importar');
});

// Calificación con rúbrica (instructor)
Route::middleware(['auth', 'instructor'])->group(function () {
    Route::post('/calificaciones/{respuesta}/rubrica', [CalificacionController::class, 'guardarRubrica'])->name('calificaciones.rubrica');
});
```

### `RubricaController` — nuevo

- `store(Request, Actividad)` — guarda/reemplaza todos los criterios y niveles de la rúbrica en una sola operación (recibe el JSON del builder)
- `ejemplo()` — descarga el archivo Excel de ejemplo
- `importar(Request, Actividad)` — lee el archivo subido, retorna JSON con los criterios/niveles parseados (no los guarda aún, los manda al frontend para revisión)

### `CalificacionController` — nuevo método

- `guardarRubrica(Request, RespuestaEstudiante)` — valida que todos los criterios tengan nivel seleccionado, llama a `calificarConRubrica`, redirige a la lista

### `ActividadController` — cambios mínimos

- `edit()` ya carga la actividad con sus relaciones; agregar `criteriosRubrica.nivelescriterio`
- `update()` ya guarda los campos de actividad; el toggle `usa_rubrica` se agrega al `$fillable`

---

## 5. Vistas

### `actividades/edit.blade.php` — sección de rúbrica (solo para tipo `tarea`)

- Toggle Alpine.js: **"Usar rúbrica de evaluación"** (bindea `usa_rubrica`)
- Cuando activo: muestra el builder con estado inicial de criterios existentes en BD
- **Builder:** lista dinámica de criterios; por cada criterio, lista de niveles con textarea de descripción + input de puntos
- **Contador en tiempo real:** suma de los puntos máximos de cada criterio → `X.XX / 5.00`
- **Importar:** botón "Importar desde Excel" → modal con:
  - Botón "Descargar archivo de ejemplo"
  - Input de archivo
  - Al subir: petición a `rubrica.importar` → carga los criterios parseados en el builder (el instructor revisa y puede editar antes de guardar)
- **Guardar:** al guardar la actividad, se hace POST a `rubrica.store` con los criterios/niveles del builder en JSON

### `actividades/show.blade.php` — tabla rúbrica (estudiante)

- Condición: `$actividad->usa_rubrica && $actividad->tipo === 'tarea'`
- Tabla responsive con criterios como filas y niveles como columnas
- Encabezado de cada columna: puntos del nivel (ej: `0.33 pts`)
- Si la respuesta del estudiante ya está calificada: resaltar la columna seleccionada por el instructor en cada fila
- En móvil: cada criterio como tarjeta con niveles apilados

### `calificaciones/show.blade.php` — interfaz de calificación

- Si `$respuesta->actividad->usa_rubrica`:
  - Muestra tabla interactiva con radio buttons por nivel
  - Contador de nota en tiempo real (Alpine.js)
  - Botón "Guardar" deshabilitado hasta que todos los criterios tengan selección
  - POST a `calificaciones.rubrica`
- Si no tiene rúbrica (o no es tarea):
  - Input decimal `0` a `5.00` (comportamiento actual, solo cambia el max)

---

## 6. Archivo de Ejemplo Excel

Generado con `maatwebsite/excel`. Contiene **2 criterios completos** con 4 niveles cada uno:

| Criterio | Nivel 1 | Nivel 2 | Nivel 3 | Nivel 4 |
|----------|---------|---------|---------|---------|
| Planteamiento del Problema y Justificación | El problema no está claramente descrito...\n\n**0 puntos** | El problema se describe de forma general...\n\n**0.33 puntos** | El problema está claramente descrito...\n\n**0.8 puntos** | El problema está planteado con total claridad...\n\n**1.0 puntos** |
| Formulación de Pregunta y Objetivos | La pregunta es vaga...\n\n**0 puntos** | La pregunta existe pero...\n\n**0.25 puntos** | La pregunta es clara...\n\n**0.5 puntos** | La pregunta es precisa...\n\n**1.0 puntos** |

- Puntos en la última línea de cada celda, en **negrita y color verde** (`#2E7D32`)
- Hoja con instrucciones en pestaña separada explicando el formato
- Primera fila congelada (freeze panes)

---

## 7. Reglas de Negocio

- La rúbrica solo aplica a actividades tipo `tarea`
- Cuando `usa_rubrica = true`, el campo `puntaje_maximo` es calculado automáticamente (suma del nivel máximo de todos los criterios) y no editable manualmente
- Al importar desde Excel, los criterios se muestran en el builder para revisión — no se guardan directamente
- El instructor no puede guardar una calificación con rúbrica si algún criterio no tiene nivel seleccionado
- Al desactivar la rúbrica en una actividad, los criterios se conservan en BD (no se eliminan) por si el instructor quiere reactivarla
- Las calificaciones existentes con rúbrica muestran el desglose de selecciones al estudiante y al instructor

---

## 8. Testing

- Unit: `CalificacionServiceTest` — agregar casos para `calificarConRubrica` y escala decimal
- Feature: `RubricaTest` — crear actividad con rúbrica, importar Excel, calificar con selecciones, verificar nota calculada
- Feature: `EscalaDecimalTest` — verificar que cuestionarios, ensayos y tareas generan calificaciones en escala 0-5.0
