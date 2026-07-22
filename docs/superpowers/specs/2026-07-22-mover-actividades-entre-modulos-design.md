# Diseño: Mover Actividades entre Módulos/Lecciones (Drag & Drop)

**Fecha:** 2026-07-22
**Proyecto:** LMS DyL Quality Consulting
**Estado:** Aprobado

---

## Resumen

Permitir al instructor reorganizar el contenido de un curso arrastrando actividades entre lecciones (de cualquier módulo) directamente desde `cursos/edit.blade.php`, y también reordenarlas dentro de la misma lección. Alcance: **solo dentro del mismo curso** — no se contempla mover actividades entre cursos distintos.

Hoy `Actividad` cuelga de `Leccion` (no de `Modulo` directamente), así que "mover entre módulos" se traduce técnicamente en reasignar el `leccion_id` de la actividad a una lección de otro módulo del mismo curso.

**Hallazgo relevante:** el proyecto no tiene ninguna funcionalidad de drag & drop hoy. Existe un endpoint `ModuloController::reordenar` pero no está conectado a ninguna vista (código muerto). Esta es la primera vez que se integra una librería de drag & drop.

---

## 1. Frontend

### Librería
SortableJS vía CDN (sin build step), consistente con el patrón ya usado en el proyecto para Quill, mammoth.js y SheetJS.

### Cambios en `resources/views/cursos/edit.blade.php`
- El contenedor de actividades de cada lección (líneas ~140-155 actuales) pasa a ser un `<div>` con `data-leccion-id="{{ $leccion->id }}"`.
- Cada tarjeta de actividad obtiene `data-actividad-id="{{ $actividad->id }}"` y un ícono de "agarre" (drag handle) separado del botón "Editar", para que arrastrar y click no se pisen.
- Se inicializa un `Sortable` por cada contenedor de lección, todos con `group: 'actividades'` — esto permite arrastrar tarjetas entre listas de distintas lecciones (incluso de distinto módulo) y también reordenar dentro de la misma lista.
- Los módulos y lecciones en sí **no** son arrastrables (fuera de alcance).

### Evento `onEnd` (compartido por todas las listas)
Se dispara una sola vez por operación (ya sea reordenar o mover). El handler:
1. Lee `evt.to` (lista destino) y arma `orden_destino` = array ordenado de `data-actividad-id` de sus hijos actuales.
2. Si `evt.from !== evt.to`, arma también `orden_origen` = array ordenado de los hijos restantes en la lista origen.
3. Hace `fetch` `POST` a `actividades.mover` con `_token` (tomado de `meta[name=csrf-token]`, mismo patrón que `actividades/edit.blade.php`), `leccion_destino_id`, `orden_destino` y (si aplica) `leccion_origen_id` + `orden_origen`.
4. Si la respuesta no es OK: `alert()` con mensaje de error y `location.reload()` para resincronizar el DOM con el estado real de la BD (Sortable ya movió el nodo visualmente; si el guardado falla, no se revierte a mano, se recarga).

---

## 2. Backend

### Ruta
```
POST /cursos/{curso}/actividades/mover   →  actividades.mover
```
Ubicada junto a `modulos.reordenar`, dentro del grupo `Route::middleware('instructor')`.

### `ActividadController::mover(Request $request, Curso $curso)`
```php
public function mover(Request $request, Curso $curso)
{
    $this->authorize('update', $curso);

    $validated = $request->validate([
        'leccion_destino_id'  => 'required|exists:lecciones,id',
        'orden_destino'       => 'required|array|min:1',
        'orden_destino.*'     => 'integer|exists:actividades,id',
        'leccion_origen_id'   => 'nullable|exists:lecciones,id',
        'orden_origen'        => 'nullable|array',
        'orden_origen.*'      => 'integer|exists:actividades,id',
    ]);

    // Las lecciones involucradas deben pertenecer al curso de la URL
    $leccionIds = array_filter([$validated['leccion_destino_id'], $validated['leccion_origen_id'] ?? null]);
    $pertenecen = Leccion::whereIn('id', $leccionIds)
        ->whereHas('modulo', fn($q) => $q->where('curso_id', $curso->id))
        ->count();
    abort_unless($pertenecen === count($leccionIds), 422);

    // Las actividades referenciadas deben pertenecer a esas lecciones (mismo curso)
    $actividadIds = array_merge($validated['orden_destino'], $validated['orden_origen'] ?? []);
    $válidas = Actividad::whereIn('id', $actividadIds)
        ->whereHas('leccion.modulo', fn($q) => $q->where('curso_id', $curso->id))
        ->count();
    abort_unless($válidas === count($actividadIds), 422);

    DB::transaction(function () use ($validated) {
        foreach ($validated['orden_destino'] as $index => $actividadId) {
            Actividad::where('id', $actividadId)->update([
                'leccion_id' => $validated['leccion_destino_id'],
                'orden'      => $index,
            ]);
        }
        foreach ($validated['orden_origen'] ?? [] as $index => $actividadId) {
            Actividad::where('id', $actividadId)->update(['orden' => $index]);
        }
    });

    return response()->json(['ok' => true]);
}
```

Notas:
- `orden` ya existe en la tabla `actividades` y `Leccion::actividades()` ya hace `orderBy('orden')`, así que el reordenamiento tiene efecto visible inmediato en cualquier vista que liste actividades de una lección (edición de curso, reproductor de lección del estudiante, etc.).
- Nada se pierde al mover: respuestas, calificaciones, progreso y criterios de rúbrica cuelgan de `actividad_id`, no de `leccion_id`.
- No se agregan restricciones por fechas de apertura/cierre ni por progreso ya registrado — mover una actividad con respuestas existentes es válido y no requiere confirmación adicional en esta primera versión.

---

## 3. Testing

Feature tests en `tests/Feature/` (mismo estilo que `CursoInscripcionTest`, etc.):

1. Instructor mueve una actividad de Lección A (Módulo 1) a Lección B (Módulo 2), mismo curso → `leccion_id` actualizado, `orden` correcto en ambas listas (destino y origen sin huecos).
2. Reordenar dentro de la misma lección (sin `leccion_origen_id`/`orden_origen`) → solo cambia `orden`, `leccion_id` se mantiene.
3. Estudiante autenticado golpea el endpoint → 403 (no pasa el middleware `instructor` / la policy `update`).
4. Instructor de un curso distinto intenta mover una actividad pasando IDs de lección/actividad que no pertenecen a `$curso` de la URL → 422.

---

## Fuera de alcance (explícito)

- Mover actividades entre cursos distintos.
- Arrastrar módulos o lecciones (solo actividades).
- Deshacer (undo) de un movimiento — si el instructor se equivoca, vuelve a arrastrar.
