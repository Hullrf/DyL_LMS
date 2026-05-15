# Actividades sin nota en lecciones

**Fecha:** 2026-05-15  
**Estado:** Aprobado  

## Resumen

Agregar 4 nuevos tipos de actividad (ejercicio, lectura, encuesta, reflexion) que no requieren calificación. Son de consulta únicamente: muestran descripción y recursos pero no presentan formulario de respuesta. Son completamente opcionales y no afectan el progreso ni el promedio final del estudiante.

---

## Base de datos

Una migración que hace `puntaje_maximo` nullable en la tabla `actividades`.

```sql
ALTER TABLE actividades MODIFY puntaje_maximo decimal(8,2) NULL;
```

El campo `tipo` en MySQL es `varchar`, por lo que los nuevos valores (`ejercicio`, `lectura`, `encuesta`, `reflexion`) solo requieren actualizar la validación en Laravel (`in:` rule), sin cambio DDL adicional.

**Regla:** los 4 tipos nuevos siempre tienen `puntaje_maximo = NULL`. Los 4 tipos existentes (`cuestionario`, `ensayo`, `tarea`, `practica`) siguen requiriendo `puntaje_maximo > 0`.

---

## Modelo y lógica de negocio

**`App\Models\Actividad`**

Agregar método helper:

```php
public function tieneCalificacion(): bool
{
    return !in_array($this->tipo, ['ejercicio', 'lectura', 'encuesta', 'reflexion']);
}
```

`puntaje_maximo` ya es `decimal:2` en `$casts`; se elimina cualquier aserción de non-null implícita.

**`CalificacionService`** — sin cambios. Solo se invoca desde `RespuestaEstudianteController`, que no es accesible para actividades sin nota.

**`CertificadoService`** — sin cambios. El cálculo de calificación final opera sobre `RespuestaEstudiante`; como las actividades sin nota no generan respuestas, quedan excluidas automáticamente.

**`ReporteService`** — cambio menor: al contar actividades calificables, filtrar por `puntaje_maximo IS NOT NULL` (o excluir los 4 tipos sin nota) para evitar divisiones por cero o promedios incorrectos.

---

## Controlador

**`ActividadController::store()` y `update()`**

Validación condicional:

```php
'puntaje_maximo' => [
    Rule::requiredIf(fn() => !in_array($request->tipo, ['ejercicio','lectura','encuesta','reflexion'])),
    'nullable',
    'decimal:0,2',
    'min:0.01',
    'max:999.99',
],
```

Resto de métodos sin cambios.

---

## Vistas

### `actividades/create.blade.php`

- Agregar 4 opciones al `<select name="tipo">`:
  - `ejercicio` → "Ejercicio (sin nota)"
  - `lectura` → "Lectura / Recurso (sin nota)"
  - `encuesta` → "Encuesta / Sondeo (sin nota)"
  - `reflexion` → "Reflexión (sin nota)"
- Con Alpine.js (`x-show`): ocultar el campo "Puntaje máximo" cuando el tipo es uno de los 4 nuevos.

### `actividades/edit.blade.php`

- Misma lógica Alpine.js para ocultar/mostrar "Puntaje máximo" según el tipo.

### `actividades/show.blade.php`

Cuando `$actividad->tieneCalificacion()` es `false`:

- Reemplazar el bloque de puntaje en el encabezado por un badge "Sin calificación".
- Omitir completamente el formulario de respuesta.
- Mostrar bloque informativo: "Esta actividad es de consulta, no requiere entrega."
- Recursos y descripción se muestran normalmente.

---

## Testing

Archivo nuevo: `tests/Feature/ActividadSinNotaTest.php`

| # | Test | Resultado esperado |
|---|------|--------------------|
| 1 | Instructor crea actividad `lectura` sin puntaje | `puntaje_maximo = NULL` en DB |
| 2 | Instructor crea actividad `cuestionario` sin puntaje | Error de validación |
| 3 | Vista `show` de actividad `lectura` | Sin formulario de respuesta |
| 4 | Actividades sin nota excluidas del promedio final | Certificado no las considera |

Los 56 tests existentes no se rompen: `puntaje_maximo` pasa a nullable, los tests actuales usan tipos con nota y siguen enviando el campo.

---

## Archivos afectados

| Archivo | Cambio |
|---------|--------|
| `database/migrations/XXXX_make_puntaje_maximo_nullable.php` | Nueva migración |
| `app/Models/Actividad.php` | Agregar `tieneCalificacion()` |
| `app/Http/Controllers/ActividadController.php` | Validación condicional en `store`/`update` |
| `app/Services/ReporteService.php` | Filtrar actividades sin nota en conteos |
| `resources/views/actividades/create.blade.php` | 4 tipos nuevos + Alpine.js hide/show |
| `resources/views/actividades/edit.blade.php` | Alpine.js hide/show puntaje |
| `resources/views/actividades/show.blade.php` | Vista condicional sin nota |
| `tests/Feature/ActividadSinNotaTest.php` | 4 tests nuevos |
