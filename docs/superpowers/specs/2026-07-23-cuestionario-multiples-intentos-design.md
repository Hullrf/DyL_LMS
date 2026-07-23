# Diseño: Múltiples Intentos para Actividades de Tipo Cuestionario

**Fecha:** 2026-07-23
**Proyecto:** LMS DyL Quality Consulting
**Estado:** Aprobado

---

## Resumen

Permitir que el instructor configure, por actividad de tipo `cuestionario`, cuántos intentos puede realizar cada estudiante (1 o más), qué calificación cuenta cuando hay varios intentos, y si el estudiante ve o no el historial de sus intentos anteriores. El resto de tipos de actividad (`ensayo`, `tarea`, `practica`) no cambian: siguen limitados a una sola entrega.

**Hallazgo relevante:** hoy `RespuestaEstudianteController::store()` bloquea cualquier segundo envío para **cualquier** tipo de actividad mediante un chequeo `$yaRespondio` genérico. Sin embargo, `ActividadController::show()` ya hace `->latest()->first()` sobre las respuestas del usuario, como si el código estuviera parcialmente preparado para múltiples filas por actividad. El riesgo real no está en permitir varias filas — está en que `ReporteService` y `CertificadoService` suman `calificacion` y `puntaje_maximo` de **todas** las respuestas de un usuario en un curso sin deduplicar por actividad; con múltiples intentos, esas sumas se inflarían.

---

## 1. Modelo de datos

Nueva migración `add_intentos_a_actividades_table`, agregando columnas a `actividades` con defaults que preservan el comportamiento actual:

```php
Schema::table('actividades', function (Blueprint $table) {
    $table->unsignedTinyInteger('intentos_permitidos')->default(1)->after('puntaje_maximo');
    $table->enum('criterio_calificacion_intentos', ['mas_alto', 'ultimo'])->default('mas_alto')->after('intentos_permitidos');
    $table->boolean('mostrar_historial_intentos')->default(true)->after('criterio_calificacion_intentos');
});
```

En `app/Models/Actividad.php`:
- Agregar los 3 campos a `$fillable`.
- Cast `mostrar_historial_intentos` como `boolean`.
- Nuevo helper:
  ```php
  public function permiteMultiplesIntentos(): bool
  {
      return $this->tipo === 'cuestionario' && $this->intentos_permitidos > 1;
  }
  ```

Estos campos solo tienen efecto real cuando `tipo === 'cuestionario'`; para el resto de tipos se guardan pero se ignoran (no se muestran en su formulario ni se leen en su flujo de envío).

---

## 2. Envío de intentos (`RespuestaEstudianteController::store`)

Reemplazar el bloqueo genérico `$yaRespondio` por una rama específica para `cuestionario`:

```php
if ($actividad->tipo === 'cuestionario') {
    $intentosUsados = RespuestaEstudiante::where('user_id', Auth::id())
        ->where('actividad_id', $actividad->id)
        ->count();

    if ($intentosUsados >= $actividad->intentos_permitidos) {
        return redirect()->route('actividades.show', $actividad)
            ->with('error', 'Ya usaste todos los intentos permitidos para este cuestionario.');
    }

    $tieneIntentoEnRevision = RespuestaEstudiante::where('user_id', Auth::id())
        ->where('actividad_id', $actividad->id)
        ->where('estado', 'en_revision')
        ->exists();

    if ($tieneIntentoEnRevision) {
        return redirect()->route('actividades.show', $actividad)
            ->with('error', 'Tienes un intento pendiente de revisión. Espera a que el instructor lo califique antes de reintentar.');
    }
} else {
    // Comportamiento actual sin cambios: una sola entrega por actividad.
    $yaRespondio = RespuestaEstudiante::where('user_id', Auth::id())
        ->where('actividad_id', $actividad->id)
        ->exists();

    if ($yaRespondio) {
        return redirect()->route('actividades.show', $actividad)
            ->with('error', 'Ya has respondido esta actividad.');
    }
}
```

Cada intento se guarda como una fila **nueva** en `respuestas_estudiantes` (no se sobrescribe la anterior). El resto del método (`calcularCuestionario`, notificación al instructor, etc.) no cambia.

`Actividad::completarPara()` no se modifica: sigue siendo idempotente por `user_id` + `actividad_id` vía `ProgresoActividad::updateOrCreate`, así que marcar la actividad como completada en el primer intento correcto sigue funcionando igual con intentos adicionales.

---

## 3. Calificación "oficial" para reportes y certificados

Nuevo método en `app/Services/CalificacionService.php`:

```php
/**
 * Dado un conjunto de respuestas (con 'actividad' cargada), devuelve una sola
 * respuesta por cada par (user_id, actividad_id), eligiendo según la política
 * de esa actividad: 'mas_alto' → mayor calificación, 'ultimo' → fecha_envio más reciente.
 */
public function respuestasOficiales(Collection $respuestas): Collection
{
    return $respuestas
        ->groupBy(fn($r) => $r->user_id . '-' . $r->actividad_id)
        ->map(function ($grupo) {
            $actividad = $grupo->first()->actividad;
            return $actividad->criterio_calificacion_intentos === 'ultimo'
                ? $grupo->sortByDesc('fecha_envio')->first()
                : $grupo->sortByDesc('calificacion')->first();
        })
        ->values();
}
```

Se aplica **antes** de sumar/promediar en los 4 puntos que hoy usan la colección cruda:

- `ReporteService::reportePorCurso()` — promedio por estudiante dentro de un curso.
- `ReporteService::reportePorEstudiante()` — promedio por curso del estudiante.
- `CertificadoService::calcularCalificacionFinal()` — elegibilidad de certificado.
- `ReporteService::kpiGenerales()` — hoy usa `RespuestaEstudiante::selectRaw('AVG(calificacion) as avg_cal')`. Se reemplaza por: traer las respuestas `calificada` de cuestionarios con `with('actividad')`, pasar por `respuestasOficiales()`, y promediar en PHP (`->avg('calificacion')`). El promedio ya no puede resolverse con `AVG()` de SQL porque la regla de "cuál cuenta" depende de un campo por actividad.

`CalificacionController::misCalificaciones()` (historial del estudiante) **no cambia su lógica** — ya lista cada intento como fila individual, que es lo correcto para un historial. Se le agrega una marca visual ("Cuenta para tu nota") sobre la fila que `respuestasOficiales()` identifica como oficial, sin alterar el resto de filas.

---

## 4. Interfaz

### `actividades/create.blade.php` y `actividades/edit.blade.php`

Bloque visible solo cuando `tipo === 'cuestionario'` (mismo patrón `x-show` ya usado para otros campos condicionales):

- Input numérico **"Intentos permitidos"** (`min="1"`, `max="20"`, default `1`).
- Select **"Cuando hay varios intentos, ¿cuál cuenta?"** → `Más alto` / `Último`.
- **Toggle** (mismo componente visual que "Permitir descarga de archivos adjuntos" / "Rúbrica de evaluación" en `edit.blade.php`, no un checkbox) — **"Mostrar a los estudiantes el resumen de sus intentos anteriores"**.

### `ActividadController::store()` / `update()`

Agregar a las reglas de validación:
```php
'intentos_permitidos' => 'nullable|integer|min:1|max:20',
'criterio_calificacion_intentos' => 'nullable|in:mas_alto,ultimo',
'mostrar_historial_intentos' => 'boolean',
```
Con defaults (`1`, `'mas_alto'`, `true`) si no vienen en el request, igual que el patrón ya usado para `es_obligatoria`.

### `ActividadController::show()`

Cambia de traer una sola respuesta (`->first()`) a cargar **todos** los intentos del usuario para esa actividad (`orderBy('fecha_envio')`), y calcular ahí mismo cuál es el oficial (reutilizando `CalificacionService::respuestasOficiales()`), para pasar a la vista: la colección completa de intentos, el oficial, y si quedan intentos disponibles.

### `actividades/show.blade.php` (sección cuestionario)

- Si `$actividad->permiteMultiplesIntentos()` y quedan intentos disponibles y no hay ninguno `en_revision`: mostrar el resultado del intento oficial + "Intento X de Y" + botón **"Reintentar"** que vuelve a mostrar el formulario de respuesta.
- Si `mostrar_historial_intentos` está activo: lista compacta debajo, con la nota y fecha de cada intento, marcando cuál es el oficial.
- Si hay un intento en estado `en_revision`: bloque informativo "Esperando revisión del instructor", sin botón de reintentar.
- Si se agotaron los intentos (`intentos_usados >= intentos_permitidos`): bloque final de solo lectura con el resultado oficial (como hoy, pero mostrando el oficial en vez de "la" respuesta única).
- Actividades con `intentos_permitidos = 1` (el default, y todo lo existente) se comportan exactamente igual que hoy.

---

## 5. Testing

Nuevo `tests/Feature/CuestionarioIntentosTest.php`:

1. Cuestionario con `intentos_permitidos = 1` (default): el segundo envío sigue bloqueado — sin regresión sobre el comportamiento actual.
2. Cuestionario con `intentos_permitidos = 3`: permite un 2º y 3er intento; el 4º queda bloqueado con el mensaje de límite alcanzado.
3. Cuestionario con preguntas de respuesta corta: un intento que queda `en_revision` bloquea un nuevo intento hasta que el instructor publique la calificación vía `publicarCuestionario`.
4. `criterio_calificacion_intentos = 'mas_alto'`: con dos intentos calificados de distinto puntaje, el reporte usa la calificación más alta.
5. `criterio_calificacion_intentos = 'ultimo'`: usa la calificación del intento más reciente aunque sea menor que uno anterior.
6. `ReporteService::reportePorCurso()` no duplica puntaje cuando un mismo estudiante tiene varios intentos calificados en la misma actividad (test de regresión directo sobre el bug evitado).
7. Otros tipos de actividad (`tarea`, `ensayo`, `practica`) no se ven afectados: el segundo envío sigue bloqueado sin importar el valor de `intentos_permitidos`.

Actualizar `tests/Unit/CalificacionServiceTest.php` con casos directos para `respuestasOficiales()` (agrupación correcta por `user_id`+`actividad_id`, selección `mas_alto` vs `ultimo`).

---

## Fuera de alcance (explícito)

- Intentos ilimitados (sin tope numérico) — el instructor siempre configura un número fijo ≥ 1.
- Múltiples intentos para tipos de actividad distintos a `cuestionario` (`ensayo`, `tarea`, `practica` siguen con una sola entrega).
- Promediar o combinar automáticamente el feedback textual de varios intentos — cada intento conserva su propio feedback si fue revisado manualmente (preguntas de respuesta corta).
- Deshacer o eliminar un intento ya enviado — si el estudiante se equivoca, debe usar un intento adicional (si le queda) o contactar al instructor.
