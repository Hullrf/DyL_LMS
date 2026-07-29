# Soporte de archivos .html en Recursos de Apoyo

**Fecha:** 2026-07-29
**Estado:** Aprobado

## Resumen

Permitir que el instructor suba archivos `.html` como "material de apoyo" (tipo `documento`) de una actividad. El archivo se comporta igual que PDF/Word/Excel/PowerPoint/ZIP: el estudiante solo puede **descargarlo**, nunca se renderiza inline en el navegador del LMS.

## Decisión de seguridad

Un `.html` subido por un instructor puede contener JavaScript. Si se sirviera inline en el mismo origen del LMS, ese script se ejecutaría con acceso a la sesión del usuario que lo abre (riesgo de XSS almacenado). Por eso se descarta deliberadamente cualquier vista previa/inline para `.html`:

- La descarga ya pasa por `RecursoActividadController::descargar()`, que usa `Storage::disk('public')->download()` — esto fuerza `Content-Disposition: attachment`, así el navegador nunca ejecuta el HTML, solo lo guarda como archivo.
- En `actividades/show.blade.php`, el visor inline para tipo `documento` solo activa `esPdf`, `esOffice` o `esImagenDoc` según la extensión. `.html` no encaja en ninguna, así que cae automáticamente en la rama "Descargar" (si `descargaPermitida()`) o "Vista previa no disponible" (si no) — no requiere cambios en el visor.

Alcance: solo el upload de recursos de apoyo del instructor (`RecursoActividadController`). Las entregas de estudiantes (`RespuestaEstudianteController`, campo `archivo_adjunto`) no se modifican.

## Cambios

**`app/Http/Controllers/RecursoActividadController.php`**

Línea 27, agregar `html` a la regla `mimes:` del tipo `documento`:

```php
'documento' => $rules['archivo'] = 'required|file|mimes:pdf,doc,docx,ppt,pptx,xls,xlsx,zip,html|max:51200',
```

**`app/Models/RecursoActividad.php`**

En `iconoDocumento()`, agregar un ícono distintivo (`</>`) para extensión `html`, en vez de caer al ícono genérico por defecto:

```php
'html'      => 'M10 20l4-16m-8 4l-4 4 4 4m12-8l4 4-4 4',
```

**`resources/views/actividades/edit.blade.php`**

Línea 204, agregar `.html` al `accept` del input de archivo y actualizar el texto de ayuda:

```html
<input type="file" name="archivo" accept=".pdf,.doc,.docx,.ppt,.pptx,.xls,.xlsx,.zip,.html" ...>
<p class="form-hint">PDF, Word, PowerPoint, Excel, ZIP o HTML — máx. 50 MB</p>
```

## Testing

Archivo nuevo: `tests/Feature/RecursoActividadHtmlTest.php`

| # | Test | Resultado esperado |
|---|------|--------------------|
| 1 | Instructor sube recurso `documento` con archivo `.html` | Se guarda correctamente, `archivo_path` no nulo |
| 2 | Descarga del recurso `.html` vía ruta `recursos.descargar` | Respuesta con cabecera `Content-Disposition: attachment` |
| 3 | Instructor sube recurso `documento` con extensión no permitida (`.exe`) | Error de validación, no se crea el recurso |

## Archivos afectados

| Archivo | Cambio |
|---------|--------|
| `app/Http/Controllers/RecursoActividadController.php` | Agregar `html` a `mimes:` |
| `app/Models/RecursoActividad.php` | Ícono distintivo para `.html` en `iconoDocumento()` |
| `resources/views/actividades/edit.blade.php` | `accept=".html"` + texto de ayuda |
| `tests/Feature/RecursoActividadHtmlTest.php` | 3 tests nuevos |
