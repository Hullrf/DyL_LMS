# SPEC: funciones inspiradas en Moodle UCundinamarca

Origen: `notas_moodle_ucundinamarca.md` (observación del Moodle de pregrado, 15-oct-2026).
Stack: Laravel + Blade + Tailwind. Roles existentes: estudiante, instructor, admin. Escala de nota 0–5.

## 0. Convenciones

- **Prioridad:** P1 = primero (bajo esfuerzo, alto valor), P2 = siguiente, P3 = después.
- **Existente** = ya está en el repo; la spec solo anota la brecha.
- Cada función: objetivo, alcance, reglas, datos, criterios de aceptación (CA) y el fallo de Moodle que evita (FE).
- Fuera de alcance de toda la spec: integraciones LTI externas, app móvil nativa, SSO institucional.

## 1. Fallos a evitar (requisitos transversales)

| ID | Fallo observado en Moodle | Requisito para nuestro LMS |
|----|---------------------------|----------------------------|
| FE-1 | Nombres de curso largos truncados con "..." (`604561/SEGURIDAD.../27928`) | Separar `titulo` (limpio) de metadatos (`codigo`, `grupo`, `sede`, `periodo`). Las listas muestran solo título; los metadatos van en subtítulo/tooltip. |
| FE-2 | Variable sin reemplazar visible al alumno (`{{correo_profesor}}`) | Plantillas con variables validadas al guardar; si falta un dato se oculta el bloque o se bloquea la publicación. Nunca se renderiza `{{ }}` literal. |
| FE-3 | Portada del curso con texto largo antes de las actividades | Primero: próximas fechas y contenido; información académica (REA, bibliografía) en pestaña/acordeón aparte. |
| FE-4 | Panel de monitoreo en blanco sin error | Todo panel con datos remotos o pesados tiene estados: cargando, vacío con explicación, error con reintento. |
| FE-5 | Tarjeta de curso sin próxima entrega ni nota actual | La tarjeta muestra progreso, próxima entrega y nota actual. |
| FE-6 | Competencias vacías ("No se han vinculado") sin guía | Estado vacío con acción clara para el instructor; para el estudiante, ocultar la sección si no hay datos. |

Requisitos no funcionales comunes:
- **RNF-1** Fechas y horas en zona `America/Bogota`, mostradas con hora (ej. 23:59).
- **RNF-2** Cada lista paginada o limitada; consultas sin N+1 (eager loading).
- **RNF-3** Autorización por política: el estudiante solo ve sus cursos/notas; el instructor solo sus cursos.
- **RNF-4** Responsive y accesible (contraste, foco de teclado, etiquetas en iconos).
- **RNF-5** Cada función nueva con test de feature (`php artisan test`, sqlite en memoria). No usar `migrate:fresh` contra la BD local.

---

## F1. Línea de tiempo "Próximos 7 días" — P1

**Objetivo:** que el estudiante vea qué vence pronto sin entrar curso por curso.
**Existente:** `actividades.fecha_cierre`, dashboard de estudiante. **Brecha:** no hay vista agrupada por fecha.

**Alcance**
- Bloque en el dashboard del estudiante y página `/linea-de-tiempo`.
- Lista de actividades de sus cursos inscritos con `fecha_cierre` en la ventana elegida (7 días por defecto; opciones 30 días y 6 meses).
- Agrupada por día; cada ítem: hora, tipo (tarea/cuestionario), título, curso, estado.
- Acción directa: botón **"Entregar"** (tarea sin entrega), **"Continuar"** (intento en progreso) o **"Ver"** (ya entregada).
- Orden por fecha; filtro por curso; buscador por nombre/tipo.
- Incluye vencidas sin entregar en sección "Atrasadas" (destacada).

**Reglas**
- Solo actividades publicadas, con fecha de cierre, de cursos con inscripción activa.
- Ocultar actividades ya entregadas por defecto (toggle "mostrar entregadas").
- Hora mostrada en Bogotá.

**CA**
1. Estudiante con 3 actividades que vencen en 7 días las ve agrupadas por día y ordenadas.
2. Una tarea ya entregada no aparece por defecto.
3. Una actividad vencida sin entrega aparece en "Atrasadas".
4. Estudiante no ve actividades de cursos donde no está inscrito.
5. Sin actividades: mensaje "No tienes entregas próximas" (FE-4).

---

## F2. Calendario mensual con exportación .ics — P2

**Objetivo:** vista mensual de vencimientos y eventos propios.

**Alcance**
- `/calendario` con vista mes (navegación anterior/siguiente), varios eventos por día, clic lleva a la actividad.
- Filtro por curso ("Todos los cursos").
- Eventos: apertura y cierre de actividades, anuncios con fecha, **eventos personales** (CRUD simple: título, fecha/hora, descripción).
- Exportar `.ics` (descarga) y URL de suscripción con token personal revocable.

**Datos**
- Nueva tabla `eventos_personales` (`user_id`, `titulo`, `inicia_en`, `termina_en` null, `descripcion`).
- Columna `users.calendario_token` (string único, regenerable).

**Reglas**
- El `.ics` incluye solo eventos que el usuario puede ver.
- Token inválido o revocado → 404, sin revelar existencia.

**CA**
1. Un día con 5 vencimientos muestra los 5 (con scroll o "+2 más").
2. El filtro por curso oculta eventos de otros cursos.
3. El `.ics` importa en Google Calendar con título, hora y enlace.
4. Regenerar token invalida la URL anterior.
5. Un usuario no puede editar ni ver eventos personales de otro.

---

## F3. Progreso por curso, "marcar como hecha" y tarjeta de curso — P1

**Existente:** `progreso_lecciones`, `progreso_actividades`. **Brecha:** tarjeta sin próxima entrega/nota; finalización manual.

**Alcance**
- Tarjeta de curso (lista y cuadrícula): título limpio (FE-1), subtítulo con código/grupo, barra de % completado, **próxima entrega** (fecha corta), **nota actual** (promedio ponderado a la fecha).
- Vista lista/tarjetas, buscador, orden (nombre, progreso, próxima entrega), filtro (en curso, completados, todos).
- Actividades sin nota (lectura, video) con botón **"Marcar como hecha"** reversible.

**Reglas**
- `% completado` = actividades obligatorias completadas / obligatorias totales (si 0 obligatorias, se usa lecciones).
- Cursos con 0 actividades muestran "Sin contenido aún", no 0 %.

**CA**
1. Marcar como hecha una actividad sube el % de la tarjeta.
2. Desmarcar baja el %.
3. Orden por próxima entrega pone primero la más cercana; los cursos sin entrega al final.
4. Un título largo no se trunca con códigos (FE-1).

---

## F4. Libro de calificaciones del estudiante con ponderación y proyección — P2

**Existente:** `/mis-calificaciones`, rúbricas, escala 0–5, `nota_aprobatoria`. **Brecha:** no hay ponderación ni "aporta al total".

**Alcance**
- Por curso, tabla: actividad, ponderación %, nota, rango (0–5), porcentaje, retroalimentación, **aporta al total**.
- Fila de total del curso (nota acumulada y % sobre el total posible).
- **Proyección:** "nota mínima que necesitas en lo pendiente para aprobar" según `nota_aprobatoria`.
- Instructor define ponderaciones por actividad (por defecto reparto igual).

**Datos**
- `actividades.ponderacion` decimal(5,2) null. Si todas null → reparto igual.

**Reglas**
- La suma de ponderaciones por curso debe ser 100 %; el formulario del instructor avisa y bloquea guardar si no cuadra.
- Actividades sin calificar se muestran como "-" (no como 0), y el total indica que es parcial.
- Actividades sin nota (lectura/video) no entran al cálculo.
- Con varios intentos se usa `criterio_calificacion_intentos` ya existente.

**CA**
1. 8 actividades iguales de 12,5 %: nota 5 en una aporta 12,5 % del total.
2. Pendientes se ven como "-" y el total lo marca como parcial.
3. La proyección calcula correctamente la nota requerida (test con caso numérico).
4. Guardar ponderaciones que suman 90 % es rechazado con mensaje claro.
5. Estudiante no accede a notas de otro (RNF-3).

---

## F5. Convención Ronda / Actividad / Semana — P1

**Objetivo:** ordenar y filtrar el curso por ronda y semana sin depender del texto del título.

**Alcance**
- Campos en actividad: `ronda` (int null), `semana` (int null), `numero_actividad` (int null).
- El prefijo visible se compone en la UI: `R2-A3-S9 · De gráfico a álgebra`; no se escribe a mano.
- Filtros por ronda y semana en la vista de curso, línea de tiempo y calificaciones.
- Importador de curso existente: si el título viene con patrón `R#-A#-S#` se rellenan los campos.

**Reglas**
- Campos opcionales: cursos sin esa estructura siguen funcionando.
- Validar rangos (ronda 1–9, semana 1–52).

**CA**
1. Actividad con ronda 2/semana 10 muestra el prefijo correcto.
2. Filtrar por ronda 2 oculta las demás.
3. Importar el título "R2-A2-S10 Miremos la seguridad" rellena los tres campos.

---

## F6. Portada de curso estandarizada (ficha académica) — P2

**Existente:** `CursoPlantillaController` (exportar/importar curso). **Brecha:** no hay ficha estructurada.

**Alcance**
- Ficha del curso: justificación, resultado esperado de aprendizaje (general), resultados específicos (lista), créditos, semestre, prerrequisitos, programa/facultad, docente (nombre y correo), bibliografía (autor, año, título, editorial).
- Vista de curso: arriba próximas fechas y contenido (FE-3); la ficha vive en pestaña **"Información del curso"**.
- Bloques "Contacte a su profesor" y "Soporte técnico" con datos reales (FE-2).

**Datos**
- Tabla `curso_fichas` (1:1 con curso) + `curso_bibliografias` (N por curso). `resultados_especificos` como JSON.

**Reglas**
- Datos faltantes: el bloque se oculta; nunca se muestra texto de plantilla.
- Antes de publicar, el instructor ve la lista de campos faltantes (aviso, no bloqueo salvo docente y correo).
- Correo del docente tomado del usuario instructor, no escrito a mano.

**CA**
1. Curso sin correo de docente no muestra el bloque "Contacte a su profesor" ni ninguna `{{ }}`.
2. Un test renderiza las vistas con una ficha vacía y verifica que no aparece `{{` ni `}}` en el HTML.
3. La bibliografía se muestra como tabla y admite 0 filas.
4. Las fechas próximas aparecen antes que la ficha.

---

## F7. Competencias / resultados de aprendizaje (REA) — P3

**Alcance**
- Instructor define competencias del curso y las enlaza a actividades (N:M).
- Estudiante ve su avance por competencia (actividades cumplidas / total enlazadas, con nota media).
- Filtro por actividad.

**Datos:** `competencias` (`curso_id`, `descripcion`), pivote `actividad_competencia`.

**Reglas:** sin competencias, la pestaña muestra estado vacío con botón "Agregar competencia" al instructor y se oculta al estudiante (FE-6).

**CA**
1. Enlazar una actividad a una competencia refleja el avance al calificarla.
2. Estudiante en curso sin competencias no ve la pestaña.
3. Instructor sin competencias ve la guía y el botón.

---

## F8. Ayuda y soporte — P1

**Alcance**
- Botón flotante "Ayuda" en todas las páginas autenticadas que abre un panel: enlace a soporte, correo, horario de atención, preguntas frecuentes.
- Datos de soporte en configuración (`config/lms.php`), no en las vistas.

**Reglas:** no debe tapar contenido ni botones de envío en móvil (se puede minimizar). Accesible por teclado.

**CA**
1. El botón aparece en dashboard, curso y actividad.
2. El panel muestra el horario y el correo configurados; si falta alguno, se omite esa línea (FE-2).
3. En móvil no cubre el botón principal de formularios.

---

## F9. Notificaciones y mensajería — Existente, ajustes P2

**Existente:** `notificaciones`, `mensajes`, campana y bandeja.

**Brechas**
- Notificación automática: nueva actividad publicada, vencimiento a 24 h, calificación publicada.
- Contador en la campana y "marcar todas como leídas".
- Sin duplicados: un vencimiento notifica una sola vez por actividad y usuario.

**CA**
1. Un job programado crea el aviso de 24 h una sola vez (idempotente).
2. Calificar una entrega notifica al estudiante.
3. "Marcar todas" deja el contador en 0.

---

## F10. Panel de seguimiento (monitoreo) para instructor — P3

**Existente:** `ReporteController` y reportes por curso/estudiante.

**Alcance**
- Panel por curso: progreso por estudiante, última conexión, actividades atrasadas, nota actual, semáforo de riesgo (sin ingreso en 7 días, 2+ atrasadas, nota proyectada bajo la aprobatoria).
- Exportar a CSV/Excel (ya hay base de exportación).
- Datos propios, sin iframe externo.

**Reglas**
- Estados de carga, vacío y error obligatorios (FE-4).
- Umbrales de riesgo configurables por curso con valores por defecto.

**CA**
1. Estudiante con 2 tareas atrasadas aparece en riesgo.
2. Si la consulta falla se muestra mensaje y botón "Reintentar", nunca pantalla en blanco.
3. Curso sin estudiantes muestra "Aún no hay estudiantes inscritos".

---

## F11. Modo edición del instructor — P3

**Alcance:** interruptor "Modo de edición" en la vista de curso que muestra/oculta controles de edición (crear, reordenar, eliminar) sin cambiar de pantalla. Estado recordado por usuario.

**CA:** con el modo apagado, el instructor ve la vista del estudiante sin controles de edición; apagarlo no cambia permisos, solo la interfaz.

---

## Orden de entrega recomendado

1. **Sprint A (P1):** F3 progreso/tarjeta (+FE-1, FE-5), F1 línea de tiempo, F5 ronda/semana, F8 ayuda.
2. **Sprint B (P2):** F4 ponderación y proyección, F2 calendario/.ics, F6 ficha académica (+FE-2, FE-3), F9 ajustes.
3. **Sprint C (P3):** F7 competencias, F10 seguimiento, F11 modo edición.

## Dependencias

- F1, F2 y F3 comparten la consulta "próxima entrega por usuario": un único servicio en `app/Services`.
- F4 depende de `ponderacion` (migración) y alimenta la "nota actual" de F3 y el riesgo de F10.
- F5 alimenta filtros de F1, F4 y la vista de curso.
- F6 es independiente.

## Preguntas abiertas

1. ¿La ponderación es opcional (reparto igual) o el instructor debe fijarla siempre?
2. ¿El calendario incluye eventos de curso creados por el instructor, o solo personales?
3. ¿Hay correo y horario oficial de soporte para F8?
4. ¿Se mantiene compatibilidad con la nomenclatura `R#-A#-S#` de la universidad, o es solo inspiración?
