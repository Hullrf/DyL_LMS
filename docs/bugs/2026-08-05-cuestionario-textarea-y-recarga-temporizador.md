# Fix: textarea horizontal en preguntas abiertas + reinicio del temporizador al recargar

**Fecha:** 2026-08-05
**Reportado por:** usuario, tras usar el cuestionario en producción

---

## Bug 1 — El cuadro de texto de las preguntas abiertas es de una sola línea

**Dónde:** `resources/views/actividades/partials/formulario-cuestionario.blade.php`

**Problema:** Las preguntas de tipo `respuesta_corta` usaban `<input type="text">`. Al escribir una
respuesta larga el texto se desplaza horizontalmente y hay que volver al inicio del campo para
releer lo ya escrito.

**Fix:** Se reemplazó el `<input>` por un `<textarea>` que crece verticalmente (renglón a renglón)
según el contenido, vía `oninput` que ajusta `style.height` al `scrollHeight`. Se aplica el mismo
ajuste al cargar la página por si el campo trae texto precargado (ej. tras un error de validación).
El script de envío (que arma el JSON en el campo oculto `respuesta`) se actualizó para leer el
`<textarea>` en vez del `<input type=text]`.

---

## Bug 2 — El temporizador parece reiniciarse cuando la pestaña se "suspende" y se recarga

**Dónde:** `app/Http/Controllers/ActividadController.php` (`show()`) y
`resources/views/actividades/partials/cuestionario-con-inicio.blade.php`

**Reporte del usuario:** mientras leía las guías de la pregunta, la pestaña quedaba "suspendida"
(comportamiento típico de los navegadores al liberar memoria de pestañas inactivas en segundo
plano) y al volver a interactuar con ella la página se recargaba. Las respuestas no se perdían,
pero el temporizador sí volvía casi al tiempo total.

**Investigación (root cause):**
- El tiempo restante ya se calculaba correctamente en el servidor a partir de `fecha_inicio`
  (`duracion_minutos * 60 - fecha_inicio->diffInSeconds(now())`), así que una petición fresca al
  servidor siempre devuelve el tiempo real restante — no había ningún bug en ese cálculo.
- La vista `actividades.show` **no enviaba ninguna cabecera `Cache-Control`**. Al no haber
  `Cache-Control`/`Expires`/`Last-Modified`, el navegador puede servir una copia cacheada de la
  página (esto es especialmente común cuando una pestaña en segundo plano se descarta por presión
  de memoria y se "recarga" al volver a ella) en vez de pedir una versión nueva al servidor. Esa
  copia cacheada trae incrustado el `segundos` original (casi el tiempo completo), y Alpine
  reinicia el conteo desde ese valor viejo → de ahí la sensación de "se reinició el temporizador".
- Además, el cronómetro decrementaba un contador simple (`segundos--`) cada segundo en vez de
  anclarse a una hora límite absoluta, así que si el navegador pausaba el `setInterval` en segundo
  plano (páginas "congeladas"), el conteo podía perder precisión al reanudar.

**Fix (dos capas, ambas necesarias):**
1. `ActividadController::show()` ahora responde con `Cache-Control: no-store, no-cache,
   must-revalidate, max-age=0` y `Pragma: no-cache` cuando hay un intento en progreso, para que el
   navegador nunca reutilice una copia vieja de la página del cuestionario.
2. El cronómetro en `cuestionario-con-inicio.blade.php` ahora calcula `segundos` a partir de una
   `deadline` absoluta (`Date.now() + segundosRestantes*1000`) en cada tick, en vez de decrementar
   un contador. También escucha `visibilitychange` para recalcular de inmediato al volver a la
   pestaña, así el tiempo mostrado siempre refleja la hora real aunque el navegador haya pausado el
   `setInterval` mientras estuvo en segundo plano.

**Verificación:** los 153 tests de la suite (`php artisan test`) pasan sin cambios, incluyendo los
de `CuestionarioTemporizadorTest`.
