# Importar cuestionarios desde Google Forms

**Fecha:** 2026-07-29
**Estado:** Aprobado

## Resumen

Permitir que el instructor importe las preguntas de un Google Form hacia un cuestionario del LMS, en lugar de recrearlas a mano. El puente entre Forms y el LMS es un Apps Script (plantilla que se entrega documentada, vive fuera de Laravel) que exporta la estructura del Form a un JSON con el esquema del LMS; el instructor sube ese archivo desde el editor de la actividad.

No se integra la API oficial de Google Forms vía OAuth (evita dar de alta un proyecto en Google Cloud, pantalla de consentimiento y gestión de tokens por instructor). La contrapartida aceptada: el instructor corre el script una vez por formulario en vez de pegar un link y que el LMS lo jale solo.

## Apps Script (plantilla, no vive en Laravel)

Vive en el repo como referencia/documentación en `docs/apps-script/`, para que el instructor la copie a [script.google.com](https://script.google.com) o a un script vinculado a su Form. Recorre `FormApp.getActiveForm().getItems()`:

- `MULTIPLE_CHOICE` / `CHECKBOX` / `LIST` (dropdown) → `tipo: "opcion_multiple"`, `multiple: true` solo si era `CHECKBOX`.
- `SHORT_ANSWER` / `PARAGRAPH_TEXT` → `tipo: "respuesta_corta"` (sin opciones — en este LMS ese tipo siempre se califica manualmente).
- Si el Form tiene modo "Cuestionario" activado (`FormApp` expone corrección por opción vía `Choice.isCorrectAnswer()`), se exporta `correcta: true/false` por opción. Si no, se exporta `correcta: null` en todas.
- Tipos no soportados (`SCALE`, `GRID`, `DATE`, `TIME`, `FILE_UPLOAD`, etc.) se omiten; el script deja constancia en su propio log de ejecución (`Logger.log`) de cuántas y cuáles se omitieron — el LMS nunca las ve, así que no forman parte de su resumen post-importación.
- Descarga el resultado como archivo `.json` (usando `DriveApp` o mostrando el JSON en un diálogo para copiar/guardar).

## Esquema JSON (versión 1)

```json
{
  "version": 1,
  "preguntas": [
    {
      "texto": "¿Cuál es la capital de Francia?",
      "tipo": "opcion_multiple",
      "multiple": false,
      "opciones": [
        {"texto": "Madrid", "correcta": false},
        {"texto": "París", "correcta": true}
      ]
    },
    { "texto": "Explica brevemente el proceso...", "tipo": "respuesta_corta" }
  ]
}
```

Reglas de interpretación en el LMS:

- `tipo` solo admite `opcion_multiple` o `respuesta_corta`.
- Si una pregunta `opcion_multiple` tiene **exactamente 2 opciones** cuyo texto (normalizado a minúsculas, sin acentos) es `"verdadero"/"falso"` o `"true"/"false"`, se importa como `tipo = verdadero_falso` en el LMS (igual que las preguntas V/F creadas a mano), no como `opcion_multiple`.
- Si ninguna opción de una pregunta trae `correcta: true`, la pregunta se importa igual pero queda sin ninguna opción marcada como correcta — el instructor debe corregirlo después (ver siguiente sección).
- `respuesta_corta` no requiere ni admite `opciones`.

## Backend

### Redistribución de puntaje reutilizable

`PreguntaController::redistribuirPuntajes()` (privado) se mueve a un método público `Actividad::redistribuirPuntajesPreguntas(): void` con la misma lógica. `PreguntaController::store()` y `destroy()` pasan a llamar `$actividad->redistribuirPuntajesPreguntas()`. El nuevo importador usa el mismo método tras crear todas las preguntas del JSON.

### Nuevo controlador de importación

`app/Http/Controllers/ImportacionCuestionarioController.php`, método `store(Request $request, Actividad $actividad)`:

1. `$this->authorize('update', $actividad->leccion->modulo->curso);` — mismo patrón que `PreguntaController`.
2. `abort_unless($actividad->tipo === 'cuestionario', 403);`
3. Valida `'archivo' => 'required|file|mimes:json|max:2048'`.
4. Decodifica el contenido (`json_decode($archivo->get(), true)`); si falla (`json_last_error() !== JSON_ERROR_NONE`) o no matchea el esquema, `return back()->withErrors(['archivo' => 'El archivo no tiene un formato válido.'])`.
5. Valida la estructura decodificada con `Validator::make()`:
   ```php
   'version'                        => 'required|integer|in:1',
   'preguntas'                      => 'required|array|min:1',
   'preguntas.*.texto'              => 'required|string',
   'preguntas.*.tipo'               => 'required|in:opcion_multiple,respuesta_corta',
   'preguntas.*.multiple'           => 'nullable|boolean',
   'preguntas.*.opciones'           => 'required_if:preguntas.*.tipo,opcion_multiple|array|min:2',
   'preguntas.*.opciones.*.texto'   => 'required|string',
   'preguntas.*.opciones.*.correcta'=> 'nullable|boolean',
   ```
6. Dentro de una transacción (`DB::transaction`), por cada pregunta del array:
   - Determina el `orden` inicial como `$actividad->preguntas()->max('orden') + 1` (se agrega al final, sin tocar las existentes).
   - Si `tipo === 'opcion_multiple'` y hay exactamente 2 opciones cuyo texto normalizado sea `{verdadero,falso}` o `{true,false}` → crea con `tipo = 'verdadero_falso'`, `seleccion_multiple = false`.
   - Si no, crea con `tipo = 'opcion_multiple'`, `seleccion_multiple = !empty($multiple)`.
   - `respuesta_corta` → crea sin opciones.
   - `puntaje` se deja en `1` (placeholder, igual que en `PreguntaController::store()`); se recalcula al final.
7. Llama `$actividad->redistribuirPuntajesPreguntas()`.
8. Cuenta cuántas preguntas quedaron con **ninguna** opción `es_correcta = true` (solo aplica a `opcion_multiple`/`verdadero_falso`) → `$pendientes`.
9. Redirige a `actividades.edit` con `with('success', "{$total} preguntas importadas." . ($pendientes > 0 ? " {$pendientes} necesitan que marques la respuesta correcta." : ''))`.

### Marcar opción correcta después de creada

Gap existente: hoy no hay forma de corregir cuál opción es correcta en una pregunta `opcion_multiple` ya creada sin borrar y re-agregar la opción (`OpcionController` solo tiene `store`/`destroy`). Esto bloquea el flujo de "Form sin modo cuestionario, corregir después".

Nuevo método en `OpcionController`:

```php
public function marcarCorrecta(Opcion $opcion)
{
    $pregunta = $opcion->pregunta;
    $this->authorize('update', $pregunta->actividad->leccion->modulo->curso);

    if ($pregunta->seleccion_multiple) {
        $opcion->update(['es_correcta' => !$opcion->es_correcta]);
    } else {
        $pregunta->opciones()->update(['es_correcta' => false]);
        $opcion->update(['es_correcta' => true]);
    }

    return redirect()
        ->route('actividades.edit', $pregunta->actividad)
        ->with('success', 'Respuesta correcta actualizada');
}
```

Ruta: `PUT /opciones/{opcion}/marcar-correcta` → `opciones.marcarCorrecta`, dentro del grupo `instructor` junto a las demás rutas de `opciones.*`.

La ruta de importación queda `POST /actividades/{actividad}/preguntas/importar` → `preguntas.importar`, junto a `preguntas.store` en el mismo grupo `instructor`.

## Frontend

En `actividades/edit.blade.php`, junto a la tarjeta "Agregar Pregunta" (dentro de `@if($actividad->tipo === 'cuestionario')`), nueva tarjeta "Importar desde Google Forms":

- Enlace/nota breve indicando dónde está la plantilla del Apps Script (`docs/apps-script/`).
- `<form action="{{ route('preguntas.importar', $actividad) }}" method="POST" enctype="multipart/form-data">` con un `<input type="file" accept=".json">` y botón "Importar".
- El mensaje flash `success` ya se muestra vía el layout existente (`session('success')`), no requiere una vista nueva.

En el listado de preguntas ya existentes: si una pregunta `opcion_multiple`/`verdadero_falso` no tiene ninguna opción `es_correcta`, se agrega un badge "Falta marcar la correcta" junto al título de la pregunta, y cada opción listada pasa a tener un botón pequeño "Marcar correcta" (`<form method="POST" action="{{ route('opciones.marcarCorrecta', $opcion) }}"><input type=hidden name=_method value=PUT>...`) en vez del texto estático actual.

## Testing

Archivo nuevo: `tests/Feature/ImportarCuestionarioGoogleFormsTest.php`

| # | Test | Resultado esperado |
|---|------|--------------------|
| 1 | Importar JSON válido con 1 pregunta `opcion_multiple` (correcta conocida) + 1 `respuesta_corta` | Se crean 2 preguntas, la de opción múltiple con su opción correcta marcada |
| 2 | Pregunta `opcion_multiple` con opciones exactamente `Verdadero`/`Falso` | Se guarda con `tipo = verdadero_falso` |
| 3 | Pregunta con todas las opciones `correcta: null` | Se crea la pregunta, ninguna opción queda `es_correcta = true`, el mensaje flash indica "1 necesita que marques la respuesta correcta" |
| 4 | Importar sobre una actividad que ya tiene preguntas | Las preguntas nuevas se agregan al final; las existentes no se tocan ni se duplican |
| 5 | JSON malformado (falta `preguntas` o `tipo` inválido) | `back()->withErrors()`, no se crea ninguna pregunta |
| 6 | Estudiante (no instructor del curso) intenta importar | 403 |
| 7 | `OpcionController::marcarCorrecta` en pregunta de opción única | Marca la opción elegida y desmarca las demás de la misma pregunta |
| 8 | `OpcionController::marcarCorrecta` en pregunta `seleccion_multiple` | Alterna solo la opción indicada, sin afectar las demás |

## Fuera de alcance

- Integración directa vía OAuth con la API de Google Forms.
- Reimportar el mismo Form no actualiza preguntas ya importadas ni evita duplicados — solo agrega.
- Imágenes dentro de las preguntas de Forms.
- Tipos de pregunta de Forms no mapeables (escala, cuadrícula, fecha, hora, archivo) — se omiten en el script, nunca llegan al LMS.
- Peso por pregunta proveniente del puntaje de Forms — el LMS siempre reparte `puntaje_maximo` equitativamente entre todas las preguntas, igual que hoy.

## Archivos afectados

| Archivo | Cambio |
|---------|--------|
| `docs/apps-script/importar-cuestionario.gs` | Nuevo — plantilla del Apps Script |
| `docs/apps-script/README.md` | Nuevo — instrucciones para el instructor |
| `app/Models/Actividad.php` | Nuevo método público `redistribuirPuntajesPreguntas()` |
| `app/Http/Controllers/PreguntaController.php` | Quita `redistribuirPuntajes()` privado, usa el método del modelo |
| `app/Http/Controllers/ImportacionCuestionarioController.php` | Nuevo — `store()` |
| `app/Http/Controllers/OpcionController.php` | Nuevo método `marcarCorrecta()` |
| `routes/web.php` | Nuevas rutas `preguntas.importar` y `opciones.marcarCorrecta` |
| `resources/views/actividades/edit.blade.php` | Tarjeta de importación + badge/botón "Marcar correcta" en preguntas existentes |
| `tests/Feature/ImportarCuestionarioGoogleFormsTest.php` | 8 tests nuevos |
