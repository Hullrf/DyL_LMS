# Aprobación de instructor/admin antes de emitir certificados

## Contexto

Hoy `CertificadoService::generarSiCorresponde()` genera el certificado de un
estudiante en cuanto `Inscripcion.estado = 'completado'` (progreso de
lecciones) — sin que ningún instructor o admin revise la nota final ni
confirme que el curso realmente se completó a satisfacción. El estudiante
dispara la generación él mismo con el botón "Obtener Certificado" en
`cursos/show.blade.php` (ruta `certificados.generar`).

Esto es insuficiente: un estudiante puede terminar todas las lecciones y
obtener el certificado aunque su calificación final sea baja, sin que nadie
lo revise. Se necesita un gate humano: el instructor/admin del curso debe
revisar y aprobar explícitamente antes de que el certificado se genere.

Este documento asume completo el rediseño de certificados de
`docs/superpowers/specs/2026-08-24-rediseno-certificados-diploma-diplomado-design.md`
(ya mergeado a `master` en `d075b0c`) — plantillas diploma/diplomado,
`tipo_certificado`, gate de `numero_documento` para diplomado, y la
previsualización real del PDF ya existen y no cambian aquí.

## Objetivo

El certificado de un estudiante solo se genera cuando se cumplen **dos**
condiciones: (1) completó el curso (como hoy) y (2) un instructor o admin
del curso lo aprobó explícitamente. La nota mínima del curso es información
que guía esa decisión, no un bloqueo automático — el instructor conserva la
autoridad de aprobar incluso por debajo del mínimo.

## Fuera de alcance

- Rechazar un certificado con motivo, o cualquier estado además de
  "pendiente"/"aprobado" — no hay ese requisito hoy.
- Re-evaluar o revocar certificados ya emitidos antes de este cambio.
- Cualquier cambio a las plantillas PDF, `tipo_certificado`, o el gate de
  `numero_documento` para diplomado — quedan exactamente como están.

## Modelo de datos

**Migración 1** — `cursos.nota_aprobatoria`:
```php
Schema::table('cursos', function (Blueprint $table) {
    $table->unsignedTinyInteger('nota_aprobatoria')->default(80)->after('tipo_certificado');
});
```
No nullable — todo curso, nuevo o existente, queda en 80 automáticamente al
correr la migración (ningún curso se queda "sin mínimo definido"). El
instructor puede cambiarlo por curso desde el formulario de crear/editar
curso (mismo patrón que el selector de `tipo_certificado` ya existente).

**Migración 2** — `certificados.aprobado_por_id`:
```php
Schema::table('certificados', function (Blueprint $table) {
    $table->foreignId('aprobado_por_id')->nullable()->after('calificacion_final')
        ->constrained('users')->nullOnDelete();
});
```
Nullable — certificados ya emitidos antes de este cambio quedan con
`aprobado_por_id = null` (no se retro-completan, no se sabe quién los
habría aprobado bajo el flujo viejo).

`Certificado::$fillable` gana `'aprobado_por_id'`; nueva relación
`Certificado::aprobador(): BelongsTo` → `User`.

**Nada nuevo en `Inscripcion`.** La existencia del `Certificado` (con su
`created_at` como fecha de aprobación) es la única señal de "ya fue
aprobado" que el sistema necesita — evita sincronizar un estado de
aprobación separado del propio certificado.

## Flujo del servicio

`CertificadoService::generarSiCorresponde()` cambia de firma:

```php
public function generarSiCorresponde(User $usuario, Curso $curso, User $aprobadoPor): ?Certificado
```

Orden de chequeos (los 3 primeros sin cambios de comportamiento):

1. ¿Ya existe certificado para este usuario+curso? → lo devuelve.
2. ¿`Inscripcion.estado === 'completado'`? → si no, `null`.
3. ¿`tipo_certificado === 'diplomado'` y falta `numero_documento`? →
   notifica al estudiante (`Notificacion::crear(...)`, sin cambios) y
   `null`.
4. Crea el `Certificado` con `aprobado_por_id = $aprobadoPor->id`, genera
   el PDF, notifica al estudiante ("¡Felicitaciones!...", sin cambios en
   el texto).

**`nota_aprobatoria` no se valida en el servicio.** No hay parámetro
`$forzar` ni bloqueo — es puramente informativa para la UI del instructor
(sección siguiente). La autoridad de aprobar ya está garantizada por quién
puede llegar a la acción (ver Autorización más abajo); no hace falta una
segunda puerta a nivel de servicio.

**Se elimina** la ruta `certificados.generar` y el método
`CertificadoController::generar()` — ya no hay caller legítimo (el
estudiante ya no dispara nada, ver "Vista del estudiante").

## Endpoint de aprobación (instructor/admin)

Nueva ruta, mismo grupo de middleware que las demás rutas de
`calificaciones.*`:

```php
Route::post('/calificaciones/curso/{curso}/estudiantes/{estudiante}/aprobar-certificado',
    [CalificacionController::class, 'aprobarCertificado'])
    ->name('calificaciones.aprobarCertificado');
```

`CalificacionController::aprobarCertificado(Curso $curso, User $estudiante)`:
1. Reutiliza `verificarAccesoCurso($curso)` (ya existe — admin o
   `curso->created_by === Auth::id()`, si no `abort(403)`).
2. Llama `$this->certificadoService->generarSiCorresponde($estudiante, $curso, Auth::user())`.
3. Si devuelve un `Certificado` → redirect a `calificaciones.curso` con
   flash `success`: *"Certificado aprobado y generado para {estudiante}."*
4. Si devuelve `null` → en el flujo normal (clic desde la matriz) el único
   motivo alcanzable es el gate de documento faltante — la matriz solo
   muestra el botón cuando el estudiante ya está `completado` y sin
   certificado, así que esos dos casos no deberían dispararse desde ahí.
   Pero la ruta es un `POST` normal, alcanzable directo sin pasar por la
   matriz (curl, form manipulado), así que el controlador no debe asumir
   la causa: antes de llamar al servicio, verifica explícitamente si el
   estudiante está `completado`; si no lo está, flash `error` *"{estudiante}
   no ha completado el curso todavía."* — si sí lo está y aun así el
   servicio devuelve `null`, ahí sí el único motivo posible es el gate de
   documento, flash `error`: *"No se pudo generar: a {estudiante} le falta
   el número de documento — se le notificó para que lo complete."*

## UI del instructor (matriz de Calificaciones)

`CalificacionController::curso()` — cada fila (`$filas`) gana dos datos
más, calculados junto a lo que ya arma hoy:
- `completado` (bool): `Inscripcion::where(...)->where('estado', 'completado')->exists()` para ese estudiante+curso.
- `certificado` (`?Certificado`): `Certificado::where('user_id', ...)->where('curso_id', $curso->id)->first()` (con `aprobador` cargado si existe).

La vista pasa además `$curso->nota_aprobatoria` (ya disponible vía `$curso`).

Nueva columna "Certificado" al final de la tabla en
`calificaciones/curso.blade.php`, un estado por fila:

| Condición | Contenido de la celda |
|---|---|
| `!completado` | `—` |
| `completado && tiene_pendientes` | `—` |
| `completado && !tiene_pendientes && !certificado`, `promedio >= nota_aprobatoria` | Botón "Aprobar certificado" (estilo normal) |
| `completado && !tiene_pendientes && !certificado`, `promedio < nota_aprobatoria` | Mismo botón, estilo de advertencia, texto "Aprobar de todas formas (nota bajo el mínimo)" |
| `certificado` existe | Insignia "Certificado emitido" + fecha (`certificado->created_at`) + quién (`certificado->aprobador->name` si existe) — enlaza a `certificados.show` |

El botón envía un `<form method="POST">` a `calificaciones.aprobarCertificado`
con `onclick="return confirm('...')"` — mensaje ajustado si es el caso de
nota-bajo-mínimo (menciona explícitamente que la nota no alcanza el
mínimo del curso).

## Vista del estudiante

`cursos/show.blade.php`: el bloque que hoy muestra el formulario "Obtener
Certificado" (dentro del `@if($certExistente) ... @else ... @endif`, ver
el `@else` actual) se reemplaza por un mensaje de estado, sin ninguna
acción disponible:

```blade
<div class="text-center text-gray-600 text-sm px-4 py-3 bg-gray-50 rounded-lg">
    Tu certificado está pendiente de revisión del instructor.
</div>
```

El caso `@if($certExistente)` (ya tiene certificado → botón "Ver
Certificado") no cambia.

## Testing

- Migraciones: `nota_aprobatoria` default 80 en cursos existentes y
  nuevos; `aprobado_por_id` nullable, no rompe certificados existentes.
- `CertificadoService`: aprobar genera certificado con `aprobado_por_id`
  correcto; aprobar con nota bajo el mínimo igual genera (no hay bloqueo);
  no permite aprobar si `Inscripcion` no está completada (devuelve `null`);
  el gate de diplomado sin documento sigue funcionando igual.
- `CalificacionController::curso()`: la columna nueva refleja los 4 casos
  de la tabla de arriba.
- `CalificacionController::aprobarCertificado()`: 403 si no es admin ni
  instructor dueño del curso; redirect+flash de éxito; redirect+flash
  específico si falla por documento faltante; redirect+flash específico
  si se llama directo (sin pasar por la matriz) para un estudiante que
  aún no completó el curso.
- `cursos/show.blade.php`: muestra el mensaje de "pendiente de revisión"
  cuando corresponde; sigue mostrando "Ver Certificado" si ya existe.
- Se elimina/reescribe `tests/Feature/CertificadoGenerarEndToEndTest.php`
  (ejercita la ruta `certificados.generar` que este cambio elimina) para
  cubrir el flujo nuevo vía `calificaciones.aprobarCertificado` en su
  lugar — mismos 3 escenarios (diploma, diplomado con documento, diplomado
  sin documento) pero disparados por el instructor, no por el estudiante.
- `resources/views/cursos/show.blade.php:100` es la única otra referencia
  a `certificados.generar` en vistas — confirmado por grep, no hay otras.
