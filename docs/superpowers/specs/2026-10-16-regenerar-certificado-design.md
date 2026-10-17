# Botón "Regenerar certificado" (instructor/admin)

## Contexto

El PDF de un certificado se genera una sola vez (al aprobarlo) y queda guardado en `storage/app/public/certificados/AAAA/` con la ruta en `certificados.archivo_pdf`. `CertificadoController::asegurarArchivoGenerado()` solo lo vuelve a crear si el archivo **no existe**. Consecuencia: al cambiar la plantilla (p. ej. el centrado de `plantilla-pdf`), los certificados ya emitidos conservan el diseño viejo y hoy la única forma de actualizarlos es borrar el archivo a mano o correr tinker en producción.

## Objetivo

Que un instructor/admin pueda rehacer el PDF de un certificado ya emitido con la plantilla vigente, desde la UI, sin tocar el servidor.

## Decisiones (recomendación)

1. **Alcance: un certificado a la vez.** "Regenerar todos los del curso" queda fuera (YAGNI): son pocos por curso y el botón individual ya sirve; se agrega solo si el volumen lo pide.
2. **El registro `Certificado` no cambia**: mismo `numero_certificado`, `fecha_emision`, `calificacion_final`, `aprobado_por_id`. Solo se reescribe el PDF. El enlace de verificación pública sigue válido.
3. **Reutilizar `CertificadoService::generarPdf()`**. Escribe en `certificado-{numero}.pdf` del año **actual** (`date('Y')`), así que `archivo_pdf` se actualiza con la ruta devuelta. Si un certificado de 2026 se regenera en 2027 queda un PDF huérfano en la carpeta 2026: se borra el archivo anterior si la ruta cambió. (Sin migración ni campos nuevos.)
4. **Sin notificación al estudiante**: es un cambio de formato, no un hecho nuevo.

## Autorización

Solo **admin o instructor dueño del curso** (`curso.created_by`). NO el estudiante dueño del certificado.

`CertificadoController::autorizar()` no sirve tal cual (deja pasar al estudiante). Se reutiliza la misma regla que `CalificacionController::verificarAccesoCurso()`: `esAdmin() || curso->created_by === user->id`, si no `abort(403)`. Implementación mínima: dentro del nuevo método del `CertificadoController`, sin policy nueva.

## Ruta y método

```
POST /certificados/{certificado}/regenerar   → CertificadoController@regenerar   name: certificados.regenerar
```

Dentro del grupo `auth` existente, junto a las otras rutas de certificados. La autorización va en el método (no en el middleware `instructor`, porque el dueño del curso también debe pasar el chequeo de `created_by`; el middleware `instructor` deja entrar a cualquier instructor).

Comportamiento de `regenerar()`:

1. Autorizar (admin o dueño del curso).
2. `try { $ruta = $this->certificadoService->generarPdf($certificado); }`
3. `catch (\RuntimeException)` → diplomado sin `numero_documento`: redirigir atrás con `error` (mismo caso ya contemplado por `redirigirPorFaltaDeDatos`, pero con texto de instructor: "falta el número de documento de {estudiante}; pídele que lo complete en su perfil"). No se notifica al estudiante de nuevo desde aquí.
4. Si `$ruta !== $certificado->archivo_pdf` y el archivo viejo existe → `Storage::disk('public')->delete($viejo)`.
5. `$certificado->update(['archivo_pdf' => $ruta])`.
6. `redirect()->back()->with('success', 'Certificado regenerado con la plantilla actual.')`.

## UI

- **`certificados/show.blade.php`**: botón secundario "Regenerar PDF" junto a "Descargar PDF", visible solo si el usuario es admin o dueño del curso (`@if(auth()->user()->esAdmin() || $certificado->curso->created_by === auth()->id())`). `<form method="POST">` con `@csrf` y `confirm('¿Regenerar el PDF con la plantilla actual?')`, igual al patrón de `celda-certificado`.
- **`calificaciones/partials/celda-certificado.blade.php`**: NO se toca. El badge "Certificado emitido" ya enlaza a `certificados.show`, donde está el botón. Evita duplicar el botón en la matriz.

Mensajes flash: se muestran con el layout existente (`success` / `error`), sin cambios.

## Casos borde

- Archivo PDF inexistente en disco (storage efímero de Railway): funciona igual, `generarPdf` lo crea; el borrado del viejo se omite si no existe.
- Diplomado sin `numero_documento`: error con mensaje claro, el PDF anterior se conserva (no se borra nada antes de generar con éxito).
- Curso borrado (soft delete) o usuario del certificado eliminado: `generarPdf` hace `load(['usuario','curso'])`; el binding del certificado ya falla en 404 si no hay curso. Fuera de alcance manejar usuario eliminado (mismo comportamiento que la descarga actual).
- Doble clic: idempotente (reescribe el mismo archivo); no requiere bloqueo.

## Tests (PHPUnit, `tests/Feature/CertificadoRegenerarTest.php`)

Siguen el estilo de `CertificadoShowPreviewTest` (`RefreshDatabase`, `Storage::fake('public')`). Se corren con `php artisan test` (sqlite en memoria); **no** usar `migrate:fresh --env=testing` contra la BD local.

1. Admin regenera: `archivo_pdf` apunta a un archivo existente y el contenido se reescribe.
2. Instructor dueño del curso regenera: OK.
3. Instructor que NO es dueño: 403.
4. Estudiante dueño del certificado: 403.
5. Diplomado sin `numero_documento`: redirect con `error`, `archivo_pdf` y el PDF viejo intactos.
6. Cambio de año (`Carbon::setTestNow`): el PDF viejo de otro año se elimina y `archivo_pdf` apunta a la nueva ruta.
7. Vista `show`: el botón aparece para admin/dueño del curso y no para el estudiante.

## Fuera de alcance

Regenerar en lote por curso; cambiar `numero_certificado` o `fecha_emision`; versionado/historial de PDFs; notificar al estudiante.
