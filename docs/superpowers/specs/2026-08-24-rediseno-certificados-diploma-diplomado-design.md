# Rediseño de certificados: diploma real de marca + carta de diplomado

## Contexto

El sistema genera hoy un solo tipo de certificado PDF (`certificados/plantilla-pdf.blade.php`), con un diseño genérico dorado/naranja que no coincide con la identidad real de D&L Quality Consulting. El usuario proporcionó dos documentos de referencia en `context/Certificados/`:

- **`Certificado Auditor_Darcy Carolina Cruz Guayacan.pdf`** — diploma horizontal (A4 paisaje) con el logo circular real de D&L, para un curso corto (Auditor Interno, 24h).
- **`Certificado Diplomado_Darcy Carolina Cruz Guayacan.docx`** — carta formal vertical tipo "certificación bancaria", para un programa largo (Diplomado, 120h).

Ambos documentos son del mismo estudiante pero de cursos distintos con formatos de certificado completamente distintos. El objetivo es que el sistema pueda emitir ambos formatos, eligiendo cuál según el curso.

## Decisiones ya confirmadas con el usuario

1. **Selección de plantilla**: campo explícito `tipo_certificado` en el curso (`diploma` | `diplomado`), elegido por el instructor/admin al crear/editar el curso. No se infiere por duración.
2. **Dato de cédula**: se agrega al perfil del usuario (`numero_documento`, `ciudad_expedicion`), reutilizable en todos sus certificados futuros — no se captura por certificado.
3. **Formato de la carta de diplomado**: PDF con el mismo diseño de la carta de referencia, generado con el mismo motor (mPDF) y flujo (`CertificadoService`, verificación pública, descarga) que ya existe. No se introduce ninguna librería nueva ni un segundo flujo de generación/descarga.
4. **Notificación por dato faltante** (capturado en sesión de brainstorming): si se intenta generar un certificado `diplomado` y al estudiante le falta `numero_documento`, el sistema NO genera el PDF con el campo vacío ni falla en silencio — usa `Notificacion::crear(...)` (mismo patrón ya usado en toda la app) para avisarle que complete su perfil, con link a `profile.edit`, y la generación queda pendiente hasta que lo complete.

## Contenido exacto de las plantillas de referencia

### Diploma (`tipo_certificado = diploma`) — horizontal, A4 paisaje

Extraído del PDF de referencia:

- Logo circular D&L (círculos multicolor concéntricos) arriba a la izquierda, con "Nit. 900282703-3" debajo.
- "D&L QUALITY CONSULTING" como encabezado tipográfico.
- "Hace Constar Que:"
- Nombre del estudiante en mayúsculas, negrita, grande — **con número de cédula debajo** ("C.C. 1.000.790.950").
- "Completó con éxito la formación y evaluación de"
- Nombre del curso en mayúsculas, color verde marca, grande.
- Fila con dos datos: "Fecha Finalización: AAAA/MM/DD" y "Intensidad: N horas".
- Una sola firma: imagen de firma manuscrita + "Sandra Marcela Fajardo" + "Directora de Formación" (texto fijo del emisor, no el instructor del curso — a diferencia de la plantilla actual que firma con `curso.creador`).
- Pie: "www.dylqualityconsulting.com" y "contacto.dylltda@gmail.com - 3103491201 - Calle 143 No. 46-55".

### Carta de diplomado (`tipo_certificado = diplomado`) — vertical, formato carta/oficio

Extraído del .docx de referencia (texto literal, con placeholders marcados):

> EL PROCESO DE FORMACIÓN DE LA ORGANIZACIÓN D&L QUALITY CONSULTING LTDA. HACE CONSTAR Que la estudiante, **[NOMBRE ESTUDIANTE]**, quien se identifica con cédula de ciudadanía número **[NUMERO_DOCUMENTO]** de **[CIUDAD_EXPEDICION]**, culminó exitosamente todos los contenidos académicos y aprobó satisfactoriamente la prueba de conocimiento del **DIPLOMADO EN [CURSO.TITULO]**, realizado entre el **[INSCRIPCION.FECHA_INICIO]** y el **[INSCRIPCION.FECHA_FIN]** con una intensidad de **[CURSO.DURACION_HORAS]** horas. Se expide a solicitud de la interesada a los **[DÍA EN PALABRAS]** (**[DÍA NUMÉRICO]**) días del mes de **[MES]** del año **[AÑO]** en la ciudad de Bogotá D.C.
>
> Atentamente,
> Sandra Marcela Fajardo Valero
> Coordinadora de formación empresarial
> D&L QUALITY CONSULTING LTDA

Notas de diseño:
- Logo D&L (versión pequeña, solo texto + barra naranja) en el encabezado.
- **No muestra la calificación final en ningún lado** — solo confirma aprobación ("aprobó satisfactoriamente"). `calificacion_final` se sigue calculando y guardando igual que hoy, solo no aparece en esta plantilla.
- Firma manuscrita (misma imagen que el diploma) + nombre + cargo (nombre completo y cargo distintos a los del diploma: "Sandra Marcela Fajardo Valero" / "Coordinadora de formación empresarial", vs. "Sandra Marcela Fajardo" / "Directora de Formación" en el diploma — se respetan ambos tal como aparecen en cada documento original).
- Pie de página con barra naranja: "Contacto: +57 305 442 2705", "Horario: L-V 8:00 am - 5:00 pm", "Email: contacto@dylqualityconsulting.com".
- El texto de fecha de expedición usa el día del mes en palabras (p.ej. "veintinueve") seguido del numeral entre paréntesis. Como el rango es fijo (1-31), se resuelve con un array estático español, sin librería externa.

### Datos que requiere cada plantilla (resumen)

| Dato | Diploma | Diplomado | Origen |
|---|---|---|---|
| Nombre estudiante | ✅ | ✅ | `certificado->usuario->name` |
| Cédula | ✅ | ✅ | `certificado->usuario->numero_documento` (nuevo) |
| Ciudad de expedición | — | ✅ | `certificado->usuario->ciudad_expedicion` (nuevo) |
| Curso | ✅ | ✅ | `certificado->curso->titulo` |
| Fecha finalización (única) | ✅ | — | `certificado->fecha_emision` |
| Fecha inicio / fin del curso | — | ✅ | `Inscripcion` del estudiante en ese curso (`fecha_inicio`/`fecha_fin`, ya existen) |
| Intensidad horaria | ✅ | ✅ | `certificado->curso->duracion_horas` |
| Calificación final | ✅ (ya se muestra) | — (se calcula pero no se muestra) | `certificado->calificacion_final` |
| Número de certificado / verificación | ✅ | ✅ | ya existente, sin cambios |

## Cambios de datos

**Migración 1 — `users`:**
```php
$table->string('numero_documento', 30)->nullable()->after('empresa');
$table->string('ciudad_expedicion', 100)->nullable()->after('numero_documento');
```
Ambos opcionales — no rompe usuarios existentes. Se completan desde "Mi Perfil".

**Migración 2 — `cursos`:**
```php
$table->enum('tipo_certificado', ['diploma', 'diplomado'])->default('diploma')->after('categoria_id');
```
Default `diploma` — todos los cursos existentes conservan el formato actual (rediseñado) sin necesitar acción manual.

## Cambios de UI

- **`profile/edit.blade.php`** (+ `ProfileController`/`ProfileUpdateRequest` correspondiente): dos campos nuevos, "Número de documento" y "Ciudad de expedición", ambos opcionales, junto a los campos existentes (nombre, email, empresa).
- **`cursos/create.blade.php` y `cursos/edit.blade.php`** (+ `CursoController::store/update`, validación `tipo_certificado => required|in:diploma,diplomado`): selector "Tipo de certificado" con las dos opciones, valor por defecto `diploma`.

## Cambios de generación

- **`CertificadoService::generarPdf()`**: elige la vista (`certificados.plantilla-pdf` o `certificados.plantilla-carta`) según `$certificado->curso->tipo_certificado`. Cuando es `plantilla-carta`, además carga la `Inscripcion` del estudiante en ese curso (`fecha_inicio`/`fecha_fin`) y pasa el helper de fecha-en-palabras a la vista. Orientación del PDF (`format`/`orientation` de mPDF) también cambia según el tipo: `A4-L`/paisaje para diploma (como hoy), `A4`/retrato para la carta.
- **`CertificadoService::generarSiCorresponde()`**: el chequeo de dato faltante va aquí, justo después de la verificación de inscripción completada y antes de calcular la calificación final — mismo método que ya decide si corresponde emitir o no, mismo contrato de retorno. Si `curso->tipo_certificado === 'diplomado'` y `usuario->numero_documento` está vacío: llama a `Notificacion::crear($usuario->id, 'certificado', 'Completa tu perfil para tu certificado', 'Necesitamos tu número de documento para emitir tu diplomado de «{curso}».', route('profile.edit'))` y devuelve `null`. `CertificadoController::generar()` no necesita cambios — ya maneja el caso `null` de `generarSiCorresponde()` con el mensaje flash existente ("Debes completar todas las lecciones..."); ese mensaje flash genérico seguirá mostrándose incluso en este caso, lo cual es aceptable porque la notificación ya creada por `Notificacion::crear()` es la que le indica al estudiante la causa real y el link a `profile.edit`.
- **Asset del logo**: la imagen circular de D&L (extraída de `context/Certificados/Certificado Diplomado_....docx → word/media/image1.jpeg`, círculos multicolor + "Nit. 900282703-3") se copia al proyecto (p.ej. `public/images/dyl-logo-certificado.jpg`) para usarla embebida en ambas plantillas vía `asset()`. La imagen de firma manuscrita (`image2.jpeg` del mismo .docx) se copia igual para reutilizarla en ambas plantillas.

## No-goals (fuera de alcance de este cambio)

- No se genera un archivo `.docx` real — ambos formatos son PDF (decisión ya confirmada).
- No se migran certificados ya emitidos a los nuevos diseños — solo aplica a certificados generados/regenerados después de este cambio.
- No se agrega selección de firmante por curso — el nombre/cargo del firmante queda fijo por plantilla, tal como en los documentos de referencia.
- No se toca `reportes/*`, `calificaciones/*` ni ningún otro módulo — cambio acotado a certificados + perfil + formulario de curso.

## Testing

- Feature tests para `CertificadoService`/`CertificadoController`: genera diploma cuando `tipo_certificado=diploma`; genera carta cuando `tipo_certificado=diplomado` con `numero_documento` presente; **no** genera y notifica cuando falta `numero_documento` en un curso `diplomado`; el helper de día-en-palabras cubre casos límite (1, 29, 31).
- Feature test de perfil: guardar `numero_documento`/`ciudad_expedicion` desde `profile.edit`.
- Feature test de curso: `tipo_certificado` se valida y persiste en crear/editar.
