# Exportar / importar curso como plantilla — Diseño

Fecha: 2026-10-15

## Objetivo

Permitir descargar un curso como un archivo ZIP que contenga todo lo que el instructor montó (estructura, configuración y archivos), y cargarlo después —en esta u otra instalación (local, Railway, VPS)— para obtener una copia equivalente.

## Decisiones confirmadas con el usuario

- **Alcance:** plantilla del curso. NO viajan estudiantes, inscripciones, entregas, calificaciones ni certificados emitidos.
- **Importar:** siempre crea un curso nuevo. Nunca sobrescribe ni mezcla con uno existente.
- **Foros y anuncios:** fuera de alcance (contenido vivo con comentarios de estudiantes). Se pueden agregar después con `version_formato` nueva.
- **Fechas absolutas** de actividades (`fecha_apertura`, `fecha_cierre`): se descartan al importar.

## Formato del archivo

ZIP con:

```
curso.json
archivos/            # todos los binarios, rutas planas por hash o ruta relativa
```

`curso.json` es un árbol anidado, sin IDs de base de datos:

```
{
  "version_formato": 1,
  "exportado_en": "...",
  "curso": {
    titulo, descripcion, duracion_horas, tipo_certificado, nota_aprobatoria, orden,
    categoria: { nombre, color } | null,
    imagen_portada: "archivos/..." | null,
    modulos: [ { titulo, descripcion, orden, duracion_horas,
      lecciones: [ { titulo, contenido_html, video_url, orden, duracion_minutos, tipo,
                     permitir_descarga_adjuntos,
        actividades: [ { tipo, titulo, descripcion, orden, puntaje_maximo, duracion_minutos,
                         es_obligatoria, usa_rubrica, permitir_descarga_adjuntos,
                         max_archivos_adjuntos, intentos_permitidos,
                         criterio_calificacion_intentos, mostrar_historial_intentos,
          preguntas: [ { pregunta_texto, tipo, seleccion_multiple, puntaje, orden,
                         imagen: "archivos/..." | null,
                         opciones: [ { texto, es_correcta, explicacion, orden } ] } ],
          recursos:  [ { tipo, titulo, descripcion, contenido, url, orden,
                         archivo: "archivos/..." | null, archivo_nombre_original } ],
          rubrica:   [ { nombre, orden, niveles: [ { descripcion, puntos, orden } ] } ]
        } ] } ] } ]
  }
}
```

La lista de campos sale de los `$fillable` de los modelos `Curso`, `Modulo`, `Leccion`, `Actividad`, `Pregunta`, `Opcion`, `RecursoActividad`, `CriterioRubrica`, `NivelCriterio`. El plan debe verificar contra los modelos al implementar (si se agrega una columna después, hay que sumarla al exportador).

## Archivos y URLs embebidas

Origen de archivos (disco `public`):
- `cursos.imagen_portada`
- `preguntas.imagen_path`
- `recurso_actividades.archivo_path` (+ `archivo_nombre_original`)
- Imágenes subidas desde el editor Quill (`editor/YYYY-MM/...`), referenciadas dentro de `lecciones.contenido_html` como URL absoluta `…/storage/editor/...`.

**Export:** al recorrer `contenido_html`, cada URL que apunte al `/storage/` de esta instalación se reemplaza por un marcador `{{archivo:<ruta-en-zip>}}` y el binario se copia a `archivos/`. URLs externas (otro host) no se tocan. Un archivo referenciado en BD que ya no existe en disco se omite y se registra en una lista `advertencias` dentro del JSON (no aborta la exportación).

**Import:** los binarios se copian a `cursos/{slug-nuevo}/...` en el disco `public`; los marcadores del HTML se reemplazan por la URL de esta instalación (`asset('storage/...')`, igual que `UploadController`).

## Componentes

- `App\Services\CursoExportService` — `exportar(Curso): string` (ruta del ZIP temporal). Único dueño del formato. Eager-load de toda la jerarquía para evitar N+1.
- `App\Services\CursoImportService` — `importar(UploadedFile, User): Curso`. Valida estructura y `version_formato`, y crea todo dentro de **una sola `DB::transaction`**. Si algo falla, borra los archivos ya copiados y relanza; no queda curso parcial.
- `CursoController::exportar(Curso)` — `GET /cursos/{curso}/exportar`, descarga el ZIP (`Content-Disposition` con slug del curso).
- `CursoController::importar(Request)` — `POST /cursos/importar`, valida `mimes:zip` y tamaño máximo, llama al servicio, redirige a `cursos.edit` del curso creado con mensaje de éxito (y advertencias si las hay).
- Rutas: `cursos/importar` y `cursos/{curso}/exportar` en el grupo `instructor`; la estática `importar` ANTES de `{curso}` (patrón ya usado en `routes/web.php`).
- UI: botón "Exportar" en `cursos/edit.blade.php`; botón/formulario "Importar curso" en `cursos/index.blade.php` (solo instructor/admin).

## Reglas de importación

- Estado `borrador`; `created_by` = usuario que importa; título + " (importado)"; `orden` al final.
- Categoría: se busca por nombre sin distinguir mayúsculas/espacios (`LOWER(TRIM(nombre))`, mismo criterio que `CategoriaController`); si no existe se crea con slug único.
- Se descartan `fecha_apertura` y `fecha_cierre` de actividades. Se conserva `duracion_minutos`.
- Las rúbricas conservan `usa_rubrica`, criterios y niveles.
- Orden de todos los hijos se conserva por el campo `orden`.

## Autorización

- Exportar: `can('update', $curso)` (el instructor solo sus cursos; admin cualquiera), vía `CursoPolicy`.
- Importar: `can('create', Curso::class)`.
- Estudiantes: 403.

## Errores

Mensaje claro y nada creado en estos casos: archivo no es ZIP, falta `curso.json`, JSON inválido, `version_formato` desconocida (mayor a la soportada), campo obligatorio ausente, archivo referenciado ausente en el ZIP, ZIP o contenido que supere el límite. Defensa contra rutas maliciosas en el ZIP (zip-slip): se rechaza cualquier entrada con `..` o ruta absoluta y se copia solo por nombre normalizado.

## Pruebas

- Ciclo completo: crear curso con módulos/lecciones/actividades/preguntas/opciones/recursos/rúbrica, exportar, importar, y comparar el árbol campo por campo (salvo IDs, estado, título, dueño y fechas descartadas).
- Imágenes del editor: el HTML importado apunta a archivos que existen en esta instalación; la URL externa queda intacta.
- Rollback: ZIP con un hijo inválido a mitad → no existe curso nuevo ni archivos huérfanos.
- Zip-slip y versión no soportada → rechazo.
- Permisos: estudiante 403; instructor ajeno no exporta curso ajeno; admin sí.
- Tests con `Storage::fake`/sqlite según el patrón de la suite. Nota: `CertificadoService` escribe fuera del facade `Storage`; este flujo debe usar siempre el facade `Storage::disk('public')` para que sea testeable.

## Fuera de alcance (YAGNI)

Sobrescribir un curso existente, exportar estudiantes/notas, foros, anuncios, exportación masiva de varios cursos, importación desde un enlace, formato distinto de ZIP.

## Riesgos conocidos

- Cursos con muchos archivos grandes: la exportación se arma en un ZIP temporal en disco (no en memoria); el límite de subida de importación depende de `upload_max_filesize`/`post_max_size` del servidor.
- Mantener sincronizados los campos exportados con las migraciones futuras: cubierto por el test de ciclo completo, que debe fallar si se agrega una columna sin exportarla.
